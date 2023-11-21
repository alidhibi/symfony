<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class MaxDepthTest extends TestCase
{
    public function testNotSetMaxDepthParameter(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter of annotation "Symfony\Component\Serializer\Annotation\MaxDepth" should be set.');
        new MaxDepth([]);
    }

    public function provideInvalidValues(): array
    {
        return [
            [''],
            ['foo'],
            ['1'],
            [0],
        ];
    }

    /**
     * @dataProvider provideInvalidValues
     */
    public function testNotAnIntMaxDepthParameter(string|int $value): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter of annotation "Symfony\Component\Serializer\Annotation\MaxDepth" must be a positive integer.');
        new MaxDepth(['value' => $value]);
    }

    public function testMaxDepthParameters(): void
    {
        $maxDepth = new MaxDepth(['value' => 3]);
        $this->assertEquals(3, $maxDepth->getMaxDepth());
    }
}
