<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\ScalarDummy;

class CustomNormalizerTest extends TestCase
{
    private \Symfony\Component\Serializer\Normalizer\CustomNormalizer $normalizer;

    protected function setUp()
    {
        $this->normalizer = new CustomNormalizer();
        $this->normalizer->setSerializer(new Serializer());
    }

    public function testInterface(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class, $this->normalizer);
        $this->assertInstanceOf(\Symfony\Component\Serializer\Normalizer\DenormalizerInterface::class, $this->normalizer);
        $this->assertInstanceOf(\Symfony\Component\Serializer\SerializerAwareInterface::class, $this->normalizer);
    }

    public function testSerialize(): void
    {
        $obj = new ScalarDummy();
        $obj->foo = 'foo';
        $obj->xmlFoo = 'xml';
        $this->assertEquals('foo', $this->normalizer->normalize($obj, 'json'));
        $this->assertEquals('xml', $this->normalizer->normalize($obj, 'xml'));
    }

    public function testDeserialize(): void
    {
        $obj = $this->normalizer->denormalize('foo', \get_class(new ScalarDummy()), 'xml');
        $this->assertEquals('foo', $obj->xmlFoo);
        $this->assertNull($obj->foo);

        $obj = $this->normalizer->denormalize('foo', \get_class(new ScalarDummy()), 'json');
        $this->assertEquals('foo', $obj->foo);
        $this->assertNull($obj->xmlFoo);
    }

    public function testDenormalizeWithObjectToPopulateUsesProvidedObject(): void
    {
        $expected = new ScalarDummy();
        $obj = $this->normalizer->denormalize('foo', ScalarDummy::class, 'json', [
            'object_to_populate' => $expected,
        ]);

        $this->assertSame($expected, $obj);
        $this->assertEquals('foo', $obj->foo);
        $this->assertNull($obj->xmlFoo);
    }

    public function testSupportsNormalization(): void
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new ScalarDummy()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testSupportsDenormalization(): void
    {
        $this->assertTrue($this->normalizer->supportsDenormalization([], \Symfony\Component\Serializer\Tests\Fixtures\ScalarDummy::class));
        $this->assertFalse($this->normalizer->supportsDenormalization([], 'stdClass'));
        $this->assertTrue($this->normalizer->supportsDenormalization([], \Symfony\Component\Serializer\Tests\Fixtures\DenormalizableDummy::class));
    }
}
