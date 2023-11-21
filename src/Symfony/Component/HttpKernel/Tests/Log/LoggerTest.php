<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Log;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Log\Logger;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class LoggerTest extends TestCase
{
    /**
     * @var LoggerInterface
     */
    private \Symfony\Component\HttpKernel\Log\Logger $logger;

    private string|bool $tmpFile;

    protected function setUp()
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'log');
        $this->logger = new Logger(LogLevel::DEBUG, $this->tmpFile);
    }

    protected function tearDown()
    {
        if (!@unlink($this->tmpFile)) {
            file_put_contents($this->tmpFile, '');
        }
    }

    public static function assertLogsMatch(array $expected, array $given): void
    {
        foreach ($given as $k => $line) {
            self::assertThat(1 === preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[\+-][0-9]{2}:[0-9]{2} '.preg_quote($expected[$k]).'/', $line), self::isTrue(), sprintf('"%s" do not match expected pattern "%s"', $line, $expected[$k]));
        }
    }

    /**
     * Return the log messages in order.
     *
     * @return string[]
     */
    public function getLogs(): array|bool
    {
        return file($this->tmpFile, \FILE_IGNORE_NEW_LINES);
    }

    public function testImplements(): void
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->logger);
    }

    /**
     * @dataProvider provideLevelsAndMessages
     */
    public function testLogsAtAllLevels($level, $message): void
    {
        $this->logger->{$level}($message, ['user' => 'Bob']);
        $this->logger->log($level, $message, ['user' => 'Bob']);

        $expected = [
            sprintf('[%s] message of level %s with context: Bob', $level, $level),
            sprintf('[%s] message of level %s with context: Bob', $level, $level),
        ];
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function provideLevelsAndMessages(): array
    {
        return [
            LogLevel::EMERGENCY => [LogLevel::EMERGENCY, 'message of level emergency with context: {user}'],
            LogLevel::ALERT => [LogLevel::ALERT, 'message of level alert with context: {user}'],
            LogLevel::CRITICAL => [LogLevel::CRITICAL, 'message of level critical with context: {user}'],
            LogLevel::ERROR => [LogLevel::ERROR, 'message of level error with context: {user}'],
            LogLevel::WARNING => [LogLevel::WARNING, 'message of level warning with context: {user}'],
            LogLevel::NOTICE => [LogLevel::NOTICE, 'message of level notice with context: {user}'],
            LogLevel::INFO => [LogLevel::INFO, 'message of level info with context: {user}'],
            LogLevel::DEBUG => [LogLevel::DEBUG, 'message of level debug with context: {user}'],
        ];
    }

    public function testLogLevelDisabled(): void
    {
        $this->logger = new Logger(LogLevel::INFO, $this->tmpFile);

        $this->logger->debug('test', ['user' => 'Bob']);
        $this->logger->log(LogLevel::DEBUG, 'test', ['user' => 'Bob']);

        // Will always be true, but asserts than an exception isn't thrown
        $this->assertSame([], $this->getLogs());
    }

    public function testThrowsOnInvalidLevel(): void
    {
        $this->expectException(\Psr\Log\InvalidArgumentException::class);
        $this->logger->log('invalid level', 'Foo');
    }

    public function testThrowsOnInvalidMinLevel(): void
    {
        $this->expectException(\Psr\Log\InvalidArgumentException::class);
        new Logger('invalid');
    }

    public function testInvalidOutput(): void
    {
        $this->expectException(\Psr\Log\InvalidArgumentException::class);
        new Logger(LogLevel::DEBUG, '/');
    }

    public function testContextReplacement(): void
    {
        $logger = $this->logger;
        $logger->info('{Message {nothing} {user} {foo.bar} a}', ['user' => 'Bob', 'foo.bar' => 'Bar']);

        $expected = ['[info] {Message {nothing} Bob Bar a}'];
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testObjectCastToString(): void
    {
        $dummy = $this->createPartialMock(DummyTest::class, ['__toString']);

        $dummy->expects($this->atLeastOnce())
            ->method('__toString')
            ->willReturn('DUMMY');

        $this->logger->warning($dummy);

        $expected = ['[warning] DUMMY'];
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testContextCanContainAnything(): void
    {
        $context = [
            'bool' => true,
            'null' => null,
            'string' => 'Foo',
            'int' => 0,
            'float' => 0.5,
            'nested' => ['with object' => new DummyTest()],
            'object' => new \DateTime(),
            'resource' => fopen('php://memory', 'r'),
        ];

        $this->logger->warning('Crazy context data', $context);

        $expected = ['[warning] Crazy context data'];
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testContextExceptionKeyCanBeExceptionOrOtherValues(): void
    {
        $logger = $this->logger;
        $logger->warning('Random message', ['exception' => 'oops']);
        $logger->critical('Uncaught Exception!', ['exception' => new \LogicException('Fail')]);

        $expected = [
            '[warning] Random message',
            '[critical] Uncaught Exception!',
        ];
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testFormatter(): void
    {
        $this->logger = new Logger(LogLevel::DEBUG, $this->tmpFile, static fn($level, $message, $context): string => json_encode(['level' => $level, 'message' => $message, 'context' => $context]).\PHP_EOL);

        $this->logger->error('An error', ['foo' => 'bar']);
        $this->logger->warning('A warning', ['baz' => 'bar']);
        $this->assertSame([
            '{"level":"error","message":"An error","context":{"foo":"bar"}}',
            '{"level":"warning","message":"A warning","context":{"baz":"bar"}}',
        ], $this->getLogs());
    }
}

class DummyTest
{
    public function __toString(): string
    {
    }
}
