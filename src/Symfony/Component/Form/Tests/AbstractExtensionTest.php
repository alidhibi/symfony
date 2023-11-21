<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\Tests\Fixtures\FooType;

class AbstractExtensionTest extends TestCase
{
    public function testHasType(): void
    {
        $loader = new ConcreteExtension();
        $this->assertTrue($loader->hasType(\Symfony\Component\Form\Tests\Fixtures\FooType::class));
        $this->assertFalse($loader->hasType('foo'));
    }

    public function testGetType(): void
    {
        $loader = new ConcreteExtension();
        $this->assertInstanceOf(\Symfony\Component\Form\Tests\Fixtures\FooType::class, $loader->getType(\Symfony\Component\Form\Tests\Fixtures\FooType::class));
    }
}

class ConcreteExtension extends AbstractExtension
{
    protected function loadTypes(): array
    {
        return [new FooType()];
    }

    protected function loadTypeGuesser()
    {
    }
}
