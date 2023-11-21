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
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\GroupDummy;
use Symfony\Component\Serializer\Tests\Fixtures\GroupDummyChild;
use Symfony\Component\Serializer\Tests\Fixtures\MaxDepthDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Php74Dummy;
use Symfony\Component\Serializer\Tests\Fixtures\PropertyCircularReferenceDummy;
use Symfony\Component\Serializer\Tests\Fixtures\PropertySiblingHolder;

class PropertyNormalizerTest extends TestCase
{
    private \Symfony\Component\Serializer\Normalizer\PropertyNormalizer $normalizer;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    protected function setUp()
    {
        $this->serializer = $this->getMockBuilder(\Symfony\Component\Serializer\SerializerInterface::class)->getMock();
        $this->normalizer = new PropertyNormalizer();
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testNormalize(): void
    {
        $obj = new PropertyDummy();
        $obj->foo = 'foo';
        $obj->setBar('bar');
        $obj->setCamelCase('camelcase');
        $this->assertEquals(
            ['foo' => 'foo', 'bar' => 'bar', 'camelCase' => 'camelcase'],
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
            ['foo' => 'foo', 'bar' => 'bar'],
            PropertyDummy::class,
            'any'
        );
        $this->assertEquals('foo', $obj->foo);
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testNormalizeWithParentClass(): void
    {
        $group = new GroupDummyChild();
        $group->setBaz('baz');
        $group->setFoo('foo');
        $group->setBar('bar');
        $group->setKevin('Kevin');
        $group->setCoopTilleuls('coop');
        $this->assertEquals(
            ['foo' => 'foo', 'bar' => 'bar', 'kevin' => 'Kevin', 'coopTilleuls' => 'coop', 'fooBar' => null, 'symfony' => null, 'baz' => 'baz'],
            $this->normalizer->normalize($group, 'any')
        );
    }

    public function testDenormalizeWithParentClass(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'kevin' => 'Kevin', 'baz' => 'baz'],
            GroupDummyChild::class,
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
        $this->assertEquals('Kevin', $obj->getKevin());
        $this->assertEquals('baz', $obj->getBaz());
        $this->assertNull($obj->getSymfony());
    }

    public function testConstructorDenormalize(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar'],
            PropertyConstructorDummy::class,
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testConstructorDenormalizeWithNullArgument(): void
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => null, 'bar' => 'bar'],
            PropertyConstructorDummy::class, '
            any'
        );
        $this->assertNull($obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    /**
     * @dataProvider provideCallbacks
     */
    public function testCallbacks(array $callbacks, string|\DateTime|array $value, array $result, string $message): void
    {
        $this->normalizer->setCallbacks($callbacks);

        $obj = new PropertyConstructorDummy('', $value);

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

        $obj = new PropertyConstructorDummy('baz', 'quux');

        $this->normalizer->normalize($obj, 'any');
    }

    public function testIgnoredAttributes(): void
    {
        $this->normalizer->setIgnoredAttributes(['foo', 'bar', 'camelCase']);

        $obj = new PropertyDummy();
        $obj->foo = 'foo';
        $obj->setBar('bar');

        $this->assertEquals(
            [],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testGroupsNormalize(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new PropertyNormalizer($classMetadataFactory);
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
        ], $this->normalizer->normalize($obj, null, [PropertyNormalizer::GROUPS => ['c']]));

        // The PropertyNormalizer is also able to hydrate properties from parent classes
        $this->assertEquals([
            'symfony' => 'symfony',
            'foo' => 'foo',
            'fooBar' => 'fooBar',
            'bar' => 'bar',
            'kevin' => 'kevin',
            'coopTilleuls' => 'coopTilleuls',
        ], $this->normalizer->normalize($obj, null, [PropertyNormalizer::GROUPS => ['a', 'c']]));
    }

    public function testGroupsDenormalize(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new PropertyNormalizer($classMetadataFactory);
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFoo('foo');

        $toNormalize = ['foo' => 'foo', 'bar' => 'bar'];

        $normalized = $this->normalizer->denormalize(
            $toNormalize,
            \Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class,
            null,
            [PropertyNormalizer::GROUPS => ['a']]
        );
        $this->assertEquals($obj, $normalized);

        $obj->setBar('bar');

        $normalized = $this->normalizer->denormalize(
            $toNormalize,
            \Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class,
            null,
            [PropertyNormalizer::GROUPS => ['a', 'b']]
        );
        $this->assertEquals($obj, $normalized);
    }

    public function testGroupsNormalizeWithNameConverter(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new PropertyNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
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
            $this->normalizer->normalize($obj, null, [PropertyNormalizer::GROUPS => ['name_converter']])
        );
    }

