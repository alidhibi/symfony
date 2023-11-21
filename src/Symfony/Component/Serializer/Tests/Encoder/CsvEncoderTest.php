<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\CsvEncoder;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CsvEncoderTest extends TestCase
{
    private \Symfony\Component\Serializer\Encoder\CsvEncoder $encoder;

    protected function setUp()
    {
        $this->encoder = new CsvEncoder();
    }

    public function testTrueFalseValues(): void
    {
        $data = [
            'string' => 'foo',
            'int' => 2,
            'false' => false,
            'true' => true,
            'int_one' => 1,
            'string_one' => '1',
        ];

        // Check that true and false are appropriately handled
        $this->assertSame($csv = <<<'CSV'
string,int,false,true,int_one,string_one
foo,2,0,1,1,1

CSV
        , $this->encoder->encode($data, 'csv'));

        $this->assertSame([
            'string' => 'foo',
            'int' => '2',
            'false' => '0',
            'true' => '1',
            'int_one' => '1',
            'string_one' => '1',
        ], $this->encoder->decode($csv, 'csv'));
    }

    /**
     * @requires PHP 7.4
     */
    public function testDoubleQuotesAndSlashes(): void
    {
        $this->assertSame($csv = <<<'CSV'
0,1,2,3,4,5
,"""","foo""","\""",\,foo\

CSV
        , $this->encoder->encode($data = ['', '"', 'foo"', '\\"', '\\', 'foo\\'], 'csv'));

        $this->assertSame($data, $this->encoder->decode($csv, 'csv'));
    }

    /**
     * @requires PHP 7.4
     */
    public function testSingleSlash(): void
    {
        $this->assertSame($csv = "0\n\\\n", $this->encoder->encode($data = ['\\'], 'csv'));
        $this->assertSame($data, $this->encoder->decode($csv, 'csv'));
        $this->assertSame($data, $this->encoder->decode(trim($csv), 'csv'));
    }

    public function testSupportEncoding(): void
    {
        $this->assertTrue($this->encoder->supportsEncoding('csv'));
        $this->assertFalse($this->encoder->supportsEncoding('foo'));
    }

    public function testEncode(): void
    {
        $value = ['foo' => 'hello', 'bar' => 'hey ho'];

        $this->assertEquals(<<<'CSV'
foo,bar
hello,"hey ho"

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCollection(): void
    {
        $value = [
            ['foo' => 'hello', 'bar' => 'hey ho'],
            ['foo' => 'hi', 'bar' => "let's go"],
        ];

        $this->assertEquals(<<<'CSV'
foo,bar
hello,"hey ho"
hi,"let's go"

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodePlainIndexedArray(): void
    {
        $this->assertEquals(<<<'CSV'
0,1,2
a,b,c

CSV
            , $this->encoder->encode(['a', 'b', 'c'], 'csv'));
    }

    public function testEncodeNonArray(): void
    {
        $this->assertEquals(<<<'CSV'
0
foo

CSV
            , $this->encoder->encode('foo', 'csv'));
    }

    public function testEncodeNestedArrays(): void
    {
        $value = ['foo' => 'hello', 'bar' => [
            ['id' => 'yo', 1 => 'wesh'],
            ['baz' => 'Halo', 'foo' => 'olá'],
        ]];

        $this->assertEquals(<<<'CSV'
foo,bar.0.id,bar.0.1,bar.1.baz,bar.1.foo
hello,yo,wesh,Halo,olá

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCustomSettings(): void
    {
        $this->encoder = new CsvEncoder(';', "'", '|', '-');

        $value = ['a' => "he'llo", 'c' => ['d' => 'foo']];

        $this->assertEquals(<<<'CSV'
a;c-d
'he''llo';foo

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCustomSettingsPassedInContext(): void
    {
        $value = ['a' => "he'llo", 'c' => ['d' => 'foo']];

        $this->assertSame(<<<'CSV'
a;c-d
'he''llo';foo

CSV
        , $this->encoder->encode($value, 'csv', [
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => "'",
            CsvEncoder::ESCAPE_CHAR_KEY => '|',
            CsvEncoder::KEY_SEPARATOR_KEY => '-',
        ]));
    }

    public function testEncodeEmptyArray(): void
    {
        $this->assertEquals("\n\n", $this->encoder->encode([], 'csv'));
        $this->assertEquals("\n\n", $this->encoder->encode([[]], 'csv'));
    }

    public function testEncodeVariableStructure(): void
    {
        $value = [
            ['a' => ['foo', 'bar']],
            ['a' => [], 'b' => 'baz'],
            ['a' => ['bar', 'foo'], 'c' => 'pong'],
        ];
        $csv = <<<CSV
a.0,a.1,c,b
foo,bar,,
,,,baz
bar,foo,pong,

CSV;

        $this->assertEquals($csv, $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCustomHeaders(): void
    {
        $context = [
            CsvEncoder::HEADERS_KEY => [
                'b',
                'c',
            ],
        ];
        $value = [
            ['a' => 'foo', 'b' => 'bar'],
        ];
        $csv = <<<CSV
b,c,a
bar,,foo

CSV;

        $this->assertEquals($csv, $this->encoder->encode($value, 'csv', $context));
    }

    public function testSupportsDecoding(): void
    {
        $this->assertTrue($this->encoder->supportsDecoding('csv'));
        $this->assertFalse($this->encoder->supportsDecoding('foo'));
    }

    public function testDecode(): void
    {
        $expected = ['foo' => 'a', 'bar' => 'b'];

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar
a,b
CSV
        , 'csv'));
    }

    public function testDecodeCollection(): void
    {
        $expected = [
            ['foo' => 'a', 'bar' => 'b'],
            ['foo' => 'c', 'bar' => 'd'],
            ['foo' => 'f'],
        ];

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar
a,b
c,d
f

CSV
        , 'csv'));
    }

    public function testDecodeToManyRelation(): void
    {
        $expected = [
            ['foo' => 'bar', 'relations' => [['a' => 'b'], ['a' => 'b']]],
            ['foo' => 'bat', 'relations' => [['a' => 'b'], ['a' => '']]],
            ['foo' => 'bat', 'relations' => [['a' => 'b']]],
            ['foo' => 'baz', 'relations' => [['a' => 'c'], ['a' => 'c']]],
        ];

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,relations.0.a,relations.1.a
bar,b,b
bat,b,
bat,b
baz,c,c
CSV
            , 'csv'));
    }

    public function testDecodeNestedArrays(): void
    {
        $expected = [
            ['foo' => 'a', 'bar' => ['baz' => ['bat' => 'b']]],
            ['foo' => 'c', 'bar' => ['baz' => ['bat' => 'd']]],
        ];

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar.baz.bat
a,b
c,d
CSV
        , 'csv'));
    }

    public function testDecodeCustomSettings(): void
    {
        $this->encoder = new CsvEncoder(';', "'", '|', '-');

        $expected = ['a' => "hell'o", 'bar' => ['baz' => 'b']];
        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
a;bar-baz
'hell''o';b;c
CSV
        , 'csv'));
    }

    public function testDecodeCustomSettingsPassedInContext(): void
    {
        $expected = ['a' => "hell'o", 'bar' => ['baz' => 'b']];
        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
a;bar-baz
'hell''o';b;c
CSV
        , 'csv', [
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => "'",
            CsvEncoder::ESCAPE_CHAR_KEY => '|',
            CsvEncoder::KEY_SEPARATOR_KEY => '-',
        ]));
    }

    public function testDecodeMalformedCollection(): void
    {
        $expected = [
            ['foo' => 'a', 'bar' => 'b'],
            ['foo' => 'c', 'bar' => 'd'],
            ['foo' => 'f'],
        ];

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar
a,b,e
c,d,g,h
f

CSV
            , 'csv'));
    }

    public function testDecodeEmptyArray(): void
    {
        $this->assertEquals([], $this->encoder->decode('', 'csv'));
    }
}
