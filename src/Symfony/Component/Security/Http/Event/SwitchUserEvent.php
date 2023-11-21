<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * SwitchUserEvent.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SwitchUserEvent extends Event
{
    private readonly \Symfony\Component\HttpFoundation\Request $request;

    private readonly \Symfony\Component\Security\Core\User\UserInterface $targetUser;

    private ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token = null;

    public function __construct(Request $request, UserInterface $targetUser, TokenInterface $token = null)
    {
        $this->request = $request;
        $this->targetUser = $targetUser;
        $this->token = $token;
    }

    public function getRequest(): \Symfony\Component\HttpFoundation\Request
    {
        return $this->request;
    }

    public function getTargetUser(): \Symfony\Component\Security\Core\User\UserInterface
    {
        return $this->targetUser;
    }

    public function getToken(): ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface
    {
        return $this->token;
    }

    public function setToken(TokenInterface $token): void
    {
        $this->token = $token;
    }
}
