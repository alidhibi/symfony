<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class AuthorizationCheckerTest extends TestCase
{
    private $authenticationManager;

    private $accessDecisionManager;

    private \Symfony\Component\Security\Core\Authorization\AuthorizationChecker $authorizationChecker;

    private \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage $tokenStorage;

    protected function setUp()
    {
        $this->authenticationManager = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock();
        $this->accessDecisionManager = $this->getMockBuilder(\Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface::class)->getMock();
        $this->tokenStorage = new TokenStorage();

        $this->authorizationChecker = new AuthorizationChecker(
            $this->tokenStorage,
            $this->authenticationManager,
            $this->accessDecisionManager
        );
    }

    public function testVoteAuthenticatesTokenIfNecessary(): void
    {
        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $this->tokenStorage->setToken($token);

        $newToken = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();

        $this->authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->equalTo($token))
            ->willReturn($newToken);

        // default with() isn't a strict check
        $tokenComparison = static fn($value): bool => $value === $newToken;

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->callback($tokenComparison))
            ->willReturn(true);

        // first run the token has not been re-authenticated yet, after isGranted is called, it should be equal
        $this->assertNotSame($newToken, $this->tokenStorage->getToken());
        $this->assertTrue($this->authorizationChecker->isGranted('foo'));
        $this->assertSame($newToken, $this->tokenStorage->getToken());
    }

    public function testVoteWithoutAuthenticationToken(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException::class);
        $this->authorizationChecker->isGranted('ROLE_FOO');
    }

    /**
     * @dataProvider isGrantedProvider
     */
    public function testIsGranted(bool $decide): void
    {
        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $token
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->willReturn($decide);
        $this->tokenStorage->setToken($token);
        $this->assertSame($decide, $this->authorizationChecker->isGranted('ROLE_FOO'));
    }

    public function isGrantedProvider(): array
    {
        return [[true], [false]];
    }
}
