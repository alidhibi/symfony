<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;

class ChildDefinitionTest extends TestCase
{
    public function testConstructor(): void
    {
        $def = new ChildDefinition('foo');

        $this->assertSame('foo', $def->getParent());
        $this->assertSame([], $def->getChanges());
    }

    /**
     * @dataProvider getPropertyTests
     */
    public function testSetProperty(string $property, string $changeKey): void
    {
        $def = new ChildDefinition('foo');

        $getter = 'get'.ucfirst($property);
        $setter = 'set'.ucfirst($property);

        $this->assertNull($def->$getter());
        $this->assertSame($def, $def->$setter('foo'));
        $this->assertSame('foo', $def->$getter());
        $this->assertSame([$changeKey => true], $def->getChanges());
    }

    public function getPropertyTests(): array
    {
        return [
            ['class', 'class'],
            ['factory', 'factory'],
            ['configurator', 'configurator'],
            ['file', 'file'],
        ];
    }

    public function testSetPublic(): void
    {
        $def = new ChildDefinition('foo');

        $this->assertTrue($def->isPublic());
        $this->assertSame($def, $def->setPublic(false));
        $this->assertFalse($def->isPublic());
        $this->assertSame(['public' => true], $def->getChanges());
    }

    public function testSetLazy(): void
    {
        $def = new ChildDefinition('foo');

        $this->assertFalse($def->isLazy());
        $this->assertSame($def, $def->setLazy(false));
        $this->assertFalse($def->isLazy());
        $this->assertSame(['lazy' => true], $def->getChanges());
    }

    public function testSetAutowired(): void
    {
        $def = new ChildDefinition('foo');

        $this->assertFalse($def->isAutowired());
        $this->assertSame($def, $def->setAutowired(true));
        $this->assertTrue($def->isAutowired());
        $this->assertSame(['autowired' => true], $def->getChanges());
    }

    public function testSetArgument(): void
    {
        $def = new ChildDefinition('foo');

        $this->assertSame([], $def->getArguments());
        $this->assertSame($def, $def->replaceArgument(0, 'foo'));
        $this->assertSame(['index_0' => 'foo'], $def->getArguments());
    }

    public function testReplaceArgumentShouldRequireIntegerIndex(): void
    {
        $this->expectException('InvalidArgumentException');
        $def = new ChildDefinition('foo');

        $def->replaceArgument('0', 'foo');
    }

    public function testReplaceArgument(): void
    {
        $def = new ChildDefinition('foo');

        $def->setArguments([0 => 'foo', 1 => 'bar']);
        $this->assertSame('foo', $def->getArgument(0));
        $this->assertSame('bar', $def->getArgument(1));

        $this->assertSame($def, $def->replaceArgument(1, 'baz'));
        $this->assertSame('foo', $def->getArgument(0));
        $this->assertSame('baz', $def->getArgument(1));

        $this->assertSame([0 => 'foo', 1 => 'bar', 'index_1' => 'baz'], $def->getArguments());

        $this->assertSame($def, $def->replaceArgument('$bar', 'val'));
        $this->assertSame('val', $def->getArgument('$bar'));
        $this->assertSame([0 => 'foo', 1 => 'bar', 'index_1' => 'baz', '$bar' => 'val'], $def->getArguments());
    }

    public function testGetArgumentShouldCheckBounds(): void
    {
        $this->expectException('OutOfBoundsException');
        $def = new ChildDefinition('foo');

        $def->setArguments([0 => 'foo']);
        $def->replaceArgument(0, 'foo');

        $def->getArgument(1);
    }

    public function testDefinitionDecoratorAliasExistsForBackwardsCompatibility(): void
    {
        $this->assertInstanceOf(ChildDefinition::class, new DefinitionDecorator('foo'));
    }

    public function testCannotCallSetAutoconfigured(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\BadMethodCallException::class);
        $def = new ChildDefinition('foo');
        $def->setAutoconfigured(true);
    }

    public function testCannotCallSetInstanceofConditionals(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\BadMethodCallException::class);
        $def = new ChildDefinition('foo');
        $def->setInstanceofConditionals(['Foo' => new ChildDefinition('')]);
    }
}
