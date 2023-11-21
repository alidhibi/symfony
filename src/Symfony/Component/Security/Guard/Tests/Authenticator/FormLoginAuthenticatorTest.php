<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;

/**
 * @author Jean Pasdeloup <jpasdeloup@sedona.fr>
 */
class FormLoginAuthenticatorTest extends TestCase
{
    private \Symfony\Component\HttpFoundation\Request $requestWithoutSession;

    private \Symfony\Component\HttpFoundation\Request $requestWithSession;

    private \Symfony\Component\Security\Guard\Tests\Authenticator\TestFormLoginAuthenticator $authenticator;

    final const LOGIN_URL = 'http://login';

    final const DEFAULT_SUCCESS_URL = 'http://defaultsuccess';

    final const CUSTOM_SUCCESS_URL = 'http://customsuccess';

    public function testAuthenticationFailureWithoutSession(): void
    {
        $failureResponse = $this->authenticator->onAuthenticationFailure($this->requestWithoutSession, new AuthenticationException());

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());
    }

    public function testAuthenticationFailureWithSession(): void
    {
        $this->requestWithSession->getSession()
            ->expects($this->once())
            ->method('set');

        $failureResponse = $this->authenticator->onAuthenticationFailure($this->requestWithSession, new AuthenticationException());

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());
    }

    /**
     * @group legacy
     */
    public function testAuthenticationSuccessWithoutSession(): void
    {
        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $redirectResponse = $this->authenticator->onAuthenticationSuccess($this->requestWithoutSession, $token, 'providerkey');

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $redirectResponse);
        $this->assertEquals(self::DEFAULT_SUCCESS_URL, $redirectResponse->getTargetUrl());
    }

    /**
     * @group legacy
     */
    public function testAuthenticationSuccessWithSessionButEmpty(): void
    {
        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestWithSession->getSession()
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $redirectResponse = $this->authenticator->onAuthenticationSuccess($this->requestWithSession, $token, 'providerkey');

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $redirectResponse);
        $this->assertEquals(self::DEFAULT_SUCCESS_URL, $redirectResponse->getTargetUrl());
    }

    /**
     * @group legacy
     */
    public function testAuthenticationSuccessWithSessionAndTarget(): void
    {
        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestWithSession->getSession()
            ->expects($this->once())
            ->method('get')
            ->willReturn(self::CUSTOM_SUCCESS_URL);

        $redirectResponse = $this->authenticator->onAuthenticationSuccess($this->requestWithSession, $token, 'providerkey');

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $redirectResponse);
        $this->assertEquals(self::CUSTOM_SUCCESS_URL, $redirectResponse->getTargetUrl());
    }

    public function testRememberMe(): void
    {
        $doSupport = $this->authenticator->supportsRememberMe();

        $this->assertTrue($doSupport);
    }

    public function testStartWithoutSession(): void
    {
        $failureResponse = $this->authenticator->start($this->requestWithoutSession, new AuthenticationException());

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());
    }

    public function testStartWithSession(): void
    {
        $failureResponse = $this->authenticator->start($this->requestWithSession, new AuthenticationException());

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());
    }

    protected function setUp()
    {
        $this->requestWithoutSession = new Request([], [], [], [], [], []);
        $this->requestWithSession = new Request([], [], [], [], [], []);

        $session = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Session\SessionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestWithSession->setSession($session);

        $this->authenticator = new TestFormLoginAuthenticator();
        $this->authenticator
            ->setLoginUrl(self::LOGIN_URL)
            ->setDefaultSuccessRedirectUrl(self::DEFAULT_SUCCESS_URL)
        ;
    }
}

class TestFormLoginAuthenticator extends AbstractFormLoginAuthenticator
{
    private $loginUrl;

    private $defaultSuccessRedirectUrl;

    /**
     * @param mixed $defaultSuccessRedirectUrl
     *
     */
    public function setDefaultSuccessRedirectUrl($defaultSuccessRedirectUrl): static
    {
        $this->defaultSuccessRedirectUrl = $defaultSuccessRedirectUrl;

        return $this;
    }

    /**
     * @param mixed $loginUrl
     *
     */
    public function setLoginUrl($loginUrl): static
    {
        $this->loginUrl = $loginUrl;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getLoginUrl()
    {
        return $this->loginUrl;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSuccessRedirectUrl()
    {
        return $this->defaultSuccessRedirectUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(Request $request): string
    {
        return 'credentials';
    }

    /**
     * {@inheritdoc}
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        return $userProvider->loadUserByUsername($credentials);
    }

    /**
     * {@inheritdoc}
     */
    public function checkCredentials($credentials, UserInterface $user): bool
    {
        return true;
    }
}
