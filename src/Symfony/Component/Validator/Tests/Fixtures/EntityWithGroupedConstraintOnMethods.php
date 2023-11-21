<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

class EntityWithGroupedConstraintOnMethods
{
    public $bar;

    public function isValidInFoo(): bool
    {
        return false;
    }

    public function getBar(): never
    {
        throw new \Exception('Should not be called');
    }
}
