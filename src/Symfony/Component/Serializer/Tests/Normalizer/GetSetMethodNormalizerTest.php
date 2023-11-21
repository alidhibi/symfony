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
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\CircularReferenceDummy;
use Symfony\Component\Serializer\Tests\Fixtures\GroupDummy;
use Symfony\Component\Serializer\Tests\Fixtures\MaxDepthDummy;
use Symfony\Component\Serializer\Tests\Fixtures\SiblingHolder;

class GetSetMethodNormalizerTest extends TestCase
{
    private \Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer $normalizer;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    protected function setUp()
    {
        $this->serializer = $this->getMockBuilder(SerializerNormalizer::class)->getMock();
        $this->normalizer = new GetSetMethodNormalizer();
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testInterface(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class, $this->normalizer);
        $this->assertInstanceOf(\Symfony\Component\Serializer\Normalizer\DenormalizerInterface::class, $this->normalizer);
    }

    public function testNormalize(): void
    {
        $obj = new GetSetDummy();
        $object = new \stdClass();
        $obj->setFoo('foo');
        $obj->setBar('bar');
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

    public function testDenormalize(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'],
            GetSetDummy::class,
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
        $this->assertTrue($obj->isBaz());
    }

    public function testIgnoredAttributesInContext(): void
    {
        $ignoredAttributes = ['foo', 'bar', 'baz', 'object'];
        $this->normalizer->setIgnoredAttributes($ignoredAttributes);
        $obj = new GetSetDummy();
        $obj->setFoo('foo');
        $obj->setBar('bar');
        $obj->setCamelCase(true);
        $this->assertEquals(
            [
                'fooBar' => 'foobar',
                'camelCase' => true,
            ],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testDenormalizeWithObject(): void
    {
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->fooBar = 'foobar';
        $obj = $this->normalizer->denormalize($data, GetSetDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testDenormalizeNull(): void
    {
        $this->assertEquals(new GetSetDummy(), $this->normalizer->denormalize(null, GetSetDummy::class));
    }

    public function testConstructorDenormalize(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'],
            GetConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithNullArgument(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => null, 'baz' => true],
            GetConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertNull($obj->getBar());
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithMissingOptionalArgument(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'test', 'baz' => [1, 2, 3]],
            GetConstructorOptionalArgsDummy::class, 'any');
        $this->assertEquals('test', $obj->getFoo());
        $this->assertEquals([], $obj->getBar());
        $this->assertEquals([1, 2, 3], $obj->getBaz());
    }

    public function testConstructorDenormalizeWithOptionalDefaultArgument(): void
    {
        $obj = $this->normalizer->denormalize(
            ['bar' => 'test'],
            GetConstructorArgsWithDefaultValueDummy::class, 'any');
        $this->assertEquals([], $obj->getFoo());
        $this->assertEquals('test', $obj->getBar());
    }

    /**
     * @requires PHP 5.6
     */
    public function testConstructorDenormalizeWithVariadicArgument(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => [1, 2, 3]],
            \Symfony\Component\Serializer\Tests\Fixtures\VariadicConstructorArgsDummy::class, 'any');
        $this->assertEquals([1, 2, 3], $obj->getFoo());
    }

    /**
     * @requires PHP 5.6
     */
    public function testConstructorDenormalizeWithMissingVariadicArgument(): void
    {
        $obj = $this->normalizer->denormalize(
            [],
            \Symfony\Component\Serializer\Tests\Fixtures\VariadicConstructorArgsDummy::class, 'any');
        $this->assertEquals([], $obj->getFoo());
    }

    public function testConstructorWithObjectDenormalize(): void
    {
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->baz = true;
        $data->fooBar = 'foobar';
        $obj = $this->normalizer->denormalize($data, GetConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testConstructorWArgWithPrivateMutator(): void
    {
        $obj = $this->normalizer->denormalize(['foo' => 'bar'], ObjectConstructorArgsWithPrivateMutatorDummy::class, 'any');
        $this->assertEquals('bar', $obj->getFoo());
    }

    public function testGroupsNormalize(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new GetSetMethodNormalizer($classMetadataFactory);
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
        ], $this->normalizer->normalize($obj, null, [GetSetMethodNormalizer::GROUPS => ['c']]));

        $this->assertEquals([
            'symfony' => 'symfony',
            'foo' => 'foo',
            'fooBar' => 'fooBar',
            'bar' => 'bar',
            'kevin' => 'kevin',
            'coopTilleuls' => 'coopTilleuls',
        ], $this->normalizer->normalize($obj, null, [GetSetMethodNormalizer::GROUPS => ['a', 'c']]));
    }

    public function testGroupsDenormalize(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new GetSetMethodNormalizer($classMetadataFactory);
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFoo('foo');

        $toNormalize = ['foo' => 'foo', 'bar' => 'bar'];

        $normalized = $this->normalizer->denormalize(
            $toNormalize,
            \Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class,
            null,
            [GetSetMethodNormalizer::GROUPS => ['a']]
        );
        $this->assertEquals($obj, $normalized);

        $obj->setBar('bar');

        $normalized = $this->normalizer->denormalize(
            $toNormalize,
            \Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class,
            null,
            [GetSetMethodNormalizer::GROUPS => ['a', 'b']]
        );
        $this->assertEquals($obj, $normalized);
    }

    public function testGroupsNormalizeWithNameConverter(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new GetSetMethodNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
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
            $this->normalizer->normalize($obj, null, [GetSetMethodNormalizer::GROUPS => ['name_converter']])
        );
    }

    public function testGroupsDenormalizeWithNameConverter(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new GetSetMethodNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
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
            ], \Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class, null, [GetSetMethodNormalizer::GROUPS => ['name_converter']])
        );
    }

    /**
     * @dataProvider provideCallbacks
     */
    public function testCallbacks(array $callbacks, string|\DateTime|array $value, array $result, string $message): void
    {
        $this->normalizer->setCallbacks($callbacks);

        $obj = new GetConstructorDummy('', $value, true);

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

        $obj = new GetConstructorDummy('baz', 'quux', true);

        $this->normalizer->normalize($obj, 'any');
    }

    public function testIgnoredAttributes(): void
    {
        $this->normalizer->setIgnoredAttributes(['foo', 'bar', 'baz', 'camelCase', 'object']);

        $obj = new GetSetDummy();
        $obj->setFoo('foo');
        $obj->setBar('bar');
        $obj->setBaz(true);

        $this->assertEquals(
            ['fooBar' => 'foobar'],
            $this->normalizer->normalize($obj, 'any')
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
                [new GetConstructorDummy('baz', '', false), new GetConstructorDummy('quux', '', false)],
                ['foo' => '', 'bar' => 'bazquux', 'baz' => true],
                'Collect a property',
            ],
            [
                [
                    'bar' => static fn($bars): int => \count($bars),
                ],
                [new GetConstructorDummy('baz', '', false), new GetConstructorDummy('quux', '', false)],
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

        $obj = new GetSetDummy();
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

    public function testObjectToPopulate(): void
    {
        $dummy = new GetSetDummy();
        $dummy->setFoo('foo');

        $obj = $this->normalizer->denormalize(
            ['bar' => 'bar'],
            GetSetDummy::class,
            null,
            [GetSetMethodNormalizer::OBJECT_TO_POPULATE => $dummy]
        );

        $this->assertEquals($dummy, $obj);
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testDenormalizeNonExistingAttribute(): void
    {
        $this->assertEquals(
            new GetSetDummy(),
            $this->normalizer->denormalize(['non_existing' => true], GetSetDummy::class)
        );
    }

    public function testDenormalizeShouldNotSetStaticAttribute(): void
    {
        $obj = $this->normalizer->denormalize(['staticObject' => true], GetSetDummy::class);

        $this->assertEquals(new GetSetDummy(), $obj);
        $this->assertNull(GetSetDummy::getStaticObject());
    }

    public function testNoTraversableSupport(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \ArrayObject()));
    }

    public function testNoStaticGetSetSupport(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new ObjectWithJustStaticSetterDummy()));
    }

    public function testPrivateSetter(): void
    {
        $obj = $this->normalizer->denormalize(['foo' => 'foobar'], ObjectWithPrivateSetterDummy::class);
        $this->assertEquals('bar', $obj->getFoo());
    }

    public function testHasGetterDenormalize(): void
    {
        $obj = $this->normalizer->denormalize(['foo' => true], ObjectWithHasGetterDummy::class);
        $this->assertTrue($obj->hasFoo());
    }

    public function testHasGetterNormalize(): void
    {
        $obj = new ObjectWithHasGetterDummy();
        $obj->setFoo(true);

        $this->assertEquals(
            ['foo' => true],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testMaxDepth(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new GetSetMethodNormalizer($classMetadataFactory);
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);

        $level1 = new MaxDepthDummy();
        $level1->bar = 'level1';

        $level2 = new MaxDepthDummy();
        $level2->bar = 'level2';
        $level1->child = $level2;

        $level3 = new MaxDepthDummy();
        $level3->bar = 'level3';
        $level2->child = $level3;

        $level4 = new MaxDepthDummy();
        $level4->bar = 'level4';
        $level3->child = $level4;

        $result = $serializer->normalize($level1, null, [GetSetMethodNormalizer::ENABLE_MAX_DEPTH => true]);

        $expected = [
            'bar' => 'level1',
            'child' => [
                    'bar' => 'level2',
                    'child' => [
                            'bar' => 'level3',
                            'child' => [
                                    'child' => null,
                                ],
                        ],
                ],
            ];

        $this->assertEquals($expected, $result);
    }
}

