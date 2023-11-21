<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Node;

/**
 * Represents a node specificity.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @see http://www.w3.org/TR/selectors/#specificity
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Specificity
{
    final const A_FACTOR = 100;

    final const B_FACTOR = 10;

    final const C_FACTOR = 1;

    private $a;

    private $b;

    private $c;

    /**
     * @param int $a
     * @param int $b
     * @param int $c
     */
    public function __construct($a, $b, $c)
    {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
    }

    public function plus(self $specificity): self
    {
        return new self($this->a + $specificity->a, $this->b + $specificity->b, $this->c + $specificity->c);
    }

    /**
     * Returns global specificity value.
     *
     */
    public function getValue(): int|float
    {
        return $this->a * self::A_FACTOR + $this->b * self::B_FACTOR + $this->c * self::C_FACTOR;
    }

    /**
     * Returns -1 if the object specificity is lower than the argument,
     * 0 if they are equal, and 1 if the argument is lower.
     *
     */
    public function compareTo(self $specificity): int
    {
        if ($this->a !== $specificity->a) {
            return $this->a > $specificity->a ? 1 : -1;
        }

        if ($this->b !== $specificity->b) {
            return $this->b > $specificity->b ? 1 : -1;
        }

        if ($this->c !== $specificity->c) {
            return $this->c > $specificity->c ? 1 : -1;
        }

        return 0;
    }
}
