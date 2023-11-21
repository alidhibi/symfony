<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcacheSessionHandler;

/**
 * @requires extension memcache
 * @group time-sensitive
 * @group legacy
 */
class MemcacheSessionHandlerTest extends TestCase
{
    final const PREFIX = 'prefix_';

    final const TTL = 1000;

    /**
     * @var MemcacheSessionHandler
     */
    protected $storage;

    protected $memcache;

    protected function setUp()
    {
        if (\defined('HHVM_VERSION')) {
            $this->markTestSkipped('PHPUnit_MockObject cannot mock the Memcache class on HHVM. See https://github.com/sebastianbergmann/phpunit-mock-objects/pull/289');
        }
        $this->memcache = $this->getMockBuilder('Memcache')->getMock();
        $this->storage = new MemcacheSessionHandler(
            $this->memcache,
            ['prefix' => self::PREFIX, 'expiretime' => self::TTL]
        );
    }

    protected function tearDown()
    {
        $this->memcache = null;
        $this->storage = null;
    }

    public function testOpenSession(): void
    {
        $this->assertTrue($this->storage->open('', ''));
    }

    public function testCloseSession(): void
    {
        $this->assertTrue($this->storage->close());
    }

    public function testReadSession(): void
    {
        $this->memcache
            ->expects($this->once())
            ->method('get')
            ->with(self::PREFIX.'id')
        ;

        $this->assertEquals('', $this->storage->read('id'));
    }

    public function testWriteSession(): void
    {
        $this->memcache
            ->expects($this->once())
            ->method('set')
            ->with(self::PREFIX.'id', 'data', 0, $this->equalTo(time() + self::TTL, 2))
            ->willReturn(true)
        ;

        $this->assertTrue($this->storage->write('id', 'data'));
    }

    public function testDestroySession(): void
    {
        $this->memcache
            ->expects($this->once())
            ->method('delete')
            ->with(self::PREFIX.'id')
            ->willReturn(true)
        ;

        $this->assertTrue($this->storage->destroy('id'));
    }

    public function testGcSession(): void
    {
        $this->assertTrue($this->storage->gc(123));
    }

    /**
     * @dataProvider getOptionFixtures
     */
    public function testSupportedOptions(array $options, bool $supported): void
    {
        try {
            new MemcacheSessionHandler($this->memcache, $options);
            $this->assertTrue($supported);
        } catch (\InvalidArgumentException $invalidArgumentException) {
            $this->assertFalse($supported);
        }
    }

    public function getOptionFixtures(): array
    {
        return [
            [['prefix' => 'session'], true],
            [['expiretime' => 100], true],
            [['prefix' => 'session', 'expiretime' => 200], true],
            [['expiretime' => 100, 'foo' => 'bar'], false],
        ];
    }

    public function testGetConnection(): void
    {
        $method = new \ReflectionMethod($this->storage, 'getMemcache');
        $method->setAccessible(true);

        $this->assertInstanceOf('\Memcache', $method->invoke($this->storage));
    }
}
