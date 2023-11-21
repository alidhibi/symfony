<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\Context;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Context\RequestStackContext;

class RequestStackContextTest extends TestCase
{
    public function testGetBasePathEmpty(): void
    {
        $requestStack = $this->getMockBuilder(\Symfony\Component\HttpFoundation\RequestStack::class)->getMock();
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertEmpty($requestStackContext->getBasePath());
    }

    public function testGetBasePathSet(): void
    {
        $testBasePath = 'test-path';

        $request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->getMock();
        $request->method('getBasePath')
            ->willReturn($testBasePath);
        $requestStack = $this->getMockBuilder(\Symfony\Component\HttpFoundation\RequestStack::class)->getMock();
        $requestStack->method('getMasterRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertSame($testBasePath, $requestStackContext->getBasePath());
    }

    public function testIsSecureFalse(): void
    {
        $requestStack = $this->getMockBuilder(\Symfony\Component\HttpFoundation\RequestStack::class)->getMock();
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertFalse($requestStackContext->isSecure());
    }

    public function testIsSecureTrue(): void
    {
        $request = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)->getMock();
        $request->method('isSecure')
            ->willReturn(true);
        $requestStack = $this->getMockBuilder(\Symfony\Component\HttpFoundation\RequestStack::class)->getMock();
        $requestStack->method('getMasterRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertTrue($requestStackContext->isSecure());
    }

    public function testDefaultContext(): void
    {
        $requestStack = $this->getMockBuilder(\Symfony\Component\HttpFoundation\RequestStack::class)->getMock();
        $requestStackContext = new RequestStackContext($requestStack, 'default-path', true);

        $this->assertSame('default-path', $requestStackContext->getBasePath());
        $this->assertTrue($requestStackContext->isSecure());
    }
}
