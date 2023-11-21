<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\Tests\Fixtures\ReturnTyped;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassIsWritable;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassMagicCall;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassMagicGet;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassSetValue;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassTypeErrorInsideCall;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestSingularAndPluralProps;
use Symfony\Component\PropertyAccess\Tests\Fixtures\Ticket5775Object;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TypeHinted;
use Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedPrivateProperty;
use Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedProperty;

class PropertyAccessorTest extends TestCase
{
    private \Symfony\Component\PropertyAccess\PropertyAccessor $propertyAccessor;

    protected function setUp()
    {
        $this->propertyAccessor = new PropertyAccessor();
    }

    public function getPathsWithUnexpectedType(): array
    {
        return [
            ['', 'foobar'],
            ['foo', 'foobar'],
            [null, 'foobar'],
            [123, 'foobar'],
            [(object) ['prop' => null], 'prop.foobar'],
            [(object) ['prop' => (object) ['subProp' => null]], 'prop.subProp.foobar'],
            [['index' => null], '[index][foobar]'],
            [['index' => ['subIndex' => null]], '[index][subIndex][foobar]'],
        ];
    }

    public function getPathsWithMissingProperty(): array
    {
        return [
            [(object) ['firstName' => 'Bernhard'], 'lastName'],
            [(object) ['property' => (object) ['firstName' => 'Bernhard']], 'property.lastName'],
            [['index' => (object) ['firstName' => 'Bernhard']], '[index].lastName'],
            [new TestClass('Bernhard'), 'protectedProperty'],
            [new TestClass('Bernhard'), 'privateProperty'],
            [new TestClass('Bernhard'), 'protectedAccessor'],
            [new TestClass('Bernhard'), 'protectedIsAccessor'],
            [new TestClass('Bernhard'), 'protectedHasAccessor'],
            [new TestClass('Bernhard'), 'privateAccessor'],
            [new TestClass('Bernhard'), 'privateIsAccessor'],
            [new TestClass('Bernhard'), 'privateHasAccessor'],

            // Properties are not camelized
            [new TestClass('Bernhard'), 'public_property'],
        ];
    }

    public function getPathsWithMissingIndex(): array
    {
        return [
            [['firstName' => 'Bernhard'], '[lastName]'],
            [[], '[index][lastName]'],
            [['index' => []], '[index][lastName]'],
            [['index' => ['firstName' => 'Bernhard']], '[index][lastName]'],
            [(object) ['property' => ['firstName' => 'Bernhard']], 'property[lastName]'],
        ];
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testGetValue(\stdClass|\Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass|array $objectOrArray, string $path, ?string $value): void
    {
        $this->assertSame($value, $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testGetValueThrowsExceptionIfPropertyNotFound(\stdClass|\Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass|array $objectOrArray, string $path): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException::class);
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testGetValueThrowsNoExceptionIfIndexNotFound(\stdClass|array $objectOrArray, string $path): void
    {
        $this->assertNull($this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testGetValueThrowsExceptionIfIndexNotFoundAndIndexExceptionsEnabled(\stdClass|array $objectOrArray, string $path): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\NoSuchIndexException::class);
        $this->propertyAccessor = new PropertyAccessor(false, true);
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @requires PHP 7.4
     */
    public function testGetValueThrowsExceptionIfUninitializedProperty(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\AccessException::class);
        $this->expectExceptionMessage('The property "' . \Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedProperty::class . '::$uninitialized" is not readable because it is typed "string". You should initialize it or declare a default value instead.');

        $this->propertyAccessor->getValue(new UninitializedProperty(), 'uninitialized');
    }

    /**
     * @requires PHP 7
     */
    public function testGetValueThrowsExceptionIfUninitializedPropertyWithGetter(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\AccessException::class);
        $this->expectExceptionMessage('The method "' . \Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedPrivateProperty::class . '::getUninitialized()" returned "null", but expected type "array". Did you forget to initialize a property or to make the return type nullable using "?array"?');

        $this->propertyAccessor->getValue(new UninitializedPrivateProperty(), 'uninitialized');
    }

    /**
     * @requires PHP 7
     */
    public function testGetValueThrowsExceptionIfUninitializedPropertyWithGetterOfAnonymousClass(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\AccessException::class);
        $this->expectExceptionMessage('The method "class@anonymous::getUninitialized()" returned "null", but expected type "array". Did you forget to initialize a property or to make the return type nullable using "?array"?');

        $object = eval('return new class() {
            private $uninitialized;

            public function getUninitialized(): array
            {
                return $this->uninitialized;
            }
        };');

        $this->propertyAccessor->getValue($object, 'uninitialized');
    }

