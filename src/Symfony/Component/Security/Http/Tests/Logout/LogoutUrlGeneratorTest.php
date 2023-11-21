<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Logout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class LogoutUrlGeneratorTest extends TestCase
{
    private \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage $tokenStorage;

    private \Symfony\Component\Security\Http\Logout\LogoutUrlGenerator $generator;

    protected function setUp()
    {
        $requestStack = $this->getMockBuilder(RequestStack::class)->getMock();
        $request = $this->getMockBuilder(Request::class)->getMock();
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $this->tokenStorage = new TokenStorage();
        $this->generator = new LogoutUrlGenerator($requestStack, null, $this->tokenStorage);
    }

    public function testGetLogoutPath(): void
    {
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->assertSame('/logout', $this->generator->getLogoutPath('secured_area'));
    }

    public function testGetLogoutPathWithoutLogoutListenerRegisteredForKeyThrowsException(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('No LogoutListener found for firewall key "unregistered_key".');
        $this->generator->registerListener('secured_area', '/logout', null, null, null);

        $this->generator->getLogoutPath('unregistered_key');
    }

    public function testGuessFromToken(): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken('user', 'password', 'secured_area'));
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->assertSame('/logout', $this->generator->getLogoutPath());
    }

    public function testGuessFromAnonymousTokenThrowsException(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Unable to generate a logout url for an anonymous token.');
        $this->tokenStorage->setToken(new AnonymousToken('default', 'anon.'));

        $this->generator->getLogoutPath();
    }

    public function testGuessFromCurrentFirewallKey(): void
    {
        $this->generator->registerListener('secured_area', '/logout', null, null);
        $this->generator->setCurrentFirewall('secured_area');

        $this->assertSame('/logout', $this->generator->getLogoutPath());
    }

    public function testGuessFromCurrentFirewallContext(): void
    {
        $this->generator->registerListener('secured_area', '/logout', null, null, null, 'secured');
        $this->generator->setCurrentFirewall('admin', 'secured');

        $this->assertSame('/logout', $this->generator->getLogoutPath());
    }

    public function testGuessFromTokenWithoutProviderKeyFallbacksToCurrentFirewall(): void
    {
        $this->tokenStorage->setToken($this->getMockBuilder(TokenInterface::class)->getMock());
        $this->generator->registerListener('secured_area', '/logout', null, null);
        $this->generator->setCurrentFirewall('secured_area');

        $this->assertSame('/logout', $this->generator->getLogoutPath());
    }

    public function testUnableToGuessThrowsException(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Unable to find the current firewall LogoutListener, please provide the provider key manually');
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->generator->getLogoutPath();
    }
}