    public function testGroupsDenormalizeWithNameConverter(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new PropertyNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
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
            ], \Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class, null, [PropertyNormalizer::GROUPS => ['name_converter']])
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
                ['foo' => '', 'bar' => 'baz'],
                'Change a string',
            ],
            [
                [
                    'bar' => static function ($bar) : void {
                    },
                ],
                'baz',
                ['foo' => '', 'bar' => null],
                'Null an item',
            ],
            [
                [
                    'bar' => static fn($bar) => $bar->format('d-m-Y H:i:s'),
                ],
                new \DateTime('2011-09-10 06:30:00'),
                ['foo' => '', 'bar' => '10-09-2011 06:30:00'],
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
                [new PropertyConstructorDummy('baz', ''), new PropertyConstructorDummy('quux', '')],
                ['foo' => '', 'bar' => 'bazquux'],
                'Collect a property',
            ],
            [
                [
                    'bar' => static fn($bars): int => \count($bars),
                ],
                [new PropertyConstructorDummy('baz', ''), new PropertyConstructorDummy('quux', '')],
                ['foo' => '', 'bar' => 2],
                'Count a property',
            ],
        ];
    }

    public function testUnableToNormalizeCircularReference(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\CircularReferenceException::class);
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);
        $this->normalizer->setCircularReferenceLimit(2);

        $obj = new PropertyCircularReferenceDummy();

        $this->normalizer->normalize($obj);
    }

    public function testSiblingReference(): void
    {
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);

        $siblingHolder = new PropertySiblingHolder();

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

        $obj = new PropertyCircularReferenceDummy();

        $expected = ['me' => \Symfony\Component\Serializer\Tests\Fixtures\PropertyCircularReferenceDummy::class];
        $this->assertEquals($expected, $this->normalizer->normalize($obj));
    }

    public function testDenormalizeNonExistingAttribute(): void
    {
        $this->assertEquals(
            new PropertyDummy(),
            $this->normalizer->denormalize(['non_existing' => true], PropertyDummy::class)
        );
    }

    public function testDenormalizeShouldIgnoreStaticProperty(): void
    {
        $obj = $this->normalizer->denormalize(['outOfScope' => true], PropertyDummy::class);

        $this->assertEquals(new PropertyDummy(), $obj);
        $this->assertEquals('out_of_scope', PropertyDummy::$outOfScope);
    }

    public function testUnableToNormalizeObjectAttribute(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\LogicException::class);
        $this->expectExceptionMessage('Cannot normalize attribute "bar" because the injected serializer is not a normalizer');
        $serializer = $this->getMockBuilder(\Symfony\Component\Serializer\SerializerInterface::class)->getMock();
        $this->normalizer->setSerializer($serializer);

        $obj = new PropertyDummy();
        $object = new \stdClass();
        $obj->setBar($object);

        $this->normalizer->normalize($obj, 'any');
    }

    public function testNoTraversableSupport(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \ArrayObject()));
    }

    public function testNoStaticPropertySupport(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new StaticPropertyDummy()));
    }

    public function testMaxDepth(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new PropertyNormalizer($classMetadataFactory);
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

        $result = $serializer->normalize($level1, null, [PropertyNormalizer::ENABLE_MAX_DEPTH => true]);

        $expected = [
            'foo' => 'level1',
            'child' => [
                    'foo' => 'level2',
                    'child' => [
                            'child' => null,
                            'bar' => null,
                        ],
                    'bar' => null,
                ],
            'bar' => null,
        ];

        $this->assertEquals($expected, $result);
    }

    public function testInheritedPropertiesSupport(): void
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new PropertyChildDummy()));
    }
}

class PropertyDummy
{
    public static $outOfScope = 'out_of_scope';

    public $foo;

    private $bar;

    protected $camelCase;

    public function getBar()
    {
        return $this->bar;
    }

    public function setBar($bar): void
    {
        $this->bar = $bar;
    }

    public function getCamelCase()
    {
        return $this->camelCase;
    }

    public function setCamelCase($camelCase): void
    {
        $this->camelCase = $camelCase;
    }
}

class PropertyConstructorDummy
{
    protected $foo;

    private $bar;

    public function __construct($foo, $bar)
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
}

class StaticPropertyDummy
{
    private static string $property = 'value';
}

class PropertyParentDummy
{
}

class PropertyChildDummy extends PropertyParentDummy
{
}
