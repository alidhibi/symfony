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
use Symfony\Component\Security\Core\Authentication\Provider\SimpleAuthenticationProvider;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\User\UserChecker;

class SimpleAuthenticationProviderTest extends TestCase
{
    public function testAuthenticateWhenPreChecksFails(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\DisabledException::class);
        $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();

        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn($user);

        $userChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock();
        $userChecker->expects($this->once())
            ->method('checkPreAuth')
            ->willThrowException(new DisabledException());

        $authenticator = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface::class)->getMock();
        $authenticator->expects($this->once())
            ->method('authenticateToken')
            ->willReturn($token);

        $provider = $this->getProvider($authenticator, null, $userChecker);

        $provider->authenticate($token);
    }

    public function testAuthenticateWhenPostChecksFails(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\LockedException::class);
        $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();

        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn($user);

        $userChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock();
        $userChecker->expects($this->once())
            ->method('checkPostAuth')
            ->willThrowException(new LockedException());

        $authenticator = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface::class)->getMock();
        $authenticator->expects($this->once())
            ->method('authenticateToken')
            ->willReturn($token);

        $provider = $this->getProvider($authenticator, null, $userChecker);

        $provider->authenticate($token);
    }

    public function testAuthenticateSkipsUserChecksForNonUserInterfaceObjects(): void
    {
        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn('string-user');
        $authenticator = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface::class)->getMock();
        $authenticator->expects($this->once())
            ->method('authenticateToken')
            ->willReturn($token);

        $this->assertSame($token, $this->getProvider($authenticator, null, new UserChecker())->authenticate($token));
    }

    protected function getProvider($simpleAuthenticator = null, $userProvider = null, $userChecker = null, $key = 'test'): \Symfony\Component\Security\Core\Authentication\Provider\SimpleAuthenticationProvider
    {
        if (null === $userChecker) {
            $userChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock();
        }

        if (null === $simpleAuthenticator) {
            $simpleAuthenticator = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface::class)->getMock();
        }

        if (null === $userProvider) {
            $userProvider = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserProviderInterface::class)->getMock();
        }

        return new SimpleAuthenticationProvider($simpleAuthenticator, $userProvider, $key, $userChecker);
    }
}
