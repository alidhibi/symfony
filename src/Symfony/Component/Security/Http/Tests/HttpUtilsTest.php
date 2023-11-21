<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\HttpUtils;

class HttpUtilsTest extends TestCase
{
    public function testCreateRedirectResponseWithPath(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator());
        $response = $utils->createRedirectResponse($this->getRequest(), '/foobar');

        $this->assertTrue($response->isRedirect('http://localhost/foobar'));
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testCreateRedirectResponseWithAbsoluteUrl(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator());
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://symfony.com/');

        $this->assertTrue($response->isRedirect('http://symfony.com/'));
    }

    public function testCreateRedirectResponseWithDomainRegexp(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://symfony\.com$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://symfony.com/blog');

        $this->assertTrue($response->isRedirect('http://symfony.com/blog'));
    }

    public function testCreateRedirectResponseWithRequestsDomain(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://%s$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://localhost/blog');

        $this->assertTrue($response->isRedirect('http://localhost/blog'));
    }

    /**
     * @dataProvider badRequestDomainUrls
     */
    public function testCreateRedirectResponseWithBadRequestsDomain(string $url): void
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://%s$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), $url);

        $this->assertTrue($response->isRedirect('http://localhost/'));
    }

    public function badRequestDomainUrls(): array
    {
        return [
            ['http://pirate.net/foo'],
            ['http:\\\\pirate.net/foo'],
            ['http:/\\pirate.net/foo'],
            ['http:\\/pirate.net/foo'],
            ['http://////pirate.net/foo'],
        ];
    }

    public function testCreateRedirectResponseWithProtocolRelativeTarget(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://%s$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), '//evil.com/do-bad-things');

        $this->assertTrue($response->isRedirect('http://localhost//evil.com/do-bad-things'), 'Protocol-relative redirection should not be supported for security reasons');
    }

    public function testCreateRedirectResponseWithRouteName(): void
    {
        $utils = new HttpUtils($urlGenerator = $this->getMockBuilder(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class)->getMock());

        $urlGenerator
            ->expects($this->any())
            ->method('generate')
            ->with('foobar', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost/foo/bar')
        ;
        $urlGenerator
            ->expects($this->any())
            ->method('getContext')
            ->willReturn($this->getMockBuilder(\Symfony\Component\Routing\RequestContext::class)->getMock())
        ;

        $response = $utils->createRedirectResponse($this->getRequest(), 'foobar');

        $this->assertTrue($response->isRedirect('http://localhost/foo/bar'));
    }

    public function testCreateRequestWithPath(): void
    {
        $request = $this->getRequest();
        $request->server->set('Foo', 'bar');

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertEquals('GET', $subRequest->getMethod());
        $this->assertEquals('/foobar', $subRequest->getPathInfo());
        $this->assertEquals('bar', $subRequest->server->get('Foo'));
    }

    public function testCreateRequestWithRouteName(): void
    {
        $utils = new HttpUtils($urlGenerator = $this->getMockBuilder(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class)->getMock());

        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('/foo/bar')
        ;
        $urlGenerator
            ->expects($this->any())
            ->method('getContext')
            ->willReturn($this->getMockBuilder(\Symfony\Component\Routing\RequestContext::class)->getMock())
        ;

        $subRequest = $utils->createRequest($this->getRequest(), 'foobar');

        $this->assertEquals('/foo/bar', $subRequest->getPathInfo());
    }

    public function testCreateRequestWithAbsoluteUrl(): void
    {
        $utils = new HttpUtils($this->getMockBuilder(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class)->getMock());
        $subRequest = $utils->createRequest($this->getRequest(), 'http://symfony.com/');

        $this->assertEquals('/', $subRequest->getPathInfo());
    }

    public function testCreateRequestPassesSessionToTheNewRequest(): void
    {
        $request = $this->getRequest();
        $request->setSession($session = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Session\SessionInterface::class)->getMock());

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertSame($session, $subRequest->getSession());
    }

    /**
     * @dataProvider provideSecurityContextAttributes
     */
    public function testCreateRequestPassesSecurityContextAttributesToTheNewRequest(string $attribute): void
    {
        $request = $this->getRequest();
        $request->attributes->set($attribute, 'foo');

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertSame('foo', $subRequest->attributes->get($attribute));
    }

    public function provideSecurityContextAttributes(): array
    {
        return [
            [Security::AUTHENTICATION_ERROR],
            [Security::ACCESS_DENIED_ERROR],
            [Security::LAST_USERNAME],
        ];
    }

    public function testCheckRequestPath(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator());

        $this->assertTrue($utils->checkRequestPath($this->getRequest(), '/'));
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), '/foo'));
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo%20bar'), '/foo bar'));
        // Plus must not decoded to space
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo+bar'), '/foo+bar'));
        // Checking unicode
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/'.urlencode('вход')), '/вход'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceNotFound(): void
    {
        $urlMatcher = $this->getMockBuilder(\Symfony\Component\Routing\Matcher\UrlMatcherInterface::class)->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->with('/')
            ->willThrowException(new ResourceNotFoundException())
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndMethodNotAllowed(): void
    {
        $request = $this->getRequest();
        $urlMatcher = $this->getMockBuilder(\Symfony\Component\Routing\Matcher\RequestMatcherInterface::class)->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('matchRequest')
            ->with($request)
            ->willThrowException(new MethodNotAllowedException([]))
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($request, 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceFoundByUrl(): void
    {
        $urlMatcher = $this->getMockBuilder(\Symfony\Component\Routing\Matcher\UrlMatcherInterface::class)->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->with('/foo/bar')
            ->willReturn(['_route' => 'foobar'])
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo/bar'), 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceFoundByRequest(): void
    {
        $request = $this->getRequest();
        $urlMatcher = $this->getMockBuilder(\Symfony\Component\Routing\Matcher\RequestMatcherInterface::class)->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('matchRequest')
            ->with($request)
            ->willReturn(['_route' => 'foobar'])
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertTrue($utils->checkRequestPath($request, 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherLoadingException(): void
    {
        $this->expectException('RuntimeException');
        $urlMatcher = $this->getMockBuilder(\Symfony\Component\Routing\Matcher\UrlMatcherInterface::class)->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->willThrowException(new \RuntimeException())
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $utils->checkRequestPath($this->getRequest(), 'foobar');
    }

    public function testCheckPathWithoutRouteParam(): void
    {
        $urlMatcher = $this->getMockBuilder(\Symfony\Component\Routing\Matcher\UrlMatcherInterface::class)->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->willReturn(['_controller' => 'PathController'])
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), 'path/index.html'));
    }

    public function testUrlMatcher(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Matcher must either implement UrlMatcherInterface or RequestMatcherInterface');
        new HttpUtils($this->getUrlGenerator(), new \stdClass());
    }

    public function testGenerateUriRemovesQueryString(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar'));
        $this->assertEquals('/foo/bar', $utils->generateUri(new Request(), 'route_name'));

        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar?param=value'));
        $this->assertEquals('/foo/bar', $utils->generateUri(new Request(), 'route_name'));
    }

    public function testGenerateUriPreservesFragment(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar?param=value#fragment'));
        $this->assertEquals('/foo/bar#fragment', $utils->generateUri(new Request(), 'route_name'));

        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar#fragment'));
        $this->assertEquals('/foo/bar#fragment', $utils->generateUri(new Request(), 'route_name'));
    }

    public function testUrlGeneratorIsRequiredToGenerateUrl(): void
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('You must provide a UrlGeneratorInterface instance to be able to use routes.');
        $utils = new HttpUtils();
        $utils->generateUri(new Request(), 'route_name');
    }

    private function getUrlGenerator(string $generatedUrl = '/foo/bar')
    {
        $urlGenerator = $this->getMockBuilder(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class)->getMock();
        $urlGenerator
            ->expects($this->any())
            ->method('generate')
            ->willReturn($generatedUrl)
        ;

        return $urlGenerator;
    }

    private function getRequest(string $path = '/')
    {
        return Request::create($path, 'get');
    }
}
