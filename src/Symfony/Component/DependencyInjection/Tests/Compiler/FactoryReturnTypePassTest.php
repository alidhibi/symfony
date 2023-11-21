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
use Symfony\Component\DependencyInjection\Compiler\FactoryReturnTypePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\factoryFunction;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryParent;

/**
 * @author Guilhem N. <egetick@gmail.com>
 *
 * @group legacy
 */
class FactoryReturnTypePassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = new ContainerBuilder();

        $factory = $container->register('factory');
        $factory->setFactory(static fn(): \Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy => \Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy::createFactory());

        $container->setAlias('alias_factory', 'factory');

        $foo = $container->register('foo');
        $foo->setFactory([new Reference('alias_factory'), 'create']);

        $bar = $container->register('bar', __CLASS__);
        $bar->setFactory([new Reference('factory'), 'create']);

        $pass = new FactoryReturnTypePass();
        $pass->process($container);

        if (method_exists(\ReflectionMethod::class, 'getReturnType')) {
            $this->assertEquals(FactoryDummy::class, $factory->getClass());
            $this->assertEquals(\stdClass::class, $foo->getClass());
        } else {
            $this->assertNull($factory->getClass());
            $this->assertNull($foo->getClass());
        }

        $this->assertEquals(__CLASS__, $bar->getClass());
    }

    /**
     * @dataProvider returnTypesProvider
     */
    public function testReturnTypes(string|array $factory, ?string $returnType, bool $hhvmSupport = true): void
    {
        if (!$hhvmSupport && \defined('HHVM_VERSION')) {
            $this->markTestSkipped('Scalar typehints not supported by hhvm.');
        }

        $container = new ContainerBuilder();

        $service = $container->register('service');
        $service->setFactory($factory);

        $pass = new FactoryReturnTypePass();
        $pass->process($container);

        if (method_exists(\ReflectionMethod::class, 'getReturnType')) {
            $this->assertEquals($returnType, $service->getClass());
        } else {
            $this->assertNull($service->getClass());
        }
    }

    public function returnTypesProvider(): array
    {
        return [
            // must be loaded before the function as they are in the same file
            [static fn(): int => (new \Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy())->createBuiltin(), null, false],
            [static fn(): \Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryParent => \Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy::createParent(), FactoryParent::class],
            [static fn(): \Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy => \Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy::createSelf(), FactoryDummy::class],
            [factoryFunction::class, FactoryDummy::class],
        ];
    }

    public function testCircularReference(): void
    {
        $container = new ContainerBuilder();

        $factory = $container->register('factory');
        $factory->setFactory([new Reference('factory2'), 'createSelf']);

        $factory2 = $container->register('factory2');
        $factory2->setFactory([new Reference('factory'), 'create']);

        $pass = new FactoryReturnTypePass();
        $pass->process($container);

        $this->assertNull($factory->getClass());
        $this->assertNull($factory2->getClass());
    }

    /**
     * @requires function ReflectionMethod::getReturnType
     * @expectedDeprecation Relying on its factory's return-type to define the class of service "factory" is deprecated since Symfony 3.3 and won't work in 4.0. Set the "class" attribute to "Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy" on the service definition instead.
     */
    public function testCompile(): void
    {
        $container = new ContainerBuilder();

        $factory = $container->register('factory');
        $factory->setFactory(static fn(): \Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy => \Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy::createFactory());

        $container->compile();

        $this->assertEquals(FactoryDummy::class, $container->getDefinition('factory')->getClass());
    }
}
