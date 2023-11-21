<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @internal
 */
trait PdoTrait
{
    private $conn;

    private $dsn;

    private $driver;

    private $serverVersion;

    private $table = 'cache_items';

    private $idCol = 'item_id';

    private $dataCol = 'item_data';

    private $lifetimeCol = 'item_lifetime';

    private $timeCol = 'item_time';

    private $username = '';

    private $password = '';

    private $connectionOptions = [];

    private $namespace;

    private function init($connOrDsn, array $namespace, $defaultLifetime, array $options): void
    {
        if (isset($namespace[0]) && preg_match('#[^-+.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('Namespace contains "%s" but only characters in [-+.A-Za-z0-9] are allowed.', $match[0]));
        }

        if ($connOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $connOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', __CLASS__));
            }

            $this->conn = $connOrDsn;
        } elseif ($connOrDsn instanceof Connection) {
            $this->conn = $connOrDsn;
        } elseif (\is_string($connOrDsn)) {
            $this->dsn = $connOrDsn;
        } else {
            throw new InvalidArgumentException(sprintf('"%s" requires PDO or Doctrine\DBAL\Connection instance or DSN string as first argument, "%s" given.', __CLASS__, \is_object($connOrDsn) ? \get_class($connOrDsn) : \gettype($connOrDsn)));
        }

        $this->table = isset($options['db_table']) ? $options['db_table'] : $this->table;
        $this->idCol = isset($options['db_id_col']) ? $options['db_id_col'] : $this->idCol;
        $this->dataCol = isset($options['db_data_col']) ? $options['db_data_col'] : $this->dataCol;
        $this->lifetimeCol = isset($options['db_lifetime_col']) ? $options['db_lifetime_col'] : $this->lifetimeCol;
        $this->timeCol = isset($options['db_time_col']) ? $options['db_time_col'] : $this->timeCol;
        $this->username = isset($options['db_username']) ? $options['db_username'] : $this->username;
        $this->password = isset($options['db_password']) ? $options['db_password'] : $this->password;
        $this->connectionOptions = isset($options['db_connection_options']) ? $options['db_connection_options'] : $this->connectionOptions;
        $this->namespace = $namespace;

        parent::__construct($namespace, $defaultLifetime);
    }

    /**
     * Creates the table to store cache items which can be called once for setup.
     *
     * Cache ID are saved in a column of maximum length 255. Cache data is
     * saved in a BLOB.
     *
     * @throws \PDOException    When the table already exists
     * @throws DBALException    When the table already exists
     * @throws \DomainException When an unsupported PDO driver is used
     */
    public function createTable(): void
    {
        // connect if we are not yet
        $conn = $this->getConnection();

        if ($conn instanceof Connection) {
            $types = [
                'mysql' => 'binary',
                'sqlite' => 'text',
                'pgsql' => 'string',
                'oci' => 'string',
                'sqlsrv' => 'string',
            ];
            if (!isset($types[$this->driver])) {
                throw new \DomainException(sprintf('Creating the cache table is currently not implemented for PDO driver "%s".', $this->driver));
            }

            $schema = new Schema();
            $table = $schema->createTable($this->table);
            $table->addColumn($this->idCol, $types[$this->driver], ['length' => 255]);
            $table->addColumn($this->dataCol, 'blob', ['length' => 16_777_215]);
            $table->addColumn($this->lifetimeCol, 'integer', ['unsigned' => true, 'notnull' => false]);
            $table->addColumn($this->timeCol, 'integer', ['unsigned' => true]);
            $table->setPrimaryKey([$this->idCol]);

            foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
                if (method_exists($conn, 'executeStatement')) {
                    $conn->executeStatement($sql);
                } else {
                    $conn->exec($sql);
                }
            }

            return;
        }

