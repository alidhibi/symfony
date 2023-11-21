<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class SiblingHolder
{
    private readonly \Symfony\Component\Serializer\Tests\Fixtures\Sibling $sibling0;

    private readonly \Symfony\Component\Serializer\Tests\Fixtures\Sibling $sibling1;

    private readonly \Symfony\Component\Serializer\Tests\Fixtures\Sibling $sibling2;

    public function __construct()
    {
        $sibling = new Sibling();

        $this->sibling0 = $sibling;
        $this->sibling1 = $sibling;
        $this->sibling2 = $sibling;
    }

    public function getSibling0(): \Symfony\Component\Serializer\Tests\Fixtures\Sibling
    {
        return $this->sibling0;
    }

    public function getSibling1(): \Symfony\Component\Serializer\Tests\Fixtures\Sibling
    {
        return $this->sibling1;
    }

    public function getSibling2(): \Symfony\Component\Serializer\Tests\Fixtures\Sibling
    {
        return $this->sibling2;
    }
}

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Sibling
{
    public function getCoopTilleuls(): string
    {
        return 'Les-Tilleuls.coop';
    }
}
