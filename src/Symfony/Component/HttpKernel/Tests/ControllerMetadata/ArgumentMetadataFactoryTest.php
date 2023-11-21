<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\ControllerMetadata;

use Fake\ImportedAndFake;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\BasicTypesController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\NullableController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;

class ArgumentMetadataFactoryTest extends TestCase
{
    private \Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory $factory;

    protected function setUp()
    {
        $this->factory = new ArgumentMetadataFactory();
    }

    public function testSignature1(): void
    {
        $arguments = $this->factory->createArgumentMetadata(fn(\Symfony\Component\HttpKernel\Tests\ControllerMetadata\ArgumentMetadataFactoryTest $foo, array $bar, callable $baz) => $this->signature1($foo, $bar, $baz));

        $this->assertEquals([
            new ArgumentMetadata('foo', self::class, false, false, null),
            new ArgumentMetadata('bar', 'array', false, false, null),
            new ArgumentMetadata('baz', 'callable', false, false, null),
        ], $arguments);
    }

    public function testSignature2(): void
    {
        $arguments = $this->factory->createArgumentMetadata(fn(?\Symfony\Component\HttpKernel\Tests\ControllerMetadata\ArgumentMetadataFactoryTest $foo = null, ?\Symfony\Component\HttpKernel\Tests\ControllerMetadata\FakeClassThatDoesNotExist $bar = null, ?\Fake\ImportedAndFake $baz = null) => $this->signature2($foo, $bar, $baz));

        $this->assertEquals([
            new ArgumentMetadata('foo', self::class, false, true, null, true),
            new ArgumentMetadata('bar', FakeClassThatDoesNotExist::class, false, true, null, true),
            new ArgumentMetadata('baz', 'Fake\ImportedAndFake', false, true, null, true),
        ], $arguments);
    }

    public function testSignature3(): void
    {
        $arguments = $this->factory->createArgumentMetadata(fn(\Symfony\Component\HttpKernel\Tests\ControllerMetadata\FakeClassThatDoesNotExist $bar, \Fake\ImportedAndFake $baz) => $this->signature3($bar, $baz));

        $this->assertEquals([
            new ArgumentMetadata('bar', FakeClassThatDoesNotExist::class, false, false, null),
            new ArgumentMetadata('baz', 'Fake\ImportedAndFake', false, false, null),
        ], $arguments);
    }

    public function testSignature4(): void
    {
        $arguments = $this->factory->createArgumentMetadata(fn($foo = 'default', $bar = 500, $baz = []) => $this->signature4($foo, $bar, $baz));

        $this->assertEquals([
            new ArgumentMetadata('foo', null, false, true, 'default'),
            new ArgumentMetadata('bar', null, false, true, 500),
            new ArgumentMetadata('baz', null, false, true, []),
        ], $arguments);
    }

    public function testSignature5(): void
    {
        $arguments = $this->factory->createArgumentMetadata(fn(?array $foo = null, $bar = null) => $this->signature5($foo, $bar));

        $this->assertEquals([
            new ArgumentMetadata('foo', 'array', false, true, null, true),
            new ArgumentMetadata('bar', null, false, true, null, true),
        ], $arguments);
    }

    /**
     * @requires PHP 5.6
     */
    public function testVariadicSignature(): void
    {
        $arguments = $this->factory->createArgumentMetadata(static fn($foo, $bar) => (new VariadicController())->action($foo, $bar));

        $this->assertEquals([
            new ArgumentMetadata('foo', null, false, false, null),
            new ArgumentMetadata('bar', null, true, false, null),
        ], $arguments);
    }

    /**
     * @requires PHP 7.0
     */
    public function testBasicTypesSignature(): void
    {
        $arguments = $this->factory->createArgumentMetadata(static fn(string $foo, int $bar, float $baz) => (new BasicTypesController())->action($foo, $bar, $baz));

        $this->assertEquals([
            new ArgumentMetadata('foo', 'string', false, false, null),
            new ArgumentMetadata('bar', 'int', false, false, null),
            new ArgumentMetadata('baz', 'float', false, false, null),
        ], $arguments);
    }

    /**
     * @requires PHP 7.1
     */
    public function testNullableTypesSignature(): void
    {
        $arguments = $this->factory->createArgumentMetadata(static fn(?string $foo, ?\stdClass $bar, ?string $baz = 'value', string $last = '') => (new NullableController())->action($foo, $bar, $baz, $last));

        $this->assertEquals([
            new ArgumentMetadata('foo', 'string', false, false, null, true),
            new ArgumentMetadata('bar', \stdClass::class, false, false, null, true),
            new ArgumentMetadata('baz', 'string', false, true, 'value', true),
            new ArgumentMetadata('last', 'string', false, true, '', false),
        ], $arguments);
    }
}
