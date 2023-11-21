<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ClassLoader\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ClassLoader\Psr4ClassLoader;

/**
 * @group legacy
 */
class Psr4ClassLoaderTest extends TestCase
{
    /**
     * @param string $className
     * @dataProvider getLoadClassTests
     */
    public function testLoadClass($className): void
    {
        $loader = new Psr4ClassLoader();
        $loader->addPrefix(
            'Acme\\DemoLib',
            __DIR__.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR.'psr-4'
        );
        $loader->loadClass($className);
        $this->assertTrue(class_exists($className), sprintf('loadClass() should load %s', $className));
    }

    public function getLoadClassTests(): array
    {
        return [
            [\Acme\DemoLib\Foo::class],
            [\Acme\DemoLib\Class_With_Underscores::class],
            [\Acme\DemoLib\Lets\Go\Deeper\Foo::class],
            [\Acme\DemoLib\Lets\Go\Deeper\Class_With_Underscores::class],
        ];
    }

    /**
     * @dataProvider getLoadNonexistentClassTests
     */
    public function testLoadNonexistentClass(string $className): void
    {
        $loader = new Psr4ClassLoader();
        $loader->addPrefix(
            'Acme\\DemoLib',
            __DIR__.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR.'psr-4'
        );
        $loader->loadClass($className);
        $this->assertFalse(class_exists($className), sprintf('loadClass() should not load %s', $className));
    }

    public function getLoadNonexistentClassTests(): array
    {
        return [
            ['Acme\\DemoLib\\I_Do_Not_Exist'],
            ['UnknownVendor\\SomeLib\\I_Do_Not_Exist'],
        ];
    }
}
