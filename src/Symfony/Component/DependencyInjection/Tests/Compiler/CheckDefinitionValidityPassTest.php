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
use Symfony\Component\DependencyInjection\Compiler\CheckDefinitionValidityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CheckDefinitionValidityPassTest extends TestCase
{
    public function testProcessDetectsSyntheticNonPublicDefinitions(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\RuntimeException::class);
        $container = new ContainerBuilder();
        $container->register('a')->setSynthetic(true)->setPublic(false);

        $this->process($container);
    }

    public function testProcessDetectsNonSyntheticNonAbstractDefinitionWithoutClass(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\RuntimeException::class);
        $container = new ContainerBuilder();
        $container->register('a')->setSynthetic(false)->setAbstract(false);

        $this->process($container);
    }

    public function testProcess(): void
    {
        $container = new ContainerBuilder();
        $container->register('a', 'class');
        $container->register('b', 'class')->setSynthetic(true)->setPublic(true);
        $container->register('c', 'class')->setAbstract(true);
        $container->register('d', 'class')->setSynthetic(true);

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    public function testValidTags(): void
    {
        $container = new ContainerBuilder();
        $container->register('a', 'class')->addTag('foo', ['bar' => 'baz']);
        $container->register('b', 'class')->addTag('foo', ['bar' => null]);
        $container->register('c', 'class')->addTag('foo', ['bar' => 1]);
        $container->register('d', 'class')->addTag('foo', ['bar' => 1.1]);

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    public function testInvalidTags(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\RuntimeException::class);
        $container = new ContainerBuilder();
        $container->register('a', 'class')->addTag('foo', ['bar' => ['baz' => 'baz']]);

        $this->process($container);
    }

    public function testDynamicPublicServiceName(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\EnvParameterException::class);
        $container = new ContainerBuilder();
        $env = $container->getParameterBag()->get('env(BAR)');
        $container->register('foo.' . $env, 'class')->setPublic(true);

        $this->process($container);
    }

    public function testDynamicPublicAliasName(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\EnvParameterException::class);
        $container = new ContainerBuilder();
        $env = $container->getParameterBag()->get('env(BAR)');
        $container->setAlias('foo.' . $env, 'class')->setPublic(true);

        $this->process($container);
    }

    public function testDynamicPrivateName(): void
    {
        $container = new ContainerBuilder();
        $env = $container->getParameterBag()->get('env(BAR)');
        $container->register('foo.' . $env, 'class');
        $container->setAlias('bar.' . $env, 'class');

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new CheckDefinitionValidityPass();
        $pass->process($container);
    }
}
