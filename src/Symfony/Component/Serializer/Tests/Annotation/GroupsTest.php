<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class GroupsTest extends TestCase
{
    public function testEmptyGroupsParameter(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);
        new Groups(['value' => []]);
    }

    public function testNotAnArrayGroupsParameter(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);
        new Groups(['value' => 12]);
    }

    public function testInvalidGroupsParameter(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);
        new Groups(['value' => ['a', 1, new \stdClass()]]);
    }

    public function testGroupsParameters(): void
    {
        $validData = ['a', 'b'];

        $groups = new Groups(['value' => $validData]);
        $this->assertEquals($validData, $groups->getGroups());
    }

    public function testSingleGroup(): void
    {
        $groups = new Groups(['value' => 'a']);
        $this->assertEquals(['a'], $groups->getGroups());
    }
}
