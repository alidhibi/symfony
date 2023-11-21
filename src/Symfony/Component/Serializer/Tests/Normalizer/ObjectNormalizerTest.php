<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\CircularReferenceDummy;
use Symfony\Component\Serializer\Tests\Fixtures\GroupDummy;
use Symfony\Component\Serializer\Tests\Fixtures\MaxDepthDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Php74Dummy;
use Symfony\Component\Serializer\Tests\Fixtures\SiblingHolder;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ObjectNormalizerTest extends TestCase
{
    private \Symfony\Component\Serializer\Normalizer\ObjectNormalizer $normalizer;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    protected function setUp()
    {
        $this->serializer = $this->getMockBuilder(ObjectSerializerNormalizer::class)->getMock();
        $this->normalizer = new ObjectNormalizer();
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testNormalize(): void
    {
        $obj = new ObjectDummy();
        $object = new \stdClass();
        $obj->setFoo('foo');
        $obj->bar = 'bar';
        $obj->setBaz(true);
        $obj->setCamelCase('camelcase');
        $obj->setObject($object);

        $this->serializer
            ->expects($this->once())
            ->method('normalize')
            ->with($object, 'any')
            ->willReturn('string_object')
        ;

        $this->assertEquals(
            [
                'foo' => 'foo',
                'bar' => 'bar',
                'baz' => true,
                'fooBar' => 'foobar',
                'camelCase' => 'camelcase',
                'object' => 'string_object',
            ],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    /**
     * @requires PHP 7.4
     */
    public function testNormalizeObjectWithUninitializedProperties(): void
    {
        $obj = new Php74Dummy();
        $this->assertEquals(
            ['initializedProperty' => 'defaultValue'],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testDenormalize(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'],
            ObjectDummy::class,
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testDenormalizeWithObject(): void
    {
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->fooBar = 'foobar';
        $obj = $this->normalizer->denormalize($data, ObjectDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
    }

    public function testDenormalizeNull(): void
    {
        $this->assertEquals(new ObjectDummy(), $this->normalizer->denormalize(null, ObjectDummy::class));
    }

    public function testConstructorDenormalize(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'],
            ObjectConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithNullArgument(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => null, 'baz' => true],
            ObjectConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertNull($obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithMissingOptionalArgument(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'test', 'baz' => [1, 2, 3]],
            ObjectConstructorOptionalArgsDummy::class, 'any');
        $this->assertEquals('test', $obj->getFoo());
        $this->assertEquals([], $obj->bar);
        $this->assertEquals([1, 2, 3], $obj->getBaz());
    }

    public function testConstructorDenormalizeWithOptionalDefaultArgument(): void
    {
        $obj = $this->normalizer->denormalize(
            ['bar' => 'test'],
            ObjectConstructorArgsWithDefaultValueDummy::class, 'any');
        $this->assertEquals([], $obj->getFoo());
        $this->assertEquals('test', $obj->getBar());
    }

    public function testConstructorWithObjectDenormalize(): void
    {
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->baz = true;
        $data->fooBar = 'foobar';
        $obj = $this->normalizer->denormalize($data, ObjectConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
    }

    public function testConstructorWithObjectTypeHintDenormalize(): void
    {
        $data = [
            'id' => 10,
            'inner' => [
                'foo' => 'oof',
                'bar' => 'rab',
            ],
        ];

        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        $obj = $normalizer->denormalize($data, DummyWithConstructorObject::class);
        $this->assertInstanceOf(DummyWithConstructorObject::class, $obj);
        $this->assertEquals(10, $obj->getId());
        $this->assertInstanceOf(ObjectInner::class, $obj->getInner());
        $this->assertEquals('oof', $obj->getInner()->foo);
        $this->assertEquals('rab', $obj->getInner()->bar);
    }

    public function testConstructorWithUnknownObjectTypeHintDenormalize(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Could not determine the class of the parameter "unknown".');
        $data = [
            'id' => 10,
            'unknown' => [
                'foo' => 'oof',
                'bar' => 'rab',
            ],
        ];

        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        $normalizer->denormalize($data, DummyWithConstructorInexistingObject::class);
    }

    public function testGroupsNormalize(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFoo('foo');
        $obj->setBar('bar');
        $obj->setFooBar('fooBar');
        $obj->setSymfony('symfony');
        $obj->setKevin('kevin');
        $obj->setCoopTilleuls('coopTilleuls');

        $this->assertEquals([
            'bar' => 'bar',
        ], $this->normalizer->normalize($obj, null, [ObjectNormalizer::GROUPS => ['c']]));

        $this->assertEquals([
            'symfony' => 'symfony',
            'foo' => 'foo',
            'fooBar' => 'fooBar',
            'bar' => 'bar',
            'kevin' => 'kevin',
            'coopTilleuls' => 'coopTilleuls',
        ], $this->normalizer->normalize($obj, null, [ObjectNormalizer::GROUPS => ['a', 'c']]));
    }

    public function testGroupsDenormalize(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFoo('foo');

        $toNormalize = ['foo' => 'foo', 'bar' => 'bar'];

        $normalized = $this->normalizer->denormalize(
            $toNormalize,
            \Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class,
            null,
            [ObjectNormalizer::GROUPS => ['a']]
        );
        $this->assertEquals($obj, $normalized);

        $obj->setBar('bar');

        $normalized = $this->normalizer->denormalize(
            $toNormalize,
            \Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class,
            null,
            [ObjectNormalizer::GROUPS => ['a', 'b']]
        );
        $this->assertEquals($obj, $normalized);
    }

    public function testNormalizeNoPropertyInGroup(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFoo('foo');

        $this->assertEquals([], $this->normalizer->normalize($obj, null, ['groups' => ['notExist']]));
    }

    public function testGroupsNormalizeWithNameConverter(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFooBar('@dunglas');
        $obj->setSymfony('@coopTilleuls');
        $obj->setCoopTilleuls('les-tilleuls.coop');

        $this->assertEquals(
            [
                'bar' => null,
                'foo_bar' => '@dunglas',
                'symfony' => '@coopTilleuls',
            ],
            $this->normalizer->normalize($obj, null, [ObjectNormalizer::GROUPS => ['name_converter']])
        );
    }

    public function testGroupsDenormalizeWithNameConverter(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFooBar('@dunglas');
        $obj->setSymfony('@coopTilleuls');

        $this->assertEquals(
            $obj,
            $this->normalizer->denormalize([
                'bar' => null,
                'foo_bar' => '@dunglas',
                'symfony' => '@coopTilleuls',
                'coop_tilleuls' => 'les-tilleuls.coop',
            ], \Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class, null, [ObjectNormalizer::GROUPS => ['name_converter']])
        );
    }

    public function testObjectToPopulateNoMatch(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory, null, null, new PhpDocExtractor());
        new Serializer([$this->normalizer]);

        $objectToPopulate = new ObjectInner();
        $objectToPopulate->foo = 'foo';

        $outer = $this->normalizer->denormalize([
            'foo' => 'foo',
            'inner' => [
                'bar' => 'bar',
            ],
        ], ObjectOuter::class, null, [ObjectNormalizer::OBJECT_TO_POPULATE => $objectToPopulate]);

        $this->assertInstanceOf(ObjectOuter::class, $outer);
        $inner = $outer->getInner();
        $this->assertInstanceOf(ObjectInner::class, $inner);
        $this->assertNotSame($objectToPopulate, $inner);
        $this->assertSame('bar', $inner->bar);
        $this->assertNull($inner->foo);
    }

    /**
     * @dataProvider provideCallbacks
     */
    public function testCallbacks(array $callbacks, string|\DateTime|array $value, array $result, string $message): void
    {
        $this->normalizer->setCallbacks($callbacks);

        $obj = new ObjectConstructorDummy('', $value, true);

        $this->assertEquals(
            $result,
            $this->normalizer->normalize($obj, 'any'),
            $message
        );
    }

    public function testUncallableCallbacks(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->normalizer->setCallbacks(['bar' => null]);

        $obj = new ObjectConstructorDummy('baz', 'quux', true);

        $this->normalizer->normalize($obj, 'any');
    }

    public function testIgnoredAttributes(): void
    {
        $this->normalizer->setIgnoredAttributes(['foo', 'bar', 'baz', 'camelCase', 'object']);

        $obj = new ObjectDummy();
        $obj->setFoo('foo');

        $obj->bar = 'bar';
        $obj->setBaz(true);

        $this->assertEquals(
            ['fooBar' => 'foobar'],
            $this->normalizer->normalize($obj, 'any')
        );

        $this->normalizer->setIgnoredAttributes(['foo', 'baz', 'camelCase', 'object']);

        $this->assertEquals(
            [
                'fooBar' => 'foobar',
                'bar' => 'bar',
            ],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testIgnoredAttributesDenormalize(): void
    {
        $this->normalizer->setIgnoredAttributes(['fooBar', 'bar', 'baz']);

        $obj = new ObjectDummy();
        $obj->setFoo('foo');

        $this->assertEquals(
            $obj,
            $this->normalizer->denormalize(['fooBar' => 'fooBar', 'foo' => 'foo', 'baz' => 'baz'], ObjectDummy::class)
        );
    }

    public function provideCallbacks(): array
    {
        return [
            [
                [
                    'bar' => static fn($bar): string => 'baz',
                ],
                'baz',
                ['foo' => '', 'bar' => 'baz', 'baz' => true],
                'Change a string',
            ],
            [
                [
                    'bar' => static function ($bar) : void {
                    },
                ],
                'baz',
                ['foo' => '', 'bar' => null, 'baz' => true],
                'Null an item',
            ],
            [
                [
                    'bar' => static fn($bar) => $bar->format('d-m-Y H:i:s'),
                ],
                new \DateTime('2011-09-10 06:30:00'),
                ['foo' => '', 'bar' => '10-09-2011 06:30:00', 'baz' => true],
                'Format a date',
            ],
            [
                [
                    'bar' => static function ($bars) : string {
                        $foos = '';
                        foreach ($bars as $bar) {
                            $foos .= $bar->getFoo();
                        }
                        return $foos;
                    },
                ],
                [new ObjectConstructorDummy('baz', '', false), new ObjectConstructorDummy('quux', '', false)],
                ['foo' => '', 'bar' => 'bazquux', 'baz' => true],
                'Collect a property',
            ],
            [
                [
                    'bar' => static fn($bars): int => \count($bars),
                ],
                [new ObjectConstructorDummy('baz', '', false), new ObjectConstructorDummy('quux', '', false)],
                ['foo' => '', 'bar' => 2, 'baz' => true],
                'Count a property',
            ],
        ];
    }

    public function testUnableToNormalizeObjectAttribute(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\LogicException::class);
        $this->expectExceptionMessage('Cannot normalize attribute "object" because the injected serializer is not a normalizer');
        $serializer = $this->getMockBuilder(\Symfony\Component\Serializer\SerializerInterface::class)->getMock();
        $this->normalizer->setSerializer($serializer);

        $obj = new ObjectDummy();
        $object = new \stdClass();
        $obj->setObject($object);

        $this->normalizer->normalize($obj, 'any');
    }

    public function testUnableToNormalizeCircularReference(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\CircularReferenceException::class);
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);
        $this->normalizer->setCircularReferenceLimit(2);

        $obj = new CircularReferenceDummy();

        $this->normalizer->normalize($obj);
    }

    public function testSiblingReference(): void
    {
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);

        $siblingHolder = new SiblingHolder();

        $expected = [
            'sibling0' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
            'sibling1' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
            'sibling2' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
        ];
        $this->assertEquals($expected, $this->normalizer->normalize($siblingHolder));
    }

    public function testCircularReferenceHandler(): void
    {
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);
        $this->normalizer->setCircularReferenceHandler(static fn($obj): string => \get_class($obj));

        $obj = new CircularReferenceDummy();

        $expected = ['me' => \Symfony\Component\Serializer\Tests\Fixtures\CircularReferenceDummy::class];
        $this->assertEquals($expected, $this->normalizer->normalize($obj));
    }

    public function testDenormalizeNonExistingAttribute(): void
    {
        $this->assertEquals(
            new ObjectDummy(),
            $this->normalizer->denormalize(['non_existing' => true], ObjectDummy::class)
        );
    }

    public function testNoTraversableSupport(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \ArrayObject()));
    }

    public function testNormalizeStatic(): void
    {
        $this->assertEquals(['foo' => 'K'], $this->normalizer->normalize(new ObjectWithStaticPropertiesAndMethods()));
    }

    public function testNormalizeUpperCaseAttributes(): void
    {
        $this->assertEquals(['Foo' => 'Foo', 'Bar' => 'BarBar'], $this->normalizer->normalize(new ObjectWithUpperCaseAttributeNames()));
    }

    public function testNormalizeNotSerializableContext(): void
    {
        $objectDummy = new ObjectDummy();
        $expected = [
            'foo' => null,
            'baz' => null,
            'fooBar' => '',
            'camelCase' => null,
            'object' => null,
            'bar' => null,
        ];

        $this->assertEquals($expected, $this->normalizer->normalize($objectDummy, null, ['not_serializable' => static function () : void {
        }]));
    }

    public function testMaxDepth(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);

        $level1 = new MaxDepthDummy();
        $level1->foo = 'level1';

        $level2 = new MaxDepthDummy();
        $level2->foo = 'level2';
        $level1->child = $level2;

        $level3 = new MaxDepthDummy();
        $level3->foo = 'level3';
        $level2->child = $level3;

        $result = $serializer->normalize($level1, null, [ObjectNormalizer::ENABLE_MAX_DEPTH => true]);

        $expected = [
            'bar' => null,
            'foo' => 'level1',
            'child' => [
                    'bar' => null,
                    'foo' => 'level2',
                    'child' => [
                            'bar' => null,
                            'child' => null,
                        ],
                ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testThrowUnexpectedValueException(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $this->normalizer->denormalize(['foo' => 'bar'], ObjectTypeHinted::class);
    }

    public function testDenomalizeRecursive(): void
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer([new ArrayDenormalizer(), new DateTimeNormalizer(), $normalizer]);

        $obj = $serializer->denormalize([
            'inner' => ['foo' => 'foo', 'bar' => 'bar'],
            'date' => '1988/01/21',
            'inners' => [['foo' => 1], ['foo' => 2]],
        ], ObjectOuter::class);

        $this->assertSame('foo', $obj->getInner()->foo);
        $this->assertSame('bar', $obj->getInner()->bar);
        $this->assertSame('1988-01-21', $obj->getDate()->format('Y-m-d'));
        $this->assertSame(1, $obj->getInners()[0]->foo);
        $this->assertSame(2, $obj->getInners()[1]->foo);
    }

    public function testAcceptJsonNumber(): void
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer([new ArrayDenormalizer(), new DateTimeNormalizer(), $normalizer]);

        $this->assertSame(10.0, $serializer->denormalize(['number' => 10], JsonNumber::class, 'json')->number);
        $this->assertSame(10.0, $serializer->denormalize(['number' => 10], JsonNumber::class, 'jsonld')->number);
    }

    public function testRejectInvalidType(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $this->expectExceptionMessage('The type of the "date" attribute for class "Symfony\Component\Serializer\Tests\Normalizer\ObjectOuter" must be one of "DateTimeInterface" ("string" given).');
        $normalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());
        $serializer = new Serializer([$normalizer]);

        $serializer->denormalize(['date' => 'foo'], ObjectOuter::class);
    }

    public function testRejectInvalidKey(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $this->expectExceptionMessage('The type of the key "a" must be "int" ("string" given).');
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer([new ArrayDenormalizer(), new DateTimeNormalizer(), $normalizer]);

        $serializer->denormalize(['inners' => ['a' => ['foo' => 1]]], ObjectOuter::class);
    }

    public function testDoNotRejectInvalidTypeOnDisableTypeEnforcementContextOption(): void
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor()]);
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer([$normalizer]);
        $context = [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true];

        $this->assertSame('foo', $serializer->denormalize(['number' => 'foo'], JsonNumber::class, null, $context)->number);
    }

    public function testExtractAttributesRespectsFormat(): void
    {
        $normalizer = new FormatAndContextAwareNormalizer();

        $data = new ObjectDummy();
        $data->setFoo('bar');

        $data->bar = 'foo';

        $this->assertSame(['foo' => 'bar', 'bar' => 'foo'], $normalizer->normalize($data, 'foo_and_bar_included'));
    }

    public function testExtractAttributesRespectsContext(): void
    {
        $normalizer = new FormatAndContextAwareNormalizer();

        $data = new ObjectDummy();
        $data->setFoo('bar');

        $data->bar = 'foo';

        $this->assertSame(['foo' => 'bar', 'bar' => 'foo'], $normalizer->normalize($data, null, ['include_foo_and_bar' => true]));
    }

    public function testAttributesContextNormalize(): void
    {
        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);

        $objectInner = new ObjectInner();
        $objectInner->foo = 'innerFoo';
        $objectInner->bar = 'innerBar';

        $objectDummy = new ObjectDummy();
        $objectDummy->setFoo('foo');
        $objectDummy->setBaz(true);
        $objectDummy->setObject($objectInner);

        $context = ['attributes' => ['foo', 'baz', 'object' => ['foo']]];
        $this->assertEquals(
            [
                'foo' => 'foo',
                'baz' => true,
                'object' => ['foo' => 'innerFoo'],
            ],
            $serializer->normalize($objectDummy, null, $context)
        );

        $context = ['attributes' => ['foo', 'baz', 'object']];
        $this->assertEquals(
            [
                'foo' => 'foo',
                'baz' => true,
                'object' => ['foo' => 'innerFoo', 'bar' => 'innerBar'],
            ],
            $serializer->normalize($objectDummy, null, $context)
        );
    }

    public function testAttributesContextDenormalize(): void
    {
        $normalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());
        $serializer = new Serializer([$normalizer]);

        $objectInner = new ObjectInner();
        $objectInner->foo = 'innerFoo';

        $objectOuter = new ObjectOuter();
        $objectOuter->bar = 'bar';
        $objectOuter->setInner($objectInner);

        $context = ['attributes' => ['bar', 'inner' => ['foo']]];
        $this->assertEquals($objectOuter, $serializer->denormalize(
            [
                'foo' => 'foo',
                'bar' => 'bar',
                'date' => '2017-02-03',
                'inner' => ['foo' => 'innerFoo', 'bar' => 'innerBar'],
            ], ObjectOuter::class, null, $context));
    }

    public function testAttributesContextDenormalizeConstructor(): void
    {
        $normalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());
        $serializer = new Serializer([$normalizer]);

        $objectInner = new ObjectInner();
        $objectInner->bar = 'bar';

        $obj = new DummyWithConstructorObjectAndDefaultValue('a', $objectInner);

        $context = ['attributes' => ['inner' => ['bar']]];
        $this->assertEquals($obj, $serializer->denormalize([
            'foo' => 'b',
            'inner' => ['foo' => 'foo', 'bar' => 'bar'],
        ], DummyWithConstructorObjectAndDefaultValue::class, null, $context));
    }

    public function testNormalizeSameObjectWithDifferentAttributes(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);

        $dummy = new ObjectOuter();
        $dummy->foo = new ObjectInner();
        $dummy->foo->foo = 'foo.foo';

        $dummy->foo->bar = 'foo.bar';

        $dummy->bar = new ObjectInner();

        $dummy->bar->foo = 'bar.foo';
        $dummy->bar->bar = 'bar.bar';

        $this->assertEquals([
            'foo' => [
                'bar' => 'foo.bar',
            ],
            'bar' => [
                'foo' => 'bar.foo',
            ],
        ], $this->normalizer->normalize($dummy, 'json', [
            'attributes' => [
                'foo' => ['bar'],
                'bar' => ['foo'],
            ],
        ]));
    }
}

