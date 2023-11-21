<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Factory;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadataFactoryTest extends TestCase
{
    public function testInterface(): void
    {
        $classMetadata = new ClassMetadataFactory(new LoaderChain([]));
        $this->assertInstanceOf(\Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface::class, $classMetadata);
    }

    public function testGetMetadataFor(): void
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $classMetadata = $factory->getMetadataFor(\Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class);

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata(true, true), $classMetadata);
    }

    public function testHasMetadataFor(): void
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->assertTrue($factory->hasMetadataFor(\Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class));
        $this->assertTrue($factory->hasMetadataFor(\Symfony\Component\Serializer\Tests\Fixtures\GroupDummyParent::class));
        $this->assertTrue($factory->hasMetadataFor(\Symfony\Component\Serializer\Tests\Fixtures\GroupDummyInterface::class));
        $this->assertFalse($factory->hasMetadataFor('Dunglas\Entity'));
    }

    /**
     * @group legacy
     */
    public function testCacheExists(): void
    {
        $cache = $this->getMockBuilder('Doctrine\Common\Cache\Cache')->getMock();
        $cache
            ->expects($this->once())
            ->method('fetch')
            ->willReturn('foo')
        ;

        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()), $cache);
        $this->assertEquals('foo', $factory->getMetadataFor(\Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class));
    }

    /**
     * @group legacy
     */
    public function testCacheNotExists(): void
    {
        $cache = $this->getMockBuilder('Doctrine\Common\Cache\Cache')->getMock();
        $cache->method('fetch')->willReturn(false);
        $cache->method('save');

        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()), $cache);
        $metadata = $factory->getMetadataFor(\Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class);

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata(true, true), $metadata);
    }
}
