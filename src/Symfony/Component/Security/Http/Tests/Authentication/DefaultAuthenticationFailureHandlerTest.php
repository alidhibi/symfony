<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authentication;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;

class DefaultAuthenticationFailureHandlerTest extends TestCase
{
    private $httpKernel;

    private $httpUtils;

    private $logger;

    private $request;

    private $session;

    private $exception;

    protected function setUp()
    {
        $this->httpKernel = $this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock();
        $this->httpUtils = $this->getMockBuilder(\Symfony\Component\Security\Http\HttpUtils::class)->getMock();
        $this->logger = $this->getMockBuilder(\Psr\Log\LoggerInterface::class)->getMock();

        $this->session = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Session\SessionInterface::class)->getMock();
        $this->request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->getMock();
        $this->request->expects($this->any())->method('getSession')->willReturn($this->session);
        $this->exception = $this->getMockBuilder(\Symfony\Component\Security\Core\Exception\AuthenticationException::class)->setMethods(['getMessage'])->getMock();
    }

    public function testForward(): void
    {
        $options = ['failure_forward' => true];

        $subRequest = $this->getRequest();
        $subRequest->attributes->expects($this->once())
            ->method('set')->with(Security::AUTHENTICATION_ERROR, $this->exception);
        $this->httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($subRequest);

        $response = new Response();
        $this->httpKernel->expects($this->once())
            ->method('handle')->with($subRequest, HttpKernelInterface::SUB_REQUEST)
            ->willReturn($response);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($response, $result);
    }

    public function testRedirect(): void
    {
        $response = new RedirectResponse('/login');
        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/login')
            ->willReturn($response);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($response, $result);
    }

    public function testExceptionIsPersistedInSession(): void
    {
        $this->session->expects($this->once())
            ->method('set')->with(Security::AUTHENTICATION_ERROR, $this->exception);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testExceptionIsPassedInRequestOnForward(): void
    {
        $options = ['failure_forward' => true];

        $subRequest = $this->getRequest();
        $subRequest->attributes->expects($this->once())
            ->method('set')->with(Security::AUTHENTICATION_ERROR, $this->exception);

        $this->httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($subRequest);

        $this->session->expects($this->never())->method('set');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testRedirectIsLogged(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Authentication failure, redirect triggered.', ['failure_path' => '/login']);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testForwardIsLogged(): void
    {
        $options = ['failure_forward' => true];

        $this->httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($this->getRequest());

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Authentication failure, forward triggered.', ['failure_path' => '/login']);

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathCanBeOverwritten(): void
    {
        $options = ['failure_path' => '/auth/login'];

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathCanBeOverwrittenWithRequest(): void
    {
        $this->request->expects($this->once())
            ->method('get')->with('_failure_path')
            ->willReturn('/auth/login');

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, [], $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathCanBeOverwrittenWithNestedAttributeInRequest(): void
    {
        $this->request->expects($this->once())
            ->method('get')->with('_failure_path')
            ->willReturn(['value' => '/auth/login']);

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, ['failure_path_parameter' => '_failure_path[value]'], $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathParameterCanBeOverwritten(): void
    {
        $options = ['failure_path_parameter' => '_my_failure_path'];

        $this->request->expects($this->once())
            ->method('get')->with('_my_failure_path')
            ->willReturn('/auth/login');

        $this->httpUtils->expects($this->once())
            ->method('createRedirectResponse')->with($this->request, '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->httpKernel, $this->httpUtils, $options, $this->logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    private function getRequest()
    {
        $request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->getMock();
        $request->attributes = $this->getMockBuilder(\Symfony\Component\HttpFoundation\ParameterBag::class)->getMock();

        return $request;
    }
}