class ObjectDummy
{
    protected $foo;

    public $bar;

    private $baz;

    protected $camelCase;

    protected $object;

    public function getFoo()
    {
        return $this->foo;
    }

    public function setFoo($foo): void
    {
        $this->foo = $foo;
    }

    public function isBaz()
    {
        return $this->baz;
    }

    public function setBaz($baz): void
    {
        $this->baz = $baz;
    }

    public function getFooBar(): string
    {
        return $this->foo.$this->bar;
    }

    public function getCamelCase()
    {
        return $this->camelCase;
    }

    public function setCamelCase($camelCase): void
    {
        $this->camelCase = $camelCase;
    }

    public function otherMethod(): never
    {
        throw new \RuntimeException('Dummy::otherMethod() should not be called');
    }

    public function setObject($object): void
    {
        $this->object = $object;
    }

    public function getObject()
    {
        return $this->object;
    }
}

class ObjectConstructorDummy
{
    protected $foo;

    public $bar;

    private $baz;

    public function __construct($foo, $bar, $baz)
    {
        $this->foo = $foo;
        $this->bar = $bar;
        $this->baz = $baz;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function isBaz()
    {
        return $this->baz;
    }

    public function otherMethod(): never
    {
        throw new \RuntimeException('Dummy::otherMethod() should not be called');
    }
}

abstract class ObjectSerializerNormalizer implements SerializerInterface, NormalizerInterface
{
}

class ObjectConstructorOptionalArgsDummy
{
    protected $foo;

