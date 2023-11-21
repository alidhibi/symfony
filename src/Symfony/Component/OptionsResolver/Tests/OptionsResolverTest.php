<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver\Tests;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OptionsResolverTest extends TestCase
{
    private \Symfony\Component\OptionsResolver\OptionsResolver|array $resolver;

    protected function setUp()
    {
        $this->resolver = new OptionsResolver();
    }

    public function testResolveFailsIfNonExistingOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException::class);
        $this->expectExceptionMessage('The option "foo" does not exist. Defined options are: "a", "z".');
        $this->resolver->setDefault('z', '1');
        $this->resolver->setDefault('a', '2');

        $this->resolver->resolve(['foo' => 'bar']);
    }

    public function testResolveFailsIfMultipleNonExistingOptions(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException::class);
        $this->expectExceptionMessage('The options "baz", "foo", "ping" do not exist. Defined options are: "a", "z".');
        $this->resolver->setDefault('z', '1');
        $this->resolver->setDefault('a', '2');

        $this->resolver->resolve(['ping' => 'pong', 'foo' => 'bar', 'baz' => 'bam']);
    }

    public function testResolveFailsFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->resolve([]);
        });

        $this->resolver->resolve();
    }

    public function testSetDefaultReturnsThis(): void
    {
        $this->assertSame($this->resolver, $this->resolver->setDefault('foo', 'bar'));
    }

    public function testSetDefault(): void
    {
        $this->resolver->setDefault('one', '1');
        $this->resolver->setDefault('two', '20');

        $this->assertEquals([
            'one' => '1',
            'two' => '20',
        ], $this->resolver->resolve());
    }

    public function testFailIfSetDefaultFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('lazy', static function (Options $options) : void {
            $options->setDefault('default', 42);
        });

        $this->resolver->resolve();
    }

    public function testHasDefault(): void
    {
        $this->assertFalse($this->resolver->hasDefault('foo'));
        $this->resolver->setDefault('foo', 42);
        $this->assertTrue($this->resolver->hasDefault('foo'));
    }

    public function testHasDefaultWithNullValue(): void
    {
        $this->assertFalse($this->resolver->hasDefault('foo'));
        $this->resolver->setDefault('foo', null);
        $this->assertTrue($this->resolver->hasDefault('foo'));
    }

    public function testSetLazyReturnsThis(): void
    {
        $this->assertSame($this->resolver, $this->resolver->setDefault('foo', static function (Options $options) : void {
        }));
    }

    public function testSetLazyClosure(): void
    {
        $this->resolver->setDefault('foo', static fn(Options $options): string => 'lazy');

        $this->assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testClosureWithoutTypeHintNotInvoked(): void
    {
        $closure = static function ($options) : void {
            Assert::fail('Should not be called');
        };

        $this->resolver->setDefault('foo', $closure);

        $this->assertSame(['foo' => $closure], $this->resolver->resolve());
    }

    public function testClosureWithoutParametersNotInvoked(): void
    {
        $closure = static function () : void {
            Assert::fail('Should not be called');
        };

        $this->resolver->setDefault('foo', $closure);

        $this->assertSame(['foo' => $closure], $this->resolver->resolve());
    }

    public function testAccessPreviousDefaultValue(): void
    {
        // defined by superclass
        $this->resolver->setDefault('foo', 'bar');

        // defined by subclass
        $this->resolver->setDefault('foo', static function (Options $options, $previousValue) : string {
            Assert::assertEquals('bar', $previousValue);
            return 'lazy';
        });

        $this->assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testAccessPreviousLazyDefaultValue(): void
    {
        // defined by superclass
        $this->resolver->setDefault('foo', static fn(Options $options): string => 'bar');

        // defined by subclass
        $this->resolver->setDefault('foo', static function (Options $options, $previousValue) : string {
            Assert::assertEquals('bar', $previousValue);
            return 'lazy';
        });

        $this->assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testPreviousValueIsNotEvaluatedIfNoSecondArgument(): void
    {
        // defined by superclass
        $this->resolver->setDefault('foo', static function () : void {
            Assert::fail('Should not be called');
        });

        // defined by subclass, no $previousValue argument defined!
        $this->resolver->setDefault('foo', static fn(Options $options): string => 'lazy');

        $this->assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testOverwrittenLazyOptionNotEvaluated(): void
    {
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            Assert::fail('Should not be called');
        });

        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testInvokeEachLazyOptionOnlyOnce(): void
    {
        $calls = 0;

        $this->resolver->setDefault('lazy1', static function (Options $options) use (&$calls) : void {
            Assert::assertSame(1, ++$calls);
            $options['lazy2'];
        });

        $this->resolver->setDefault('lazy2', static function (Options $options) use (&$calls) : void {
            Assert::assertSame(2, ++$calls);
        });

        $this->resolver->resolve();

        $this->assertSame(2, $calls);
    }

    public function testSetRequiredReturnsThis(): void
    {
        $this->assertSame($this->resolver, $this->resolver->setRequired('foo'));
    }

    public function testFailIfSetRequiredFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->setRequired('bar');
        });

        $this->resolver->resolve();
    }

    public function testResolveFailsIfRequiredOptionMissing(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\MissingOptionsException::class);
        $this->resolver->setRequired('foo');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfRequiredOptionSet(): void
    {
        $this->resolver->setRequired('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfRequiredOptionPassed(): void
    {
        $this->resolver->setRequired('foo');

        $this->assertNotEmpty($this->resolver->resolve(['foo' => 'bar']));
    }

    public function testIsRequired(): void
    {
        $this->assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->assertTrue($this->resolver->isRequired('foo'));
    }

    public function testRequiredIfSetBefore(): void
    {
        $this->assertFalse($this->resolver->isRequired('foo'));

        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setRequired('foo');

        $this->assertTrue($this->resolver->isRequired('foo'));
    }

    public function testStillRequiredAfterSet(): void
    {
        $this->assertFalse($this->resolver->isRequired('foo'));

        $this->resolver->setRequired('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertTrue($this->resolver->isRequired('foo'));
    }

    public function testIsNotRequiredAfterRemove(): void
    {
        $this->assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->resolver->remove('foo');
        $this->assertFalse($this->resolver->isRequired('foo'));
    }

    public function testIsNotRequiredAfterClear(): void
    {
        $this->assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isRequired('foo'));
    }

    public function testGetRequiredOptions(): void
    {
        $this->resolver->setRequired(['foo', 'bar']);
        $this->resolver->setDefault('bam', 'baz');
        $this->resolver->setDefault('foo', 'boo');

        $this->assertSame(['foo', 'bar'], $this->resolver->getRequiredOptions());
    }

    public function testIsMissingIfNotSet(): void
    {
        $this->assertFalse($this->resolver->isMissing('foo'));
        $this->resolver->setRequired('foo');
        $this->assertTrue($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingIfSet(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->assertFalse($this->resolver->isMissing('foo'));
        $this->resolver->setRequired('foo');
        $this->assertFalse($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingAfterRemove(): void
    {
        $this->resolver->setRequired('foo');
        $this->resolver->remove('foo');
        $this->assertFalse($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingAfterClear(): void
    {
        $this->resolver->setRequired('foo');
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isRequired('foo'));
    }

    public function testGetMissingOptions(): void
    {
        $this->resolver->setRequired(['foo', 'bar']);
        $this->resolver->setDefault('bam', 'baz');
        $this->resolver->setDefault('foo', 'boo');

        $this->assertSame(['bar'], $this->resolver->getMissingOptions());
    }

    public function testFailIfSetDefinedFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->setDefined('bar');
        });

        $this->resolver->resolve();
    }

    public function testDefinedOptionsNotIncludedInResolvedOptions(): void
    {
        $this->resolver->setDefined('foo');

        $this->assertSame([], $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfDefaultSetBefore(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefined('foo');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfDefaultSetAfter(): void
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfPassedToResolve(): void
    {
        $this->resolver->setDefined('foo');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve(['foo' => 'bar']));
    }

    public function testIsDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testLazyOptionsAreDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefault('foo', static function (Options $options) : void {
        });
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testRequiredOptionsAreDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setRequired('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testSetOptionsAreDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefault('foo', 'bar');
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testGetDefinedOptions(): void
    {
        $this->resolver->setDefined(['foo', 'bar']);
        $this->resolver->setDefault('baz', 'bam');
        $this->resolver->setRequired('boo');

        $this->assertSame(['foo', 'bar', 'baz', 'boo'], $this->resolver->getDefinedOptions());
    }

    public function testRemovedOptionsAreNotDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
        $this->resolver->remove('foo');
        $this->assertFalse($this->resolver->isDefined('foo'));
    }

    public function testClearedOptionsAreNotDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isDefined('foo'));
    }

    public function testSetAllowedTypesFailsIfUnknownOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException::class);
        $this->resolver->setAllowedTypes('foo', 'string');
    }

    public function testResolveTypedArray(): void
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[]');

        $options = $this->resolver->resolve(['foo' => ['bar', 'baz']]);

        $this->assertSame(['foo' => ['bar', 'baz']], $options);
    }

    public function testFailIfSetAllowedTypesFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->setAllowedTypes('bar', 'string');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testResolveFailsIfInvalidTypedArray(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value array is expected to be of type "int[]", but one of the elements is of type "DateTime".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');

        $this->resolver->resolve(['foo' => [new \DateTime()]]);
    }

    public function testResolveFailsWithNonArray(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value "bar" is expected to be of type "int[]", but is of type "string".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');

        $this->resolver->resolve(['foo' => 'bar']);
    }

    public function testResolveFailsIfTypedArrayContainsInvalidTypes(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value array is expected to be of type "int[]", but one of the elements is of type "stdClass|array|DateTime".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');

        $values = range(1, 5);
        $values[] = new \stdClass();
        $values[] = [];
        $values[] = new \DateTime();
        $values[] = 123;

        $this->resolver->resolve(['foo' => $values]);
    }

    public function testResolveFailsWithCorrectLevelsButWrongScalar(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value array is expected to be of type "int[][]", but one of the elements is of type "double".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');

        $this->resolver->resolve([
            'foo' => [
                [1.2],
            ],
        ]);
    }

    /**
     * @dataProvider provideInvalidTypes
     */
    public function testResolveFailsIfInvalidType($actualType, string|array $allowedType, string $exceptionMessage): void
    {
        $this->resolver->setDefined('option');
        $this->resolver->setAllowedTypes('option', $allowedType);

        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->resolver->resolve(['option' => $actualType]);
    }

    public function provideInvalidTypes(): array
    {
        return [
            [true, 'string', 'The option "option" with value true is expected to be of type "string", but is of type "boolean".'],
            [false, 'string', 'The option "option" with value false is expected to be of type "string", but is of type "boolean".'],
            [fopen(__FILE__, 'r'), 'string', 'The option "option" with value resource is expected to be of type "string", but is of type "resource".'],
            [[], 'string', 'The option "option" with value array is expected to be of type "string", but is of type "array".'],
            [new OptionsResolver(), 'string', 'The option "option" with value Symfony\Component\OptionsResolver\OptionsResolver is expected to be of type "string", but is of type "Symfony\Component\OptionsResolver\OptionsResolver".'],
            [42, 'string', 'The option "option" with value 42 is expected to be of type "string", but is of type "integer".'],
            [null, 'string', 'The option "option" with value null is expected to be of type "string", but is of type "NULL".'],
            ['bar', '\stdClass', 'The option "option" with value "bar" is expected to be of type "\stdClass", but is of type "string".'],
            [['foo', 12], 'string[]', 'The option "option" with value array is expected to be of type "string[]", but one of the elements is of type "integer".'],
            [123, ['string[]', 'string'], 'The option "option" with value 123 is expected to be of type "string[]" or "string", but is of type "integer".'],
            [[null], ['string[]', 'string'], 'The option "option" with value array is expected to be of type "string[]" or "string", but one of the elements is of type "NULL".'],
            [['string', null], ['string[]', 'string'], 'The option "option" with value array is expected to be of type "string[]" or "string", but one of the elements is of type "NULL".'],
            [[\stdClass::class], ['string'], 'The option "option" with value array is expected to be of type "string", but is of type "array".'],
        ];
    }

    public function testResolveSucceedsIfValidType(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveFailsIfInvalidTypeMultiple(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value 42 is expected to be of type "string" or "bool", but is of type "integer".');
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedTypes('foo', ['string', 'bool']);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidTypeMultiple(): void
    {
        $this->resolver->setDefault('foo', true);
        $this->resolver->setAllowedTypes('foo', ['string', 'bool']);

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfInstanceOfClass(): void
    {
        $this->resolver->setDefault('foo', new \stdClass());
        $this->resolver->setAllowedTypes('foo', '\stdClass');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfTypedArray(): void
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedTypes('foo', ['null', 'DateTime[]']);

        $data = [
            'foo' => [
                new \DateTime(),
                new \DateTime(),
            ],
        ];

        $result = $this->resolver->resolve($data);
        $this->assertEquals($data, $result);
    }

    public function testResolveFailsIfNotInstanceOfClass(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', '\stdClass');

        $this->resolver->resolve();
    }

    public function testAddAllowedTypesFailsIfUnknownOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException::class);
        $this->resolver->addAllowedTypes('foo', 'string');
    }

    public function testFailIfAddAllowedTypesFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->addAllowedTypes('bar', 'string');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testResolveFailsIfInvalidAddedType(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedTypes('foo', 'string');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedType(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedTypes('foo', 'string');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveFailsIfInvalidAddedTypeMultiple(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedTypes('foo', ['string', 'bool']);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedTypeMultiple(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedTypes('foo', ['string', 'bool']);

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testAddAllowedTypesDoesNotOverwrite(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');
        $this->resolver->addAllowedTypes('foo', 'bool');

        $this->resolver->setDefault('foo', 'bar');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testAddAllowedTypesDoesNotOverwrite2(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');
        $this->resolver->addAllowedTypes('foo', 'bool');

        $this->resolver->setDefault('foo', false);

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testSetAllowedValuesFailsIfUnknownOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException::class);
        $this->resolver->setAllowedValues('foo', 'bar');
    }

    public function testFailIfSetAllowedValuesFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->setAllowedValues('bar', 'baz');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testResolveFailsIfInvalidValue(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value 42 is invalid. Accepted values are: "bar".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->resolver->resolve(['foo' => 42]);
    }

    public function testResolveFailsIfInvalidValueIsNull(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value null is invalid. Accepted values are: "bar".');
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->resolver->resolve();
    }

    public function testResolveFailsIfInvalidValueStrict(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', '42');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidValue(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidValueIsNull(): void
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedValues('foo', null);

        $this->assertEquals(['foo' => null], $this->resolver->resolve());
    }

    public function testResolveFailsIfInvalidValueMultiple(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value 42 is invalid. Accepted values are: "bar", false, null.');
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', ['bar', false, null]);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidValueMultiple(): void
    {
        $this->resolver->setDefault('foo', 'baz');
        $this->resolver->setAllowedValues('foo', ['bar', 'baz']);

        $this->assertEquals(['foo' => 'baz'], $this->resolver->resolve());
    }

    public function testResolveFailsIfClosureReturnsFalse(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', static function ($value) use (&$passedValue) : bool {
            $passedValue = $value;
            return false;
        });

        try {
            $this->resolver->resolve();
            $this->fail('Should fail');
        } catch (InvalidOptionsException $invalidOptionsException) {
        }

        $this->assertSame(42, $passedValue);
    }

    public function testResolveSucceedsIfClosureReturnsTrue(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', static function ($value) use (&$passedValue) : bool {
            $passedValue = $value;
            return true;
        });

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
        $this->assertSame('bar', $passedValue);
    }

    public function testResolveFailsIfAllClosuresReturnFalse(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', [
            static fn(): false => false,
            static fn(): false => false,
            static fn(): false => false,
        ]);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfAnyClosureReturnsTrue(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', [
            static fn(): false => false,
            static fn(): true => true,
            static fn(): false => false,
        ]);

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testAddAllowedValuesFailsIfUnknownOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException::class);
        $this->resolver->addAllowedValues('foo', 'bar');
    }

    public function testFailIfAddAllowedValuesFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->addAllowedValues('bar', 'baz');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testResolveFailsIfInvalidAddedValue(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedValues('foo', 'bar');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedValue(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'bar');

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidAddedValueIsNull(): void
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->addAllowedValues('foo', null);

        $this->assertEquals(['foo' => null], $this->resolver->resolve());
    }

    public function testResolveFailsIfInvalidAddedValueMultiple(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedValues('foo', ['bar', 'baz']);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedValueMultiple(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedValues('foo', ['bar', 'baz']);

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testAddAllowedValuesDoesNotOverwrite(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'baz');

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testAddAllowedValuesDoesNotOverwrite2(): void
    {
        $this->resolver->setDefault('foo', 'baz');
        $this->resolver->setAllowedValues('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'baz');

        $this->assertEquals(['foo' => 'baz'], $this->resolver->resolve());
    }

    public function testResolveFailsIfAllAddedClosuresReturnFalse(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', static fn(): false => false);
        $this->resolver->addAllowedValues('foo', static fn(): false => false);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfAnyAddedClosureReturnsTrue(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', static fn(): false => false);
        $this->resolver->addAllowedValues('foo', static fn(): true => true);

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testResolveSucceedsIfAnyAddedClosureReturnsTrue2(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', static fn(): true => true);
        $this->resolver->addAllowedValues('foo', static fn(): false => false);

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testSetNormalizerReturnsThis(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->assertSame($this->resolver, $this->resolver->setNormalizer('foo', static function () : void {
        }));
    }

    public function testSetNormalizerClosure(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', static fn(): string => 'normalized');

        $this->assertEquals(['foo' => 'normalized'], $this->resolver->resolve());
    }

    public function testSetNormalizerFailsIfUnknownOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException::class);
        $this->resolver->setNormalizer('foo', static function () : void {
        });
    }

    public function testFailIfSetNormalizerFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->setNormalizer('foo', static function () : void {
            });
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testNormalizerReceivesSetOption(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setNormalizer('foo', static fn(Options $options, $value): string => 'normalized['.$value.']');

        $this->assertEquals(['foo' => 'normalized[bar]'], $this->resolver->resolve());
    }

    public function testNormalizerReceivesPassedOption(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setNormalizer('foo', static fn(Options $options, $value): string => 'normalized['.$value.']');

        $resolved = $this->resolver->resolve(['foo' => 'baz']);

        $this->assertEquals(['foo' => 'normalized[baz]'], $resolved);
    }

    public function testValidateTypeBeforeNormalization(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setAllowedTypes('foo', 'int');

        $this->resolver->setNormalizer('foo', static function () : void {
            Assert::fail('Should not be called.');
        });

        $this->resolver->resolve();
    }

    public function testValidateValueBeforeNormalization(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setAllowedValues('foo', 'baz');

        $this->resolver->setNormalizer('foo', static function () : void {
            Assert::fail('Should not be called.');
        });

        $this->resolver->resolve();
    }

    public function testNormalizerCanAccessOtherOptions(): void
    {
        $this->resolver->setDefault('default', 'bar');
        $this->resolver->setDefault('norm', 'baz');

        $this->resolver->setNormalizer('norm', static function (Options $options) : string {
            /* @var TestCase $test */
            Assert::assertSame('bar', $options['default']);
            return 'normalized';
        });

        $this->assertEquals([
            'default' => 'bar',
            'norm' => 'normalized',
        ], $this->resolver->resolve());
    }

    public function testNormalizerCanAccessLazyOptions(): void
    {
        $this->resolver->setDefault('lazy', static fn(Options $options): string => 'bar');
        $this->resolver->setDefault('norm', 'baz');

        $this->resolver->setNormalizer('norm', static function (Options $options) : string {
            /* @var TestCase $test */
            Assert::assertEquals('bar', $options['lazy']);
            return 'normalized';
        });

        $this->assertEquals([
            'lazy' => 'bar',
            'norm' => 'normalized',
        ], $this->resolver->resolve());
    }

    public function testFailIfCyclicDependencyBetweenNormalizers(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\OptionDefinitionException::class);
        $this->resolver->setDefault('norm1', 'bar');
        $this->resolver->setDefault('norm2', 'baz');

        $this->resolver->setNormalizer('norm1', static function (Options $options) : void {
            $options['norm2'];
        });

        $this->resolver->setNormalizer('norm2', static function (Options $options) : void {
            $options['norm1'];
        });

        $this->resolver->resolve();
    }

    public function testFailIfCyclicDependencyBetweenNormalizerAndLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\OptionDefinitionException::class);
        $this->resolver->setDefault('lazy', static function (Options $options) : void {
            $options['norm'];
        });

        $this->resolver->setDefault('norm', 'baz');

        $this->resolver->setNormalizer('norm', static function (Options $options) : void {
            $options['lazy'];
        });

        $this->resolver->resolve();
    }

    public function testCaughtExceptionFromNormalizerDoesNotCrashOptionResolver(): void
    {
        $throw = true;

        $this->resolver->setDefaults(['catcher' => null, 'thrower' => null]);

        $this->resolver->setNormalizer('catcher', static function (Options $options) {
            try {
                return $options['thrower'];
            } catch (\Exception $exception) {
                return false;
            }
        });

        $this->resolver->setNormalizer('thrower', static function () use (&$throw) : bool {
            if ($throw) {
                $throw = false;
                throw new \UnexpectedValueException('throwing');
            }
            return true;
        });

        $this->assertSame(['catcher' => false, 'thrower' => true], $this->resolver->resolve());
    }

    public function testCaughtExceptionFromLazyDoesNotCrashOptionResolver(): void
    {
        $throw = true;

        $this->resolver->setDefault('catcher', static function (Options $options) {
            try {
                return $options['thrower'];
            } catch (\Exception $exception) {
                return false;
            }
        });

        $this->resolver->setDefault('thrower', static function (Options $options) use (&$throw) : bool {
            if ($throw) {
                $throw = false;
                throw new \UnexpectedValueException('throwing');
            }
            return true;
        });

        $this->assertSame(['catcher' => false, 'thrower' => true], $this->resolver->resolve());
    }

    public function testInvokeEachNormalizerOnlyOnce(): void
    {
        $calls = 0;

        $this->resolver->setDefault('norm1', 'bar');
        $this->resolver->setDefault('norm2', 'baz');

        $this->resolver->setNormalizer('norm1', static function ($options) use (&$calls) : void {
            Assert::assertSame(1, ++$calls);
        });
        $this->resolver->setNormalizer('norm2', static function () use (&$calls) : void {
            Assert::assertSame(2, ++$calls);
        });

        $this->resolver->resolve();

        $this->assertSame(2, $calls);
    }

    public function testNormalizerNotCalledForUnsetOptions(): void
    {
        $this->resolver->setDefined('norm');

        $this->resolver->setNormalizer('norm', static function () : void {
            Assert::fail('Should not be called.');
        });

        $this->assertEmpty($this->resolver->resolve());
    }

    public function testSetDefaultsReturnsThis(): void
    {
        $this->assertSame($this->resolver, $this->resolver->setDefaults(['foo', 'bar']));
    }

    public function testSetDefaults(): void
    {
        $this->resolver->setDefault('one', '1');
        $this->resolver->setDefault('two', 'bar');

        $this->resolver->setDefaults([
            'two' => '2',
            'three' => '3',
        ]);

        $this->assertEquals([
            'one' => '1',
            'two' => '2',
            'three' => '3',
        ], $this->resolver->resolve());
    }

    public function testFailIfSetDefaultsFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->setDefaults(['two' => '2']);
        });

        $this->resolver->resolve();
    }

    public function testRemoveReturnsThis(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame($this->resolver, $this->resolver->remove('foo'));
    }

    public function testRemoveSingleOption(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefault('baz', 'boo');
        $this->resolver->remove('foo');

        $this->assertSame(['baz' => 'boo'], $this->resolver->resolve());
    }

    public function testRemoveMultipleOptions(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefault('baz', 'boo');
        $this->resolver->setDefault('doo', 'dam');

        $this->resolver->remove(['foo', 'doo']);

        $this->assertSame(['baz' => 'boo'], $this->resolver->resolve());
    }

    public function testRemoveLazyOption(): void
    {
        $this->resolver->setDefault('foo', static fn(Options $options): string => 'lazy');
        $this->resolver->remove('foo');

        $this->assertSame([], $this->resolver->resolve());
    }

    public function testRemoveNormalizer(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', static fn(Options $options, $value): string => 'normalized');
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testRemoveAllowedTypes(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'int');
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testRemoveAllowedValues(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', ['baz', 'boo']);
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testFailIfRemoveFromLazyOption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->remove('bar');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testRemoveUnknownOptionIgnored(): void
    {
        $this->assertNotNull($this->resolver->remove('foo'));
    }

    public function testClearReturnsThis(): void
    {
        $this->assertSame($this->resolver, $this->resolver->clear());
    }

    public function testClearRemovesAllOptions(): void
    {
        $this->resolver->setDefault('one', 1);
        $this->resolver->setDefault('two', 2);

        $this->resolver->clear();

        $this->assertEmpty($this->resolver->resolve());
    }

    public function testClearLazyOption(): void
    {
        $this->resolver->setDefault('foo', static fn(Options $options): string => 'lazy');
        $this->resolver->clear();

        $this->assertSame([], $this->resolver->resolve());
    }

    public function testClearNormalizer(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', static fn(Options $options, $value): string => 'normalized');
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testClearAllowedTypes(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'int');
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testClearAllowedValues(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'baz');
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testFailIfClearFromLazyption(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', static function (Options $options) : void {
            $options->clear();
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testClearOptionAndNormalizer(): void
    {
        $this->resolver->setDefault('foo1', 'bar');
        $this->resolver->setNormalizer('foo1', static fn(Options $options): string => '');
        $this->resolver->setDefault('foo2', 'bar');
        $this->resolver->setNormalizer('foo2', static fn(Options $options): string => '');

        $this->resolver->clear();
        $this->assertEmpty($this->resolver->resolve());
    }

    public function testArrayAccess(): void
    {
        $this->resolver->setDefault('default1', 0);
        $this->resolver->setDefault('default2', 1);
        $this->resolver->setRequired('required');
        $this->resolver->setDefined('defined');
        $this->resolver->setDefault('lazy1', static fn(Options $options): string => 'lazy');

        $this->resolver->setDefault('lazy2', static function (Options $options) : void {
            Assert::assertArrayHasKey('default1', $options);
            Assert::assertArrayHasKey('default2', $options);
            Assert::assertArrayHasKey('required', $options);
            Assert::assertArrayHasKey('lazy1', $options);
            Assert::assertArrayHasKey('lazy2', $options);
            Assert::assertArrayNotHasKey('defined', $options);
            Assert::assertSame(0, $options['default1']);
            Assert::assertSame(42, $options['default2']);
            Assert::assertSame('value', $options['required']);
            Assert::assertSame('lazy', $options['lazy1']);
            // Obviously $options['lazy'] and $options['defined'] cannot be
            // accessed
        });

        $this->resolver->resolve(['default2' => 42, 'required' => 'value']);
    }

    public function testArrayAccessGetFailsOutsideResolve(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('default', 0);
    }

    public function testArrayAccessExistsFailsOutsideResolve(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('default', 0);
    }

    public function testArrayAccessSetNotSupported(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver['default'] = 0;
    }

    public function testArrayAccessUnsetNotSupported(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('default', 0);

        unset($this->resolver['default']);
    }

    public function testFailIfGetNonExisting(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\NoSuchOptionException::class);
        $this->expectExceptionMessage('The option "undefined" does not exist. Defined options are: "foo", "lazy".');
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setDefault('lazy', static function (Options $options) : void {
            $options['undefined'];
        });

        $this->resolver->resolve();
    }

    public function testFailIfGetDefinedButUnset(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\NoSuchOptionException::class);
        $this->expectExceptionMessage('The optional option "defined" has no value set. You should make sure it is set with "isset" before reading it.');
        $this->resolver->setDefined('defined');

        $this->resolver->setDefault('lazy', static function (Options $options) : void {
            $options['defined'];
        });

        $this->resolver->resolve();
    }

    public function testFailIfCyclicDependency(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\OptionDefinitionException::class);
        $this->resolver->setDefault('lazy1', static function (Options $options) : void {
            $options['lazy2'];
        });

        $this->resolver->setDefault('lazy2', static function (Options $options) : void {
            $options['lazy1'];
        });

        $this->resolver->resolve();
    }

    public function testCount(): void
    {
        $this->resolver->setDefault('default', 0);
        $this->resolver->setRequired('required');
        $this->resolver->setDefined('defined');
        $this->resolver->setDefault('lazy1', static function () : void {
        });

        $this->resolver->setDefault('lazy2', static function (Options $options) : void {
            Assert::assertCount(4, $options);
        });

        $this->assertCount(4, $this->resolver->resolve(['required' => 'value']));
    }

    /**
     * In resolve() we count the options that are actually set (which may be
     * only a subset of the defined options). Outside of resolve(), it's not
     * clear what is counted.
     */
    public function testCountFailsOutsideResolve(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\AccessException::class);
        $this->resolver->setDefault('foo', 0);
        $this->resolver->setRequired('bar');
        $this->resolver->setDefined('bar');
        $this->resolver->setDefault('lazy1', static function () : void {
        });

        \count($this->resolver);
    }

    public function testNestedArrays(): void
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');

        $this->assertEquals([
            'foo' => [
                [
                    1, 2,
                ],
            ],
        ], $this->resolver->resolve([
            'foo' => [
                [1, 2],
            ],
        ]));
    }

    public function testNested2Arrays(): void
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][][][]');

        $this->assertEquals([
            'foo' => [
                [
                    [
                        [
                            1, 2,
                        ],
                    ],
                ],
            ],
        ], $this->resolver->resolve(
            [
                'foo' => [
                    [
                        [
                            [1, 2],
                        ],
                    ],
                ],
            ]
        ));
    }

    public function testNestedArraysException(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value array is expected to be of type "float[][][][]", but one of the elements is of type "integer".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'float[][][][]');

        $this->resolver->resolve([
            'foo' => [
                [
                    [
                        [1, 2],
                    ],
                ],
            ],
        ]);
    }

    public function testNestedArrayException1(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value array is expected to be of type "int[][]", but one of the elements is of type "boolean|string|array".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');
        $this->resolver->resolve([
            'foo' => [
                [1, true, 'str', [2, 3]],
            ],
        ]);
    }

    public function testNestedArrayException2(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value array is expected to be of type "int[][]", but one of the elements is of type "boolean|string|array".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');
        $this->resolver->resolve([
            'foo' => [
                [true, 'str', [2, 3]],
            ],
        ]);
    }

    public function testNestedArrayException3(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value array is expected to be of type "string[][][]", but one of the elements is of type "string|integer".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[][][]');
        $this->resolver->resolve([
            'foo' => [
                ['str', [1, 2]],
            ],
        ]);
    }

    public function testNestedArrayException4(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value array is expected to be of type "string[][][]", but one of the elements is of type "integer".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[][][]');
        $this->resolver->resolve([
            'foo' => [
                [
                    ['str'], [1, 2], ],
            ],
        ]);
    }

    public function testNestedArrayException5(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "foo" with value array is expected to be of type "string[]", but one of the elements is of type "array".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[]');
        $this->resolver->resolve([
            'foo' => [
                [
                    ['str'], [1, 2], ],
            ],
        ]);
    }
}
