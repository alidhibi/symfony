<?php

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;

/**
 * @author Jérôme Parmentier <jerome@prmntr.me>
 */
class DateIntervalNormalizerTest extends TestCase
{
    private \Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer $normalizer;

    protected function setUp()
    {
        $this->normalizer = new DateIntervalNormalizer();
    }

    public function dataProviderISO(): array
    {
        return [
            ['P%YY%MM%DDT%HH%IM%SS', 'P00Y00M00DT00H00M00S', 'PT0S'],
            ['P%yY%mM%dDT%hH%iM%sS', 'P0Y0M0DT0H0M0S', 'PT0S'],
            ['P%yY%mM%dDT%hH%iM%sS', 'P10Y2M3DT16H5M6S', 'P10Y2M3DT16H5M6S'],
            ['P%yY%mM%dDT%hH%iM', 'P10Y2M3DT16H5M', 'P10Y2M3DT16H5M'],
            ['P%yY%mM%dDT%hH', 'P10Y2M3DT16H', 'P10Y2M3DT16H'],
            ['P%yY%mM%dD', 'P10Y2M3D', 'P10Y2M3DT0H'],
            ['%RP%yY%mM%dD', '-P10Y2M3D', '-P10Y2M3DT0H'],
            ['%RP%yY%mM%dD', '+P10Y2M3D', '+P10Y2M3DT0H'],
            ['%RP%yY%mM%dD', '+P10Y2M3D', 'P10Y2M3DT0H'],
            ['%rP%yY%mM%dD', '-P10Y2M3D', '-P10Y2M3DT0H'],
            ['%rP%yY%mM%dD', 'P10Y2M3D', 'P10Y2M3DT0H'],
        ];
    }

    public function testSupportsNormalization(): void
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new \DateInterval('P00Y00M00DT00H00M00S')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize(): void
    {
        $this->assertEquals('P0Y0M0DT0H0M0S', $this->normalizer->normalize(new \DateInterval('PT0S')));
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testNormalizeUsingFormatPassedInContext(string $format, string $output, string $input): void
    {
        $this->assertEquals($output, $this->normalizer->normalize($this->getInterval($input), null, [DateIntervalNormalizer::FORMAT_KEY => $format]));
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testNormalizeUsingFormatPassedInConstructor(string $format, string $output, string $input): void
    {
        $this->assertEquals($output, (new DateIntervalNormalizer($format))->normalize($this->getInterval($input)));
    }

    public function testNormalizeInvalidObjectThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('The object must be an instance of "\DateInterval".');
        $this->normalizer->normalize(new \stdClass());
    }

    public function testSupportsDenormalization(): void
    {
        $this->assertTrue($this->normalizer->supportsDenormalization('P00Y00M00DT00H00M00S', \DateInterval::class));
        $this->assertFalse($this->normalizer->supportsDenormalization('foo', 'Bar'));
    }

    public function testDenormalize(): void
    {
        $this->assertDateIntervalEquals(new \DateInterval('P00Y00M00DT00H00M00S'), $this->normalizer->denormalize('P00Y00M00DT00H00M00S', \DateInterval::class));
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testDenormalizeUsingFormatPassedInContext(string $format, string $input, string $output): void
    {
        $this->assertDateIntervalEquals($this->getInterval($output), $this->normalizer->denormalize($input, \DateInterval::class, null, [DateIntervalNormalizer::FORMAT_KEY => $format]));
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testDenormalizeUsingFormatPassedInConstructor(string $format, string $input, string $output): void
    {
        $this->assertDateIntervalEquals($this->getInterval($output), (new DateIntervalNormalizer($format))->denormalize($input, \DateInterval::class));
    }

    public function testDenormalizeExpectsString(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);
        $this->normalizer->denormalize(1234, \DateInterval::class);
    }

    public function testDenormalizeNonISO8601IntervalStringThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected a valid ISO 8601 interval string.');
        $this->normalizer->denormalize('10 years 2 months 3 days', \DateInterval::class, null);
    }

    public function testDenormalizeInvalidDataThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $this->normalizer->denormalize('invalid interval', \DateInterval::class);
    }

    public function testDenormalizeFormatMismatchThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $this->normalizer->denormalize('P00Y00M00DT00H00M00S', \DateInterval::class, null, [DateIntervalNormalizer::FORMAT_KEY => 'P%yY%mM%dD']);
    }

    private function assertDateIntervalEquals(\DateInterval $expected, \DateInterval $actual): void
    {
        $this->assertEquals($expected->format('%RP%yY%mM%dDT%hH%iM%sS'), $actual->format('%RP%yY%mM%dDT%hH%iM%sS'));
    }

    private function getInterval(string $data)
    {
        if ('-' === $data[0]) {
            $interval = new \DateInterval(substr($data, 1));
            $interval->invert = 1;

            return $interval;
        }

        if ('+' === $data[0]) {
            return new \DateInterval(substr($data, 1));
        }

        return new \DateInterval($data);
    }
}
