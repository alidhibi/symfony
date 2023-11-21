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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Firewall\ContextListener;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

class ContextListenerTest extends TestCase
{
    public function testItRequiresContextKey(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('$contextKey must not be empty');
        new ContextListener(
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock(),
            [],
            ''
        );
    }

    public function testUserProvidersNeedToImplementAnInterface(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('User provider "stdClass" must implement "Symfony\Component\Security\Core\User\UserProviderInterface');
        $this->handleEventWithPreviousSession(new TokenStorage(), [new \stdClass()]);
    }

    public function testOnKernelResponseWillAddSession(): void
    {
        $session = $this->runSessionOnKernelResponse(
            new UsernamePasswordToken('test1', 'pass1', 'phpunit'),
            null
        );

        $token = unserialize($session->get('_security_session'));
        $this->assertInstanceOf(\Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken::class, $token);
        $this->assertEquals('test1', $token->getUsername());
    }

    public function testOnKernelResponseWillReplaceSession(): void
    {
        $session = $this->runSessionOnKernelResponse(
            new UsernamePasswordToken('test1', 'pass1', 'phpunit'),
            'C:10:"serialized"'
        );

        $token = unserialize($session->get('_security_session'));
        $this->assertInstanceOf(\Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken::class, $token);
        $this->assertEquals('test1', $token->getUsername());
    }

    public function testOnKernelResponseWillRemoveSession(): void
    {
        $session = $this->runSessionOnKernelResponse(
            null,
            'C:10:"serialized"'
        );

        $this->assertFalse($session->has('_security_session'));
    }

    public function testOnKernelResponseWillRemoveSessionOnAnonymousToken(): void
    {
        $session = $this->runSessionOnKernelResponse(new AnonymousToken('secret', 'anon.'), 'C:10:"serialized"');

        $this->assertFalse($session->has('_security_session'));
    }

