<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToDuplicatesTransformer;

class ValueToDuplicatesTransformerTest extends TestCase
{
    private ?\Symfony\Component\Form\Extension\Core\DataTransformer\ValueToDuplicatesTransformer $transformer = null;

    protected function setUp()
    {
        $this->transformer = new ValueToDuplicatesTransformer(['a', 'b', 'c']);
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function testTransform(): void
    {
        $output = [
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => 'Foo',
        ];

        $this->assertSame($output, $this->transformer->transform('Foo'));
    }

    public function testTransformEmpty(): void
    {
        $output = [
            'a' => null,
            'b' => null,
            'c' => null,
        ];

        $this->assertSame($output, $this->transformer->transform(null));
    }

    public function testReverseTransform(): void
    {
        $input = [
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => 'Foo',
        ];

        $this->assertSame('Foo', $this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmpty(): void
    {
        $input = [
            'a' => '',
            'b' => '',
            'c' => '',
        ];

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyNull(): void
    {
        $input = [
            'a' => null,
            'b' => null,
            'c' => null,
        ];

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformEmptyArray(): void
    {
        $input = [
            'a' => [],
            'b' => [],
            'c' => [],
        ];

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformZeroString(): void
    {
        $input = [
            'a' => '0',
            'b' => '0',
            'c' => '0',
        ];

        $this->assertSame('0', $this->transformer->reverseTransform($input));
    }

    public function testReverseTransformPartiallyNull(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $input = [
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => null,
        ];

        $this->transformer->reverseTransform($input);
    }

    public function testReverseTransformDifferences(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $input = [
            'a' => 'Foo',
            'b' => 'Bar',
            'c' => 'Foo',
        ];

        $this->transformer->reverseTransform($input);
    }

    public function testReverseTransformRequiresArray(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $this->transformer->reverseTransform('12345');
    }
}
