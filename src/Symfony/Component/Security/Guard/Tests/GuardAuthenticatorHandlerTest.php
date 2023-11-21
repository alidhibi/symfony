<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class GuardAuthenticatorHandlerTest extends TestCase
{
    private $tokenStorage;

    private $dispatcher;

    private $token;

    private ?\Symfony\Component\HttpFoundation\Request $request = null;

    private $sessionStrategy;

    private $guardAuthenticator;

    public function testAuthenticateWithToken(): void
    {
        $this->tokenStorage->expects($this->once())
            ->method('setToken')
            ->with($this->token);

        $loginEvent = new InteractiveLoginEvent($this->request, $this->token);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(SecurityEvents::INTERACTIVE_LOGIN), $this->equalTo($loginEvent))
        ;

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $handler->authenticateWithToken($this->token, $this->request);
    }

    public function testHandleAuthenticationSuccess(): void
    {
        $providerKey = 'my_handleable_firewall';
        $response = new Response('Guard all the things!');
        $this->guardAuthenticator->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($this->request, $this->token, $providerKey)
            ->willReturn($response);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $actualResponse = $handler->handleAuthenticationSuccess($this->token, $this->request, $this->guardAuthenticator, $providerKey);
        $this->assertSame($response, $actualResponse);
    }

    public function testHandleAuthenticationFailure(): void
    {
        // setToken() not called - getToken() will return null, so there's nothing to clear
        $this->tokenStorage->expects($this->never())
            ->method('setToken')
            ->with(null);
        $authException = new AuthenticationException('Bad password!');

        $response = new Response('Try again, but with the right password!');
        $this->guardAuthenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $authException)
            ->willReturn($response);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $actualResponse = $handler->handleAuthenticationFailure($authException, $this->request, $this->guardAuthenticator, 'firewall_provider_key');
        $this->assertSame($response, $actualResponse);
    }

    /**
     * @dataProvider getTokenClearingTests
     */
    public function testHandleAuthenticationClearsToken(string $tokenProviderKey, string $actualProviderKey): void
    {
        $this->tokenStorage->expects($this->never())
            ->method('setToken')
            ->with(null);
        $authException = new AuthenticationException('Bad password!');

        $response = new Response('Try again, but with the right password!');
        $this->guardAuthenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($this->request, $authException)
            ->willReturn($response);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $actualResponse = $handler->handleAuthenticationFailure($authException, $this->request, $this->guardAuthenticator, $actualProviderKey);
        $this->assertSame($response, $actualResponse);
    }

    public function getTokenClearingTests(): array
    {
        return [
            // matching firewall => clear the token
            ['the_firewall_key', 'the_firewall_key'],
            ['the_firewall_key', 'different_key'],
            ['the_firewall_key', 'the_firewall_key'],
        ];
    }

    public function testNoFailureIfSessionStrategyNotPassed(): void
    {
        $this->configurePreviousSession();

        $this->tokenStorage->expects($this->once())
            ->method('setToken')
            ->with($this->token);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $handler->authenticateWithToken($this->token, $this->request);
    }

    public function testSessionStrategyIsCalled(): void
    {
        $this->configurePreviousSession();

        $this->sessionStrategy->expects($this->once())
            ->method('onAuthentication')
            ->with($this->request, $this->token);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher);
        $handler->setSessionAuthenticationStrategy($this->sessionStrategy);
        $handler->authenticateWithToken($this->token, $this->request);
    }

    public function testSessionStrategyIsNotCalledWhenStateless(): void
    {
        $this->configurePreviousSession();

        $this->sessionStrategy->expects($this->never())
            ->method('onAuthentication');

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher, ['some_provider_key']);
        $handler->setSessionAuthenticationStrategy($this->sessionStrategy);
        $handler->authenticateWithToken($this->token, $this->request, 'some_provider_key');
    }

    /**
     * @requires function \Symfony\Component\HttpFoundation\Request::setSessionFactory
     */
    public function testSessionIsNotInstantiatedOnStatelessFirewall(): void
    {
        $sessionFactory = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();

        $sessionFactory->expects($this->never())
            ->method('__invoke');

        $this->request->setSessionFactory($sessionFactory);

        $handler = new GuardAuthenticatorHandler($this->tokenStorage, $this->dispatcher, ['stateless_provider_key']);
        $handler->setSessionAuthenticationStrategy($this->sessionStrategy);
        $handler->authenticateWithToken($this->token, $this->request, 'stateless_provider_key');
    }

    protected function setUp()
    {
        $this->tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $this->dispatcher = $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->getMock();
        $this->token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $this->request = new Request([], [], [], [], [], []);
        $this->sessionStrategy = $this->getMockBuilder(\Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface::class)->getMock();
        $this->guardAuthenticator = $this->getMockBuilder(AuthenticatorInterface::class)->getMock();
    }

    protected function tearDown()
    {
        $this->tokenStorage = null;
        $this->dispatcher = null;
        $this->token = null;
        $this->request = null;
        $this->guardAuthenticator = null;
    }

    private function configurePreviousSession(): void
    {
        $session = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Session\SessionInterface::class)->getMock();
        $session->expects($this->any())
            ->method('getName')
            ->willReturn('test_session_name');
        $this->request->setSession($session);
        $this->request->cookies->set('test_session_name', 'session_cookie_val');
    }
}
