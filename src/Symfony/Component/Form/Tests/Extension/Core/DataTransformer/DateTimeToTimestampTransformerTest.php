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
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;

class DateTimeToTimestampTransformerTest extends TestCase
{
    public function testTransform(): void
    {
        $transformer = new DateTimeToTimestampTransformer('UTC', 'UTC');

        $input = new \DateTime('2010-02-03 04:05:06 UTC');
        $output = $input->format('U');

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformEmpty(): void
    {
        $transformer = new DateTimeToTimestampTransformer();

        $this->assertNull($transformer->transform(null));
    }

    public function testTransformWithDifferentTimezones(): void
    {
        $transformer = new DateTimeToTimestampTransformer('Asia/Hong_Kong', 'America/New_York');

        $input = new \DateTime('2010-02-03 04:05:06 America/New_York');
        $output = $input->format('U');
        $input->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformFromDifferentTimezone(): void
    {
        $transformer = new DateTimeToTimestampTransformer('Asia/Hong_Kong', 'UTC');

        $input = new \DateTime('2010-02-03 04:05:06 Asia/Hong_Kong');

        $dateTime = clone $input;
        $dateTime->setTimezone(new \DateTimeZone('UTC'));

        $output = $dateTime->format('U');

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformDateTimeImmutable(): void
    {
        $transformer = new DateTimeToTimestampTransformer('Asia/Hong_Kong', 'America/New_York');

        $input = new \DateTimeImmutable('2010-02-03 04:05:06 America/New_York');
        $output = $input->format('U');
        $input = $input->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformExpectsDateTime(): void
    {
        $transformer = new DateTimeToTimestampTransformer();

        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);

        $transformer->transform('1234');
    }

    public function testReverseTransform(): void
    {
        $reverseTransformer = new DateTimeToTimestampTransformer('UTC', 'UTC');

        $output = new \DateTime('2010-02-03 04:05:06 UTC');
        $input = $output->format('U');

        $this->assertEquals($output, $reverseTransformer->reverseTransform($input));
    }

    public function testReverseTransformEmpty(): void
    {
        $reverseTransformer = new DateTimeToTimestampTransformer();

        $this->assertNull($reverseTransformer->reverseTransform(null));
    }

    public function testReverseTransformWithDifferentTimezones(): void
    {
        $reverseTransformer = new DateTimeToTimestampTransformer('Asia/Hong_Kong', 'America/New_York');

        $output = new \DateTime('2010-02-03 04:05:06 America/New_York');
        $input = $output->format('U');
        $output->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $this->assertEquals($output, $reverseTransformer->reverseTransform($input));
    }

    public function testReverseTransformExpectsValidTimestamp(): void
    {
        $reverseTransformer = new DateTimeToTimestampTransformer();

        $this->expectException(\Symfony\Component\Form\Exception\TransformationFailedException::class);

        $reverseTransformer->reverseTransform('2010-2010-2010');
    }
}
