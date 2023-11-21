<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\DateFormatter;

use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;
use Symfony\Component\Intl\Globals\IntlGlobals;

class IntlDateFormatterTest extends AbstractIntlDateFormatterTest
{
    public function testConstructor(): void
    {
        $formatter = new IntlDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC', IntlDateFormatter::GREGORIAN, 'y-M-d');
        $this->assertEquals('y-M-d', $formatter->getPattern());
    }

    public function testConstructorWithoutLocale(): void
    {
        $formatter = new IntlDateFormatter(null, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC', IntlDateFormatter::GREGORIAN, 'y-M-d');
        $this->assertEquals('y-M-d', $formatter->getPattern());
    }

    public function testConstructorWithoutCalendar(): void
    {
        $formatter = new IntlDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC', null, 'y-M-d');
        $this->assertEquals('y-M-d', $formatter->getPattern());
    }

    public function testConstructorWithUnsupportedLocale(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);
        new IntlDateFormatter('pt_BR', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);
    }

    public function testStaticCreate(): void
    {
        $formatter = IntlDateFormatter::create('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);
        $this->assertInstanceOf('\\' . \Symfony\Component\Intl\DateFormatter\IntlDateFormatter::class, $formatter);
    }

    public function testFormatWithUnsupportedTimestampArgument(): void
    {
        $formatter = $this->getDefaultDateFormatter();

        $localtime = [
            'tm_sec' => 59,
            'tm_min' => 3,
            'tm_hour' => 15,
            'tm_mday' => 15,
            'tm_mon' => 3,
            'tm_year' => 112,
            'tm_wday' => 0,
            'tm_yday' => 105,
            'tm_isdst' => 0,
        ];

        try {
            $formatter->format($localtime);
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class, $exception);

            $this->assertStringEndsWith('Only integer Unix timestamps and DateTime objects are supported.  Please install the "intl" extension for full localization capabilities.', $exception->getMessage());
        }
    }

    public function testFormatWithUnimplementedChars(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\NotImplementedException::class);
        $pattern = 'Y';
        $formatter = new IntlDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC', IntlDateFormatter::GREGORIAN, $pattern);
        $formatter->format(0);
    }

    public function testFormatWithNonIntegerTimestamp(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\NotImplementedException::class);
        $formatter = $this->getDefaultDateFormatter();
        $formatter->format([]);
    }

    public function testGetErrorCode(): void
    {
        $formatter = $this->getDefaultDateFormatter();
        $this->assertEquals(IntlGlobals::getErrorCode(), $formatter->getErrorCode());
    }

    public function testGetErrorMessage(): void
    {
        $formatter = $this->getDefaultDateFormatter();
        $this->assertEquals(IntlGlobals::getErrorMessage(), $formatter->getErrorMessage());
    }

    public function testIsLenient(): void
    {
        $formatter = $this->getDefaultDateFormatter();
        $this->assertFalse($formatter->isLenient());
    }

    public function testLocaltime(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $formatter = $this->getDefaultDateFormatter();
        $formatter->localtime('Wednesday, December 31, 1969 4:00:00 PM PT');
    }

    public function testParseWithNotNullPositionValue(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException::class);
        $position = 0;
        $formatter = $this->getDefaultDateFormatter('y');
        $this->assertSame(0, $formatter->parse('1970', $position));
    }

    public function testSetCalendar(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $formatter = $this->getDefaultDateFormatter();
        $formatter->setCalendar(IntlDateFormatter::GREGORIAN);
    }

    public function testSetLenient(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);
        $formatter = $this->getDefaultDateFormatter();
        $formatter->setLenient(true);
    }

    public function testFormatWithGmtTimeZoneAndMinutesOffset(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\NotImplementedException::class);
        parent::testFormatWithGmtTimeZoneAndMinutesOffset();
    }

    public function testFormatWithNonStandardTimezone(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\NotImplementedException::class);
        parent::testFormatWithNonStandardTimezone();
    }

    public function parseStandaloneAmPmProvider()
    {
        return $this->notImplemented(parent::parseStandaloneAmPmProvider());
    }

    public function parseDayOfWeekProvider()
    {
        return $this->notImplemented(parent::parseDayOfWeekProvider());
    }

    public function parseDayOfYearProvider()
    {
        return $this->notImplemented(parent::parseDayOfYearProvider());
    }

    public function parseQuarterProvider()
    {
        return $this->notImplemented(parent::parseQuarterProvider());
    }

    public function testParseThreeDigitsYears(): void
    {
        if (\PHP_INT_SIZE < 8) {
            $this->markTestSkipped('Parsing three digits years requires a 64bit PHP.');
        }

        $formatter = $this->getDefaultDateFormatter('yyyy-M-d');
        $this->assertSame(-32_157_648_000, $formatter->parse('950-12-19'));
        $this->assertIsIntlSuccess($formatter, 'U_ZERO_ERROR', IntlGlobals::U_ZERO_ERROR);
    }

    protected function getDateFormatter($locale, $datetype, $timetype, $timezone = null, $calendar = IntlDateFormatter::GREGORIAN, $pattern = null): \Symfony\Component\Intl\DateFormatter\IntlDateFormatter
    {
        return new IntlDateFormatter($locale, $datetype, $timetype, $timezone, $calendar, $pattern);
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

    /**
     * Just to document the differences between the stub and the intl
     * implementations. The intl can parse any of the tested formats alone. The
     * stub does not implement them as it would be needed to add more
     * abstraction, passing more context to the transformers objects. Any of the
     * formats are ignored alone or with date/time data (years, months, days,
     * hours, minutes and seconds).
     *
     * Also in intl, format like 'ss E' for '10 2' (2nd day of year
     * + 10 seconds) are added, then we have 86,400 seconds (24h * 60min * 60s)
     * + 10 seconds
     *
     */
    private function notImplemented(array $dataSets): array
    {
        return array_map(static fn($row): array => [$row[0], $row[1], 0], $dataSets);
    }
}
