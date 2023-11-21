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
use Symfony\Component\Security\Core\Authentication\Provider\RememberMeAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\User;

class RememberMeAuthenticationProviderTest extends TestCase
{
    public function testSupports(): void
    {
        $provider = $this->getProvider();

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock()));
        $this->assertFalse($provider->supports($this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\RememberMeToken::class)->disableOriginalConstructor()->getMock()));
    }

    public function testAuthenticateWhenTokenIsNotSupported(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('The token is not supported by this authentication provider.');
        $provider = $this->getProvider();

        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $provider->authenticate($token);
    }

    public function testAuthenticateWhenSecretsDoNotMatch(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $provider = $this->getProvider(null, 'secret1');
        $token = $this->getSupportedToken(null, 'secret2');

        $provider->authenticate($token);
    }

    public function testAuthenticateThrowsOnNonUserInterfaceInstance(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\LogicException::class);
        $this->expectExceptionMessage('Method "' . \Symfony\Component\Security\Core\Authentication\Token\RememberMeToken::class . '::getUser()" must return a "Symfony\Component\Security\Core\User\UserInterface" instance, "string" returned.');

        $provider = $this->getProvider();
        $token = new RememberMeToken(new User('dummyuser', null), 'foo', 'test');
        $token->setUser('stringish-user');

        $provider->authenticate($token);
    }

    public function testAuthenticateWhenPreChecksFails(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\DisabledException::class);
        $userChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock();
        $userChecker->expects($this->once())
            ->method('checkPreAuth')
            ->willThrowException(new DisabledException());

        $provider = $this->getProvider($userChecker);

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticate(): void
    {
        $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();
        $user->expects($this->exactly(2))
             ->method('getRoles')
             ->willReturn(['ROLE_FOO']);

        $provider = $this->getProvider();

        $token = $this->getSupportedToken($user);
        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf(\Symfony\Component\Security\Core\Authentication\Token\RememberMeToken::class, $authToken);
        $this->assertSame($user, $authToken->getUser());
        $this->assertEquals([new Role('ROLE_FOO')], $authToken->getRoles());
        $this->assertEquals('', $authToken->getCredentials());
    }

    protected function getSupportedToken($user = null, $secret = 'test')
    {
        if (null === $user) {
            $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();
            $user
                ->expects($this->any())
                ->method('getRoles')
                ->willReturn([]);
        }

        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\RememberMeToken::class)->setMethods(['getProviderKey'])->setConstructorArgs([$user, 'foo', $secret])->getMock();
        $token
            ->expects($this->once())
            ->method('getProviderKey')
            ->willReturn('foo');

        return $token;
    }

    protected function getProvider($userChecker = null, $key = 'test'): \Symfony\Component\Security\Core\Authentication\Provider\RememberMeAuthenticationProvider
    {
        if (null === $userChecker) {
            $userChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserCheckerInterface::class)->getMock();
        }

        return new RememberMeAuthenticationProvider($userChecker, $key, 'foo');
    }
}
