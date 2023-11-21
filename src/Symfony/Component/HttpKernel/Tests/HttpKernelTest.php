<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class HttpKernelTest extends TestCase
{
    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrue(): void
    {
        $this->expectException('RuntimeException');
        $kernel = $this->getHttpKernel(new EventDispatcher(), static function () : never {
            throw new \RuntimeException();
        });

        $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);
    }

    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsFalseAndNoListenerIsRegistered(): void
    {
        $this->expectException('RuntimeException');
        $kernel = $this->getHttpKernel(new EventDispatcher(), static function () : never {
            throw new \RuntimeException();
        });

        $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, false);
    }

    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrueWithAHandlingListener(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, static function ($event) : void {
            $event->setResponse(new Response($event->getException()->getMessage()));
        });

        $kernel = $this->getHttpKernel($dispatcher, static function () : never {
            throw new \RuntimeException('foo');
        });
        $response = $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);

        $this->assertEquals('500', $response->getStatusCode());
        $this->assertEquals('foo', $response->getContent());
    }

    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrueWithANonHandlingListener(): void
    {
        $exception = new \RuntimeException();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, static function ($event) : void {
            // should set a response, but does not
        });

        $kernel = $this->getHttpKernel($dispatcher, static function () use ($exception) : never {
            throw $exception;
        });

        try {
            $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);
            $this->fail('LogicException expected');
        } catch (\RuntimeException $runtimeException) {
            $this->assertSame($exception, $runtimeException);
        }
    }

    public function testHandleExceptionWithARedirectionResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, static function ($event) : void {
            $event->setResponse(new RedirectResponse('/login', 301));
        });

        $kernel = $this->getHttpKernel($dispatcher, static function () : never {
            throw new AccessDeniedHttpException();
        });
        $response = $kernel->handle(new Request());

        $this->assertEquals('301', $response->getStatusCode());
        $this->assertEquals('/login', $response->headers->get('Location'));
    }

    public function testHandleHttpException(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, static function ($event) : void {
            $event->setResponse(new Response($event->getException()->getMessage()));
        });

        $kernel = $this->getHttpKernel($dispatcher, static function () : never {
            throw new MethodNotAllowedHttpException(['POST']);
        });
        $response = $kernel->handle(new Request());

        $this->assertEquals('405', $response->getStatusCode());
        $this->assertEquals('POST', $response->headers->get('Allow'));
    }

    /**
     * @group legacy
     * @dataProvider getStatusCodes
     */
    public function testLegacyHandleWhenAnExceptionIsHandledWithASpecificStatusCode(int $responseStatusCode, int $expectedStatusCode): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, static function ($event) use ($responseStatusCode, $expectedStatusCode) : void {
            $event->setResponse(new Response('', $responseStatusCode, ['X-Status-Code' => $expectedStatusCode]));
        });

        $kernel = $this->getHttpKernel($dispatcher, static function () : never {
            throw new \RuntimeException();
        });
        $response = $kernel->handle(new Request());

        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
        $this->assertFalse($response->headers->has('X-Status-Code'));
    }

    public function getStatusCodes(): array
    {
        return [
            [200, 404],
            [404, 200],
            [301, 200],
            [500, 200],
        ];
    }

    /**
     * @dataProvider getSpecificStatusCodes
     */
    public function testHandleWhenAnExceptionIsHandledWithASpecificStatusCode(int $expectedStatusCode): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, static function (GetResponseForExceptionEvent $event) use ($expectedStatusCode) : void {
            $event->allowCustomResponseCode();
            $event->setResponse(new Response('', $expectedStatusCode));
        });

        $kernel = $this->getHttpKernel($dispatcher, static function () : never {
            throw new \RuntimeException();
        });
        $response = $kernel->handle(new Request());

        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
    }

    public function getSpecificStatusCodes(): array
    {
        return [
            [200],
            [302],
            [403],
        ];
    }

    public function testHandleWhenAListenerReturnsAResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::REQUEST, static function ($event) : void {
            $event->setResponse(new Response('hello'));
        });

        $kernel = $this->getHttpKernel($dispatcher);

        $this->assertEquals('hello', $kernel->handle(new Request())->getContent());
    }

    public function testHandleWhenNoControllerIsFound(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, false);

        $kernel->handle(new Request());
    }

    public function testHandleWhenTheControllerIsAClosure(): void
    {
        $response = new Response('foo');
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, static fn(): \Symfony\Component\HttpFoundation\Response => $response);

        $this->assertSame($response, $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAnObjectWithInvoke(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, new TestController());

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAFunction(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, 'Symfony\Component\HttpKernel\Tests\controller_func');

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAnArray(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, static fn() => (new TestController())->controller());

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAStaticArray(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, static fn() => \Symfony\Component\HttpKernel\Tests\TestController::staticController());

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerDoesNotReturnAResponse(): void
    {
        $this->expectException('LogicException');
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, static fn(): string => 'foo');

        $kernel->handle(new Request());
    }

    public function testHandleWhenTheControllerDoesNotReturnAResponseButAViewIsRegistered(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::VIEW, static function ($event) : void {
            $event->setResponse(new Response($event->getControllerResult()));
        });

        $kernel = $this->getHttpKernel($dispatcher, static fn(): string => 'foo');

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleWithAResponseListener(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::RESPONSE, static function ($event) : void {
            $event->setResponse(new Response('foo'));
        });
        $kernel = $this->getHttpKernel($dispatcher);

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleAllowChangingControllerArguments(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, static function (FilterControllerArgumentsEvent $event) : void {
            $event->setArguments(['foo']);
        });

        $kernel = $this->getHttpKernel($dispatcher, static fn($content): \Symfony\Component\HttpFoundation\Response => new Response($content));

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleAllowChangingControllerAndArguments(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, static function (FilterControllerArgumentsEvent $event) : void {
            $oldController = $event->getController();
            $oldArguments = $event->getArguments();
            $newController = static function ($id) use ($oldController, $oldArguments) {
                $response = \call_user_func_array($oldController, $oldArguments);
                $response->headers->set('X-Id', $id);
                return $response;
            };
            $event->setController($newController);
            $event->setArguments(['bar']);
        });

        $kernel = $this->getHttpKernel($dispatcher, static fn($content): \Symfony\Component\HttpFoundation\Response => new Response($content), null, ['foo']);

        $this->assertResponseEquals(new Response('foo', 200, ['X-Id' => 'bar']), $kernel->handle(new Request()));
    }

    public function testTerminate(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher);
        $dispatcher->addListener(KernelEvents::TERMINATE, static function ($event) use (&$called, &$capturedKernel, &$capturedRequest, &$capturedResponse) : void {
            $called = true;
            $capturedKernel = $event->getKernel();
            $capturedRequest = $event->getRequest();
            $capturedResponse = $event->getResponse();
        });

        $kernel->terminate($request = Request::create('/'), $response = new Response());
        $this->assertTrue($called);
        $this->assertEquals($kernel, $capturedKernel);
        $this->assertEquals($request, $capturedRequest);
        $this->assertEquals($response, $capturedResponse);
    }

    public function testVerifyRequestStackPushPopDuringHandle(): void
    {
        $request = new Request();

        $stack = $this->getMockBuilder(\Symfony\Component\HttpFoundation\RequestStack::class)->setMethods(['push', 'pop'])->getMock();
        $stack->expects($this->once())->method('push')->with($this->equalTo($request));
        $stack->expects($this->once())->method('pop');

        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, null, $stack);

        $kernel->handle($request, HttpKernelInterface::MASTER_REQUEST);
    }

    public function testInconsistentClientIpsOnMasterRequests(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $request = new Request();
        $request->setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED);

        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('FORWARDED', 'for=2.2.2.2');
        $request->headers->set('X_FORWARDED_FOR', '3.3.3.3');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::REQUEST, static function ($event) : void {
            $event->getRequest()->getClientIp();
        });

        $kernel = $this->getHttpKernel($dispatcher);
        $kernel->handle($request, $kernel::MASTER_REQUEST, false);

        Request::setTrustedProxies([], -1);
    }

    private function getHttpKernel(EventDispatcherInterface $eventDispatcher, \Closure|bool|\Symfony\Component\HttpKernel\Tests\TestController|string|array|null $controller = null, RequestStack $requestStack = null, array $arguments = []): \Symfony\Component\HttpKernel\HttpKernel
    {
        if (null === $controller) {
            $controller = static fn(): \Symfony\Component\HttpFoundation\Response => new Response('Hello');
        }

        $controllerResolver = $this->getMockBuilder(ControllerResolverInterface::class)->getMock();
        $controllerResolver
            ->expects($this->any())
            ->method('getController')
            ->willReturn($controller);

        $argumentResolver = $this->getMockBuilder(ArgumentResolverInterface::class)->getMock();
        $argumentResolver
            ->expects($this->any())
            ->method('getArguments')
            ->willReturn($arguments);

        return new HttpKernel($eventDispatcher, $controllerResolver, $requestStack, $argumentResolver);
    }

    private function assertResponseEquals(Response $expected, Response $actual): void
    {
        $expected->setDate($actual->getDate());
        $this->assertEquals($expected, $actual);
    }
}

class TestController
{
    public function __invoke(): \Symfony\Component\HttpFoundation\Response
    {
        return new Response('foo');
    }

    public function controller(): \Symfony\Component\HttpFoundation\Response
    {
        return new Response('foo');
    }

    public static function staticController(): \Symfony\Component\HttpFoundation\Response
    {
        return new Response('foo');
    }
}

function controller_func(): \Symfony\Component\HttpFoundation\Response
{
    return new Response('foo');
}
