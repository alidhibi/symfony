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
class PropertySiblingHolder
{
    /**
     * @var \Symfony\Component\Serializer\Tests\Fixtures\PropertySibling
     */
    public $sibling0;

    /**
     * @var \Symfony\Component\Serializer\Tests\Fixtures\PropertySibling
     */
    public $sibling1;

    /**
     * @var \Symfony\Component\Serializer\Tests\Fixtures\PropertySibling
     */
    public $sibling2;

    public function __construct()
    {
        $sibling = new PropertySibling();

        $this->sibling0 = $sibling;
        $this->sibling1 = $sibling;
        $this->sibling2 = $sibling;
    }
}

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertySibling
{
    public $coopTilleuls = 'Les-Tilleuls.coop';
}
