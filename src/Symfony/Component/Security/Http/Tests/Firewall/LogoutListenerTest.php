<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

class LogoutListenerTest extends TestCase
{
    public function testHandleUnmatchedPath(): void
    {
        list($listener, , $httpUtils, $options) = $this->getListener();

        list($event, $request) = $this->getGetResponseEvent();

        $event->expects($this->never())
            ->method('setResponse');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(false);

        $listener->handle($event);
    }

    public function testHandleMatchedPathWithSuccessHandlerAndCsrfValidation(): void
    {
        $successHandler = $this->getSuccessHandler();
        $tokenManager = $this->getTokenManager();

        list($listener, $tokenStorage, $httpUtils, $options) = $this->getListener($successHandler, $tokenManager);

        list($event, $request) = $this->getGetResponseEvent();

        $request->query->set('_csrf_token', 'token');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(true);

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->willReturn($response = new Response());

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token = $this->getToken());

        $handler = $this->getHandler();
        $handler->expects($this->once())
            ->method('logout')
            ->with($request, $response, $token);

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($response);

        $listener->addHandler($handler);

        $listener->handle($event);
    }

    public function testHandleMatchedPathWithoutSuccessHandlerAndCsrfValidation(): void
    {
        $successHandler = $this->getSuccessHandler();

        list($listener, $tokenStorage, $httpUtils, $options) = $this->getListener($successHandler);

        list($event, $request) = $this->getGetResponseEvent();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->willReturn($response = new Response());

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token = $this->getToken());

        $handler = $this->getHandler();
        $handler->expects($this->once())
            ->method('logout')
            ->with($request, $response, $token);

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($response);

        $listener->addHandler($handler);

        $listener->handle($event);
    }

    /**
     * @group legacy
     */
    public function testSuccessHandlerReturnsNonResponse(): void
    {
        $this->expectException('RuntimeException');
        $successHandler = $this->getSuccessHandler();

        list($listener, , $httpUtils, $options) = $this->getListener($successHandler);

        list($event, $request) = $this->getGetResponseEvent();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->willReturn(null);

        $listener->handle($event);
    }

    public function testCsrfValidationFails(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\LogoutException::class);
        $tokenManager = $this->getTokenManager();

        list($listener, , $httpUtils, $options) = $this->getListener(null, $tokenManager);

        list($event, $request) = $this->getGetResponseEvent();

        $request->query->set('_csrf_token', 'token');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(false);

        $listener->handle($event);
    }

    private function getTokenManager()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface::class)->getMock();
    }

    private function getTokenStorage()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
    }

    private function getGetResponseEvent(): array
    {
        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\GetResponseEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->willReturn($request = new Request());

        return [$event, $request];
    }

    private function getHandler()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Http\Logout\LogoutHandlerInterface::class)->getMock();
    }

    private function getHttpUtils()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Http\HttpUtils::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getListener($successHandler = null, $tokenManager = null): array
    {
        $listener = new LogoutListener(
            $tokenStorage = $this->getTokenStorage(),
            $httpUtils = $this->getHttpUtils(),
            $successHandler ?: $this->getSuccessHandler(),
            $options = [
                'csrf_parameter' => '_csrf_token',
                'csrf_token_id' => 'logout',
                'logout_path' => '/logout',
                'target_url' => '/',
            ],
            $tokenManager
        );

        return [$listener, $tokenStorage, $httpUtils, $options];
    }

    private function getSuccessHandler()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface::class)->getMock();
    }

    private function getToken()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
    }
}
