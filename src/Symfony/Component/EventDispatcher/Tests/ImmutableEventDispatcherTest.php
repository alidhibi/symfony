<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\ImmutableEventDispatcher;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ImmutableEventDispatcherTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $innerDispatcher;

    private \Symfony\Component\EventDispatcher\ImmutableEventDispatcher $dispatcher;

    protected function setUp()
    {
        $this->innerDispatcher = $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->getMock();
        $this->dispatcher = new ImmutableEventDispatcher($this->innerDispatcher);
    }

    public function testDispatchDelegates(): void
    {
        $event = new Event();
        $resultEvent = new Event();

        $this->innerDispatcher->expects($this->once())
            ->method('dispatch')
            ->with('event', $event)
            ->willReturn($resultEvent);

        $this->assertSame($resultEvent, $this->dispatcher->dispatch('event', $event));
    }

    public function testGetListenersDelegates(): void
    {
        $this->innerDispatcher->expects($this->once())
            ->method('getListeners')
            ->with('event')
            ->willReturn(['result']);

        $this->assertSame(['result'], $this->dispatcher->getListeners('event'));
    }

    public function testHasListenersDelegates(): void
    {
        $this->innerDispatcher->expects($this->once())
            ->method('hasListeners')
            ->with('event')
            ->willReturn(true);

        $this->assertTrue($this->dispatcher->hasListeners('event'));
    }

    public function testAddListenerDisallowed(): void
    {
        $this->expectException('\BadMethodCallException');
        $this->dispatcher->addListener('event', static fn(): string => 'foo');
    }

    public function testAddSubscriberDisallowed(): void
    {
        $this->expectException('\BadMethodCallException');
        $subscriber = $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventSubscriberInterface::class)->getMock();

        $this->dispatcher->addSubscriber($subscriber);
    }

    public function testRemoveListenerDisallowed(): void
    {
        $this->expectException('\BadMethodCallException');
        $this->dispatcher->removeListener('event', static fn(): string => 'foo');
    }

    public function testRemoveSubscriberDisallowed(): void
    {
        $this->expectException('\BadMethodCallException');
        $subscriber = $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventSubscriberInterface::class)->getMock();

        $this->dispatcher->removeSubscriber($subscriber);
    }
}