        switch ($this->driver) {
            case 'mysql':
                // We use varbinary for the ID column because it prevents unwanted conversions:
                // - character set conversions between server and client
                // - trailing space removal
                // - case-insensitivity
                // - language processing like é == e
                $sql = sprintf('CREATE TABLE %s (%s VARBINARY(255) NOT NULL PRIMARY KEY, %s MEDIUMBLOB NOT NULL, %s INTEGER UNSIGNED, %s INTEGER UNSIGNED NOT NULL) COLLATE utf8_bin, ENGINE = InnoDB', $this->table, $this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol);
                break;
            case 'sqlite':
                $sql = sprintf('CREATE TABLE %s (%s TEXT NOT NULL PRIMARY KEY, %s BLOB NOT NULL, %s INTEGER, %s INTEGER NOT NULL)', $this->table, $this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol);
                break;
            case 'pgsql':
                $sql = sprintf('CREATE TABLE %s (%s VARCHAR(255) NOT NULL PRIMARY KEY, %s BYTEA NOT NULL, %s INTEGER, %s INTEGER NOT NULL)', $this->table, $this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol);
                break;
            case 'oci':
                $sql = sprintf('CREATE TABLE %s (%s VARCHAR2(255) NOT NULL PRIMARY KEY, %s BLOB NOT NULL, %s INTEGER, %s INTEGER NOT NULL)', $this->table, $this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol);
                break;
            case 'sqlsrv':
                $sql = sprintf('CREATE TABLE %s (%s VARCHAR(255) NOT NULL PRIMARY KEY, %s VARBINARY(MAX) NOT NULL, %s INTEGER, %s INTEGER NOT NULL)', $this->table, $this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol);
                break;
            default:
                throw new \DomainException(sprintf('Creating the cache table is currently not implemented for PDO driver "%s".', $this->driver));
        }

