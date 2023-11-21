<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingRequest;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingSession;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\NullableController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;

class ArgumentResolverTest extends TestCase
{
    private static \Symfony\Component\HttpKernel\Controller\ArgumentResolver $resolver;

    public static function setUpBeforeClass(): void
    {
        $factory = new ArgumentMetadataFactory();

        self::$resolver = new ArgumentResolver($factory);
    }

    public function testDefaultState(): void
    {
        $this->assertEquals(self::$resolver, new ArgumentResolver());
        $this->assertNotEquals(self::$resolver, new ArgumentResolver(null, [new RequestAttributeValueResolver()]));
    }

    public function testGetArguments(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = static fn($foo) => (new self())->controllerWithFoo($foo);

        $this->assertEquals(['foo'], self::$resolver->getArguments($request, $controller), '->getArguments() returns an array of arguments for the controller method');
    }

    public function testGetArgumentsReturnsEmptyArrayWhenNoArguments(): void
    {
        $request = Request::create('/');
        $controller = static fn() => (new self())->controllerWithoutArguments();

        $this->assertEquals([], self::$resolver->getArguments($request, $controller), '->getArguments() returns an empty array if the method takes no arguments');
    }

    public function testGetArgumentsUsesDefaultValue(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = static fn($foo, $bar = null) => (new self())->controllerWithFooAndDefaultBar($foo, $bar);

        $this->assertEquals(['foo', null], self::$resolver->getArguments($request, $controller), '->getArguments() uses default values if present');
    }

    public function testGetArgumentsOverrideDefaultValueByRequestAttribute(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'bar');

        $controller = static fn($foo, $bar = null) => (new self())->controllerWithFooAndDefaultBar($foo, $bar);

