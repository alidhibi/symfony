<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RegexTest extends TestCase
{
    public function testConstraintGetDefaultOption(): void
    {
        $constraint = new Regex('/^\d+$/');

        $this->assertSame('/^\d+$/', $constraint->pattern);
    }

    public function provideHtmlPatterns(): array
    {
        return [
            // HTML5 wraps the pattern in ^(?:pattern)$
            ['/^\d+$/', '[0-9]+'],
            ['/\d+$/', '.*[0-9]+'],
            ['/^\d+/', '[0-9]+.*'],
            ['/\d+/', '.*[0-9]+.*'],
            // We need a smart way to allow matching of patterns that contain
            // ^ and $ at various sub-clauses of an or-clause
            // .*(pattern).* seems to work correctly
            ['/\d$|[a-z]+/', '.*([0-9]$|[a-z]+).*'],
            ['/\d$|^[a-z]+/', '.*([0-9]$|^[a-z]+).*'],
            ['/^\d|[a-z]+$/', '.*(^[0-9]|[a-z]+$).*'],
            // Unescape escaped delimiters
            ['/^\d+\/$/', '[0-9]+/'],
            ['#^\d+\#$#', '[0-9]+#'],
            // Cannot be converted
            ['/^[0-9]+$/i', null],

            // Inverse matches are simple, just wrap in
            // ((?!pattern).)*
            ['/^\d+$/', '((?!^[0-9]+$).)*', false],
            ['/\d+$/', '((?![0-9]+$).)*', false],
            ['/^\d+/', '((?!^[0-9]+).)*', false],
            ['/\d+/', '((?![0-9]+).)*', false],
            ['/\d$|[a-z]+/', '((?![0-9]$|[a-z]+).)*', false],
            ['/\d$|^[a-z]+/', '((?![0-9]$|^[a-z]+).)*', false],
            ['/^\d|[a-z]+$/', '((?!^[0-9]|[a-z]+$).)*', false],
            ['/^\d+\/$/', '((?!^[0-9]+/$).)*', false],
            ['#^\d+\#$#', '((?!^[0-9]+#$).)*', false],
            ['/^[0-9]+$/i', null, false],
        ];
    }

    /**
     * @dataProvider provideHtmlPatterns
     */
    public function testGetHtmlPattern(string $pattern, ?string $htmlPattern, bool $match = true): void
    {
        $constraint = new Regex([
            'pattern' => $pattern,
            'match' => $match,
        ]);

        $this->assertSame($pattern, $constraint->pattern);
        $this->assertSame($htmlPattern, $constraint->getHtmlPattern());
    }

    public function testGetCustomHtmlPattern(): void
    {
        $constraint = new Regex([
            'pattern' => '((?![0-9]$|[a-z]+).)*',
            'htmlPattern' => 'foobar',
        ]);

        $this->assertSame('((?![0-9]$|[a-z]+).)*', $constraint->pattern);
        $this->assertSame('foobar', $constraint->getHtmlPattern());
    }
}