class GetSetDummy
{
    protected $foo;

    private $bar;

    private $baz;

    protected $camelCase;

    protected $object;

    private static $staticObject;

    public function getFoo()
    {
        return $this->foo;
    }

    public function setFoo($foo): void
    {
        $this->foo = $foo;
    }

    public function getBar()
    {
        return $this->bar;
    }

    public function setBar($bar): void
    {
        $this->bar = $bar;
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

    public static function getStaticObject()
    {
        return self::$staticObject;
    }

    public static function setStaticObject($object): void
    {
        self::$staticObject = $object;
    }

    protected function getPrivate(): never
    {
        throw new \RuntimeException('Dummy::getPrivate() should not be called');
    }
}

class GetConstructorDummy
{
    protected $foo;

    private $bar;

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

    public function getBar()
    {
        return $this->bar;
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

abstract class SerializerNormalizer implements SerializerInterface, NormalizerInterface
{
}

class GetConstructorOptionalArgsDummy
{
    protected $foo;

    private $bar;

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

    public function getBar()
    {
        return $this->bar;
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

class GetConstructorArgsWithDefaultValueDummy
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

class ObjectConstructorArgsWithPrivateMutatorDummy
{
    private $foo;

    public function __construct($foo)
    {
        $this->setFoo($foo);
    }

    public function getFoo()
    {
        return $this->foo;
    }

    private function setFoo($foo): void
    {
        $this->foo = $foo;
    }
}

class ObjectWithPrivateSetterDummy
{
    private string $foo = 'bar';

    public function getFoo(): string
    {
        return $this->foo;
    }
}

class ObjectWithJustStaticSetterDummy
{
    private static string $foo = 'bar';

    public static function getFoo(): string
    {
        return self::$foo;
    }

    public static function setFoo(string $foo): void
    {
        self::$foo = $foo;
    }
}

class ObjectWithHasGetterDummy
{
    private $foo;

    public function setFoo($foo): void
    {
        $this->foo = $foo;
    }

    public function hasFoo()
    {
        return $this->foo;
    }
}
