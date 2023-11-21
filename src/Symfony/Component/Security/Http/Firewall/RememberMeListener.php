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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

/**
 * RememberMeListener implements authentication capabilities via a cookie.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RememberMeListener implements ListenerInterface
{
    private readonly \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $tokenStorage;

    private readonly \Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface $rememberMeServices;

    private readonly \Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface $authenticationManager;

    private ?\Psr\Log\LoggerInterface $logger = null;

    private ?\Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher = null;

    private bool $catchExceptions = true;

    private readonly \Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface $sessionStrategy;

    public function __construct(TokenStorageInterface $tokenStorage, RememberMeServicesInterface $rememberMeServices, AuthenticationManagerInterface $authenticationManager, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null, bool $catchExceptions = true, SessionAuthenticationStrategyInterface $sessionStrategy = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->rememberMeServices = $rememberMeServices;
        $this->authenticationManager = $authenticationManager;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
        $this->catchExceptions = $catchExceptions;
        $this->sessionStrategy = $sessionStrategy instanceof \Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface ? $sessionStrategy : new SessionAuthenticationStrategy(SessionAuthenticationStrategy::MIGRATE);
    }

    /**
     * Handles remember-me cookie based authentication.
     */
    public function handle(GetResponseEvent $event): void
    {
        if (null !== $this->tokenStorage->getToken()) {
            return;
        }

        $request = $event->getRequest();
        try {
            if (null === $token = $this->rememberMeServices->autoLogin($request)) {
                return;
            }
        } catch (AuthenticationException $authenticationException) {
            if ($this->logger instanceof \Psr\Log\LoggerInterface) {
                $this->logger->warning(
                    'The token storage was not populated with remember-me token as the'
                   .' RememberMeServices was not able to create a token from the remember'
                   .' me information.', ['exception' => $authenticationException]
                );
            }

            $this->rememberMeServices->loginFail($request);

            if (!$this->catchExceptions) {
                throw $authenticationException;
            }

            return;
        }

        try {
            $token = $this->authenticationManager->authenticate($token);
            if ($request->hasSession() && $request->getSession()->isStarted()) {
                $this->sessionStrategy->onAuthentication($request, $token);
            }

            $this->tokenStorage->setToken($token);

            if ($this->dispatcher instanceof \Symfony\Component\EventDispatcher\EventDispatcherInterface) {
                $loginEvent = new InteractiveLoginEvent($request, $token);
                $this->dispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
            }

            if ($this->logger instanceof \Psr\Log\LoggerInterface) {
                $this->logger->debug('Populated the token storage with a remember-me token.');
            }
        } catch (AuthenticationException $authenticationException) {
            if ($this->logger instanceof \Psr\Log\LoggerInterface) {
                $this->logger->warning(
                    'The token storage was not populated with remember-me token as the'
                   .' AuthenticationManager rejected the AuthenticationToken returned'
                   .' by the RememberMeServices.', ['exception' => $authenticationException]
                );
            }

            $this->rememberMeServices->loginFail($request, $authenticationException);

            if (!$this->catchExceptions) {
                throw $authenticationException;
            }
        }
    }
}
