<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Collator;

use Symfony\Component\Intl\Collator\Collator;
use Symfony\Component\Intl\Globals\IntlGlobals;

class CollatorTest extends AbstractCollatorTest
{
    public function testConstructorWithUnsupportedLocale(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException::class);
        new Collator('pt_BR');
    }

    public function testCompare(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->compare('a', 'b');
    }

    public function testGetAttribute(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->getAttribute(Collator::NUMERIC_COLLATION);
    }

    public function testGetErrorCode(): void
    {
        $collator = $this->getCollator('en');
        $this->assertEquals(IntlGlobals::U_ZERO_ERROR, $collator->getErrorCode());
    }

    public function testGetErrorMessage(): void
    {
        $collator = $this->getCollator('en');
        $this->assertEquals('U_ZERO_ERROR', $collator->getErrorMessage());
    }

    public function testGetLocale(): void
    {
        $collator = $this->getCollator('en');
        $this->assertEquals('en', $collator->getLocale());
    }

    public function testConstructWithoutLocale(): void
    {
        $collator = $this->getCollator(null);
        $this->assertInstanceOf('\\' . \Symfony\Component\Intl\Collator\Collator::class, $collator);
    }

    public function testGetSortKey(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->getSortKey('Hello');
    }

    public function testGetStrength(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->getStrength();
    }

    public function testSetAttribute(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
    }

    public function testSetStrength(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);
        $collator = $this->getCollator('en');
        $collator->setStrength(Collator::PRIMARY);
    }

    public function testStaticCreate(): void
    {
        $collator = Collator::create('en');
        $this->assertInstanceOf('\\' . \Symfony\Component\Intl\Collator\Collator::class, $collator);
    }

    protected function getCollator($locale): \Symfony\Component\Intl\Collator\Collator
    {
        return new Collator($locale);
    }
}
