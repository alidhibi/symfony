<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher;

class RequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getMethodData
     */
    public function testMethod(string $requestMethod, string|array $matcherMethod, bool $isMatch): void
    {
        $matcher = new RequestMatcher();
        $matcher->matchMethod($matcherMethod);

        $request = Request::create('', $requestMethod);
        $this->assertSame($isMatch, $matcher->matches($request));

        $matcher = new RequestMatcher(null, null, $matcherMethod);
        $request = Request::create('', $requestMethod);
        $this->assertSame($isMatch, $matcher->matches($request));
    }

    public function getMethodData(): array
    {
        return [
            ['get', 'get', true],
            ['get', ['get', 'post'], true],
            ['get', 'post', false],
            ['get', 'GET', true],
            ['get', ['GET', 'POST'], true],
            ['get', 'POST', false],
        ];
    }

    public function testScheme(): void
    {
        $httpRequest = Request::create('');
        $request = $httpRequest;

        $httpsRequest = Request::create('', 'get', [], [], [], ['HTTPS' => 'on']);
        $matcher = new RequestMatcher();
        $matcher->matchScheme('https');
        $this->assertFalse($matcher->matches($httpRequest));
        $this->assertTrue($matcher->matches($httpsRequest));

        $matcher->matchScheme('http');
        $this->assertFalse($matcher->matches($httpsRequest));
        $this->assertTrue($matcher->matches($httpRequest));

        $matcher = new RequestMatcher();
        $this->assertTrue($matcher->matches($httpsRequest));
        $this->assertTrue($matcher->matches($httpRequest));
    }

    /**
     * @dataProvider getHostData
     */
    public function testHost(string $pattern, bool $isMatch): void
    {
        $matcher = new RequestMatcher();
        $request = Request::create('', 'get', [], [], [], ['HTTP_HOST' => 'foo.example.com']);

        $matcher->matchHost($pattern);
        $this->assertSame($isMatch, $matcher->matches($request));

        $matcher = new RequestMatcher(null, $pattern);
        $this->assertSame($isMatch, $matcher->matches($request));
    }

    public function getHostData(): array
    {
        return [
            ['.*\.example\.com', true],
            ['\.example\.com$', true],
            ['^.*\.example\.com$', true],
            ['.*\.sensio\.com', false],
            ['.*\.example\.COM', true],
            ['\.example\.COM$', true],
            ['^.*\.example\.COM$', true],
            ['.*\.sensio\.COM', false],
        ];
    }

    public function testPath(): void
    {
        $matcher = new RequestMatcher();

        $request = Request::create('/admin/foo');

        $matcher->matchPath('/admin/.*');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchPath('/admin');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchPath('^/admin/.*$');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchMethod('/blog/.*');
        $this->assertFalse($matcher->matches($request));
    }

    public function testPathWithLocaleIsNotSupported(): void
    {
        $matcher = new RequestMatcher();
        $request = Request::create('/en/login');
        $request->setLocale('en');

        $matcher->matchPath('^/{_locale}/login$');
        $this->assertFalse($matcher->matches($request));
    }

    public function testPathWithEncodedCharacters(): void
    {
        $matcher = new RequestMatcher();
        $request = Request::create('/admin/fo%20o');
        $matcher->matchPath('^/admin/fo o*$');
        $this->assertTrue($matcher->matches($request));
    }

    public function testAttributes(): void
    {
        $matcher = new RequestMatcher();

        $request = Request::create('/admin/foo');
        $request->attributes->set('foo', 'foo_bar');

        $matcher->matchAttribute('foo', 'foo_.*');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchAttribute('foo', 'foo');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchAttribute('foo', '^foo_bar$');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchAttribute('foo', 'babar');
        $this->assertFalse($matcher->matches($request));
    }
}
