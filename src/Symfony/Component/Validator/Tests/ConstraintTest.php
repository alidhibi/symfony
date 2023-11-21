<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Tests\Fixtures\ClassConstraint;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintC;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintWithStaticProperty;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintWithTypedProperty;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintWithValue;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintWithValueAsDefault;

class ConstraintTest extends TestCase
{
    public function testSetProperties(): void
    {
        $constraint = new ConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
        ]);

        $this->assertEquals('foo', $constraint->property1);
        $this->assertEquals('bar', $constraint->property2);
    }

    public function testSetNotExistingPropertyThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\InvalidOptionsException::class);

        new ConstraintA([
            'foo' => 'bar',
        ]);
    }

    public function testMagicPropertiesAreNotAllowed(): void
    {
        $constraint = new ConstraintA();

        $this->expectException(\Symfony\Component\Validator\Exception\InvalidOptionsException::class);

        $constraint->foo = 'bar';
    }

    public function testInvalidAndRequiredOptionsPassed(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\InvalidOptionsException::class);

        new ConstraintC([
            'option1' => 'default',
            'foo' => 'bar',
        ]);
    }

    public function testSetDefaultProperty(): void
    {
        $constraint = new ConstraintA('foo');

        $this->assertEquals('foo', $constraint->property2);
    }

    public function testSetDefaultPropertyDoctrineStyle(): void
    {
        $constraint = new ConstraintA(['value' => 'foo']);

        $this->assertEquals('foo', $constraint->property2);
    }

    public function testSetDefaultPropertyDoctrineStylePlusOtherProperty(): void
    {
        $constraint = new ConstraintA(['value' => 'foo', 'property1' => 'bar']);

        $this->assertEquals('foo', $constraint->property2);
        $this->assertEquals('bar', $constraint->property1);
    }

    public function testSetDefaultPropertyDoctrineStyleWhenDefaultPropertyIsNamedValue(): void
    {
        $constraint = new ConstraintWithValueAsDefault(['value' => 'foo']);

        $this->assertEquals('foo', $constraint->value);
        $this->assertNull($constraint->property);
    }

    public function testDontSetDefaultPropertyIfValuePropertyExists(): void
    {
        $constraint = new ConstraintWithValue(['value' => 'foo']);

        $this->assertEquals('foo', $constraint->value);
        $this->assertNull($constraint->property);
    }

    public function testSetUndefinedDefaultProperty(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);

        new ConstraintB('foo');
    }

    public function testRequiredOptionsMustBeDefined(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\MissingOptionsException::class);

        new ConstraintC();
    }

    public function testRequiredOptionsPassed(): void
    {
        $constraint = new ConstraintC(['option1' => 'default']);

        $this->assertSame('default', $constraint->option1);
    }

    public function testGroupsAreConvertedToArray(): void
    {
        $constraint = new ConstraintA(['groups' => 'Foo']);

        $this->assertEquals(['Foo'], $constraint->groups);
    }

    public function testAddDefaultGroupAddsGroup(): void
    {
        $constraint = new ConstraintA(['groups' => 'Default']);
        $constraint->addImplicitGroupName('Foo');
        $this->assertEquals(['Default', 'Foo'], $constraint->groups);
    }

    public function testAllowsSettingZeroRequiredPropertyValue(): void
    {
        $constraint = new ConstraintA(0);
        $this->assertEquals(0, $constraint->property2);
    }

    public function testCanCreateConstraintWithNoDefaultOptionAndEmptyArray(): void
    {
        $constraint = new ConstraintB([]);

        $this->assertSame([Constraint::PROPERTY_CONSTRAINT, Constraint::CLASS_CONSTRAINT], $constraint->getTargets());
    }

    public function testGetTargetsCanBeString(): void
    {
        $constraint = new ClassConstraint();

        $this->assertEquals('class', $constraint->getTargets());
    }

    public function testGetTargetsCanBeArray(): void
    {
        $constraint = new ConstraintA();

        $this->assertEquals(['property', 'class'], $constraint->getTargets());
    }

    public function testSerialize(): void
    {
        $constraint = new ConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
        ]);

        $restoredConstraint = unserialize(serialize($constraint));

        $this->assertEquals($constraint, $restoredConstraint);
    }

    public function testSerializeInitializesGroupsOptionToDefault(): void
    {
        $constraint = new ConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
        ]);

        $constraint = unserialize(serialize($constraint));

        $expected = new ConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
            'groups' => 'Default',
        ]);

        $this->assertEquals($expected, $constraint);
    }

    public function testSerializeKeepsCustomGroups(): void
    {
        $constraint = new ConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
            'groups' => 'MyGroup',
        ]);

        $constraint = unserialize(serialize($constraint));

        $this->assertSame(['MyGroup'], $constraint->groups);
    }

    public function testGetErrorNameForUnknownCode(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\InvalidArgumentException::class);
        Constraint::getErrorName(1);
    }

    public function testOptionsAsDefaultOption(): void
    {
        $constraint = new ConstraintA($options = ['value1']);

        $this->assertEquals($options, $constraint->property2);

        $constraint = new ConstraintA($options = ['value1', 'property1' => 'value2']);

        $this->assertEquals($options, $constraint->property2);
    }

    public function testInvalidOptions(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The options "0", "5" do not exist in constraint "Symfony\Component\Validator\Tests\Fixtures\ConstraintA".');
        new ConstraintA(['property2' => 'foo', 'bar', 5 => 'baz']);
    }

    public function testOptionsWithInvalidInternalPointer(): void
    {
        $options = ['property1' => 'foo'];
        next($options);
        next($options);

        $constraint = new ConstraintA($options);

        $this->assertEquals('foo', $constraint->property1);
    }

    public function testAnnotationSetUndefinedDefaultOption(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);
        $this->expectExceptionMessage('No default option is configured for constraint "Symfony\Component\Validator\Tests\Fixtures\ConstraintB".');
        new ConstraintB(['value' => 1]);
    }

    public function testStaticPropertiesAreNoOptions(): void
    {
        $this->expectException(InvalidOptionsException::class);

        new ConstraintWithStaticProperty([
            'foo' => 'bar',
        ]);
    }

    /**
     * @requires PHP 7.4
     */
    public function testSetTypedProperty(): void
    {
        $constraint = new ConstraintWithTypedProperty([
            'foo' => 'bar',
        ]);

        $this->assertSame('bar', $constraint->foo);
    }
}