    public $bar;

    private $baz;

    public function __construct($foo, $bar = [], $baz = [])
    {
        $this->foo = $foo;
        $this->bar = $bar;
        $this->baz = $baz;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function getBaz()
    {
        return $this->baz;
    }

    public function otherMethod(): never
    {
        throw new \RuntimeException('Dummy::otherMethod() should not be called');
    }
}

class ObjectConstructorArgsWithDefaultValueDummy
{
    protected $foo;

    protected $bar;

    public function __construct($foo = [], $bar = null)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function getBar()
    {
        return $this->bar;
    }

    public function otherMethod(): never
    {
        throw new \RuntimeException('Dummy::otherMethod() should not be called');
    }
}

class ObjectWithStaticPropertiesAndMethods
{
    public $foo = 'K';

    public static $bar = 'A';

    public static function getBaz(): string
    {
        return 'L';
    }
}

class ObjectTypeHinted
{
    public function setFoo(array $f): void
    {
    }
}

class ObjectOuter
{
    public $foo;

    public $bar;

    private ?\Symfony\Component\Serializer\Tests\Normalizer\ObjectInner $inner = null;

    private ?\DateTimeInterface $date = null;

    /**
     * @var ObjectInner[]
     */
    private ?array $inners = null;

    public function getInner(): ?\Symfony\Component\Serializer\Tests\Normalizer\ObjectInner
    {
        return $this->inner;
    }

