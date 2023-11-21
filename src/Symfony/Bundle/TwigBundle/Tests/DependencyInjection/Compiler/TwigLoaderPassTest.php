<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\TwigLoaderPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class TwigLoaderPassTest extends TestCase
{
    private \Symfony\Component\DependencyInjection\ContainerBuilder $builder;

    private \Symfony\Component\DependencyInjection\Definition $chainLoader;

    private \Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\TwigLoaderPass $pass;

    protected function setUp()
    {
        $this->builder = new ContainerBuilder();
        $this->builder->register('twig');

        $this->chainLoader = new Definition('loader');
        $this->pass = new TwigLoaderPass();
    }

    public function testMapperPassWithOneTaggedLoader(): void
    {
        $this->builder->register('test_loader_1')
            ->addTag('twig.loader');

        $this->pass->process($this->builder);

        $this->assertSame('test_loader_1', (string) $this->builder->getAlias('twig.loader'));
    }

    public function testMapperPassWithTwoTaggedLoaders(): void
    {
        $this->builder->setDefinition('twig.loader.chain', $this->chainLoader);
        $this->builder->register('test_loader_1')
            ->addTag('twig.loader');
        $this->builder->register('test_loader_2')
            ->addTag('twig.loader');

        $this->pass->process($this->builder);

        $this->assertSame('twig.loader.chain', (string) $this->builder->getAlias('twig.loader'));
        $calls = $this->chainLoader->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertEquals('addLoader', $calls[0][0]);
        $this->assertEquals('addLoader', $calls[1][0]);
        $this->assertEquals('test_loader_1', (string) $calls[0][1][0]);
        $this->assertEquals('test_loader_2', (string) $calls[1][1][0]);
    }

    public function testMapperPassWithTwoTaggedLoadersWithPriority(): void
    {
        $this->builder->setDefinition('twig.loader.chain', $this->chainLoader);
        $this->builder->register('test_loader_1')
            ->addTag('twig.loader', ['priority' => 100]);
        $this->builder->register('test_loader_2')
            ->addTag('twig.loader', ['priority' => 200]);

        $this->pass->process($this->builder);

        $this->assertSame('twig.loader.chain', (string) $this->builder->getAlias('twig.loader'));
        $calls = $this->chainLoader->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertEquals('addLoader', $calls[0][0]);
        $this->assertEquals('addLoader', $calls[1][0]);
        $this->assertEquals('test_loader_2', (string) $calls[0][1][0]);
        $this->assertEquals('test_loader_1', (string) $calls[1][1][0]);
    }

    public function testMapperPassWithZeroTaggedLoaders(): void
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\LogicException');
        $this->pass->process($this->builder);
    }
}
