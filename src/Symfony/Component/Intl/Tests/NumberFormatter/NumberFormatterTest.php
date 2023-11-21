<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\NumberFormatter;

use Symfony\Component\Intl\Globals\IntlGlobals;
use Symfony\Component\Intl\NumberFormatter\NumberFormatter;

/**
 * Note that there are some values written like -2147483647 - 1. This is the lower 32bit int max and is a known
 * behavior of PHP.
 */
class NumberFormatterTest extends AbstractNumberFormatterTest
{
    public function testConstructorWithUnsupportedLocale(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);
        new NumberFormatter('pt_BR');
    }

    public function testConstructorWithUnsupportedStyle(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);
        new NumberFormatter('en', NumberFormatter::PATTERN_DECIMAL);
    }

    public function testConstructorWithPatternDifferentThanNull(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException::class);
        new NumberFormatter('en', NumberFormatter::DECIMAL, '');
    }

    public function testSetAttributeWithUnsupportedAttribute(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::LENIENT_PARSE, null);
    }

    public function testSetAttributeInvalidRoundingMode(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, null);
    }

    public function testConstructWithoutLocale(): void
    {
        $this->assertInstanceOf(
            '\\' . \Symfony\Component\Intl\NumberFormatter\NumberFormatter::class,
            $this->getNumberFormatter(null, NumberFormatter::DECIMAL)
        );
    }

    public function testCreate(): void
    {
        $this->assertInstanceOf(
            '\\' . \Symfony\Component\Intl\NumberFormatter\NumberFormatter::class,
            NumberFormatter::create('en', NumberFormatter::DECIMAL)
        );
    }

    public function testFormatWithCurrencyStyle(): void
    {
        $this->expectException('RuntimeException');
        parent::testFormatWithCurrencyStyle();
    }

    /**
     * @dataProvider formatTypeInt32Provider
     */
    public function testFormatTypeInt32(\NumberFormatter $formatter, int|float $value, string $expected, string $message = ''): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);
        parent::testFormatTypeInt32($formatter, $value, $expected, $message);
    }

    /**
     * @dataProvider formatTypeInt32WithCurrencyStyleProvider
     */
    public function testFormatTypeInt32WithCurrencyStyle(\NumberFormatter $formatter, int|float $value, string $expected, string $message = ''): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\NotImplementedException::class);
        parent::testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message);
    }

    /**
     * @dataProvider formatTypeInt64Provider
     */
    public function testFormatTypeInt64(\NumberFormatter $formatter, int|float $value, string $expected): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);
        parent::testFormatTypeInt64($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeInt64WithCurrencyStyleProvider
     */
    public function testFormatTypeInt64WithCurrencyStyle(\NumberFormatter $formatter, int|float $value, string $expected): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\NotImplementedException::class);
        parent::testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeDoubleProvider
     */
    public function testFormatTypeDouble(\NumberFormatter $formatter, int|float $value, string $expected): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);
        parent::testFormatTypeDouble($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeDoubleWithCurrencyStyleProvider
     */
    public function testFormatTypeDoubleWithCurrencyStyle(\NumberFormatter $formatter, int|float $value, string $expected): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\NotImplementedException::class);
        parent::testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected);
    }

    public function testGetPattern(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->getPattern();
    }

    public function testGetErrorCode(): void
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $this->assertEquals(IntlGlobals::U_ZERO_ERROR, $formatter->getErrorCode());
    }

    public function testParseCurrency(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->parseCurrency(null, $currency);
    }

    public function testSetPattern(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setPattern(null);
    }

    public function testSetSymbol(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setSymbol(null, null);
    }

    public function testSetTextAttribute(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setTextAttribute(null, null);
    }

    protected function getNumberFormatter($locale = 'en', $style = null, $pattern = null): \Symfony\Component\Intl\NumberFormatter\NumberFormatter
    {
        return new NumberFormatter($locale, $style, $pattern);
    }

    protected function getIntlErrorMessage()
    {
        return IntlGlobals::getErrorMessage();
    }

    protected function getIntlErrorCode()
    {
        return IntlGlobals::getErrorCode();
    }

    protected function isIntlFailure($errorCode)
    {
        return IntlGlobals::isFailure($errorCode);
    }
}
