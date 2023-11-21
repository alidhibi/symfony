<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Argument;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;

class RewindableGeneratorTest extends TestCase
{
    public function testImplementsCountable(): void
    {
        $this->assertInstanceOf(\Countable::class, new RewindableGenerator(static function () : \Generator {
            yield 1;
        }, 1));
    }

    public function testCountUsesProvidedValue(): void
    {
        $generator = new RewindableGenerator(static function () : \Generator {
            yield 1;
        }, 3);

        $this->assertCount(3, $generator);
    }

    public function testCountUsesProvidedValueAsCallback(): void
    {
        $called = 0;
        $generator = new RewindableGenerator(static function () : \Generator {
            yield 1;
        }, static function () use (&$called) : int {
            ++$called;
            return 3;
        });

        $this->assertSame(0, $called, 'Count callback is called lazily');
        $this->assertCount(3, $generator);

        \count($generator);

        $this->assertSame(1, $called, 'Count callback is called only once');
    }
}
