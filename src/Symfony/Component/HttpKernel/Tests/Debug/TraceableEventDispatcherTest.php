<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableEventDispatcherTest extends TestCase
{
    public function testStopwatchSections(): void
    {
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch = new Stopwatch());
        $kernel = $this->getHttpKernel($dispatcher, static fn(): \Symfony\Component\HttpFoundation\Response => new Response('', 200, ['X-Debug-Token' => '292e1e']));
        $request = Request::create('/');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $events = $stopwatch->getSectionEvents($response->headers->get('X-Debug-Token'));
        $this->assertEquals([
            '__section__',
            'kernel.request',
            'kernel.controller',
            'kernel.controller_arguments',
            'controller',
            'kernel.response',
            'kernel.terminate',
        ], array_keys($events));
    }

    public function testStopwatchCheckControllerOnRequestEvent(): void
    {
        $stopwatch = $this->getMockBuilder(\Symfony\Component\Stopwatch\Stopwatch::class)
            ->setMethods(['isStarted'])
            ->getMock();
        $stopwatch->expects($this->once())
            ->method('isStarted')
            ->willReturn(false);

        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch);

        $kernel = $this->getHttpKernel($dispatcher, static fn(): \Symfony\Component\HttpFoundation\Response => new Response());
        $request = Request::create('/');
        $kernel->handle($request);
    }

    public function testStopwatchStopControllerOnRequestEvent(): void
    {
        $stopwatch = $this->getMockBuilder(\Symfony\Component\Stopwatch\Stopwatch::class)
            ->setMethods(['isStarted', 'stop'])
            ->getMock();
        $stopwatch->expects($this->once())
            ->method('isStarted')
            ->willReturn(true);
        $stopwatch->expects($this->once())
            ->method('stop');

        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch);

        $kernel = $this->getHttpKernel($dispatcher, static fn(): \Symfony\Component\HttpFoundation\Response => new Response());
        $request = Request::create('/');
        $kernel->handle($request);
    }

    public function testAddListenerNested(): void
    {
        $called1 = false;
        $called2 = false;
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $dispatcher->addListener('my-event', static function () use ($dispatcher, &$called1, &$called2) : void {
            $called1 = true;
            $dispatcher->addListener('my-event', static function () use (&$called2) : void {
                $called2 = true;
            });
        });
        $dispatcher->dispatch('my-event');
        $this->assertTrue($called1);
        $this->assertFalse($called2);
        $dispatcher->dispatch('my-event');
        $this->assertTrue($called2);
    }

    public function testListenerCanRemoveItselfWhenExecuted(): void
    {
        $eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $listener1 = static function () use ($eventDispatcher, &$listener1) : void {
            $eventDispatcher->removeListener('foo', $listener1);
        };
        $eventDispatcher->addListener('foo', $listener1);
        $eventDispatcher->addListener('foo', static function () : void {
        });
        $eventDispatcher->dispatch('foo');

        $this->assertCount(1, $eventDispatcher->getListeners('foo'), 'expected listener1 to be removed');
    }

    protected function getHttpKernel($dispatcher, $controller): \Symfony\Component\HttpKernel\HttpKernel
    {
        $controllerResolver = $this->getMockBuilder(\Symfony\Component\HttpKernel\Controller\ControllerResolverInterface::class)->getMock();
        $controllerResolver->expects($this->once())->method('getController')->willReturn($controller);
        $argumentResolver = $this->getMockBuilder(\Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface::class)->getMock();
        $argumentResolver->expects($this->once())->method('getArguments')->willReturn([]);

        return new HttpKernel($dispatcher, $controllerResolver, new RequestStack(), $argumentResolver);
    }
}
