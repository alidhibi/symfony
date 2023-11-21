<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Firewall\AccessListener;

class AccessListenerTest extends TestCase
{
    public function testHandleWhenTheAccessDecisionManagerDecidesToRefuseAccess(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $accessMap = $this->getMockBuilder(\Symfony\Component\Security\Http\AccessMapInterface::class)->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $token
            ->expects($this->any())
            ->method('isAuthenticated')
            ->willReturn(true)
        ;

        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $accessDecisionManager = $this->getMockBuilder(\Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface::class)->getMock();
        $accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->equalTo($token), $this->equalTo(['foo' => 'bar']), $this->equalTo($request))
            ->willReturn(false)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock()
        );

        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\GetResponseEvent::class)->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }

    public function testHandleWhenTheTokenIsNotAuthenticated(): void
    {
        $request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $accessMap = $this->getMockBuilder(\Symfony\Component\Security\Http\AccessMapInterface::class)->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([['foo' => 'bar'], null])
        ;

        $notAuthenticatedToken = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $notAuthenticatedToken
            ->expects($this->any())
            ->method('isAuthenticated')
            ->willReturn(false)
        ;

        $authenticatedToken = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $authenticatedToken
            ->expects($this->any())
            ->method('isAuthenticated')
            ->willReturn(true)
        ;

        $authManager = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock();
        $authManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->equalTo($notAuthenticatedToken))
            ->willReturn($authenticatedToken)
        ;

        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($notAuthenticatedToken)
        ;
        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo($authenticatedToken))
        ;

        $accessDecisionManager = $this->getMockBuilder(\Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface::class)->getMock();
        $accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->equalTo($authenticatedToken), $this->equalTo(['foo' => 'bar']), $this->equalTo($request))
            ->willReturn(true)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $accessDecisionManager,
            $accessMap,
            $authManager
        );

        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\GetResponseEvent::class)->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }

    public function testHandleWhenThereIsNoAccessMapEntryMatchingTheRequest(): void
    {
        $request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $accessMap = $this->getMockBuilder(\Symfony\Component\Security\Http\AccessMapInterface::class)->getMock();
        $accessMap
            ->expects($this->any())
            ->method('getPatterns')
            ->with($this->equalTo($request))
            ->willReturn([null, null])
        ;

        $token = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $token
            ->expects($this->never())
            ->method('isAuthenticated')
        ;

        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface::class)->getMock(),
            $accessMap,
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock()
        );

        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\GetResponseEvent::class)->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener->handle($event);
    }

    public function testHandleWhenTheSecurityTokenStorageHasNoToken(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException::class);
        $tokenStorage = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn(null)
        ;

        $listener = new AccessListener(
            $tokenStorage,
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface::class)->getMock(),
            $this->getMockBuilder(\Symfony\Component\Security\Http\AccessMapInterface::class)->getMock(),
            $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::class)->getMock()
        );

        $event = $this->getMockBuilder(\Symfony\Component\HttpKernel\Event\GetResponseEvent::class)->disableOriginalConstructor()->getMock();

        $listener->handle($event);
    }
}
