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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordFormAuthenticationListener;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;

class UsernamePasswordFormAuthenticationListenerTest extends TestCase
{
    /**
     * @dataProvider getUsernameForLength
     */
    public function testHandleWhenUsernameLength($username, bool $ok): void
    {
        $request = Request::create('/login_check', 'POST', ['_username' => $username]);
        $request->setSession($this->getMockBuilder(\Symfony\Component\HttpFoundation\Session\SessionInterface::class)->getMock());

        $httpUtils = $this->getMockBuilder(\Symfony\Component\Security\Http\HttpUtils::class)->getMock();
        $httpUtils
            ->expects($this->any())
            ->method('checkRequestPath')
            ->willReturn(true)
        ;
        $httpUtils
            ->method('createRedirectResponse')
            ->willReturn(new RedirectResponse('/hello'))
        ;

        $failureHandler = $this->getMockBuilder(\Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface::class)->getMock();
        $failureHandler
            ->expects($ok ? $this->never() : $this->once())
            ->method('onAuthenticationFailure')
            ->willReturn(new Response())
        ;

        $authenticationManager = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager::class)->disableOriginalConstructor()->getMock();
        $authenticationManager
            ->expects($ok ? $this->once() : $this->never())
            ->method('authenticate')
            ->willReturnArgument(0)
        ;

        $listener = new UsernamePasswordFormAuthenticationListener(
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock(),
            $authenticationManager,
            $this->getMockBuilder(\Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface::class)->getMock(),
            $httpUtils,
            'TheProviderKey',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            $failureHandler,
            ['require_previous_session' => false]
        );

        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\GetResponseEvent::class)->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithArray(bool $postOnly): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "array" given.');
        $request = Request::create('/login_check', 'POST', ['_username' => []]);
        $request->setSession($this->getMockBuilder(\Symfony\Component\HttpFoundation\Session\SessionInterface::class)->getMock());

        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock(),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new GetResponseEvent($this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
        $listener->handle($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithInt(bool $postOnly): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "integer" given.');
        $request = Request::create('/login_check', 'POST', ['_username' => 42]);
        $request->setSession($this->getMockBuilder(\Symfony\Component\HttpFoundation\Session\SessionInterface::class)->getMock());

        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock(),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new GetResponseEvent($this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
        $listener->handle($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithObject(bool $postOnly): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "object" given.');
        $request = Request::create('/login_check', 'POST', ['_username' => new \stdClass()]);
        $request->setSession($this->getMockBuilder(\Symfony\Component\HttpFoundation\Session\SessionInterface::class)->getMock());

        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock(),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new GetResponseEvent($this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
        $listener->handle($event);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWith__toString(bool $postOnly): void
    {
        $usernameClass = $this->getMockBuilder(DummyUserClass::class)->getMock();
        $usernameClass
            ->expects($this->atLeastOnce())
            ->method('__toString')
            ->willReturn('someUsername');

        $request = Request::create('/login_check', 'POST', ['_username' => $usernameClass]);
        $request->setSession($this->getMockBuilder(\Symfony\Component\HttpFoundation\Session\SessionInterface::class)->getMock());

        $listener = new UsernamePasswordFormAuthenticationListener(
            new TokenStorage(),
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock(),
            new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE),
            $httpUtils = new HttpUtils(),
            'foo',
            new DefaultAuthenticationSuccessHandler($httpUtils),
            new DefaultAuthenticationFailureHandler($this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(), $httpUtils),
            ['require_previous_session' => false, 'post_only' => $postOnly]
        );
        $event = new GetResponseEvent($this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
        $listener->handle($event);
    }

    public function postOnlyDataProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }

    public function getUsernameForLength(): array
    {
        return [
            [str_repeat('x', Security::MAX_USERNAME_LENGTH + 1), false],
            [str_repeat('x', Security::MAX_USERNAME_LENGTH - 1), true],
        ];
    }
}

class DummyUserClass
{
    public function __toString(): string
    {
        return '';
    }
}
