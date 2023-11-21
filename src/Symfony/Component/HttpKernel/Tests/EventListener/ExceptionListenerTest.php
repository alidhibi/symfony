<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpKernel\Tests\Logger;

/**
 * ExceptionListenerTest.
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 *
 * @group time-sensitive
 */
class ExceptionListenerTest extends TestCase
{
    public function testConstruct(): void
    {
        $logger = new TestLogger();
        $l = new ExceptionListener('foo', $logger);

        $_logger = new \ReflectionProperty(\get_class($l), 'logger');
        $_logger->setAccessible(true);

        $_controller = new \ReflectionProperty(\get_class($l), 'controller');
        $_controller->setAccessible(true);

        $this->assertSame($logger, $_logger->getValue($l));
        $this->assertSame('foo', $_controller->getValue($l));
    }

    /**
     * @dataProvider provider
     */
    public function testHandleWithoutLogger(\Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event, \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event2): void
    {
        $this->iniSet('error_log', file_exists('/dev/null') ? '/dev/null' : 'nul');

        $l = new ExceptionListener('foo');
        $l->onKernelException($event);

        $this->assertEquals(new Response('foo'), $event->getResponse());

        try {
            $l->onKernelException($event2);
            $this->fail('RuntimeException expected');
        } catch (\RuntimeException $runtimeException) {
            $this->assertSame('bar', $runtimeException->getMessage());
            $this->assertSame('foo', $runtimeException->getPrevious()->getMessage());
        }
    }

    /**
     * @dataProvider provider
     */
    public function testHandleWithLogger(\Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event, \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event2): void
    {
        $logger = new TestLogger();

        $l = new ExceptionListener('foo', $logger);
        $l->onKernelException($event);

        $this->assertEquals(new Response('foo'), $event->getResponse());

        try {
            $l->onKernelException($event2);
            $this->fail('RuntimeException expected');
        } catch (\RuntimeException $runtimeException) {
            $this->assertSame('bar', $runtimeException->getMessage());
            $this->assertSame('foo', $runtimeException->getPrevious()->getMessage());
        }

        $this->assertEquals(3, $logger->countErrors());
        $this->assertCount(3, $logger->getLogs('critical'));
    }

    public function provider(): array
    {
        if (!class_exists(\Symfony\Component\HttpFoundation\Request::class)) {
            return [[null, null]];
        }

        $request = new Request();
        $exception = new \Exception('foo');
        $event = new GetResponseForExceptionEvent(new TestKernel(), $request, HttpKernelInterface::MASTER_REQUEST, $exception);
        $event2 = new GetResponseForExceptionEvent(new TestKernelThatThrowsException(), $request, HttpKernelInterface::MASTER_REQUEST, $exception);

        return [
            [$event, $event2],
        ];
    }

    public function testSubRequestFormat(): void
    {
        $listener = new ExceptionListener('foo', $this->getMockBuilder(\Psr\Log\LoggerInterface::class)->getMock());

        $kernel = $this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock();
        $kernel->expects($this->once())->method('handle')->willReturnCallback(static fn(Request $request): \Symfony\Component\HttpFoundation\Response => new Response($request->getRequestFormat()));

        $request = Request::create('/');
        $request->setRequestFormat('xml');

        $event = new GetResponseForExceptionEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, new \Exception('foo'));
        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertEquals('xml', $response->getContent());
    }

    public function testCSPHeaderIsRemoved(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock();
        $kernel->expects($this->once())->method('handle')->willReturnCallback(static fn(Request $request): \Symfony\Component\HttpFoundation\Response => new Response($request->getRequestFormat()));

        $listener = new ExceptionListener('foo', $this->getMockBuilder(\Psr\Log\LoggerInterface::class)->getMock(), true);

        $dispatcher->addSubscriber($listener);

        $request = Request::create('/');
        $event = new GetResponseForExceptionEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, new \Exception('foo'));
        $dispatcher->dispatch(KernelEvents::EXCEPTION, $event);

        $response = new Response('', 200, ['content-security-policy' => "style-src 'self'"]);
        $this->assertTrue($response->headers->has('content-security-policy'));

        $event = new FilterResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response);
        $dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertFalse($response->headers->has('content-security-policy'), 'CSP header has been removed');
        $this->assertFalse($dispatcher->hasListeners(KernelEvents::RESPONSE), 'CSP removal listener has been removed');
    }
}

class TestLogger extends Logger implements DebugLoggerInterface
{
    public function countErrors(): int
    {
        return \count($this->logs['critical']);
    }
}

class TestKernel implements HttpKernelInterface
{
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true): \Symfony\Component\HttpFoundation\Response
    {
        return new Response('foo');
    }
}

class TestKernelThatThrowsException implements HttpKernelInterface
{
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true): never
    {
        throw new \RuntimeException('bar');
    }
}