        if (method_exists($conn, 'executeStatement')) {
            $conn->executeStatement($sql);
        } else {
            $conn->exec($sql);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function prune()
    {
        $deleteSql = sprintf('DELETE FROM %s WHERE %s + %s <= :time', $this->table, $this->lifetimeCol, $this->timeCol);

        if ('' !== $this->namespace) {
            $deleteSql .= sprintf(' AND %s LIKE :namespace', $this->idCol);
        }

        $delete = $this->getConnection()->prepare($deleteSql);
        $delete->bindValue(':time', time(), \PDO::PARAM_INT);

        if ('' !== $this->namespace) {
            $delete->bindValue(':namespace', sprintf('%s%%', $this->namespace), \PDO::PARAM_STR);
        }

        return $delete->execute();
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        $now = time();
        $expired = [];

        $sql = str_pad('', (\count($ids) << 1) - 1, '?,');
        $sql = sprintf('SELECT %s, CASE WHEN %s IS NULL OR %s + %s > ? THEN %s ELSE NULL END FROM %s WHERE %s IN (%s)', $this->idCol, $this->lifetimeCol, $this->lifetimeCol, $this->timeCol, $this->dataCol, $this->table, $this->idCol, $sql);

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->bindValue($i = 1, $now, \PDO::PARAM_INT);
        foreach ($ids as $id) {
            $stmt->bindValue(++$i, $id);
        }

        $result = $stmt->execute();

        if (\is_object($result)) {
            $result = $result->iterateNumeric();
        } else {
            $stmt->setFetchMode(\PDO::FETCH_NUM);
            $result = $stmt;
        }

        foreach ($result as $row) {
            if (null === $row[1]) {
                $expired[] = $row[0];
            } else {
                yield $row[0] => parent::unserialize(\is_resource($row[1]) ? stream_get_contents($row[1]) : $row[1]);
            }
        }

        if ($expired !== []) {
            $sql = str_pad('', (\count($expired) << 1) - 1, '?,');
            $sql = sprintf('DELETE FROM %s WHERE %s + %s <= ? AND %s IN (%s)', $this->table, $this->lifetimeCol, $this->timeCol, $this->idCol, $sql);
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue($i = 1, $now, \PDO::PARAM_INT);
            foreach ($expired as $id) {
                $stmt->bindValue(++$i, $id);
            }

            $stmt->execute();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id): bool
    {
        $sql = sprintf('SELECT 1 FROM %s WHERE %s = :id AND (%s IS NULL OR %s + %s > :time)', $this->table, $this->idCol, $this->lifetimeCol, $this->lifetimeCol, $this->timeCol);
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':time', time(), \PDO::PARAM_INT);

        $result = $stmt->execute();

        return (bool) (\is_object($result) ? $result->fetchOne() : $stmt->fetchColumn());
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace): bool
    {
        $conn = $this->getConnection();

        if ('' === $namespace) {
            $sql = 'sqlite' === $this->driver ? 'DELETE FROM ' . $this->table : 'TRUNCATE TABLE ' . $this->table;
        } else {
            $sql = sprintf('DELETE FROM %s WHERE %s LIKE \'%s%%\'', $this->table, $this->idCol, $namespace);
        }

        if (method_exists($conn, 'executeStatement')) {
            $conn->executeStatement($sql);
        } else {
            $conn->exec($sql);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids): bool
    {
        $sql = str_pad('', (\count($ids) << 1) - 1, '?,');
        $sql = sprintf('DELETE FROM %s WHERE %s IN (%s)', $this->table, $this->idCol, $sql);

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($ids));

        return true;
    }

    /**
     * {@inheritdoc}
     * @return int[]|string[]
     */
    protected function doSave(array $values, $lifetime): array
    {
        $serialized = [];
        $failed = [];

        foreach ($values as $id => $value) {
            try {
                $serialized[$id] = serialize($value);
            } catch (\Exception $e) {
                $failed[] = $id;
            }
        }

        if ($serialized === []) {
            return $failed;
        }

        $conn = $this->getConnection();
        $driver = $this->driver;
        $insertSql = sprintf('INSERT INTO %s (%s, %s, %s, %s) VALUES (:id, :data, :lifetime, :time)', $this->table, $this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol);

        switch (true) {
            case 'mysql' === $driver:
                $sql = $insertSql.sprintf(' ON DUPLICATE KEY UPDATE %s = VALUES(%s), %s = VALUES(%s), %s = VALUES(%s)', $this->dataCol, $this->dataCol, $this->lifetimeCol, $this->lifetimeCol, $this->timeCol, $this->timeCol);
                break;
            case 'oci' === $driver:
                // DUAL is Oracle specific dummy table
                $sql = sprintf('MERGE INTO %s USING DUAL ON (%s = ?) ', $this->table, $this->idCol).
                    sprintf('WHEN NOT MATCHED THEN INSERT (%s, %s, %s, %s) VALUES (?, ?, ?, ?) ', $this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol).
                    sprintf('WHEN MATCHED THEN UPDATE SET %s = ?, %s = ?, %s = ?', $this->dataCol, $this->lifetimeCol, $this->timeCol);
                break;
            case 'sqlsrv' === $driver && version_compare($this->getServerVersion(), '10', '>='):
                // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                // It also requires HOLDLOCK according to http://weblogs.sqlteam.com/dang/archive/2009/01/31/UPSERT-Race-Condition-With-MERGE.aspx
                $sql = sprintf('MERGE INTO %s WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON (%s = ?) ', $this->table, $this->idCol).
                    sprintf('WHEN NOT MATCHED THEN INSERT (%s, %s, %s, %s) VALUES (?, ?, ?, ?) ', $this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol).
                    sprintf('WHEN MATCHED THEN UPDATE SET %s = ?, %s = ?, %s = ?;', $this->dataCol, $this->lifetimeCol, $this->timeCol);
                break;
            case 'sqlite' === $driver:
                $sql = 'INSERT OR REPLACE'.substr($insertSql, 6);
                break;
            case 'pgsql' === $driver && version_compare($this->getServerVersion(), '9.5', '>='):
                $sql = $insertSql.sprintf(' ON CONFLICT (%s) DO UPDATE SET (%s, %s, %s) = (EXCLUDED.%s, EXCLUDED.%s, EXCLUDED.%s)', $this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol, $this->dataCol, $this->lifetimeCol, $this->timeCol);
                break;
            default:
                $driver = null;
                $sql = sprintf('UPDATE %s SET %s = :data, %s = :lifetime, %s = :time WHERE %s = :id', $this->table, $this->dataCol, $this->lifetimeCol, $this->timeCol, $this->idCol);
                break;
        }

        $now = time();
        $lifetime = $lifetime ?: null;
        $stmt = $conn->prepare($sql);

        if ('sqlsrv' === $driver || 'oci' === $driver) {
            $stmt->bindParam(1, $id);
            $stmt->bindParam(2, $id);
            $stmt->bindParam(3, $data, \PDO::PARAM_LOB);
            $stmt->bindValue(4, $lifetime, \PDO::PARAM_INT);
            $stmt->bindValue(5, $now, \PDO::PARAM_INT);
            $stmt->bindParam(6, $data, \PDO::PARAM_LOB);
            $stmt->bindValue(7, $lifetime, \PDO::PARAM_INT);
            $stmt->bindValue(8, $now, \PDO::PARAM_INT);
        } else {
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':data', $data, \PDO::PARAM_LOB);
            $stmt->bindValue(':lifetime', $lifetime, \PDO::PARAM_INT);
            $stmt->bindValue(':time', $now, \PDO::PARAM_INT);
        }

        if (null === $driver) {
            $insertStmt = $conn->prepare($insertSql);

            $insertStmt->bindParam(':id', $id);
            $insertStmt->bindParam(':data', $data, \PDO::PARAM_LOB);
            $insertStmt->bindValue(':lifetime', $lifetime, \PDO::PARAM_INT);
            $insertStmt->bindValue(':time', $now, \PDO::PARAM_INT);
        }

        foreach ($serialized as $data) {
            $result = $stmt->execute();

            if (null === $driver && !(\is_object($result) ? $result->rowCount() : $stmt->rowCount())) {
                try {
                    $insertStmt->execute();
                } catch (DBALException $e) {
                } catch (\PDOException $e) {
                    // A concurrent write won, let it be
                }
            }
        }

        return $failed;
    }

