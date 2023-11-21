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
 * CSS selector token.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Token
{
    final const TYPE_FILE_END = 'eof';

    final const TYPE_DELIMITER = 'delimiter';

    final const TYPE_WHITESPACE = 'whitespace';

    final const TYPE_IDENTIFIER = 'identifier';

    final const TYPE_HASH = 'hash';

    final const TYPE_NUMBER = 'number';

    final const TYPE_STRING = 'string';

    private $type;

    private $value;

    private $position;

    /**
     * @param int    $type
     * @param string $value
     * @param int    $position
     */
    public function __construct($type, $value, $position)
    {
        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    public function isFileEnd(): bool
    {
        return self::TYPE_FILE_END === $this->type;
    }

    public function isDelimiter(array $values = []): bool
    {
        if (self::TYPE_DELIMITER !== $this->type) {
            return false;
        }

        if ($values === []) {
            return true;
        }

        return \in_array($this->value, $values);
    }

    public function isWhitespace(): bool
    {
        return self::TYPE_WHITESPACE === $this->type;
    }

    public function isIdentifier(): bool
    {
        return self::TYPE_IDENTIFIER === $this->type;
    }

    public function isHash(): bool
    {
        return self::TYPE_HASH === $this->type;
    }

    public function isNumber(): bool
    {
        return self::TYPE_NUMBER === $this->type;
    }

    public function isString(): bool
    {
        return self::TYPE_STRING === $this->type;
    }

    public function __toString(): string
    {
        if ($this->value !== '' && $this->value !== '0') {
            return sprintf('<%s "%s" at %s>', $this->type, $this->value, $this->position);
        }

        return sprintf('<%s at %s>', $this->type, $this->position);
    }
}
