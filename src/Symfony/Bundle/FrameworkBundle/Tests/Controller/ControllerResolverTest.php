<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Psr\Container\ContainerInterface as Psr11ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Tests\Controller\ContainerControllerResolverTest;

class ControllerResolverTest extends ContainerControllerResolverTest
{
    public function testGetControllerOnContainerAware(): void
    {
        $resolver = $this->createControllerResolver();
        $request = Request::create('/');
        $request->attributes->set('_controller', \Symfony\Bundle\FrameworkBundle\Tests\Controller\ContainerAwareController::class . '::testAction');

        $controller = $resolver->getController($request);

        $this->assertInstanceOf('Symfony\Component\DependencyInjection\ContainerInterface', $controller[0]->getContainer());
        $this->assertSame('testAction', $controller[1]);
    }

    public function testGetControllerOnContainerAwareInvokable(): void
    {
        $resolver = $this->createControllerResolver();
        $request = Request::create('/');
        $request->attributes->set('_controller', \Symfony\Bundle\FrameworkBundle\Tests\Controller\ContainerAwareController::class);

        $controller = $resolver->getController($request);

        $this->assertInstanceOf(\Symfony\Bundle\FrameworkBundle\Tests\Controller\ContainerAwareController::class, $controller);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\ContainerInterface', $controller->getContainer());
    }

    public function testGetControllerWithBundleNotation(): void
    {
        $shortName = 'FooBundle:Default:test';
        $parser = $this->createMockParser();
        $parser->expects($this->once())
            ->method('parse')
            ->with($shortName)
            ->willReturn(\Symfony\Bundle\FrameworkBundle\Tests\Controller\ContainerAwareController::class . '::testAction')
        ;

        $resolver = $this->createControllerResolver(null, null, $parser);
        $request = Request::create('/');
        $request->attributes->set('_controller', $shortName);

        $controller = $resolver->getController($request);

        $this->assertInstanceOf(\Symfony\Bundle\FrameworkBundle\Tests\Controller\ContainerAwareController::class, $controller[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\ContainerInterface', $controller[0]->getContainer());
        $this->assertSame('testAction', $controller[1]);
    }

    public function testContainerAwareControllerGetsContainerWhenNotSet(): void
    {
        class_exists(AbstractControllerTest::class);

        $controller = new ContainerAwareController();

        $container = new Container();
        $container->set(TestAbstractController::class, $controller);

        $resolver = $this->createControllerResolver(null, $container);

        $request = Request::create('/');
        $request->attributes->set('_controller', TestAbstractController::class.':testAction');

        $this->assertSame(static fn() => $controller->testAction(), $resolver->getController($request));
        $this->assertSame($container, $controller->getContainer());
    }

    public function testAbstractControllerGetsContainerWhenNotSet(): void
    {
        class_exists(AbstractControllerTest::class);

        $controller = new TestAbstractController(false);

        $container = new Container();
        $container->set(TestAbstractController::class, $controller);

        $resolver = $this->createControllerResolver(null, $container);

        $request = Request::create('/');
        $request->attributes->set('_controller', TestAbstractController::class.'::fooAction');

        $this->assertSame(static fn() => $controller->fooAction(), $resolver->getController($request));
        $this->assertSame($container, $controller->setContainer($container));
    }

    public function testAbstractControllerServiceWithFcqnIdGetsContainerWhenNotSet(): void
    {
        class_exists(AbstractControllerTest::class);

        $controller = new DummyController();

        $container = new Container();
        $container->set(DummyController::class, $controller);

        $resolver = $this->createControllerResolver(null, $container);

        $request = Request::create('/');
        $request->attributes->set('_controller', DummyController::class.':fooAction');

        $this->assertSame(static fn() => $controller->fooAction(), $resolver->getController($request));
        $this->assertSame($container, $controller->getContainer());
    }

    public function testAbstractControllerGetsNoContainerWhenSet(): void
    {
        class_exists(AbstractControllerTest::class);

        $controller = new TestAbstractController(false);
        $controllerContainer = new Container();
        $controller->setContainer($controllerContainer);

        $container = new Container();
        $container->set(TestAbstractController::class, $controller);

        $resolver = $this->createControllerResolver(null, $container);

        $request = Request::create('/');
        $request->attributes->set('_controller', TestAbstractController::class.'::fooAction');

        $this->assertSame(static fn() => $controller->fooAction(), $resolver->getController($request));
        $this->assertSame($controllerContainer, $controller->setContainer($container));
    }

    public function testAbstractControllerServiceWithFcqnIdGetsNoContainerWhenSet(): void
    {
        class_exists(AbstractControllerTest::class);

        $controller = new DummyController();
        $controllerContainer = new Container();
        $controller->setContainer($controllerContainer);

        $container = new Container();
        $container->set(DummyController::class, $controller);

        $resolver = $this->createControllerResolver(null, $container);

        $request = Request::create('/');
        $request->attributes->set('_controller', DummyController::class.':fooAction');

        $this->assertSame(static fn() => $controller->fooAction(), $resolver->getController($request));
        $this->assertSame($controllerContainer, $controller->getContainer());
    }

    protected function createControllerResolver(LoggerInterface $logger = null, Psr11ContainerInterface $container = null, ControllerNameParser $parser = null)
    {
        if (!$parser instanceof \Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser) {
            $parser = $this->createMockParser();
        }

        if (!$container instanceof \Psr\Container\ContainerInterface) {
            $container = $this->createMockContainer();
        }

        return new ControllerResolver($container, $parser, $logger);
    }

    protected function createMockParser()
    {
        return $this->getMockBuilder(\Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser::class)->disableOriginalConstructor()->getMock();
    }

    protected function createMockContainer()
    {
        return $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
    }
}

class ContainerAwareController implements ContainerAwareInterface
{
    private ?\Symfony\Component\DependencyInjection\ContainerInterface $container = null;

    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    public function getContainer(): ?\Symfony\Component\DependencyInjection\ContainerInterface
    {
        return $this->container;
    }

    public function testAction(): void
    {
    }

    public function __invoke()
    {
    }
}

class DummyController extends AbstractController
{
    public function getContainer()
    {
        return $this->container;
    }

    public function fooAction(): void
    {
    }
}