        $this->assertEquals(['foo', 'bar'], self::$resolver->getArguments($request, $controller), '->getArguments() overrides default values if provided in the request attributes');
    }

    public function testGetArgumentsFromClosure(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = static function ($foo) : void {
        };

        $this->assertEquals(['foo'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsUsesDefaultValueFromClosure(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = static function ($foo, $bar = 'bar') : void {
        };

        $this->assertEquals(['foo', 'bar'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFromInvokableObject(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new self();

        $this->assertEquals(['foo', null], self::$resolver->getArguments($request, $controller));

        // Test default bar overridden by request attribute
        $request->attributes->set('bar', 'bar');

        $this->assertEquals(['foo', 'bar'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFromFunctionName(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');

        $controller = __NAMESPACE__.'\controller_function';

        $this->assertEquals(['foo', 'foobar'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFailsOnUnresolvedValue(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');

        $controller = static fn($foo, $bar, $foobar) => (new self())->controllerWithFooBarFoobar($foo, $bar, $foobar);

        try {
            self::$resolver->getArguments($request, $controller);
            $this->fail('->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        } catch (\Exception $exception) {
            $this->assertInstanceOf('\RuntimeException', $exception, '->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        }
    }

    public function testGetArgumentsInjectsRequest(): void
    {
        $request = Request::create('/');
        $controller = static fn(\Symfony\Component\HttpFoundation\Request $request) => (new self())->controllerWithRequest($request);

        $this->assertEquals([$request], self::$resolver->getArguments($request, $controller), '->getArguments() injects the request');
    }

    public function testGetArgumentsInjectsExtendingRequest(): void
    {
        $request = ExtendingRequest::create('/');
        $controller = static fn(\Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingRequest $request) => (new self())->controllerWithExtendingRequest($request);

        $this->assertEquals([$request], self::$resolver->getArguments($request, $controller), '->getArguments() injects the request when extended');
    }

    /**
     * @requires PHP 5.6
     */
    public function testGetVariadicArguments(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', ['foo', 'bar']);

        $controller = static fn($foo, $bar) => (new VariadicController())->action($foo, $bar);

        $this->assertEquals(['foo', 'foo', 'bar'], self::$resolver->getArguments($request, $controller));
    }

    /**
     * @requires PHP 5.6
     */
    public function testGetVariadicArgumentsWithoutArrayInRequest(): void
    {
        $this->expectException('InvalidArgumentException');
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');

        $controller = static fn($foo, $bar) => (new VariadicController())->action($foo, $bar);

        self::$resolver->getArguments($request, $controller);
    }

    /**
     * @requires PHP 5.6
     */
    public function testGetArgumentWithoutArray(): void
    {
        $this->expectException('InvalidArgumentException');
        $factory = new ArgumentMetadataFactory();
        $valueResolver = $this->getMockBuilder(ArgumentValueResolverInterface::class)->getMock();
        $resolver = new ArgumentResolver($factory, [$valueResolver]);

        $valueResolver->expects($this->any())->method('supports')->willReturn(true);
        $valueResolver->expects($this->any())->method('resolve')->willReturn('foo');

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');

        $controller = fn($foo, $bar = null) => $this->controllerWithFooAndDefaultBar($foo, $bar);
        $resolver->getArguments($request, $controller);
    }

    public function testIfExceptionIsThrownWhenMissingAnArgument(): void
    {
        $this->expectException('RuntimeException');
        $request = Request::create('/');
        $controller = fn($foo) => $this->controllerWithFoo($foo);

        self::$resolver->getArguments($request, $controller);
    }

    /**
     * @requires PHP 7.1
     */
    public function testGetNullableArguments(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', new \stdClass());
        $request->attributes->set('last', 'last');

        $controller = static fn(?string $foo, ?\stdClass $bar, ?string $baz = 'value', string $last = '') => (new NullableController())->action($foo, $bar, $baz, $last);

        $this->assertEquals(['foo', new \stdClass(), 'value', 'last'], self::$resolver->getArguments($request, $controller));
    }

    /**
     * @requires PHP 7.1
     */
    public function testGetNullableArgumentsWithDefaults(): void
    {
        $request = Request::create('/');
        $request->attributes->set('last', 'last');
        $controller = static fn(?string $foo, ?\stdClass $bar, ?string $baz = 'value', string $last = '') => (new NullableController())->action($foo, $bar, $baz, $last);

        $this->assertEquals([null, null, 'value', 'last'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArguments(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $controller = fn(\Symfony\Component\HttpFoundation\Session\Session $session) => $this->controllerWithSession($session);

        $this->assertEquals([$session], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithExtendedSession(): void
    {
        $session = new ExtendingSession(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $controller = fn(\Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingSession $session) => $this->controllerWithExtendingSession($session);

        $this->assertEquals([$session], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithInterface(): void
    {
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $request = Request::create('/');
        $request->setSession($session);

        $controller = fn(\Symfony\Component\HttpFoundation\Session\SessionInterface $session) => $this->controllerWithSessionInterface($session);

        $this->assertEquals([$session], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionMissMatchWithInterface(): void
    {
        $this->expectException('RuntimeException');
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $request = Request::create('/');
        $request->setSession($session);

        $controller = fn(\Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingSession $session) => $this->controllerWithExtendingSession($session);

        self::$resolver->getArguments($request, $controller);
    }

    public function testGetSessionMissMatchWithImplementation(): void
    {
        $this->expectException('RuntimeException');
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $controller = fn(\Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingSession $session) => $this->controllerWithExtendingSession($session);

        self::$resolver->getArguments($request, $controller);
    }

    public function testGetSessionMissMatchOnNull(): void
    {
        $this->expectException('RuntimeException');
        $request = Request::create('/');
        $controller = fn(\Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingSession $session) => $this->controllerWithExtendingSession($session);

        self::$resolver->getArguments($request, $controller);
    }

    public function __invoke($foo, $bar = null)
    {
    }

    public function controllerWithFoo($foo): void
    {
    }

    public function controllerWithoutArguments(): void
    {
    }

    protected function controllerWithFooAndDefaultBar($foo, $bar = null)
    {
    }

    protected function controllerWithFooBarFoobar($foo, $bar, $foobar)
    {
    }

    protected function controllerWithRequest(Request $request)
    {
    }

    protected function controllerWithExtendingRequest(ExtendingRequest $request)
    {
    }

    protected function controllerWithSession(Session $session)
    {
    }

    protected function controllerWithSessionInterface(SessionInterface $session)
    {
    }

    protected function controllerWithExtendingSession(ExtendingSession $session)
    {
    }
}

function controller_function($foo, $foobar): void
{
}
