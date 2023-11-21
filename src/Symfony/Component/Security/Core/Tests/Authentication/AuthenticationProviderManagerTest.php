<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\ProviderNotFoundException;

class AuthenticationProviderManagerTest extends TestCase
{
    public function testAuthenticateWithoutProviders(): void
    {
        $this->expectException('InvalidArgumentException');
        new AuthenticationProviderManager([]);
    }

    public function testAuthenticateWithProvidersWithIncorrectInterface(): void
    {
        $this->expectException('InvalidArgumentException');
        (new AuthenticationProviderManager([
            new \stdClass(),
        ]))->authenticate($this->getMockBuilder(TokenInterface::class)->getMock());
    }

    public function testAuthenticateWhenNoProviderSupportsToken(): void
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(false),
        ]);

        try {
            $manager->authenticate($token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock());
            $this->fail();
        } catch (ProviderNotFoundException $providerNotFoundException) {
            $this->assertSame($token, $providerNotFoundException->getToken());
        }
    }

    public function testAuthenticateWhenProviderReturnsAccountStatusException(): void
    {
        $secondAuthenticationProvider = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface::class)->getMock();

        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, \Symfony\Component\Security\Core\Exception\AccountStatusException::class),
            $secondAuthenticationProvider,
        ]);

        // AccountStatusException stops authentication
        $secondAuthenticationProvider->expects($this->never())->method('supports');

        try {
            $manager->authenticate($token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock());
            $this->fail();
        } catch (AccountStatusException $accountStatusException) {
            $this->assertSame($token, $accountStatusException->getToken());
        }
    }

    public function testAuthenticateWhenProviderReturnsAuthenticationException(): void
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, \Symfony\Component\Security\Core\Exception\AuthenticationException::class),
        ]);

        try {
            $manager->authenticate($token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock());
            $this->fail();
        } catch (AuthenticationException $authenticationException) {
            $this->assertSame($token, $authenticationException->getToken());
        }
    }

    public function testAuthenticateWhenOneReturnsAuthenticationExceptionButNotAll(): void
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, \Symfony\Component\Security\Core\Exception\AuthenticationException::class),
            $this->getAuthenticationProvider(true, $expected = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock()),
        ]);

        $token = $manager->authenticate($this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock());
        $this->assertSame($expected, $token);
    }

    public function testAuthenticateReturnsTokenOfTheFirstMatchingProvider(): void
    {
        $second = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface::class)->getMock();
        $second
            ->expects($this->never())
            ->method('supports')
        ;
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $expected = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock()),
            $second,
        ]);

        $token = $manager->authenticate($this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock());
        $this->assertSame($expected, $token);
    }

    public function testEraseCredentialFlag(): void
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar', 'key')),
        ]);

        $token = $manager->authenticate($this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock());
        $this->assertEquals('', $token->getCredentials());

        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar', 'key')),
        ], false);

        $token = $manager->authenticate($this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock());
        $this->assertEquals('bar', $token->getCredentials());
    }

    public function testAuthenticateDispatchesAuthenticationFailureEvent(): void
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');
        $provider = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface::class)->getMock();
        $provider->expects($this->once())->method('supports')->willReturn(true);
        $provider->expects($this->once())->method('authenticate')->willThrowException($exception = new AuthenticationException());

        $dispatcher = $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->getMock();
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(AuthenticationEvents::AUTHENTICATION_FAILURE, $this->equalTo(new AuthenticationFailureEvent($token, $exception)));

        $manager = new AuthenticationProviderManager([$provider]);
        $manager->setEventDispatcher($dispatcher);

        try {
            $manager->authenticate($token);
            $this->fail('->authenticate() should rethrow exceptions');
        } catch (AuthenticationException $authenticationException) {
            $this->assertSame($token, $exception->getToken());
        }
    }

    public function testAuthenticateDispatchesAuthenticationSuccessEvent(): void
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');

        $provider = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface::class)->getMock();
        $provider->expects($this->once())->method('supports')->willReturn(true);
        $provider->expects($this->once())->method('authenticate')->willReturn($token);

        $dispatcher = $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->getMock();
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(AuthenticationEvents::AUTHENTICATION_SUCCESS, $this->equalTo(new AuthenticationEvent($token)));

        $manager = new AuthenticationProviderManager([$provider]);
        $manager->setEventDispatcher($dispatcher);

        $this->assertSame($token, $manager->authenticate($token));
    }

    protected function getAuthenticationProvider($supports, $token = null, $exception = null)
    {
        $provider = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface::class)->getMock();
        $provider->expects($this->once())
                 ->method('supports')
                 ->willReturn($supports)
        ;

        if (null !== $token) {
            $provider->expects($this->once())
                     ->method('authenticate')
                     ->willReturn($token)
            ;
        } elseif (null !== $exception) {
            $provider->expects($this->once())
                     ->method('authenticate')
                     ->willThrowException($this->getMockBuilder($exception)->setMethods(null)->getMock())
            ;
        }

        return $provider;
    }
}
