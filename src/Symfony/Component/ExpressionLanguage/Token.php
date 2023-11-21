<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage;

/**
 * Represents a Token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Token
{
    public $value;

    public $type;

    public $cursor;

    final const EOF_TYPE = 'end of expression';

    final const NAME_TYPE = 'name';

    final const NUMBER_TYPE = 'number';

    final const STRING_TYPE = 'string';

    final const OPERATOR_TYPE = 'operator';

    final const PUNCTUATION_TYPE = 'punctuation';

    /**
     * @param string                $type   The type of the token (self::*_TYPE)
     * @param string|int|float|null $value  The token value
     * @param int                   $cursor The cursor position in the source
     */
    public function __construct($type, $value, $cursor)
    {
        $this->type = $type;
        $this->value = $value;
        $this->cursor = $cursor;
    }

    /**
     * Returns a string representation of the token.
     *
     * @return string A string representation of the token
     */
    public function __toString(): string
    {
        return sprintf('%3d %-11s %s', $this->cursor, strtoupper($this->type), $this->value);
    }

    /**
     * Tests the current token for a type and/or a value.
     *
     * @param string      $type  The type to test
     * @param string|null $value The token value
     *
     */
    public function test($type, $value = null): bool
    {
        return $this->type === $type && (null === $value || $this->value == $value);
    }
}
