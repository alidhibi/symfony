<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Exception;

use Symfony\Component\CssSelector\Parser\Token;

/**
 * ParseException is thrown when a CSS selector syntax is not valid.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class SyntaxErrorException extends ParseException
{
    /**
     * @param string $expectedValue
     *
     */
    public static function unexpectedToken($expectedValue, Token $foundToken): self
    {
        return new self(sprintf('Expected %s, but %s found.', $expectedValue, $foundToken));
    }

    /**
     * @param string $pseudoElement
     * @param string $unexpectedLocation
     *
     */
    public static function pseudoElementFound($pseudoElement, $unexpectedLocation): self
    {
        return new self(sprintf('Unexpected pseudo-element "::%s" found %s.', $pseudoElement, $unexpectedLocation));
    }

    /**
     * @param int $position
     *
     */
    public static function unclosedString($position): self
    {
        return new self(sprintf('Unclosed/invalid string at %s.', $position));
    }

    public static function nestedNot(): self
    {
        return new self('Got nested ::not().');
    }

    public static function stringAsFunctionArgument(): self
    {
        return new self('String not allowed as function argument.');
    }
}
