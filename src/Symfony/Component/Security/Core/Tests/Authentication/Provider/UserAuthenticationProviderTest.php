<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\Security\Core\User\UserInterface;

class UserAuthenticationProviderTest extends TestCase
{
    public function testSupports(): void
    {
        $provider = $this->getProvider();

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock()));
    }

    public function testAuthenticateWhenTokenIsNotSupported(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('The token is not supported by this authentication provider.');
        $provider = $this->getProvider();

        $provider->authenticate($this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock());
    }

    public function testAuthenticateWhenUsernameIsNotFound(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\UsernameNotFoundException::class);
        $provider = $this->getProvider(false, false);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willThrowException(new UsernameNotFoundException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenUsernameIsNotFoundAndHideIsTrue(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $provider = $this->getProvider(false, true);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willThrowException(new UsernameNotFoundException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenCredentialsAreInvalidAndHideIsTrue(): void
    {
        $provider = $this->getProvider();
        $provider->expects($this->once())
            ->method('retrieveUser')
            ->willReturn($this->createMock(UserInterface::class))
        ;
        $provider->expects($this->once())
            ->method('checkAuthentication')
            ->willThrowException(new BadCredentialsException())
        ;

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Bad credentials.');

        $provider->authenticate($this->getSupportedToken());
    }

    /**
     * @group legacy
     */
    public function testAuthenticateWhenProviderDoesNotReturnAnUserInterface(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationServiceException::class);
        $provider = $this->getProvider(false, true);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn(null)
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPreChecksFails(): void
    {
        $this->expectException(BadCredentialsException::class);
        $userChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock();
        $userChecker->expects($this->once())
                    ->method('checkPreAuth')
                    ->willThrowException(new CredentialsExpiredException())
        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPostChecksFails(): void
    {
        $this->expectException(BadCredentialsException::class);
        $userChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock();
        $userChecker->expects($this->once())
                    ->method('checkPostAuth')
                    ->willThrowException(new AccountExpiredException())
        ;

        $provider = $this->getProvider($userChecker);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPostCheckAuthenticationFails(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $this->expectExceptionMessage('Bad credentials.');
        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock())
        ;
        $provider->expects($this->once())
                 ->method('checkAuthentication')
                 ->willThrowException(new CredentialsExpiredException())
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticateWhenPostCheckAuthenticationFailsWithHideFalse(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $this->expectExceptionMessage('Foo');
        $provider = $this->getProvider(false, false);
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock())
        ;
        $provider->expects($this->once())
                 ->method('checkAuthentication')
                 ->willThrowException(new BadCredentialsException('Foo'))
        ;

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticate(): void
    {
        $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();
        $user->expects($this->once())
             ->method('getRoles')
             ->willReturn(['ROLE_FOO'])
        ;

        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($user)
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->willReturn('foo')
        ;

        $token->expects($this->once())
              ->method('getRoles')
              ->willReturn([])
        ;

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf(\Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken::class, $authToken);
        $this->assertSame($user, $authToken->getUser());
        $this->assertEquals([new Role('ROLE_FOO')], $authToken->getRoles());
        $this->assertEquals('foo', $authToken->getCredentials());
        $this->assertEquals(['foo' => 'bar'], $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    public function testAuthenticateWithPreservingRoleSwitchUserRole(): void
    {
        $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();
        $user->expects($this->once())
             ->method('getRoles')
             ->willReturn(['ROLE_FOO'])
        ;

        $provider = $this->getProvider();
        $provider->expects($this->once())
                 ->method('retrieveUser')
                 ->willReturn($user)
        ;

        $token = $this->getSupportedToken();
        $token->expects($this->once())
              ->method('getCredentials')
              ->willReturn('foo')
        ;

        $switchUserRole = new SwitchUserRole('foo', $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock());
        $token->expects($this->once())
              ->method('getRoles')
              ->willReturn([$switchUserRole])
        ;

        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf(\Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken::class, $authToken);
        $this->assertSame($user, $authToken->getUser());
        $this->assertContainsEquals(new Role('ROLE_FOO'), $authToken->getRoles());
        $this->assertContainsEquals($switchUserRole, $authToken->getRoles());
        $this->assertEquals('foo', $authToken->getCredentials());
        $this->assertEquals(['foo' => 'bar'], $authToken->getAttributes(), '->authenticate() copies token attributes');
    }

    protected function getSupportedToken()
    {
        $mock = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken::class)->setMethods(['getCredentials', 'getProviderKey', 'getRoles'])->disableOriginalConstructor()->getMock();
        $mock
            ->expects($this->any())
            ->method('getProviderKey')
            ->willReturn('key')
        ;

        $mock->setAttributes(['foo' => 'bar']);

        return $mock;
    }

    protected function getProvider($userChecker = false, $hide = true)
    {
        if (false === $userChecker) {
            $userChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock();
        }

        return $this->getMockForAbstractClass(\Symfony\Component\Security\Core\Authentication\Provider\UserAuthenticationProvider::class, [$userChecker, 'key', $hide]);
    }
}
