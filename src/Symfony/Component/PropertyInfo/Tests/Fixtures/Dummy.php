<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Dummy extends ParentDummy
{
    /**
     * Should be used.
     *
     * @var int Should be ignored
     */
    protected $baz;

    /**
     * @var \DateTime
     */
    public $bal;

    /**
     * @var ParentDummy
     */
    public $parent;

    /**
     * @var \DateTime[]
     * @Groups({"a", "b"})
     */
    public $collection = [];

    /**
     * @var string[][]
     */
    public $nestedCollection = [];

    /**
     * @var mixed[]
     */
    public $mixedCollection = [];

    /**
     * @var ParentDummy
     */
    public $B;

    /**
     * @var int
     */
    protected $Id;

    /**
     * @var string
     */
    public $Guid;

    /**
     * Nullable array.
     *
     * @var array|null
     */
    public $g;

    /**
     * @var ?string
     */
    public $h;

    /**
     * @var ?string|int
     */
    public $i;

    /**
     * @var ?\DateTime
     */
    public $j;

    /**
     * This should not be removed.
     *
     * @var
     */
    public $emptyVar;

    public static function getStatic(): void
    {
    }

    /**
     * @return string
     */
    public static function staticGetter(): void
    {
    }

    public static function staticSetter(\DateTime $d): void
    {
    }

    /**
     * A.
     *
     * @return int
     */
    public function getA(): void
    {
    }

    /**
     * B.
     *
     * @param ParentDummy|null $parent
     */
    public function setB(ParentDummy $parent = null): void
    {
    }

    /**
     * Date of Birth.
     *
     * @return \DateTime
     */
    public function getDOB(): void
    {
    }

    /**
     * @return int
     */
    public function getId(): void
    {
    }

    public function get123(): void
    {
    }

    public function setSelf(self $self): void
    {
    }

    public function setRealParent(parent $realParent): void
    {
    }

    /**
     * @return array
     */
    public function getXTotals(): void
    {
    }

    /**
     * @return string
     */
    public function getYT(): void
    {
    }

    public function setDate(\DateTime $date): void
    {
    }

    public function addDate(\DateTime $date): void
    {
    }
}
