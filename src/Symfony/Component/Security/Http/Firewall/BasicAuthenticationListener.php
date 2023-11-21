<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

/**
 * BasicAuthenticationListener implements Basic HTTP authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class BasicAuthenticationListener implements ListenerInterface
{
    private readonly \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $tokenStorage;

    private readonly \Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface $authenticationManager;

    private $providerKey;

    private readonly \Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface $authenticationEntryPoint;

    private ?\Psr\Log\LoggerInterface $logger = null;

    private readonly bool $ignoreFailure;

    private ?\Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface $sessionStrategy = null;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, $providerKey, AuthenticationEntryPointInterface $authenticationEntryPoint, LoggerInterface $logger = null)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->logger = $logger;
        $this->ignoreFailure = false;
    }

    /**
     * Handles basic authentication.
     */
    public function handle(GetResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (null === $username = $request->headers->get('PHP_AUTH_USER')) {
            return;
        }

        if (null !== ($token = $this->tokenStorage->getToken()) && ($token instanceof UsernamePasswordToken && $token->isAuthenticated() && $token->getUsername() === $username)) {
            return;
        }

        if ($this->logger instanceof \Psr\Log\LoggerInterface) {
            $this->logger->info('Basic authentication Authorization header found for user.', ['username' => $username]);
        }

        try {
            $token = $this->authenticationManager->authenticate(new UsernamePasswordToken($username, $request->headers->get('PHP_AUTH_PW'), $this->providerKey));

            $this->migrateSession($request, $token);

            $this->tokenStorage->setToken($token);
        } catch (AuthenticationException $authenticationException) {
            $token = $this->tokenStorage->getToken();
            if ($token instanceof UsernamePasswordToken && $this->providerKey === $token->getProviderKey()) {
                $this->tokenStorage->setToken(null);
            }

            if ($this->logger instanceof \Psr\Log\LoggerInterface) {
                $this->logger->info('Basic authentication failed for user.', ['username' => $username, 'exception' => $authenticationException]);
            }

            if ($this->ignoreFailure) {
                return;
            }

            $event->setResponse($this->authenticationEntryPoint->start($request, $authenticationException));
        }
    }

    /**
     * Call this method if your authentication token is stored to a session.
     *
     * @final
     */
    public function setSessionAuthenticationStrategy(SessionAuthenticationStrategyInterface $sessionStrategy): void
    {
        $this->sessionStrategy = $sessionStrategy;
    }

    private function migrateSession(Request $request, TokenInterface $token): void
    {
        if (!$this->sessionStrategy || !$request->hasSession() || !$request->hasPreviousSession()) {
            return;
        }

        $this->sessionStrategy->onAuthentication($request, $token);
    }
}
