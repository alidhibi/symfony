<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Parser;

/**
 * CSS selector reader.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Reader
{
    private $source;

    private int $length;

    private int $position = 0;

    /**
     * @param string $source
     */
    public function __construct($source)
    {
        $this->source = $source;
        $this->length = \strlen($source);
    }

    public function isEOF(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    public function getRemainingLength(): int|float
    {
        return $this->length - $this->position;
    }

    /**
     * @param int $length
     * @param int $offset
     *
     */
    public function getSubstring($length, $offset = 0): string
    {
        return substr($this->source, $this->position + $offset, $length);
    }

    /**
     * @param string $string
     *
     */
    public function getOffset($string): int|float|bool
    {
        $position = strpos($this->source, $string, $this->position);

        return false === $position ? false : $position - $this->position;
    }

    /**
     * @param string $pattern
     *
     * @return array|false
     */
    public function findPattern($pattern)
    {
        $source = substr($this->source, $this->position);

        if (preg_match($pattern, $source, $matches)) {
            return $matches;
        }

        return false;
    }

    /**
     * @param int $length
     */
    public function moveForward($length): void
    {
        $this->position += $length;
    }

    public function moveToEnd(): void
    {
        $this->position = $this->length;
    }
}
