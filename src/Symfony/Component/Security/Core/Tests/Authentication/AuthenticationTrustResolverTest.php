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
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;

class AuthenticationTrustResolverTest extends TestCase
{
    public function testIsAnonymous(): void
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isAnonymous(null));
        $this->assertFalse($resolver->isAnonymous($this->getToken()));
        $this->assertFalse($resolver->isAnonymous($this->getRememberMeToken()));
        $this->assertTrue($resolver->isAnonymous($this->getAnonymousToken()));
    }

    public function testIsRememberMe(): void
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isRememberMe(null));
        $this->assertFalse($resolver->isRememberMe($this->getToken()));
        $this->assertFalse($resolver->isRememberMe($this->getAnonymousToken()));
        $this->assertTrue($resolver->isRememberMe($this->getRememberMeToken()));
    }

    public function testisFullFledged(): void
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isFullFledged(null));
        $this->assertFalse($resolver->isFullFledged($this->getAnonymousToken()));
        $this->assertFalse($resolver->isFullFledged($this->getRememberMeToken()));
        $this->assertTrue($resolver->isFullFledged($this->getToken()));
    }

    protected function getToken()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
    }

    protected function getAnonymousToken()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\AnonymousToken::class)->setConstructorArgs(['', ''])->getMock();
    }

    protected function getRememberMeToken()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\RememberMeToken::class)->setMethods(['setPersistent'])->disableOriginalConstructor()->getMock();
    }

    protected function getResolver(): \Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver
    {
        return new AuthenticationTrustResolver(
            \Symfony\Component\Security\Core\Authentication\Token\AnonymousToken::class,
            \Symfony\Component\Security\Core\Authentication\Token\RememberMeToken::class
        );
    }
}
