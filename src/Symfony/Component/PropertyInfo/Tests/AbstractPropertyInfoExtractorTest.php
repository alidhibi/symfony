<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\NullExtractor;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AbstractPropertyInfoExtractorTest extends TestCase
{
    /**
     * @var PropertyInfoExtractor
     */
    protected $propertyInfo;

    protected function setUp()
    {
        $extractors = [new NullExtractor(), new DummyExtractor()];
        $this->propertyInfo = new PropertyInfoExtractor($extractors, $extractors, $extractors, $extractors);
    }

    public function testInstanceOf(): void
    {
        $this->assertInstanceOf(\Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface::class, $this->propertyInfo);
        $this->assertInstanceOf(\Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface::class, $this->propertyInfo);
        $this->assertInstanceOf(\Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface::class, $this->propertyInfo);
        $this->assertInstanceOf(\Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface::class, $this->propertyInfo);
    }

    public function testGetShortDescription(): void
    {
        $this->assertSame('short', $this->propertyInfo->getShortDescription('Foo', 'bar', []));
    }

    public function testGetLongDescription(): void
    {
        $this->assertSame('long', $this->propertyInfo->getLongDescription('Foo', 'bar', []));
    }

    public function testGetTypes(): void
    {
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_INT)], $this->propertyInfo->getTypes('Foo', 'bar', []));
    }

    public function testIsReadable(): void
    {
        $this->assertTrue($this->propertyInfo->isReadable('Foo', 'bar', []));
    }

    public function testIsWritable(): void
    {
        $this->assertTrue($this->propertyInfo->isWritable('Foo', 'bar', []));
    }

    public function testGetProperties(): void
    {
        $this->assertEquals(['a', 'b'], $this->propertyInfo->getProperties('Foo'));
    }
}