    /**
     * @requires PHP 7
     */
    public function testGetValueThrowsExceptionIfUninitializedPropertyWithGetterOfAnonymousStdClass(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\AccessException::class);
        $this->expectExceptionMessage('The method "stdClass@anonymous::getUninitialized()" returned "null", but expected type "array". Did you forget to initialize a property or to make the return type nullable using "?array"?');

        $object = eval('return new class() extends \stdClass {
            private $uninitialized;

            public function getUninitialized(): array
            {
                return $this->uninitialized;
            }
        };');

        $this->propertyAccessor->getValue($object, 'uninitialized');
    }

    /**
     * @requires PHP 7
     */
    public function testGetValueThrowsExceptionIfUninitializedPropertyWithGetterOfAnonymousChildClass(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\AccessException::class);
        $this->expectExceptionMessage('The method "Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedPrivateProperty@anonymous::getUninitialized()" returned "null", but expected type "array". Did you forget to initialize a property or to make the return type nullable using "?array"?');

        $object = eval('return new class() extends \Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedPrivateProperty {};');

        $this->propertyAccessor->getValue($object, 'uninitialized');
    }

    public function testGetValueThrowsExceptionIfNotArrayAccess(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\NoSuchIndexException::class);
        $this->propertyAccessor->getValue(new \stdClass(), '[index]');
    }

    public function testGetValueReadsMagicGet(): void
    {
        $this->assertSame('Bernhard', $this->propertyAccessor->getValue(new TestClassMagicGet('Bernhard'), 'magicProperty'));
    }

    public function testGetValueReadsArrayWithMissingIndexForCustomPropertyPath(): void
    {
        $object = new \ArrayObject();
        $array = ['child' => ['index' => $object]];

        $this->assertNull($this->propertyAccessor->getValue($array, '[child][index][foo][bar]'));
        $this->assertSame([], $object->getArrayCopy());
    }

    // https://github.com/symfony/symfony/pull/4450
    public function testGetValueReadsMagicGetThatReturnsConstant(): void
    {
        $this->assertSame('constant value', $this->propertyAccessor->getValue(new TestClassMagicGet('Bernhard'), 'constantMagicProperty'));
    }

    public function testGetValueNotModifyObject(): void
    {
        $object = new \stdClass();
        $object->firstName = ['Bernhard'];

        $this->assertNull($this->propertyAccessor->getValue($object, 'firstName[1]'));
        $this->assertSame(['Bernhard'], $object->firstName);
    }

    public function testGetValueNotModifyObjectException(): void
    {
        $propertyAccessor = new PropertyAccessor(false, true);
        $object = new \stdClass();
        $object->firstName = ['Bernhard'];

        try {
            $propertyAccessor->getValue($object, 'firstName[1]');
        } catch (NoSuchIndexException $noSuchIndexException) {
        }

        $this->assertSame(['Bernhard'], $object->firstName);
    }

    public function testGetValueDoesNotReadMagicCallByDefault(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException::class);
        $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'magicCallProperty');
    }

    public function testGetValueReadsMagicCallIfEnabled(): void
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $this->assertSame('Bernhard', $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    // https://github.com/symfony/symfony/pull/4450
    public function testGetValueReadsMagicCallThatReturnsConstant(): void
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $this->assertSame('constant value', $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'constantMagicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testGetValueThrowsExceptionIfNotObjectOrArray(string|int|\stdClass|array|null $objectOrArray, string $path): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException::class);
        $this->expectExceptionMessage('PropertyAccessor requires a graph of objects or arrays to operate on');
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testSetValue(\stdClass|\Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testSetValueThrowsExceptionIfPropertyNotFound(\stdClass|\Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass|array $objectOrArray, string $path): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException::class);
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testSetValueThrowsNoExceptionIfIndexNotFound(\stdClass|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testSetValueThrowsNoExceptionIfIndexNotFoundAndIndexExceptionsEnabled(\stdClass|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    public function testSetValueThrowsExceptionIfNotArrayAccess(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\NoSuchIndexException::class);
        $object = new \stdClass();

        $this->propertyAccessor->setValue($object, '[index]', 'Updated');
    }

    public function testSetValueUpdatesMagicSet(): void
    {
        $author = new TestClassMagicGet('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicProperty', 'Updated');

        $this->assertEquals('Updated', $author->__get('magicProperty'));
    }

    public function testSetValueThrowsExceptionIfThereAreMissingParameters(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException::class);
        $object = new TestClass('Bernhard');

        $this->propertyAccessor->setValue($object, 'publicAccessorWithMoreRequiredParameters', 'Updated');
    }

    public function testSetValueDoesNotUpdateMagicCallByDefault(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException::class);
        $author = new TestClassMagicCall('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');
    }

    public function testSetValueUpdatesMagicCallIfEnabled(): void
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $author = new TestClassMagicCall('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');

        $this->assertEquals('Updated', $author->__call('getMagicCallProperty', []));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testSetValueThrowsExceptionIfNotObjectOrArray(string|int|\stdClass|array|null $objectOrArray, string $path): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException::class);
        $this->expectExceptionMessage('PropertyAccessor requires a graph of objects or arrays to operate on');
        $this->propertyAccessor->setValue($objectOrArray, $path, 'value');
    }

    public function testGetValueWhenArrayValueIsNull(): void
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);
        $this->assertNull($this->propertyAccessor->getValue(['index' => ['nullable' => null]], '[index][nullable]'));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsReadable(\stdClass|\Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass|array $objectOrArray, string $path): void
    {
        $this->assertTrue($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testIsReadableReturnsFalseIfPropertyNotFound(\stdClass|\Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass|array $objectOrArray, string $path): void
    {
        $this->assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsReadableReturnsTrueIfIndexNotFound(\stdClass|array $objectOrArray, string $path): void
    {
        // Non-existing indices can be read. In this case, null is returned
        $this->assertTrue($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsReadableReturnsFalseIfIndexNotFoundAndIndexExceptionsEnabled(\stdClass|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);

        // When exceptions are enabled, non-existing indices cannot be read
        $this->assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    public function testIsReadableRecognizesMagicGet(): void
    {
        $this->assertTrue($this->propertyAccessor->isReadable(new TestClassMagicGet('Bernhard'), 'magicProperty'));
    }

    public function testIsReadableDoesNotRecognizeMagicCallByDefault(): void
    {
        $this->assertFalse($this->propertyAccessor->isReadable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    public function testIsReadableRecognizesMagicCallIfEnabled(): void
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $this->assertTrue($this->propertyAccessor->isReadable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testIsReadableReturnsFalseIfNotObjectOrArray(string|int|\stdClass|array|null $objectOrArray, string $path): void
    {
        $this->assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsWritable(\stdClass|\Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass|array $objectOrArray, string $path): void
    {
        $this->assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testIsWritableReturnsFalseIfPropertyNotFound(\stdClass|\Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass|array $objectOrArray, string $path): void
    {
        $this->assertFalse($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsWritableReturnsTrueIfIndexNotFound(\stdClass|array $objectOrArray, string $path): void
    {
        // Non-existing indices can be written. Arrays are created on-demand.
        $this->assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsWritableReturnsTrueIfIndexNotFoundAndIndexExceptionsEnabled(\stdClass|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);

        // Non-existing indices can be written even if exceptions are enabled
        $this->assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    public function testIsWritableRecognizesMagicSet(): void
    {
        $this->assertTrue($this->propertyAccessor->isWritable(new TestClassMagicGet('Bernhard'), 'magicProperty'));
    }

    public function testIsWritableDoesNotRecognizeMagicCallByDefault(): void
    {
        $this->assertFalse($this->propertyAccessor->isWritable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    public function testIsWritableRecognizesMagicCallIfEnabled(): void
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $this->assertTrue($this->propertyAccessor->isWritable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testIsWritableReturnsFalseIfNotObjectOrArray(string|int|\stdClass|array|null $objectOrArray, string $path): void
    {
        $this->assertFalse($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    public function getValidPropertyPaths(): array
    {
        return [
            [['Bernhard', 'Schussek'], '[0]', 'Bernhard'],
            [['Bernhard', 'Schussek'], '[1]', 'Schussek'],
            [['firstName' => 'Bernhard'], '[firstName]', 'Bernhard'],
            [['index' => ['firstName' => 'Bernhard']], '[index][firstName]', 'Bernhard'],
            [(object) ['firstName' => 'Bernhard'], 'firstName', 'Bernhard'],
            [(object) ['property' => ['firstName' => 'Bernhard']], 'property[firstName]', 'Bernhard'],
            [['index' => (object) ['firstName' => 'Bernhard']], '[index].firstName', 'Bernhard'],
            [(object) ['property' => (object) ['firstName' => 'Bernhard']], 'property.firstName', 'Bernhard'],

            // Accessor methods
            [new TestClass('Bernhard'), 'publicProperty', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicAccessor', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicAccessorWithDefaultValue', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicAccessorWithRequiredAndDefaultValue', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicIsAccessor', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicHasAccessor', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicGetSetter', 'Bernhard'],

            // Methods are camelized
            [new TestClass('Bernhard'), 'public_accessor', 'Bernhard'],
            [new TestClass('Bernhard'), '_public_accessor', 'Bernhard'],

            // Missing indices
            [['index' => []], '[index][firstName]', null],
            [['root' => ['index' => []]], '[root][index][firstName]', null],

            // Special chars
            [['%!@$§.' => 'Bernhard'], '[%!@$§.]', 'Bernhard'],
            [['index' => ['%!@$§.' => 'Bernhard']], '[index][%!@$§.]', 'Bernhard'],
            [(object) ['%!@$§' => 'Bernhard'], '%!@$§', 'Bernhard'],
            [(object) ['property' => (object) ['%!@$§' => 'Bernhard']], 'property.%!@$§', 'Bernhard'],

            // nested objects and arrays
            [['foo' => new TestClass('bar')], '[foo].publicGetSetter', 'bar'],
            [new TestClass(['foo' => 'bar']), 'publicGetSetter[foo]', 'bar'],
            [new TestClass(new TestClass('bar')), 'publicGetter.publicGetSetter', 'bar'],
            [new TestClass(['foo' => new TestClass('bar')]), 'publicGetter[foo].publicGetSetter', 'bar'],
            [new TestClass(new TestClass(new TestClass('bar'))), 'publicGetter.publicGetter.publicGetSetter', 'bar'],
            [new TestClass(['foo' => ['baz' => new TestClass('bar')]]), 'publicGetter[foo][baz].publicGetSetter', 'bar'],
        ];
    }

    public function testTicket5755(): void
    {
        $object = new Ticket5775Object();

        $this->propertyAccessor->setValue($object, 'property', 'foobar');

        $this->assertEquals('foobar', $object->getProperty());
    }

    public function testSetValueDeepWithMagicGetter(): void
    {
        $obj = new TestClassMagicGet('foo');
        $obj->publicProperty = ['foo' => ['bar' => 'some_value']];
        $this->propertyAccessor->setValue($obj, 'publicProperty[foo][bar]', 'Updated');
        $this->assertSame('Updated', $obj->publicProperty['foo']['bar']);
    }

    public function getReferenceChainObjectsForSetValue(): array
    {
        return [
            [['a' => ['b' => ['c' => 'old-value']]], '[a][b][c]', 'new-value'],
            [new TestClassSetValue(new TestClassSetValue('old-value')), 'value.value', 'new-value'],
            [new TestClassSetValue(['a' => ['b' => ['c' => new TestClassSetValue('old-value')]]]), 'value[a][b][c].value', 'new-value'],
            [new TestClassSetValue(['a' => ['b' => 'old-value']]), 'value[a][b]', 'new-value'],
            [new \ArrayIterator(['a' => ['b' => ['c' => 'old-value']]]), '[a][b][c]', 'new-value'],
        ];
    }

    /**
     * @dataProvider getReferenceChainObjectsForSetValue
     */
    public function testSetValueForReferenceChainIssue(\Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassSetValue|\ArrayIterator|array $object, string $path, string $value): void
    {
        $this->propertyAccessor->setValue($object, $path, $value);

        $this->assertEquals($value, $this->propertyAccessor->getValue($object, $path));
    }

    public function getReferenceChainObjectsForIsWritable(): array
    {
        return [
            [new TestClassIsWritable(['a' => ['b' => 'old-value']]), 'value[a][b]', false],
            [new TestClassIsWritable(new \ArrayIterator(['a' => ['b' => 'old-value']])), 'value[a][b]', true],
            [new TestClassIsWritable(['a' => ['b' => ['c' => new TestClassSetValue('old-value')]]]), 'value[a][b][c].value', true],
        ];
    }

    /**
     * @dataProvider getReferenceChainObjectsForIsWritable
     */
    public function testIsWritableForReferenceChainIssue(\Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassIsWritable $object, string $path, bool $value): void
    {
        $this->assertEquals($value, $this->propertyAccessor->isWritable($object, $path));
    }

    public function testThrowTypeError(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected argument of type "DateTime", "string" given');
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', 'This is a string, \DateTime expected.');
    }

    public function testThrowTypeErrorWithNullArgument(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected argument of type "DateTime", "null" given');
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', null);
    }

    public function testSetTypeHint(): void
    {
        $date = new \DateTime();
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', $date);
        $this->assertSame($date, $object->getDate());
    }

    public function testArrayNotBeeingOverwritten(): void
    {
        $value = ['value1' => 'foo', 'value2' => 'bar'];
        $object = new TestClass($value);

        $this->propertyAccessor->setValue($object, 'publicAccessor[value2]', 'baz');
        $this->assertSame('baz', $this->propertyAccessor->getValue($object, 'publicAccessor[value2]'));
        $this->assertSame(['value1' => 'foo', 'value2' => 'baz'], $object->getPublicAccessor());
    }

    public function testCacheReadAccess(): void
    {
        $obj = new TestClass('foo');

        $propertyAccessor = new PropertyAccessor(false, false, new ArrayAdapter());
        $this->assertEquals('foo', $propertyAccessor->getValue($obj, 'publicGetSetter'));
        $propertyAccessor->setValue($obj, 'publicGetSetter', 'bar');
        $propertyAccessor->setValue($obj, 'publicGetSetter', 'baz');
        $this->assertEquals('baz', $propertyAccessor->getValue($obj, 'publicGetSetter'));
    }

    public function testAttributeWithSpecialChars(): void
    {
        $obj = new \stdClass();
        $obj->{'@foo'} = 'bar';
        $obj->{'a/b'} = '1';
        $obj->{'a%2Fb'} = '2';

        $propertyAccessor = new PropertyAccessor(false, false, new ArrayAdapter());
        $this->assertSame('bar', $propertyAccessor->getValue($obj, '@foo'));
        $this->assertSame('1', $propertyAccessor->getValue($obj, 'a/b'));
        $this->assertSame('2', $propertyAccessor->getValue($obj, 'a%2Fb'));
    }

    public function testThrowTypeErrorWithInterface(): void
    {
        $this->expectException(\Symfony\Component\PropertyAccess\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected argument of type "Countable", "string" given');
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'countable', 'This is a string, \Countable expected.');
    }

    /**
     * @requires PHP 7.0
     */
    public function testAnonymousClassRead(): void
    {
        $value = 'bar';

        $obj = $this->generateAnonymousClass();

        $propertyAccessor = new PropertyAccessor(false, false, new ArrayAdapter());

        $this->assertEquals($value, $propertyAccessor->getValue($obj, 'foo'));
    }

    /**
     * @requires PHP 7.0
     */
    public function testAnonymousClassWrite(): void
    {
        $value = 'bar';

        $obj = $this->generateAnonymousClass();

        $propertyAccessor = new PropertyAccessor(false, false, new ArrayAdapter());
        $propertyAccessor->setValue($obj, 'foo', $value);

        $this->assertEquals($value, $propertyAccessor->getValue($obj, 'foo'));
    }

    private function generateAnonymousClass()
    {
        return eval('return new class($value)
        {
            private $foo;

            public function __construct($foo)
            {
                $this->foo = $foo;
            }

            /**
             * @return mixed
             */
            public function getFoo()
            {
                return $this->foo;
            }

            /**
             * @param mixed $foo
             */
            public function setFoo($foo)
            {
                $this->foo = $foo;
            }
        };');
    }

    /**
     * @requires PHP 7.0
     */
    public function testThrowTypeErrorInsideSetterCall(): void
    {
        $this->expectException('TypeError');
        $object = new TestClassTypeErrorInsideCall();

        $this->propertyAccessor->setValue($object, 'property', 'foo');
    }

    /**
     * @requires PHP 7
     */
    public function testDoNotDiscardReturnTypeError(): void
    {
        $this->expectException('TypeError');
        $object = new ReturnTyped();

        $this->propertyAccessor->setValue($object, 'foos', [new \DateTime()]);
    }

    /**
     * @requires PHP 7
     */
    public function testDoNotDiscardReturnTypeErrorWhenWriterMethodIsMisconfigured(): void
    {
        $this->expectException('TypeError');
        $object = new ReturnTyped();

        $this->propertyAccessor->setValue($object, 'name', 'foo');
    }

    public function testWriteToSingularPropertyWhilePluralOneExists(): void
    {
        $object = new TestSingularAndPluralProps();

        $this->propertyAccessor->isWritable($object, 'email'); //cache access info
        $this->propertyAccessor->setValue($object, 'email', 'test@email.com');

        self::assertEquals('test@email.com', $object->getEmail());
        self::assertEmpty($object->getEmails());
    }

    public function testWriteToPluralPropertyWhileSingularOneExists(): void
    {
        $object = new TestSingularAndPluralProps();

        $this->propertyAccessor->isWritable($object, 'emails'); //cache access info
        $this->propertyAccessor->setValue($object, 'emails', ['test@email.com']);

        $this->assertEquals(['test@email.com'], $object->getEmails());
        $this->assertNull($object->getEmail());
    }

    public function testAdderAndRemoverArePreferredOverSetter(): void
    {
        $object = new TestPluralAdderRemoverAndSetter();

        $this->propertyAccessor->isWritable($object, 'emails'); //cache access info
        $this->propertyAccessor->setValue($object, 'emails', ['test@email.com']);

        $this->assertEquals(['test@email.com'], $object->getEmails());
    }

    public function testAdderAndRemoverArePreferredOverSetterForSameSingularAndPlural(): void
    {
        $object = new TestPluralAdderRemoverAndSetterSameSingularAndPlural();

        $this->propertyAccessor->isWritable($object, 'aircraft'); //cache access info
        $this->propertyAccessor->setValue($object, 'aircraft', ['aeroplane']);

        $this->assertEquals(['aeroplane'], $object->getAircraft());
    }
}
