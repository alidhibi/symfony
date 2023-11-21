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
use Symfony\Component\Form\Extension\Core\DataTransformer\MoneyToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class MoneyToLocalizedStringTransformerTest extends TestCase
{
    private string|bool $previousLocale;

    protected function setUp()
    {
        $this->previousLocale = setlocale(\LC_ALL, '0');
    }

    protected function tearDown()
    {
        setlocale(\LC_ALL, $this->previousLocale);
    }

    public function testTransform(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new MoneyToLocalizedStringTransformer(null, null, null, 100);

        $this->assertEquals('1,23', $transformer->transform(123));
    }

    public function testTransformExpectsNumeric(): void
    {
        $transformer = new MoneyToLocalizedStringTransformer(null, null, null, 100);

        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);

        $transformer->transform('abcd');
    }

    public function testTransformEmpty(): void
    {
        $transformer = new MoneyToLocalizedStringTransformer();

        $this->assertSame('', $transformer->transform(null));
    }

    public function testReverseTransform(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new MoneyToLocalizedStringTransformer(null, null, null, 100);

        $this->assertEquals(123, $transformer->reverseTransform('1,23'));
    }

    public function testReverseTransformExpectsString(): void
    {
        $transformer = new MoneyToLocalizedStringTransformer(null, null, null, 100);

        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);

        $transformer->reverseTransform(12345);
    }

    public function testReverseTransformEmpty(): void
    {
        $transformer = new MoneyToLocalizedStringTransformer();

        $this->assertNull($transformer->reverseTransform(''));
    }

    public function testFloatToIntConversionMismatchOnReverseTransform(): void
    {
        $transformer = new MoneyToLocalizedStringTransformer(null, null, null, 100);
        IntlTestHelper::requireFullIntl($this, false);
        \Locale::setDefault('de_AT');

        $this->assertSame(3655, (int) $transformer->reverseTransform('36,55'));
    }

    public function testFloatToIntConversionMismatchOnTransform(): void
    {
        $transformer = new MoneyToLocalizedStringTransformer(null, null, MoneyToLocalizedStringTransformer::ROUND_DOWN, 100);
        IntlTestHelper::requireFullIntl($this, false);
        \Locale::setDefault('de_AT');

        $this->assertSame('10,20', $transformer->transform(1020));
    }

    public function testValidNumericValuesWithNonDotDecimalPointCharacter(): void
    {
        // calling setlocale() here is important as it changes the representation of floats when being cast to strings
        setlocale(\LC_ALL, 'de_AT.UTF-8');

        $transformer = new MoneyToLocalizedStringTransformer(4, null, null, 100);
        IntlTestHelper::requireFullIntl($this, false);
        \Locale::setDefault('de_AT');

        $this->assertSame('0,0035', $transformer->transform(12 / 34));
    }
}
