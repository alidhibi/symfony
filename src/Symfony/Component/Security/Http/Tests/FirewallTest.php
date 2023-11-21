<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Firewall;

class FirewallTest extends TestCase
{
    public function testOnKernelRequestRegistersExceptionListener(): void
    {
        $dispatcher = $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->getMock();

        $listener = $this->getMockBuilder(\Symfony\Component\Security\Http\Firewall\ExceptionListener::class)->disableOriginalConstructor()->getMock();
        $listener
            ->expects($this->once())
            ->method('register')
            ->with($this->equalTo($dispatcher))
        ;

        $request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $map = $this->getMockBuilder(\Symfony\Component\Security\Http\FirewallMapInterface::class)->getMock();
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->willReturn([[], $listener])
        ;

        $event = new GetResponseEvent($this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);

        $firewall = new Firewall($map, $dispatcher);
        $firewall->onKernelRequest($event);
    }

    public function testOnKernelRequestStopsWhenThereIsAResponse(): void
    {
        $first = $this->getMockBuilder(\Symfony\Component\Security\Http\Firewall\ListenerInterface::class)->getMock();
        $first
            ->expects($this->once())
            ->method('handle')
        ;

        $second = $this->getMockBuilder(\Symfony\Component\Security\Http\Firewall\ListenerInterface::class)->getMock();
        $second
            ->expects($this->never())
            ->method('handle')
        ;

        $map = $this->getMockBuilder(\Symfony\Component\Security\Http\FirewallMapInterface::class)->getMock();
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->willReturn([[$first, $second], null])
        ;

        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\GetResponseEvent::class)
            ->setMethods(['hasResponse'])
            ->setConstructorArgs([
                $this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(),
                $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->disableOriginalConstructor()->disableOriginalClone()->getMock(),
                HttpKernelInterface::MASTER_REQUEST,
            ])
            ->getMock()
        ;
        $event
            ->expects($this->once())
            ->method('hasResponse')
            ->willReturn(true)
        ;

        $firewall = new Firewall($map, $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->getMock());
        $firewall->onKernelRequest($event);
    }

    public function testOnKernelRequestWithSubRequest(): void
    {
        $map = $this->getMockBuilder(\Symfony\Component\Security\Http\FirewallMapInterface::class)->getMock();
        $map
            ->expects($this->never())
            ->method('getListeners')
        ;

        $event = new GetResponseEvent(
            $this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(),
            $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->getMock(),
            HttpKernelInterface::SUB_REQUEST
        );

        $firewall = new Firewall($map, $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->getMock());
        $firewall->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }
}
