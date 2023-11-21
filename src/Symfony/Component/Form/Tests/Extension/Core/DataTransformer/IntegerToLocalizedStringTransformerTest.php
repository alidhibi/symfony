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
use Symfony\Component\Form\Extension\Core\DataTransformer\IntegerToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class IntegerToLocalizedStringTransformerTest extends TestCase
{
    private $defaultLocale;

    protected function setUp()
    {
        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('en');
    }

    protected function tearDown()
    {
        \Locale::setDefault($this->defaultLocale);
    }

    public function transformWithRoundingProvider(): array
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            [1234.5, '1235', IntegerToLocalizedStringTransformer::ROUND_CEILING],
            [1234.4, '1235', IntegerToLocalizedStringTransformer::ROUND_CEILING],
            [-1234.5, '-1234', IntegerToLocalizedStringTransformer::ROUND_CEILING],
            [-1234.4, '-1234', IntegerToLocalizedStringTransformer::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            [1234.5, '1234', IntegerToLocalizedStringTransformer::ROUND_FLOOR],
            [1234.4, '1234', IntegerToLocalizedStringTransformer::ROUND_FLOOR],
            [-1234.5, '-1235', IntegerToLocalizedStringTransformer::ROUND_FLOOR],
            [-1234.4, '-1235', IntegerToLocalizedStringTransformer::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            [1234.5, '1235', IntegerToLocalizedStringTransformer::ROUND_UP],
            [1234.4, '1235', IntegerToLocalizedStringTransformer::ROUND_UP],
            [-1234.5, '-1235', IntegerToLocalizedStringTransformer::ROUND_UP],
            [-1234.4, '-1235', IntegerToLocalizedStringTransformer::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            [1234.5, '1234', IntegerToLocalizedStringTransformer::ROUND_DOWN],
            [1234.4, '1234', IntegerToLocalizedStringTransformer::ROUND_DOWN],
            [-1234.5, '-1234', IntegerToLocalizedStringTransformer::ROUND_DOWN],
            [-1234.4, '-1234', IntegerToLocalizedStringTransformer::ROUND_DOWN],
            // round halves (.5) to the next even number
            [1234.6, '1235', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1234.5, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1234.4, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1233.5, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1232.5, '1232', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [-1234.6, '-1235', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [-1234.5, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [-1234.4, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [-1233.5, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [-1232.5, '-1232', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            // round halves (.5) away from zero
            [1234.6, '1235', IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            [1234.5, '1235', IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            [1234.4, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            [-1234.6, '-1235', IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            [-1234.5, '-1235', IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            [-1234.4, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            // round halves (.5) towards zero
            [1234.6, '1235', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1234.5, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1234.4, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [-1234.6, '-1235', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [-1234.5, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [-1234.4, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
        ];
    }

    /**
     * @dataProvider transformWithRoundingProvider
     */
    public function testTransformWithRounding(float $input, string $output, int $roundingMode): void
    {
        $transformer = new IntegerToLocalizedStringTransformer(null, null, $roundingMode);

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testReverseTransform(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new IntegerToLocalizedStringTransformer();

        $this->assertEquals(1, $transformer->reverseTransform('1'));
        $this->assertEquals(12345, $transformer->reverseTransform('12345'));
    }

    public function testReverseTransformEmpty(): void
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $this->assertNull($transformer->reverseTransform(''));
    }

    public function testReverseTransformWithGrouping(): void
    {
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $transformer = new IntegerToLocalizedStringTransformer(null, true);

        $this->assertEquals(1234, $transformer->reverseTransform('1.234'));
        $this->assertEquals(12345, $transformer->reverseTransform('12.345'));
        $this->assertEquals(1234, $transformer->reverseTransform('1234'));
        $this->assertEquals(12345, $transformer->reverseTransform('12345'));
    }

    public function reverseTransformWithRoundingProvider(): array
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            ['1234,5', 1235, IntegerToLocalizedStringTransformer::ROUND_CEILING],
            ['1234,4', 1235, IntegerToLocalizedStringTransformer::ROUND_CEILING],
            ['-1234,5', -1234, IntegerToLocalizedStringTransformer::ROUND_CEILING],
            ['-1234,4', -1234, IntegerToLocalizedStringTransformer::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            ['1234,5', 1234, IntegerToLocalizedStringTransformer::ROUND_FLOOR],
            ['1234,4', 1234, IntegerToLocalizedStringTransformer::ROUND_FLOOR],
            ['-1234,5', -1235, IntegerToLocalizedStringTransformer::ROUND_FLOOR],
            ['-1234,4', -1235, IntegerToLocalizedStringTransformer::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            ['1234,5', 1235, IntegerToLocalizedStringTransformer::ROUND_UP],
            ['1234,4', 1235, IntegerToLocalizedStringTransformer::ROUND_UP],
            ['-1234,5', -1235, IntegerToLocalizedStringTransformer::ROUND_UP],
            ['-1234,4', -1235, IntegerToLocalizedStringTransformer::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            ['1234,5', 1234, IntegerToLocalizedStringTransformer::ROUND_DOWN],
            ['1234,4', 1234, IntegerToLocalizedStringTransformer::ROUND_DOWN],
            ['-1234,5', -1234, IntegerToLocalizedStringTransformer::ROUND_DOWN],
            ['-1234,4', -1234, IntegerToLocalizedStringTransformer::ROUND_DOWN],
            // round halves (.5) to the next even number
            ['1234,6', 1235, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            ['1234,5', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            ['1234,4', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            ['1233,5', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            ['1232,5', 1232, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            ['-1234,6', -1235, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            ['-1234,5', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            ['-1234,4', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            ['-1233,5', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            ['-1232,5', -1232, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN],
            // round halves (.5) away from zero
            ['1234,6', 1235, IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            ['1234,5', 1235, IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            ['1234,4', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            ['-1234,6', -1235, IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            ['-1234,5', -1235, IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            ['-1234,4', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_UP],
            // round halves (.5) towards zero
            ['1234,6', 1235, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
            ['1234,5', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
            ['1234,4', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
            ['-1234,6', -1235, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
            ['-1234,5', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
            ['-1234,4', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN],
        ];
    }

    /**
     * @dataProvider reverseTransformWithRoundingProvider
     */
    public function testReverseTransformWithRounding(string $input, int $output, int $roundingMode): void
    {
        $transformer = new IntegerToLocalizedStringTransformer(null, null, $roundingMode);

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformExpectsString(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform(1);
    }

    public function testReverseTransformExpectsValidNumber(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('foo');
    }

    /**
     * @dataProvider floatNumberProvider
     */
    public function testReverseTransformExpectsInteger(string $number, string $locale): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault($locale);

        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform($number);
    }

    public function floatNumberProvider(): array
    {
        return [
            ['12345.912', 'en'],
            ['1.234,5', 'de_DE'],
        ];
    }

    public function testReverseTransformDisallowsNaN(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('NaN');
    }

    public function testReverseTransformDisallowsNaN2(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('nan');
    }

    public function testReverseTransformDisallowsInfinity(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('∞');
    }

    public function testReverseTransformDisallowsNegativeInfinity(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('-∞');
    }
}
