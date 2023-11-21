<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\NotSupportedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * SemaphoreStore is a StoreInterface implementation using Semaphore as store engine.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class SemaphoreStore implements StoreInterface
{
    /**
     * Returns whether or not the store is supported.
     *
     * @param bool|null $blocking when not null, checked again the blocking mode
     *
     *
     * @internal
     */
    public static function isSupported($blocking = null): bool
    {
        if (!\extension_loaded('sysvsem')) {
            return false;
        }
        return !(false === $blocking && \PHP_VERSION_ID < 50601);
    }

    public function __construct()
    {
        if (!static::isSupported()) {
            throw new InvalidArgumentException('Semaphore extension (sysvsem) is required.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key): void
    {
        $this->lock($key, false);
    }

    /**
     * {@inheritdoc}
     */
    public function waitAndSave(Key $key): void
    {
        $this->lock($key, true);
    }

    private function lock(Key $key, bool $blocking): void
    {
        if ($key->hasState(__CLASS__)) {
            return;
        }

        $keyId = crc32($key);
        $resource = sem_get($keyId);

        $acquired = @sem_acquire($resource, !$blocking);

        while ($blocking && !$acquired) {
            $resource = sem_get($keyId);
            $acquired = @sem_acquire($resource);
        }

        if (!$acquired) {
            throw new LockConflictedException();
        }

        $key->setState(__CLASS__, $resource);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key): void
    {
        // The lock is maybe not acquired.
        if (!$key->hasState(__CLASS__)) {
            return;
        }

        $resource = $key->getState(__CLASS__);

        sem_remove($resource);

        $key->removeState(__CLASS__);
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl): void
    {
        // do nothing, the semaphore locks forever.
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        return $key->hasState(__CLASS__);
    }
}