    /**
     * @return \PDO|Connection
     */
    private function getConnection()
    {
        if (null === $this->conn) {
            $this->conn = new \PDO($this->dsn, $this->username, $this->password, $this->connectionOptions);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        if (null === $this->driver) {
            if ($this->conn instanceof \PDO) {
                $this->driver = $this->conn->getAttribute(\PDO::ATTR_DRIVER_NAME);
            } else {
                $driver = $this->conn->getDriver();

                switch (true) {
                    case $driver instanceof \Doctrine\DBAL\Driver\AbstractMySQLDriver:
                    case $driver instanceof \Doctrine\DBAL\Driver\DrizzlePDOMySql\Driver:
                    case $driver instanceof \Doctrine\DBAL\Driver\Mysqli\Driver:
                    case $driver instanceof \Doctrine\DBAL\Driver\PDOMySql\Driver:
                    case $driver instanceof \Doctrine\DBAL\Driver\PDO\MySQL\Driver:
                        $this->driver = 'mysql';
                        break;
                    case $driver instanceof \Doctrine\DBAL\Driver\PDOSqlite\Driver:
                    case $driver instanceof \Doctrine\DBAL\Driver\PDO\SQLite\Driver:
                        $this->driver = 'sqlite';
                        break;
                    case $driver instanceof \Doctrine\DBAL\Driver\PDOPgSql\Driver:
                    case $driver instanceof \Doctrine\DBAL\Driver\PDO\PgSQL\Driver:
                        $this->driver = 'pgsql';
                        break;
                    case $driver instanceof \Doctrine\DBAL\Driver\OCI8\Driver:
                    case $driver instanceof \Doctrine\DBAL\Driver\PDOOracle\Driver:
                    case $driver instanceof \Doctrine\DBAL\Driver\PDO\OCI\Driver:
                        $this->driver = 'oci';
                        break;
                    case $driver instanceof \Doctrine\DBAL\Driver\SQLSrv\Driver:
                    case $driver instanceof \Doctrine\DBAL\Driver\PDOSqlsrv\Driver:
                    case $driver instanceof \Doctrine\DBAL\Driver\PDO\SQLSrv\Driver:
                        $this->driver = 'sqlsrv';
                        break;
                    default:
                        $this->driver = \get_class($driver);
                        break;
                }
            }
        }

        return $this->conn;
    }

    /**
     * @return string
     */
    private function getServerVersion()
    {
        if (null === $this->serverVersion) {
            $conn = $this->conn instanceof \PDO ? $this->conn : $this->conn->getWrappedConnection();
            if ($conn instanceof \PDO) {
                $this->serverVersion = $conn->getAttribute(\PDO::ATTR_SERVER_VERSION);
            } elseif ($conn instanceof ServerInfoAwareConnection) {
                $this->serverVersion = $conn->getServerVersion();
            } else {
                $this->serverVersion = '0';
            }
        }

        return $this->serverVersion;
    }
}