    public function testOnKernelResponseWithoutSession(): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken('test1', 'pass1', 'phpunit'));

        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = new FilterResponseEvent(
            $this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            new Response()
        );

        $listener = new ContextListener($tokenStorage, [], 'session', null, new EventDispatcher());
        $listener->onKernelResponse($event);

        $this->assertTrue($session->isStarted());
    }

    public function testOnKernelResponseWithoutSessionNorToken(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = new FilterResponseEvent(
            $this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            new Response()
        );

        $listener = new ContextListener(new TokenStorage(), [], 'session', null, new EventDispatcher());
        $listener->onKernelResponse($event);

        $this->assertFalse($session->isStarted());
    }

    /**
     * @dataProvider provideInvalidToken
     */
    public function testInvalidTokenInSession(?string $token): void
    {
        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\GetResponseEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->getMock();
        $session = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Session\SessionInterface::class)->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->willReturn($request);
        $request->expects($this->any())
            ->method('hasPreviousSession')
            ->willReturn(true);
        $request->expects($this->any())
            ->method('getSession')
            ->willReturn($session);
        $session->expects($this->any())
            ->method('get')
            ->with('_security_key123')
            ->willReturn($token);
        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $listener = new ContextListener($tokenStorage, [], 'key123');
        $listener->handle($event);
    }

    public function provideInvalidToken(): array
    {
        return [
            ['foo'],
            ['O:8:"NotFound":0:{}'],
            [serialize(new \__PHP_Incomplete_Class())],
            [serialize(null)],
            [null],
        ];
    }

    public function testHandleAddsKernelResponseListener(): void
    {
        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $dispatcher = $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->getMock();
        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\GetResponseEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $listener = new ContextListener($tokenStorage, [], 'key123', null, $dispatcher);

        $event->expects($this->any())
            ->method('isMasterRequest')
            ->willReturn(true);
        $event->expects($this->any())
            ->method('getRequest')
            ->willReturn($this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->getMock());

        $dispatcher->expects($this->once())
            ->method('addListener')
            ->with(KernelEvents::RESPONSE, static fn(\Symfony\Component\HttpKernel\Event\FilterResponseEvent $event) => $listener->onKernelResponse($event));

        $listener->handle($event);
    }

    public function testOnKernelResponseListenerRemovesItself(): void
    {
        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $dispatcher = $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->getMock();
        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\FilterResponseEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $listener = new ContextListener($tokenStorage, [], 'key123', null, $dispatcher);

        $request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->getMock();
        $request->expects($this->any())
            ->method('hasSession')
            ->willReturn(true);

        $event->expects($this->any())
            ->method('isMasterRequest')
            ->willReturn(true);
        $event->expects($this->any())
            ->method('getRequest')
            ->willReturn($request);

        $dispatcher->expects($this->once())
            ->method('removeListener')
            ->with(KernelEvents::RESPONSE, static fn(\Symfony\Component\HttpKernel\Event\FilterResponseEvent $event) => $listener->onKernelResponse($event));

        $listener->onKernelResponse($event);
    }

    public function testHandleRemovesTokenIfNoPreviousSessionWasFound(): void
    {
        $request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->getMock();
        $request->expects($this->any())->method('hasPreviousSession')->willReturn(false);

        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\GetResponseEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->any())->method('getRequest')->willReturn($request);

        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $tokenStorage->expects($this->once())->method('setToken')->with(null);

        $listener = new ContextListener($tokenStorage, [], 'key123');
        $listener->handle($event);
    }

    /**
     * @group legacy
     * @expectedDeprecation Refreshing a deauthenticated user is deprecated as of 3.4 and will trigger a logout in 4.0.
     */
    public function testIfTokenIsDeauthenticatedTriggersDeprecations(): void
    {
        $tokenStorage = new TokenStorage();
        $refreshedUser = new User('foobar', 'baz');
        $this->handleEventWithPreviousSession($tokenStorage, [new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider($refreshedUser)]);

        $this->assertSame($refreshedUser, $tokenStorage->getToken()->getUser());
    }

    public function testIfTokenIsDeauthenticated(): void
    {
        $tokenStorage = new TokenStorage();
        $refreshedUser = new User('foobar', 'baz');
        $this->handleEventWithPreviousSession($tokenStorage, [new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider($refreshedUser)], null, true);

        $this->assertNull($tokenStorage->getToken());
    }

    public function testIfTokenIsNotDeauthenticated(): void
    {
        $tokenStorage = new TokenStorage();
        $badRefreshedUser = new User('foobar', 'baz');
        $goodRefreshedUser = new User('foobar', 'bar');
        $this->handleEventWithPreviousSession($tokenStorage, [new SupportingUserProvider($badRefreshedUser), new SupportingUserProvider($goodRefreshedUser)], $goodRefreshedUser, true);
        $this->assertSame($goodRefreshedUser, $tokenStorage->getToken()->getUser());
    }

    public function testRememberMeGetsCanceledIfTokenIsDeauthenticated(): void
    {
        $tokenStorage = new TokenStorage();
        $refreshedUser = new User('foobar', 'baz');

        $rememberMeServices = $this->createMock(RememberMeServicesInterface::class);
        $rememberMeServices->expects($this->once())->method('loginFail');

        $this->handleEventWithPreviousSession($tokenStorage, [new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider($refreshedUser)], null, true, $rememberMeServices);

        $this->assertNull($tokenStorage->getToken());
    }

    public function testTryAllUserProvidersUntilASupportingUserProviderIsFound(): void
    {
        $tokenStorage = new TokenStorage();
        $refreshedUser = new User('foobar', 'baz');
        $this->handleEventWithPreviousSession($tokenStorage, [new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider($refreshedUser)], $refreshedUser);

        $this->assertSame($refreshedUser, $tokenStorage->getToken()->getUser());
    }

    public function testNextSupportingUserProviderIsTriedIfPreviousSupportingUserProviderDidNotLoadTheUser(): void
    {
        $tokenStorage = new TokenStorage();
        $refreshedUser = new User('foobar', 'baz');
        $this->handleEventWithPreviousSession($tokenStorage, [new SupportingUserProvider(), new SupportingUserProvider($refreshedUser)], $refreshedUser);

        $this->assertSame($refreshedUser, $tokenStorage->getToken()->getUser());
    }

    public function testTokenIsSetToNullIfNoUserWasLoadedByTheRegisteredUserProviders(): void
    {
        $tokenStorage = new TokenStorage();
        $this->handleEventWithPreviousSession($tokenStorage, [new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider()]);

        $this->assertNull($tokenStorage->getToken());
    }

    public function testRuntimeExceptionIsThrownIfNoSupportingUserProviderWasRegistered(): void
    {
        $this->expectException('RuntimeException');
        $this->handleEventWithPreviousSession(new TokenStorage(), [new NotSupportingUserProvider(false), new NotSupportingUserProvider(true)]);
    }

    public function testAcceptsProvidersAsTraversable(): void
    {
        $tokenStorage = new TokenStorage();
        $refreshedUser = new User('foobar', 'baz');
        $this->handleEventWithPreviousSession($tokenStorage, new \ArrayObject([new NotSupportingUserProvider(true), new NotSupportingUserProvider(false), new SupportingUserProvider($refreshedUser)]), $refreshedUser);

        $this->assertSame($refreshedUser, $tokenStorage->getToken()->getUser());
    }

    protected function runSessionOnKernelResponse(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $newToken, $original = null): \Symfony\Component\HttpFoundation\Session\Session
    {
        $session = new Session(new MockArraySessionStorage());

        if (null !== $original) {
            $session->set('_security_session', $original);
        }

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($newToken);

        $request = new Request();
        $request->setSession($session);

        $request->cookies->set('MOCKSESSID', true);

        $event = new FilterResponseEvent(
            $this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            new Response()
        );

        $listener = new ContextListener($tokenStorage, [], 'session', null, new EventDispatcher());
        $listener->onKernelResponse($event);

        return $session;
    }

    private function handleEventWithPreviousSession(TokenStorageInterface $tokenStorage, array|\ArrayObject $userProviders, ?\Symfony\Component\Security\Core\User\User $user = null, bool $logoutOnUserChange = false, RememberMeServicesInterface $rememberMeServices = null): void
    {
        $user = $user ?: new User('foo', 'bar');
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security_context_key', serialize(new UsernamePasswordToken($user, '', 'context_key', ['ROLE_USER'])));

        $request = new Request();
        $request->setSession($session);

        $request->cookies->set('MOCKSESSID', true);

        $listener = new ContextListener($tokenStorage, $userProviders, 'context_key');
        $listener->setLogoutOnUserChange($logoutOnUserChange);

        if ($rememberMeServices instanceof \Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface) {
            $listener->setRememberMeServices($rememberMeServices);
        }

        $listener->handle(new GetResponseEvent($this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST));
    }
}

class NotSupportingUserProvider implements UserProviderInterface
{
    /** @var bool */
    private $throwsUnsupportedException;

    public function __construct($throwsUnsupportedException)
    {
        $this->throwsUnsupportedException = $throwsUnsupportedException;
    }

    public function loadUserByUsername($username): never
    {
        throw new UsernameNotFoundException();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if ($this->throwsUnsupportedException) {
            throw new UnsupportedUserException();
        }

        return $user;
    }

    public function supportsClass($class): bool
    {
        return false;
    }
}

class SupportingUserProvider implements UserProviderInterface
{
    private ?\Symfony\Component\Security\Core\User\User $refreshedUser = null;

    public function __construct(User $refreshedUser = null)
    {
        $this->refreshedUser = $refreshedUser;
    }

    public function loadUserByUsername($username): void
    {
    }

    public function refreshUser(UserInterface $user): ?\Symfony\Component\Security\Core\User\User
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException();
        }

        if (!$this->refreshedUser instanceof \Symfony\Component\Security\Core\User\User) {
            throw new UsernameNotFoundException();
        }

        return $this->refreshedUser;
    }

    public function supportsClass($class): bool
    {
        return \Symfony\Component\Security\Core\User\User::class === $class;
    }
}
