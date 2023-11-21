<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\StoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class LockTest extends TestCase
{
    public function testAcquireNoBlocking(): void
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store);

        $store
            ->expects($this->once())
            ->method('save');

        $this->assertTrue($lock->acquire(false));
    }

    public function testAcquireReturnsFalse(): void
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store);

        $store
            ->expects($this->once())
            ->method('save')
            ->willThrowException(new LockConflictedException());

        $this->assertFalse($lock->acquire(false));
    }

    public function testAcquireBlocking(): void
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store);

        $store
            ->expects($this->never())
            ->method('save');
        $store
            ->expects($this->once())
            ->method('waitAndSave');

        $this->assertTrue($lock->acquire(true));
    }

    public function testAcquireSetsTtl(): void
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('save');
        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 10);

        $lock->acquire();
    }

    public function testRefresh(): void
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, 10);

        $lock->refresh();
    }

    public function testIsAquired(): void
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->any())
            ->method('exists')
            ->with($key)
            ->will($this->onConsecutiveCalls(true, false));

        $this->assertTrue($lock->isAcquired());
    }

    public function testRelease(): void
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $store
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(false);

        $lock->release();
    }

    public function testReleaseOnDestruction(): void
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls([true, false])
        ;
        $store
            ->expects($this->once())
            ->method('delete')
        ;

        $lock->acquire(false);
        unset($lock);
    }

    public function testNoAutoReleaseWhenNotConfigured(): void
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10, false);

        $store
            ->method('exists')
            ->willReturnOnConsecutiveCalls([true, false])
        ;
        $store
            ->expects($this->never())
            ->method('delete')
        ;

        $lock->acquire(false);
        unset($lock);
    }

    public function testReleaseThrowsExceptionIfNotWellDeleted(): void
    {
        $this->expectException(\Symfony\Component\Lock\Exception\LockReleasingException::class);
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $store
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(true);

        $lock->release();
    }

    public function testReleaseThrowsAndLog(): void
    {
        $this->expectException(\Symfony\Component\Lock\Exception\LockReleasingException::class);
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $lock = new Lock($key, $store, 10, true);
        $lock->setLogger($logger);

        $logger->expects($this->atLeastOnce())
            ->method('notice')
            ->with('Failed to release the "{resource}" lock.', ['resource' => $key]);

        $store
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $store
            ->expects($this->once())
            ->method('exists')
            ->with($key)
            ->willReturn(true);

        $lock->release();
    }

    /**
     * @dataProvider provideExpiredDates
     */
    public function testExpiration(array $ttls, bool $expected): void
    {
        $key = new Key(uniqid(__METHOD__, true));
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $lock = new Lock($key, $store, 10);

        foreach ($ttls as $ttl) {
            if (null === $ttl) {
                $key->resetLifetime();
            } else {
                $key->reduceLifetime($ttl);
            }
        }

        $this->assertSame($expected, $lock->isExpired());
    }

    public function provideExpiredDates(): \Generator
    {
        yield [[-0.1], true];
        yield [[0.1, -0.1], true];
        yield [[-0.1, 0.1], true];

        yield [[], false];
        yield [[0.1], false];
        yield [[-0.1, null], false];
    }
}
