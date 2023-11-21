<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;

class RegisterControllerArgumentLocatorsPassTest extends TestCase
{
    public function testInvalidClass(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "Symfony\Component\HttpKernel\Tests\DependencyInjection\NotFound" used for service "foo" cannot be found.');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NotFound::class)
            ->addTag('controller.service_arguments')
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testNoAction(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "action" attribute on tag "controller.service_arguments" {"argument":"bar"} for service "foo".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['argument' => 'bar'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testNoArgument(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "argument" attribute on tag "controller.service_arguments" {"action":"fooAction"} for service "foo".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'fooAction'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testNoService(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "id" attribute on tag "controller.service_arguments" {"action":"fooAction","argument":"bar"} for service "foo".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'fooAction', 'argument' => 'bar'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testInvalidMethod(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "action" attribute on tag "controller.service_arguments" for service "foo": no public "barAction()" method found on class "Symfony\Component\HttpKernel\Tests\DependencyInjection\RegisterTestController".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'barAction', 'argument' => 'bar', 'id' => 'bar_service'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testInvalidArgument(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "controller.service_arguments" tag for service "foo": method "fooAction()" has no "baz" argument on class "Symfony\Component\HttpKernel\Tests\DependencyInjection\RegisterTestController".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'fooAction', 'argument' => 'baz', 'id' => 'bar'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testAllActions(): void
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments')
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);

        $this->assertEquals(['foo:fooAction'], array_keys($locator));
        $this->assertInstanceof(ServiceClosureArgument::class, $locator['foo:fooAction']);

        $locator = $container->getDefinition((string) $locator['foo:fooAction']->getValues()[0]);

        $this->assertSame(ServiceLocator::class, $locator->getClass());
        $this->assertFalse($locator->isPublic());

        $expected = ['bar' => new ServiceClosureArgument(new TypedReference(ControllerDummy::class, ControllerDummy::class, RegisterTestController::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE))];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testExplicitArgument(): void
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'fooAction', 'argument' => 'bar', 'id' => 'bar'])
            ->addTag('controller.service_arguments', ['action' => 'fooAction', 'argument' => 'bar', 'id' => 'baz']) // should be ignored, the first wins
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $locator = $container->getDefinition((string) $locator['foo:fooAction']->getValues()[0]);

        $expected = ['bar' => new ServiceClosureArgument(new TypedReference('bar', ControllerDummy::class, RegisterTestController::class))];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testOptionalArgument(): void
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'fooAction', 'argument' => 'bar', 'id' => '?bar'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $locator = $container->getDefinition((string) $locator['foo:fooAction']->getValues()[0]);

        $expected = ['bar' => new ServiceClosureArgument(new TypedReference('bar', ControllerDummy::class, RegisterTestController::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE))];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testSkipSetContainer(): void
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', ContainerAwareRegisterTestController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertSame(['foo:fooAction'], array_keys($locator));
    }

    public function testExceptionOnNonExistentTypeHint(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot determine controller argument for "' . \Symfony\Component\HttpKernel\Tests\DependencyInjection\NonExistentClassController::class . '::fooAction()": the $nonExistent argument is type-hinted with the non-existent class or interface: "Symfony\Component\HttpKernel\Tests\DependencyInjection\NonExistentClass". Did you forget to add a use statement?');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NonExistentClassController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testExceptionOnNonExistentTypeHintDifferentNamespace(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot determine controller argument for "' . \Symfony\Component\HttpKernel\Tests\DependencyInjection\NonExistentClassDifferentNamespaceController::class . '::fooAction()": the $nonExistent argument is type-hinted with the non-existent class or interface: "Acme\NonExistentClass".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NonExistentClassDifferentNamespaceController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testNoExceptionOnNonExistentTypeHintOptionalArg(): void
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NonExistentClassOptionalController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertSame(['foo:barAction', 'foo:fooAction'], array_keys($locator));
    }

    public function testArgumentWithNoTypeHintIsOk(): void
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', ArgumentWithoutTypeController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertEmpty(array_keys($locator));
    }

    public function testControllersAreMadePublic(): void
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', ArgumentWithoutTypeController::class)
            ->setPublic(false)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $this->assertTrue($container->getDefinition('foo')->isPublic());
    }

    /**
     * @dataProvider provideBindings
     */
    public function testBindings(string $bindingName): void
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->setBindings([$bindingName => new Reference('foo')])
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);

        $locator = $container->getDefinition((string) $locator['foo:fooAction']->getValues()[0]);

        $expected = ['bar' => new ServiceClosureArgument(new Reference('foo'))];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function provideBindings(): array
    {
        return [[ControllerDummy::class], ['$bar']];
    }

    public function testDoNotBindScalarValueToControllerArgument(): void
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', ArgumentWithoutTypeController::class)
            ->setBindings(['$someArg' => '%foo%'])
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertEmpty($locator);
    }

    public function testBindingsOnChildDefinitions(): void
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('parent', ArgumentWithoutTypeController::class);

        $container->setDefinition('child', (new ChildDefinition('parent'))
            ->setBindings(['$someArg' => new Reference('parent')])
            ->addTag('controller.service_arguments')
        );

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertInstanceOf(ServiceClosureArgument::class, $locator['child:fooAction']);

        $locator = $container->getDefinition((string) $locator['child:fooAction']->getValues()[0])->getArgument(0);
        $this->assertInstanceOf(ServiceClosureArgument::class, $locator['someArg']);
        $this->assertEquals(new Reference('parent'), $locator['someArg']->getValues()[0]);
    }
}

class RegisterTestController
{
    public function fooAction(ControllerDummy $bar): void
    {
    }

    protected function barAction(ControllerDummy $bar)
    {
    }
}

class ContainerAwareRegisterTestController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function fooAction(ControllerDummy $bar): void
    {
    }
}

class ControllerDummy
{
}

class NonExistentClassController
{
    public function fooAction(NonExistentClass $nonExistent): void
    {
    }
}

class NonExistentClassDifferentNamespaceController
{
    public function fooAction(\Acme\NonExistentClass $nonExistent): void
    {
    }
}

class NonExistentClassOptionalController
{
    public function fooAction(NonExistentClass $nonExistent = null): void
    {
    }

    public function barAction($bar, NonExistentClass $nonExistent = null): void
    {
    }
}

class ArgumentWithoutTypeController
{
    public function fooAction($someArg): void
    {
    }
}
