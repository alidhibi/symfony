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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\LegacyNullableController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;

class ControllerResolverTest extends TestCase
{
    public function testGetControllerWithoutControllerParameter(): void
    {
        $logger = $this->getMockBuilder(\Psr\Log\LoggerInterface::class)->getMock();
        $logger->expects($this->once())->method('warning')->with('Unable to look for the controller as the "_controller" parameter is missing.');
        $resolver = $this->createControllerResolver($logger);

        $request = Request::create('/');
        $this->assertFalse($resolver->getController($request), '->getController() returns false when the request has no _controller attribute');
    }

    public function testGetControllerWithLambda(): void
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', $lambda = static function () : void {
        });
        $controller = $resolver->getController($request);
        $this->assertSame($lambda, $controller);
    }

    public function testGetControllerWithObjectAndInvokeMethod(): void
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', $this);
        $controller = $resolver->getController($request);
        $this->assertSame($this, $controller);
    }

    public function testGetControllerWithObjectAndMethod(): void
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', fn($foo) => $this->controllerMethod1($foo));
        $controller = $resolver->getController($request);
        $this->assertSame(fn($foo) => $this->controllerMethod1($foo), $controller);
    }

    public function testGetControllerWithClassAndMethod(): void
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', static fn() => \Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest::controllerMethod4());
        $controller = $resolver->getController($request);
        $this->assertSame(static fn() => \Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest::controllerMethod4(), $controller);
    }

    public function testGetControllerWithObjectAndMethodAsString(): void
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', \Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest::class . '::controllerMethod1');
        $controller = $resolver->getController($request);
        $this->assertInstanceOf(\Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest::class, $controller[0], '->getController() returns a PHP callable');
    }

    public function testGetControllerWithClassAndInvokeMethod(): void
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', \Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest::class);
        $controller = $resolver->getController($request);
        $this->assertInstanceOf(\Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest::class, $controller);
    }

    public function testGetControllerOnObjectWithoutInvokeMethod(): void
    {
        $this->expectException('InvalidArgumentException');
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', new \stdClass());
        $resolver->getController($request);
    }

    public function testGetControllerWithFunction(): void
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', 'Symfony\Component\HttpKernel\Tests\Controller\some_controller_function');
        $controller = $resolver->getController($request);
        $this->assertSame('Symfony\Component\HttpKernel\Tests\Controller\some_controller_function', $controller);
    }

    /**
     * @dataProvider getUndefinedControllers
     */
    public function testGetControllerOnNonUndefinedFunction(int|string $controller, string $exceptionName = null, string $exceptionMessage = null): void
    {
        $resolver = $this->createControllerResolver();
        $this->expectException($exceptionName);
        $this->expectExceptionMessage($exceptionMessage);

        $request = Request::create('/');
        $request->attributes->set('_controller', $controller);
        $resolver->getController($request);
    }

    public function getUndefinedControllers()
    {
        return [
            [1, 'InvalidArgumentException', 'Unable to find controller "1".'],
            ['foo', 'InvalidArgumentException', 'Unable to find controller "foo".'],
            ['oof::bar', 'InvalidArgumentException', 'Class "oof" does not exist.'],
            ['stdClass', 'InvalidArgumentException', 'Unable to find controller "stdClass".'],
            [\Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::class . '::staticsAction', 'InvalidArgumentException', 'The controller for URI "/" is not callable: Expected method "staticsAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest", did you mean "staticAction"?'],
            [\Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::class . '::privateAction', 'InvalidArgumentException', 'The controller for URI "/" is not callable: Method "privateAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest" should be public and non-abstract'],
            [\Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::class . '::protectedAction', 'InvalidArgumentException', 'The controller for URI "/" is not callable: Method "protectedAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest" should be public and non-abstract'],
            [\Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::class . '::undefinedAction', 'InvalidArgumentException', 'The controller for URI "/" is not callable: Expected method "undefinedAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest". Available methods: "publicAction", "staticAction"'],
        ];
    }

    /**
     * @group legacy
     */
    public function testGetArguments(): void
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $controller = static fn() => (new self())->testGetArguments();
        $this->assertEquals([], $resolver->getArguments($request, $controller), '->getArguments() returns an empty array if the method takes no arguments');

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = static fn($foo) => (new self())->controllerMethod1($foo);
        $this->assertEquals(['foo'], $resolver->getArguments($request, $controller), '->getArguments() returns an array of arguments for the controller method');

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = static fn($foo, $bar = null) => (new self())->controllerMethod2($foo, $bar);
        $this->assertEquals(['foo', null], $resolver->getArguments($request, $controller), '->getArguments() uses default values if present');

        $request->attributes->set('bar', 'bar');
        $this->assertEquals(['foo', 'bar'], $resolver->getArguments($request, $controller), '->getArguments() overrides default values if provided in the request attributes');

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = static function ($foo) : void {
        };
        $this->assertEquals(['foo'], $resolver->getArguments($request, $controller));

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = static function ($foo, $bar = 'bar') : void {
        };
        $this->assertEquals(['foo', 'bar'], $resolver->getArguments($request, $controller));

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new self();
        $this->assertEquals(['foo', null], $resolver->getArguments($request, $controller));
        $request->attributes->set('bar', 'bar');
        $this->assertEquals(['foo', 'bar'], $resolver->getArguments($request, $controller));

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');

        $controller = 'Symfony\Component\HttpKernel\Tests\Controller\some_controller_function';
        $this->assertEquals(['foo', 'foobar'], $resolver->getArguments($request, $controller));

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');

        $controller = static fn($foo, $bar, $foobar) => (new self())->controllerMethod3($foo, $bar, $foobar);

        try {
            $resolver->getArguments($request, $controller);
            $this->fail('->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        } catch (\Exception $exception) {
            $this->assertInstanceOf('\RuntimeException', $exception, '->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        }

        $request = Request::create('/');
        $controller = static fn(\Symfony\Component\HttpFoundation\Request $request) => (new self())->controllerMethod5($request);
        $this->assertEquals([$request], $resolver->getArguments($request, $controller), '->getArguments() injects the request');
    }

    /**
     * @requires PHP 5.6
     * @group legacy
     */
    public function testGetVariadicArguments(): void
    {
        $resolver = new ControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', ['foo', 'bar']);

        $controller = static fn($foo, $bar) => (new VariadicController())->action($foo, $bar);
        $this->assertEquals(['foo', 'foo', 'bar'], $resolver->getArguments($request, $controller));
    }

    public function testCreateControllerCanReturnAnyCallable(): void
    {
        $mock = $this->getMockBuilder(\Symfony\Component\HttpKernel\Controller\ControllerResolver::class)->setMethods(['createController'])->getMock();
        $mock->expects($this->once())->method('createController')->willReturn('Symfony\Component\HttpKernel\Tests\Controller\some_controller_function');

        $request = Request::create('/');
        $request->attributes->set('_controller', 'foobar');
        $mock->getController($request);
    }

    /**
     * @group legacy
     */
    public function testIfExceptionIsThrownWhenMissingAnArgument(): void
    {
        $this->expectException('RuntimeException');
        $resolver = new ControllerResolver();
        $request = Request::create('/');

        $controller = fn($foo) => $this->controllerMethod1($foo);

        $resolver->getArguments($request, $controller);
    }

    /**
     * @requires PHP 7.1
     * @group legacy
     */
    public function testGetNullableArguments(): void
    {
        $this->markTestSkipped('PHP < 8 required.');

        $resolver = new ControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', new \stdClass());
        $request->attributes->set('mandatory', 'mandatory');

        $controller = static fn(?string $foo, ?\stdClass $bar, ?string $baz = 'value', $mandatory) => (new LegacyNullableController())->action($foo, $bar, $mandatory, $baz);
        $this->assertEquals(['foo', new \stdClass(), 'value', 'mandatory'], $resolver->getArguments($request, $controller));
    }

    /**
     * @requires PHP 7.1
     * @group legacy
     */
    public function testGetNullableArgumentsWithDefaults(): void
    {
        $this->markTestSkipped('PHP < 8 required.');

        $resolver = new ControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('mandatory', 'mandatory');
        $controller = static fn(?string $foo, ?\stdClass $bar, ?string $baz = 'value', $mandatory) => (new LegacyNullableController())->action($foo, $bar, $mandatory, $baz);
        $this->assertEquals([null, null, 'value', 'mandatory'], $resolver->getArguments($request, $controller));
    }

    protected function createControllerResolver(LoggerInterface $logger = null)
    {
        return new ControllerResolver($logger);
    }

    public function __invoke($foo, $bar = null)
    {
    }

    public function controllerMethod1($foo): void
    {
    }

    protected function controllerMethod2($foo, $bar = null)
    {
    }

    protected function controllerMethod3($foo, $bar, $foobar)
    {
    }

    protected static function controllerMethod4()
    {
    }

    protected function controllerMethod5(Request $request)
    {
    }
}

function some_controller_function($foo, $foobar): void
{
}

class ControllerTest
{
    public function publicAction(): void
    {
    }

    protected function protectedAction()
    {
    }

    public static function staticAction(): void
    {
    }
}
