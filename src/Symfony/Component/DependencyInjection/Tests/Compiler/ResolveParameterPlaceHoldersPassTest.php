<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\ResolveParameterPlaceHoldersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

class ResolveParameterPlaceHoldersPassTest extends TestCase
{
    private \Symfony\Component\DependencyInjection\Compiler\ResolveParameterPlaceHoldersPass $compilerPass;

    private $container;

    private $fooDefinition;

    protected function setUp()
    {
        $this->compilerPass = new ResolveParameterPlaceHoldersPass();
        $this->container = $this->createContainerBuilder();
        $this->compilerPass->process($this->container);
        $this->fooDefinition = $this->container->getDefinition('foo');
    }

    public function testClassParametersShouldBeResolved(): void
    {
        $this->assertSame('Foo', $this->fooDefinition->getClass());
    }

    public function testFactoryParametersShouldBeResolved(): void
    {
        $this->assertSame(['FooFactory', 'getFoo'], $this->fooDefinition->getFactory());
    }

    public function testArgumentParametersShouldBeResolved(): void
    {
        $this->assertSame(['bar', ['bar' => 'baz']], $this->fooDefinition->getArguments());
    }

    public function testMethodCallParametersShouldBeResolved(): void
    {
        $this->assertSame([['foobar', ['bar', ['bar' => 'baz']]]], $this->fooDefinition->getMethodCalls());
    }

    public function testPropertyParametersShouldBeResolved(): void
    {
        $this->assertSame(['bar' => 'baz'], $this->fooDefinition->getProperties());
    }

    public function testFileParametersShouldBeResolved(): void
    {
        $this->assertSame('foo.php', $this->fooDefinition->getFile());
    }

    public function testAliasParametersShouldBeResolved(): void
    {
        $this->assertSame('foo', $this->container->getAlias('bar')->__toString());
    }

    public function testBindingsShouldBeResolved(): void
    {
        list($boundValue) = $this->container->getDefinition('foo')->getBindings()['$baz']->getValues();

        $this->assertSame($this->container->getParameterBag()->resolveValue('%env(BAZ)%'), $boundValue);
    }

    public function testParameterNotFoundExceptionsIsThrown(): void
    {
        $this->expectException(ParameterNotFoundException::class);
        $this->expectExceptionMessage('The service "baz_service_id" has a dependency on a non-existent parameter "non_existent_param".');

        $containerBuilder = new ContainerBuilder();
        $definition = $containerBuilder->register('baz_service_id');
        $definition->setArgument(0, '%non_existent_param%');

        $pass = new ResolveParameterPlaceHoldersPass();
        $pass->process($containerBuilder);
    }

    public function testParameterNotFoundExceptionsIsNotThrown(): void
    {
        $containerBuilder = new ContainerBuilder();
        $definition = $containerBuilder->register('baz_service_id');
        $definition->setArgument(0, '%non_existent_param%');

        $pass = new ResolveParameterPlaceHoldersPass(true, false);
        $pass->process($containerBuilder);

        $this->assertCount(1, $definition->getErrors());
    }

    private function createContainerBuilder(): \Symfony\Component\DependencyInjection\ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->setParameter('foo.class', 'Foo');
        $containerBuilder->setParameter('foo.factory.class', 'FooFactory');
        $containerBuilder->setParameter('foo.arg1', 'bar');
        $containerBuilder->setParameter('foo.arg2', ['%foo.arg1%' => 'baz']);
        $containerBuilder->setParameter('foo.method', 'foobar');
        $containerBuilder->setParameter('foo.property.name', 'bar');
        $containerBuilder->setParameter('foo.property.value', 'baz');
        $containerBuilder->setParameter('foo.file', 'foo.php');
        $containerBuilder->setParameter('alias.id', 'bar');

        $fooDefinition = $containerBuilder->register('foo', '%foo.class%');
        $fooDefinition->setFactory(['%foo.factory.class%', 'getFoo']);
        $fooDefinition->setArguments(['%foo.arg1%', ['%foo.arg1%' => 'baz']]);
        $fooDefinition->addMethodCall('%foo.method%', ['%foo.arg1%', '%foo.arg2%']);
        $fooDefinition->setProperty('%foo.property.name%', '%foo.property.value%');
        $fooDefinition->setFile('%foo.file%');
        $fooDefinition->setBindings(['$baz' => '%env(BAZ)%']);

        $containerBuilder->setAlias('%alias.id%', 'foo');

        return $containerBuilder;
    }
}
