<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

class CollectionValidatorArrayObjectTest extends CollectionValidatorTest
{
    protected function prepareTestData(array $contents): \ArrayObject
    {
        return new \ArrayObject($contents);
    }
}
