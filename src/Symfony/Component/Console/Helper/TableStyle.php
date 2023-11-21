<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;

/**
 * Defines the styles for a Table.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Саша Стаменковић <umpirsky@gmail.com>
 */
class TableStyle
{
    private string $paddingChar = ' ';

    private string $horizontalBorderChar = '-';

    private string $verticalBorderChar = '|';

    private string $crossingChar = '+';

    private string $cellHeaderFormat = '<info>%s</info>';

    private string $cellRowFormat = '%s';

    private string $cellRowContentFormat = ' %s ';

    private string $borderFormat = '%s';

    private int $padType = \STR_PAD_RIGHT;

    /**
     * Sets padding character, used for cell padding.
     *
     * @param string $paddingChar
     *
     * @return $this
     */
    public function setPaddingChar($paddingChar): static
    {
        if ($paddingChar === '' || $paddingChar === '0') {
            throw new LogicException('The padding char must not be empty.');
        }

        $this->paddingChar = $paddingChar;

        return $this;
    }

    /**
     * Gets padding character, used for cell padding.
     *
     */
    public function getPaddingChar(): string
    {
        return $this->paddingChar;
    }

    /**
     * Sets horizontal border character.
     *
     *
     * @return $this
     */
    public function setHorizontalBorderChar(string $horizontalBorderChar): static
    {
        $this->horizontalBorderChar = $horizontalBorderChar;

        return $this;
    }

    /**
     * Gets horizontal border character.
     *
     */
    public function getHorizontalBorderChar(): string
    {
        return $this->horizontalBorderChar;
    }

    /**
     * Sets vertical border character.
     *
     *
     * @return $this
     */
    public function setVerticalBorderChar(string $verticalBorderChar): static
    {
        $this->verticalBorderChar = $verticalBorderChar;

        return $this;
    }

    /**
     * Gets vertical border character.
     *
     */
    public function getVerticalBorderChar(): string
    {
        return $this->verticalBorderChar;
    }

    /**
     * Sets crossing character.
     *
     *
     * @return $this
     */
    public function setCrossingChar(string $crossingChar): static
    {
        $this->crossingChar = $crossingChar;

        return $this;
    }

    /**
     * Gets crossing character.
     *
     */
    public function getCrossingChar(): string
    {
        return $this->crossingChar;
    }

    /**
     * Sets header cell format.
     *
     *
     * @return $this
     */
    public function setCellHeaderFormat(string $cellHeaderFormat): static
    {
        $this->cellHeaderFormat = $cellHeaderFormat;

        return $this;
    }

    /**
     * Gets header cell format.
     *
     */
    public function getCellHeaderFormat(): string
    {
        return $this->cellHeaderFormat;
    }

    /**
     * Sets row cell format.
     *
     *
     * @return $this
     */
    public function setCellRowFormat(string $cellRowFormat): static
    {
        $this->cellRowFormat = $cellRowFormat;

        return $this;
    }

    /**
     * Gets row cell format.
     *
     */
    public function getCellRowFormat(): string
    {
        return $this->cellRowFormat;
    }

    /**
     * Sets row cell content format.
     *
     *
     * @return $this
     */
    public function setCellRowContentFormat(string $cellRowContentFormat): static
    {
        $this->cellRowContentFormat = $cellRowContentFormat;

        return $this;
    }

    /**
     * Gets row cell content format.
     *
     */
    public function getCellRowContentFormat(): string
    {
        return $this->cellRowContentFormat;
    }

    /**
     * Sets table border format.
     *
     *
     * @return $this
     */
    public function setBorderFormat(string $borderFormat): static
    {
        $this->borderFormat = $borderFormat;

        return $this;
    }

    /**
     * Gets table border format.
     *
     */
    public function getBorderFormat(): string
    {
        return $this->borderFormat;
    }

    /**
     * Sets cell padding type.
     *
     * @param int $padType STR_PAD_*
     *
     * @return $this
     */
    public function setPadType($padType): static
    {
        if (!\in_array($padType, [\STR_PAD_LEFT, \STR_PAD_RIGHT, \STR_PAD_BOTH], true)) {
            throw new InvalidArgumentException('Invalid padding type. Expected one of (STR_PAD_LEFT, STR_PAD_RIGHT, STR_PAD_BOTH).');
        }

        $this->padType = $padType;

        return $this;
    }

    /**
     * Gets cell padding type.
     *
     */
    public function getPadType(): int
    {
        return $this->padType;
    }
}
