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
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;

class DateTimeToArrayTransformerTest extends TestCase
{
    public function testTransform(): void
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC');

        $input = new \DateTime('2010-02-03 04:05:06 UTC');

        $output = [
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ];

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransformEmpty(): void
    {
        $transformer = new DateTimeToArrayTransformer();

        $output = [
            'year' => '',
            'month' => '',
            'day' => '',
            'hour' => '',
            'minute' => '',
            'second' => '',
        ];

        $this->assertSame($output, $transformer->transform(null));
    }

    public function testTransformEmptyWithFields(): void
    {
        $transformer = new DateTimeToArrayTransformer(null, null, ['year', 'minute', 'second']);

        $output = [
            'year' => '',
            'minute' => '',
            'second' => '',
        ];

        $this->assertSame($output, $transformer->transform(null));
    }

    public function testTransformWithFields(): void
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC', ['year', 'month', 'minute', 'second']);

        $input = new \DateTime('2010-02-03 04:05:06 UTC');

        $output = [
            'year' => '2010',
            'month' => '2',
            'minute' => '5',
            'second' => '6',
        ];

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransformWithPadding(): void
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC', null, true);

        $input = new \DateTime('2010-02-03 04:05:06 UTC');

        $output = [
            'year' => '2010',
            'month' => '02',
            'day' => '03',
            'hour' => '04',
            'minute' => '05',
            'second' => '06',
        ];

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransformDifferentTimezones(): void
    {
        $transformer = new DateTimeToArrayTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = new \DateTime('2010-02-03 04:05:06 America/New_York');

        $dateTime = new \DateTime('2010-02-03 04:05:06 America/New_York');
        $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $output = [
            'year' => (string) (int) $dateTime->format('Y'),
            'month' => (string) (int) $dateTime->format('m'),
            'day' => (string) (int) $dateTime->format('d'),
            'hour' => (string) (int) $dateTime->format('H'),
            'minute' => (string) (int) $dateTime->format('i'),
            'second' => (string) (int) $dateTime->format('s'),
        ];

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransformDateTimeImmutable(): void
    {
        $transformer = new DateTimeToArrayTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = new \DateTimeImmutable('2010-02-03 04:05:06 America/New_York');

        $dateTime = new \DateTimeImmutable('2010-02-03 04:05:06 America/New_York');
        $dateTime = $dateTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $output = [
            'year' => (string) (int) $dateTime->format('Y'),
            'month' => (string) (int) $dateTime->format('m'),
            'day' => (string) (int) $dateTime->format('d'),
            'hour' => (string) (int) $dateTime->format('H'),
            'minute' => (string) (int) $dateTime->format('i'),
            'second' => (string) (int) $dateTime->format('s'),
        ];

        $this->assertSame($output, $transformer->transform($input));
    }

    public function testTransformRequiresDateTime(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform('12345');
    }

    public function testReverseTransform(): void
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC');

        $input = [
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ];

        $output = new \DateTime('2010-02-03 04:05:06 UTC');

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformWithSomeZero(): void
    {
        $transformer = new DateTimeToArrayTransformer('UTC', 'UTC');

        $input = [
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '0',
            'second' => '0',
        ];

        $output = new \DateTime('2010-02-03 04:00:00 UTC');

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmpty(): void
    {
        $transformer = new DateTimeToArrayTransformer();

        $input = [
            'year' => '',
            'month' => '',
            'day' => '',
            'hour' => '',
            'minute' => '',
            'second' => '',
        ];

        $this->assertNull($transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmptySubsetOfFields(): void
    {
        $transformer = new DateTimeToArrayTransformer(null, null, ['year', 'month', 'day']);

        $input = [
            'year' => '',
            'month' => '',
            'day' => '',
        ];

        $this->assertNull($transformer->reverseTransform($input));
    }

    public function testReverseTransformPartiallyEmptyYear(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformPartiallyEmptyMonth(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformPartiallyEmptyDay(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformPartiallyEmptyHour(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformPartiallyEmptyMinute(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'second' => '6',
        ]);
    }

    public function testReverseTransformPartiallyEmptySecond(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
        ]);
    }

    public function testReverseTransformNull(): void
    {
        $transformer = new DateTimeToArrayTransformer();

        $this->assertNull($transformer->reverseTransform(null));
    }

    public function testReverseTransformDifferentTimezones(): void
    {
        $transformer = new DateTimeToArrayTransformer('America/New_York', 'Asia/Hong_Kong');

        $input = [
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ];

        $output = new \DateTime('2010-02-03 04:05:06 Asia/Hong_Kong');
        $output->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformToDifferentTimezone(): void
    {
        $transformer = new DateTimeToArrayTransformer('Asia/Hong_Kong', 'UTC');

        $input = [
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ];

        $output = new \DateTime('2010-02-03 04:05:06 UTC');
        $output->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformRequiresArray(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform('12345');
    }

    public function testReverseTransformWithNegativeYear(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '-1',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithNegativeMonth(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '-1',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithNegativeDay(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '-1',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithNegativeHour(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '-1',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithNegativeMinute(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '-1',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithNegativeSecond(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '-1',
        ]);
    }

    public function testReverseTransformWithInvalidMonth(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '13',
            'day' => '3',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithInvalidDay(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithStringDay(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => 'bazinga',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithStringMonth(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => 'bazinga',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithStringYear(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => 'bazinga',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithEmptyStringHour(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '',
            'minute' => '5',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithEmptyStringMinute(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '',
            'second' => '6',
        ]);
    }

    public function testReverseTransformWithEmptyStringSecond(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);
        $transformer = new DateTimeToArrayTransformer();
        $transformer->reverseTransform([
            'year' => '2010',
            'month' => '2',
            'day' => '31',
            'hour' => '4',
            'minute' => '5',
            'second' => '',
        ]);
    }
}
