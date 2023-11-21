<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Proxy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;

/**
 * Tests for SessionHandlerProxy class.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SessionHandlerProxyTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\Matcher
     */
    private $mock;

    private ?\Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy $proxy = null;

    protected function setUp()
    {
        $this->mock = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $this->proxy = new SessionHandlerProxy($this->mock);
    }

    protected function tearDown()
    {
        $this->mock = null;
        $this->proxy = null;
    }

    public function testOpenTrue(): void
    {
        $this->mock->expects($this->once())
            ->method('open')
            ->willReturn(true);

        $this->assertFalse($this->proxy->isActive());
        $this->proxy->open('name', 'id');
        $this->assertFalse($this->proxy->isActive());
    }

    public function testOpenFalse(): void
    {
        $this->mock->expects($this->once())
            ->method('open')
            ->willReturn(false);

        $this->assertFalse($this->proxy->isActive());
        $this->proxy->open('name', 'id');
        $this->assertFalse($this->proxy->isActive());
    }

    public function testClose(): void
    {
        $this->mock->expects($this->once())
            ->method('close')
            ->willReturn(true);

        $this->assertFalse($this->proxy->isActive());
        $this->proxy->close();
        $this->assertFalse($this->proxy->isActive());
    }

    public function testCloseFalse(): void
    {
        $this->mock->expects($this->once())
            ->method('close')
            ->willReturn(false);

        $this->assertFalse($this->proxy->isActive());
        $this->proxy->close();
        $this->assertFalse($this->proxy->isActive());
    }

    public function testRead(): void
    {
        $this->mock->expects($this->once())
            ->method('read');

        $this->proxy->read('id');
    }

    public function testWrite(): void
    {
        $this->mock->expects($this->once())
            ->method('write');

        $this->proxy->write('id', 'data');
    }

    public function testDestroy(): void
    {
        $this->mock->expects($this->once())
            ->method('destroy');

        $this->proxy->destroy('id');
    }

    public function testGc(): void
    {
        $this->mock->expects($this->once())
            ->method('gc');

        $this->proxy->gc(86400);
    }

    /**
     * @requires PHPUnit 5.1
     */
    public function testValidateId(): void
    {
        $mock = $this->getMockBuilder(TestSessionHandler::class)->getMock();
        $mock->expects($this->once())
            ->method('validateId');

        $proxy = new SessionHandlerProxy($mock);
        $proxy->validateId('id');

        $this->assertTrue($this->proxy->validateId('id'));
    }

    /**
     * @requires PHPUnit 5.1
     */
    public function testUpdateTimestamp(): void
    {
        $mock = $this->getMockBuilder(TestSessionHandler::class)->getMock();
        $mock->expects($this->once())
            ->method('updateTimestamp')
            ->willReturn(false);

        $proxy = new SessionHandlerProxy($mock);
        $proxy->updateTimestamp('id', 'data');

        $this->mock->expects($this->once())
            ->method('write');

        $this->proxy->updateTimestamp('id', 'data');
    }
}

abstract class TestSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
}
