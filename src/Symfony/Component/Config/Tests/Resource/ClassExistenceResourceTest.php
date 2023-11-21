<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Resource;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\Config\Tests\Fixtures\BadFileName;
use Symfony\Component\Config\Tests\Fixtures\BadParent;
use Symfony\Component\Config\Tests\Fixtures\ParseError;
use Symfony\Component\Config\Tests\Fixtures\Resource\ConditionalClass;

class ClassExistenceResourceTest extends TestCase
{
    public function testToString(): void
    {
        $res = new ClassExistenceResource('BarClass');
        $this->assertSame('BarClass', (string) $res);
    }

    public function testGetResource(): void
    {
        $res = new ClassExistenceResource('BarClass');
        $this->assertSame('BarClass', $res->getResource());
    }

    public function testIsFreshWhenClassDoesNotExist(): void
    {
        $res = new ClassExistenceResource('Symfony\Component\Config\Tests\Fixtures\BarClass');

        $this->assertTrue($res->isFresh(time()));

        eval(<<<EOF
namespace Symfony\Component\Config\Tests\Fixtures;

class BarClass
{
}
EOF
        );

        $this->assertFalse($res->isFresh(time()));
    }

    public function testIsFreshWhenClassExists(): void
    {
        $res = new ClassExistenceResource(\Symfony\Component\Config\Tests\Resource\ClassExistenceResourceTest::class);

        $this->assertTrue($res->isFresh(time()));
    }

    public function testExistsKo(): void
    {
        spl_autoload_register($autoloader = static function ($class) use (&$loadedClass) : void {
            $loadedClass = $class;
        });

        try {
            $res = new ClassExistenceResource('MissingFooClass');
            $this->assertTrue($res->isFresh(0));

            $this->assertSame('MissingFooClass', $loadedClass);

            $loadedClass = 123;

            new ClassExistenceResource('MissingFooClass', false);

            $this->assertSame(123, $loadedClass);
        } finally {
            spl_autoload_unregister($autoloader);
        }
    }

    public function testBadParentWithTimestamp(): void
    {
        $res = new ClassExistenceResource(BadParent::class, false);
        $this->assertTrue($res->isFresh(time()));
    }

    public function testBadParentWithNoTimestamp(): void
    {
        $this->expectException('ReflectionException');
        $this->expectExceptionMessage('Class "Symfony\Component\Config\Tests\Fixtures\MissingParent" not found while loading "Symfony\Component\Config\Tests\Fixtures\BadParent".');

        $res = new ClassExistenceResource(BadParent::class, false);
        $res->isFresh(0);
    }

    public function testBadFileName(): void
    {
        $this->expectException('ReflectionException');
        $this->expectExceptionMessage('Mismatch between file name and class name.');

        $res = new ClassExistenceResource(BadFileName::class, false);
        $res->isFresh(0);
    }

    public function testBadFileNameBis(): void
    {
        $this->expectException('ReflectionException');
        $this->expectExceptionMessage('Mismatch between file name and class name.');

        $res = new ClassExistenceResource(BadFileName::class, false);
        $res->isFresh(0);
    }

    public function testConditionalClass(): void
    {
        $res = new ClassExistenceResource(ConditionalClass::class, false);

        $this->assertFalse($res->isFresh(0));
    }

    /**
     * @requires PHP 7
     */
    public function testParseError(): void
    {
        $this->expectException('ParseError');

        $res = new ClassExistenceResource(ParseError::class, false);
        $res->isFresh(0);
    }
}
