<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Extractor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\AdderRemoverDummy;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ReflectionExtractorTest extends TestCase
{
    private \Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor $extractor;

    protected function setUp()
    {
        $this->extractor = new ReflectionExtractor();
    }

    public function testGetProperties(): void
    {
        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'emptyVar',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'a',
                'DOB',
                'Id',
                '123',
                'self',
                'realParent',
                'xTotals',
                'YT',
                'date',
                'c',
                'd',
                'e',
                'f',
            ],
            $this->extractor->getProperties(\Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy::class)
        );

        $this->assertNull($this->extractor->getProperties(\Symfony\Component\PropertyInfo\Tests\Fixtures\NoProperties::class));
    }

    public function testGetPropertiesWithCustomPrefixes(): void
    {
        $customExtractor = new ReflectionExtractor(['add', 'remove'], ['is', 'can']);

        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'emptyVar',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'date',
                'c',
                'd',
                'e',
                'f',
            ],
            $customExtractor->getProperties(\Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy::class)
        );
    }

    public function testGetPropertiesWithNoPrefixes(): void
    {
        $noPrefixExtractor = new ReflectionExtractor([], [], []);

        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'emptyVar',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
            ],
            $noPrefixExtractor->getProperties(\Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy::class)
        );
    }

    /**
     * @dataProvider typesProvider
     */
    public function testExtractors(string $property, array $type = null): void
    {
        $this->assertEquals($type, $this->extractor->getTypes(\Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy::class, $property, []));
    }

    public function typesProvider(): array
    {
        return [
            ['a', null],
            ['b', [new Type(Type::BUILTIN_TYPE_OBJECT, true, \Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy::class)]],
            ['c', [new Type(Type::BUILTIN_TYPE_BOOL)]],
            ['d', [new Type(Type::BUILTIN_TYPE_BOOL)]],
            ['e', null],
            ['f', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTime'))]],
            ['donotexist', null],
            ['staticGetter', null],
            ['staticSetter', null],
            ['self', [new Type(Type::BUILTIN_TYPE_OBJECT, false, \Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy::class)]],
            ['realParent', [new Type(Type::BUILTIN_TYPE_OBJECT, false, \Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy::class)]],
            ['date', [new Type(Type::BUILTIN_TYPE_OBJECT, false, \DateTime::class)]],
            ['dates', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, \DateTime::class))]],
        ];
    }

    /**
     * @dataProvider php7TypesProvider
     * @requires PHP 7.0
     */
    public function testExtractPhp7Type(string $property, array $type = null): void
    {
        $this->assertEquals($type, $this->extractor->getTypes(\Symfony\Component\PropertyInfo\Tests\Fixtures\Php7Dummy::class, $property, []));
    }

    public function php7TypesProvider(): array
    {
        return [
            ['foo', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true)]],
            ['bar', [new Type(Type::BUILTIN_TYPE_INT)]],
            ['baz', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))]],
            ['buz', [new Type(Type::BUILTIN_TYPE_OBJECT, false, \Symfony\Component\PropertyInfo\Tests\Fixtures\Php7Dummy::class)]],
            ['biz', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'stdClass')]],
            ['donotexist', null],
        ];
    }

    /**
     * @dataProvider php71TypesProvider
     * @requires PHP 7.1
     */
    public function testExtractPhp71Type(string $property, array $type = null): void
    {
        $this->assertEquals($type, $this->extractor->getTypes(\Symfony\Component\PropertyInfo\Tests\Fixtures\Php71Dummy::class, $property, []));
    }

    public function php71TypesProvider(): array
    {
        return [
            ['foo', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true)]],
            ['buz', [new Type(Type::BUILTIN_TYPE_NULL)]],
            ['bar', [new Type(Type::BUILTIN_TYPE_INT, true)]],
            ['baz', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))]],
            ['donotexist', null],
        ];
    }

    /**
     * @dataProvider php80TypesProvider
     * @requires PHP 8
     */
    public function testExtractPhp80Type(string $property, array $type = null): void
    {
        $this->assertEquals($type, $this->extractor->getTypes(\Symfony\Component\PropertyInfo\Tests\Fixtures\Php80Dummy::class, $property, []));
    }

    public function php80TypesProvider(): array
    {
        return [
            ['foo', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true)]],
            ['bar', [new Type(Type::BUILTIN_TYPE_INT, true)]],
            ['timeout', [new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_FLOAT)]],
            ['optional', [new Type(Type::BUILTIN_TYPE_INT, true), new Type(Type::BUILTIN_TYPE_FLOAT, true)]],
            ['string', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Stringable'), new Type(Type::BUILTIN_TYPE_STRING)]],
            ['payload', null],
            ['data', null],
        ];
    }

    /**
     * @dataProvider getReadableProperties
     */
    public function testIsReadable(string $property, bool $expected): void
    {
        $this->assertSame(
            $expected,
            $this->extractor->isReadable(\Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy::class, $property, [])
        );
    }

    public function getReadableProperties(): array
    {
        return [
            ['bar', false],
            ['baz', false],
            ['parent', true],
            ['a', true],
            ['b', false],
            ['c', true],
            ['d', true],
            ['e', false],
            ['f', false],
            ['Id', true],
            ['id', true],
            ['Guid', true],
            ['guid', false],
        ];
    }

    /**
     * @dataProvider getWritableProperties
     */
    public function testIsWritable(string $property, bool $expected): void
    {
        $this->assertSame(
            $expected,
            $this->extractor->isWritable(\Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy::class, $property, [])
        );
    }

    public function getWritableProperties(): array
    {
        return [
            ['bar', false],
            ['baz', false],
            ['parent', true],
            ['a', false],
            ['b', true],
            ['c', false],
            ['d', false],
            ['e', true],
            ['f', true],
            ['Id', false],
            ['Guid', true],
            ['guid', false],
        ];
    }

    public function testSingularize(): void
    {
        $this->assertTrue($this->extractor->isWritable(AdderRemoverDummy::class, 'analyses'));
        $this->assertTrue($this->extractor->isWritable(AdderRemoverDummy::class, 'feet'));
        $this->assertEquals(['analyses', 'feet'], $this->extractor->getProperties(AdderRemoverDummy::class));
    }
}