    public function setInner(ObjectInner $inner): void
    {
        $this->inner = $inner;
    }

    public function setDate(\DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setInners(array $inners): void
    {
        $this->inners = $inners;
    }

    public function getInners(): ?array
    {
        return $this->inners;
    }
}

class ObjectInner
{
    public $foo;

    public $bar;
}

class FormatAndContextAwareNormalizer extends ObjectNormalizer
{
    protected function isAllowedAttribute($classOrObject, $attribute, $format = null, array $context = []): bool
    {
        if (\in_array($attribute, ['foo', 'bar']) && 'foo_and_bar_included' === $format) {
            return true;
        }
        return \in_array($attribute, ['foo', 'bar']) && isset($context['include_foo_and_bar']) && true === $context['include_foo_and_bar'];
    }
}

class DummyWithConstructorObject
{
    private $id;

    private readonly \Symfony\Component\Serializer\Tests\Normalizer\ObjectInner $inner;

    public function __construct($id, ObjectInner $inner)
    {
        $this->id = $id;
        $this->inner = $inner;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getInner(): \Symfony\Component\Serializer\Tests\Normalizer\ObjectInner
    {
        return $this->inner;
    }
}

class DummyWithConstructorInexistingObject
{
}

class JsonNumber
{
    /**
     * @var float
     */
    public $number;
}

class DummyWithConstructorObjectAndDefaultValue
{
    private $foo;

    private ?\Symfony\Component\Serializer\Tests\Normalizer\ObjectInner $inner = null;

    public function __construct($foo = 'a', ObjectInner $inner = null)
    {
        $this->foo = $foo;
        $this->inner = $inner;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function getInner(): ?\Symfony\Component\Serializer\Tests\Normalizer\ObjectInner
    {
        return $this->inner;
    }
}

class ObjectWithUpperCaseAttributeNames
{
    private string $Foo = 'Foo';

    public $Bar = 'BarBar';

    public function getFoo(): string
    {
        return $this->Foo;
    }
}
