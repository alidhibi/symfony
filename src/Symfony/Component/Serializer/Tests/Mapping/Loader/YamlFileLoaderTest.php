<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class YamlFileLoaderTest extends TestCase
{
    private \Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader $loader;

    private \Symfony\Component\Serializer\Mapping\ClassMetadata $metadata;

    protected function setUp()
    {
        $this->loader = new YamlFileLoader(__DIR__.'/../../Fixtures/serialization.yml');
        $this->metadata = new ClassMetadata(\Symfony\Component\Serializer\Tests\Fixtures\GroupDummy::class);
    }

    public function testInterface(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Serializer\Mapping\Loader\LoaderInterface::class, $this->loader);
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful(): void
    {
        $this->assertTrue($this->loader->loadClassMetadata($this->metadata));
    }

    public function testLoadClassMetadataReturnsFalseWhenEmpty(): void
    {
        $loader = new YamlFileLoader(__DIR__.'/../../Fixtures/empty-mapping.yml');
        $this->assertFalse($loader->loadClassMetadata($this->metadata));
    }

    public function testLoadClassMetadataReturnsThrowsInvalidMapping(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\MappingException::class);
        $loader = new YamlFileLoader(__DIR__.'/../../Fixtures/invalid-mapping.yml');
        $loader->loadClassMetadata($this->metadata);
    }

    public function testLoadClassMetadata(): void
    {
        $this->loader->loadClassMetadata($this->metadata);

        $this->assertEquals(TestClassMetadataFactory::createXmlCLassMetadata(), $this->metadata);
    }

    public function testMaxDepth(): void
    {
        $classMetadata = new ClassMetadata(\Symfony\Component\Serializer\Tests\Fixtures\MaxDepthDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(2, $attributesMetadata['foo']->getMaxDepth());
        $this->assertEquals(3, $attributesMetadata['bar']->getMaxDepth());
    }
}
