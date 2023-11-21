<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TypeHinted
{
    private ?\DateTime $date = null;

    private ?\Countable $countable = null;

    public function setDate(\DateTime $date): void
    {
        $this->date = $date;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    /**
     * @return \Countable
     */
    public function getCountable(): ?\Countable
    {
        return $this->countable;
    }

    public function setCountable(\Countable $countable): void
    {
        $this->countable = $countable;
    }
}
