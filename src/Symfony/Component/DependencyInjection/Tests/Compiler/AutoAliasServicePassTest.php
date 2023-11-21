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
use Symfony\Component\DependencyInjection\Compiler\AutoAliasServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AutoAliasServicePassTest extends TestCase
{
    public function testProcessWithMissingParameter(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException::class);
        $container = new ContainerBuilder();

        $container->register('example')
            ->addTag('auto_alias', ['format' => '%non_existing%.example']);

        $pass = new AutoAliasServicePass();
        $pass->process($container);
    }

    public function testProcessWithMissingFormat(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException::class);
        $container = new ContainerBuilder();

        $container->register('example')
            ->addTag('auto_alias', []);
        $container->setParameter('existing', 'mysql');

        $pass = new AutoAliasServicePass();
        $pass->process($container);
    }

    public function testProcessWithNonExistingAlias(): void
    {
        $container = new ContainerBuilder();

        $container->register('example', \Symfony\Component\DependencyInjection\Tests\Compiler\ServiceClassDefault::class)
            ->addTag('auto_alias', ['format' => '%existing%.example']);
        $container->setParameter('existing', 'mysql');

        $pass = new AutoAliasServicePass();
        $pass->process($container);

        $this->assertEquals(\Symfony\Component\DependencyInjection\Tests\Compiler\ServiceClassDefault::class, $container->getDefinition('example')->getClass());
    }

    public function testProcessWithExistingAlias(): void
    {
        $container = new ContainerBuilder();

        $container->register('example', \Symfony\Component\DependencyInjection\Tests\Compiler\ServiceClassDefault::class)
            ->addTag('auto_alias', ['format' => '%existing%.example']);

        $container->register('mysql.example', \Symfony\Component\DependencyInjection\Tests\Compiler\ServiceClassMysql::class);
        $container->setParameter('existing', 'mysql');

        $pass = new AutoAliasServicePass();
        $pass->process($container);

        $this->assertTrue($container->hasAlias('example'));
        $this->assertEquals('mysql.example', $container->getAlias('example'));
        $this->assertSame(\Symfony\Component\DependencyInjection\Tests\Compiler\ServiceClassMysql::class, $container->getDefinition('mysql.example')->getClass());
    }

    public function testProcessWithManualAlias(): void
    {
        $container = new ContainerBuilder();

        $container->register('example', \Symfony\Component\DependencyInjection\Tests\Compiler\ServiceClassDefault::class)
            ->addTag('auto_alias', ['format' => '%existing%.example']);

        $container->register('mysql.example', \Symfony\Component\DependencyInjection\Tests\Compiler\ServiceClassMysql::class);
        $container->register('mariadb.example', \Symfony\Component\DependencyInjection\Tests\Compiler\ServiceClassMariaDb::class);
        $container->setAlias('example', 'mariadb.example');
        $container->setParameter('existing', 'mysql');

        $pass = new AutoAliasServicePass();
        $pass->process($container);

        $this->assertTrue($container->hasAlias('example'));
        $this->assertEquals('mariadb.example', $container->getAlias('example'));
        $this->assertSame(\Symfony\Component\DependencyInjection\Tests\Compiler\ServiceClassMariaDb::class, $container->getDefinition('mariadb.example')->getClass());
    }
}

class ServiceClassDefault
{
}

class ServiceClassMysql extends ServiceClassDefault
{
}

class ServiceClassMariaDb extends ServiceClassMysql
{
}
