<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\NormalizableTraversableDummy;
use Symfony\Component\Serializer\Tests\Fixtures\TraversableDummy;
use Symfony\Component\Serializer\Tests\Normalizer\TestDenormalizer;
use Symfony\Component\Serializer\Tests\Normalizer\TestNormalizer;

class SerializerTest extends TestCase
{
    public function testInterface(): void
    {
        $serializer = new Serializer();

        $this->assertInstanceOf(\Symfony\Component\Serializer\SerializerInterface::class, $serializer);
        $this->assertInstanceOf(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class, $serializer);
        $this->assertInstanceOf(\Symfony\Component\Serializer\Normalizer\DenormalizerInterface::class, $serializer);
        $this->assertInstanceOf(\Symfony\Component\Serializer\Encoder\EncoderInterface::class, $serializer);
        $this->assertInstanceOf(\Symfony\Component\Serializer\Encoder\DecoderInterface::class, $serializer);
    }

    public function testNormalizeNoMatch(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([$this->getMockBuilder(\Symfony\Component\Serializer\Normalizer\CustomNormalizer::class)->getMock()]);
        $serializer->normalize(new \stdClass(), 'xml');
    }

    public function testNormalizeTraversable(): void
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $result = $serializer->serialize(new TraversableDummy(), 'json');
        $this->assertEquals('{"foo":"foo","bar":"bar"}', $result);
    }

    public function testNormalizeGivesPriorityToInterfaceOverTraversable(): void
    {
        $serializer = new Serializer([new CustomNormalizer()], ['json' => new JsonEncoder()]);
        $result = $serializer->serialize(new NormalizableTraversableDummy(), 'json');
        $this->assertEquals('{"foo":"normalizedFoo","bar":"normalizedBar"}', $result);
    }

    public function testNormalizeOnDenormalizer(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([new TestDenormalizer()], []);
        $this->assertTrue($serializer->normalize(new \stdClass(), 'json'));
    }

    public function testDenormalizeNoMatch(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([$this->getMockBuilder(\Symfony\Component\Serializer\Normalizer\CustomNormalizer::class)->getMock()]);
        $serializer->denormalize('foo', 'stdClass');
    }

    public function testDenormalizeOnNormalizer(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([new TestNormalizer()], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $this->assertTrue($serializer->denormalize(json_encode($data), 'stdClass', 'json'));
    }

    public function testCustomNormalizerCanNormalizeCollectionsAndScalar(): void
    {
        $serializer = new Serializer([new TestNormalizer()], []);
        $this->assertNull($serializer->normalize(['a', 'b']));
        $this->assertNull($serializer->normalize(new \ArrayObject(['c', 'd'])));
        $this->assertNull($serializer->normalize([]));
        $this->assertNull($serializer->normalize('test'));
    }

    public function testNormalizeWithSupportOnData(): void
    {
        $normalizer1 = $this->getMockBuilder(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class)->getMock();
        $normalizer1->method('supportsNormalization')
            ->willReturnCallback(static fn($data, $format): bool => isset($data->test));
        $normalizer1->method('normalize')->willReturn('test1');

        $normalizer2 = $this->getMockBuilder(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class)->getMock();
        $normalizer2->method('supportsNormalization')
            ->willReturn(true);
        $normalizer2->method('normalize')->willReturn('test2');

        $serializer = new Serializer([$normalizer1, $normalizer2]);

        $data = new \stdClass();
        $data->test = true;
        $this->assertEquals('test1', $serializer->normalize($data));

        $this->assertEquals('test2', $serializer->normalize(new \stdClass()));
    }

    public function testDenormalizeWithSupportOnData(): void
    {
        $denormalizer1 = $this->getMockBuilder(\Symfony\Component\Serializer\Normalizer\DenormalizerInterface::class)->getMock();
        $denormalizer1->method('supportsDenormalization')
            ->willReturnCallback(static fn($data, $type, $format): bool => isset($data['test1']));
        $denormalizer1->method('denormalize')->willReturn('test1');

        $denormalizer2 = $this->getMockBuilder(\Symfony\Component\Serializer\Normalizer\DenormalizerInterface::class)->getMock();
        $denormalizer2->method('supportsDenormalization')
            ->willReturn(true);
        $denormalizer2->method('denormalize')->willReturn('test2');

        $serializer = new Serializer([$denormalizer1, $denormalizer2]);

        $this->assertEquals('test1', $serializer->denormalize(['test1' => true], 'test'));

        $this->assertEquals('test2', $serializer->denormalize([], 'test'));
    }

    public function testSerialize(): void
    {
        $serializer = new Serializer([new GetSetMethodNormalizer()], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $result = $serializer->serialize(Model::fromArray($data), 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testSerializeScalar(): void
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $result = $serializer->serialize('foo', 'json');
        $this->assertEquals('"foo"', $result);
    }

    public function testSerializeArrayOfScalars(): void
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['foo', [5, 3]];
        $result = $serializer->serialize($data, 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testSerializeNoEncoder(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->serialize($data, 'json');
    }

    public function testSerializeNoNormalizer(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\LogicException::class);
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->serialize(Model::fromArray($data), 'json');
    }

    public function testDeserialize(): void
    {
        $serializer = new Serializer([new GetSetMethodNormalizer()], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $result = $serializer->deserialize(json_encode($data), '\\' . \Symfony\Component\Serializer\Tests\Model::class, 'json');
        $this->assertEquals($data, $result->toArray());
    }

    public function testDeserializeUseCache(): void
    {
        $serializer = new Serializer([new GetSetMethodNormalizer()], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->deserialize(json_encode($data), '\\' . \Symfony\Component\Serializer\Tests\Model::class, 'json');
        $data = ['title' => 'bar', 'numbers' => [2, 8]];
        $result = $serializer->deserialize(json_encode($data), '\\' . \Symfony\Component\Serializer\Tests\Model::class, 'json');
        $this->assertEquals($data, $result->toArray());
    }

    public function testDeserializeNoNormalizer(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\LogicException::class);
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->deserialize(json_encode($data), '\\' . \Symfony\Component\Serializer\Tests\Model::class, 'json');
    }

    public function testDeserializeWrongNormalizer(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([new CustomNormalizer()], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->deserialize(json_encode($data), '\\' . \Symfony\Component\Serializer\Tests\Model::class, 'json');
    }

    public function testDeserializeNoEncoder(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->deserialize(json_encode($data), '\\' . \Symfony\Component\Serializer\Tests\Model::class, 'json');
    }

    public function testDeserializeSupported(): void
    {
        $serializer = new Serializer([new GetSetMethodNormalizer()], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $this->assertTrue($serializer->supportsDenormalization(json_encode($data), '\\' . \Symfony\Component\Serializer\Tests\Model::class, 'json'));
    }

    public function testDeserializeNotSupported(): void
    {
        $serializer = new Serializer([new GetSetMethodNormalizer()], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $this->assertFalse($serializer->supportsDenormalization(json_encode($data), 'stdClass', 'json'));
    }

    public function testDeserializeNotSupportedMissing(): void
    {
        $serializer = new Serializer([], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $this->assertFalse($serializer->supportsDenormalization(json_encode($data), '\\' . \Symfony\Component\Serializer\Tests\Model::class, 'json'));
    }

    public function testEncode(): void
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['foo', [5, 3]];
        $result = $serializer->encode($data, 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testDecode(): void
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['foo', [5, 3]];
        $result = $serializer->decode(json_encode($data), 'json');
        $this->assertEquals($data, $result);
    }

    public function testSupportsArrayDeserialization(): void
    {
        $serializer = new Serializer(
            [
                new GetSetMethodNormalizer(),
                new PropertyNormalizer(),
                new ObjectNormalizer(),
                new CustomNormalizer(),
                new ArrayDenormalizer(),
            ],
            [
                'json' => new JsonEncoder(),
            ]
        );

        $this->assertTrue(
            $serializer->supportsDenormalization([], __NAMESPACE__.'\Model[]', 'json')
        );
    }

    public function testDeserializeArray(): void
    {
        $jsonData = '[{"title":"foo","numbers":[5,3]},{"title":"bar","numbers":[2,8]}]';

        $expectedData = [
            Model::fromArray(['title' => 'foo', 'numbers' => [5, 3]]),
            Model::fromArray(['title' => 'bar', 'numbers' => [2, 8]]),
        ];

        $serializer = new Serializer(
            [
                new GetSetMethodNormalizer(),
                new ArrayDenormalizer(),
            ],
            [
                'json' => new JsonEncoder(),
            ]
        );

        $this->assertEquals(
            $expectedData,
            $serializer->deserialize($jsonData, __NAMESPACE__.'\Model[]', 'json')
        );
    }

    public function testNormalizerAware(): void
    {
        $normalizerAware = $this->getMockBuilder(NormalizerAwareInterface::class)->getMock();
        $normalizerAware->expects($this->once())
            ->method('setNormalizer')
            ->with($this->isInstanceOf(NormalizerInterface::class));

        new Serializer([$normalizerAware]);
    }

    public function testDenormalizerAware(): void
    {
        $denormalizerAware = $this->getMockBuilder(DenormalizerAwareInterface::class)->getMock();
        $denormalizerAware->expects($this->once())
            ->method('setDenormalizer')
            ->with($this->isInstanceOf(DenormalizerInterface::class));

        new Serializer([$denormalizerAware]);
    }

    public function testDeserializeObjectConstructorWithObjectTypeHint(): void
    {
        $jsonData = '{"bar":{"value":"baz"}}';

        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $this->assertEquals(new Foo(new Bar('baz')), $serializer->deserialize($jsonData, Foo::class, 'json'));
    }

    public function testNotNormalizableValueExceptionMessageForAResource(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('An unexpected value could not be normalized: stream resource');

        (new Serializer())->normalize(tmpfile());
    }
}

class Model
{
    private $title;

    private $numbers;

    public static function fromArray(array $array): self
    {
        $model = new self();
        if (isset($array['title'])) {
            $model->setTitle($array['title']);
        }

        if (isset($array['numbers'])) {
            $model->setNumbers($array['numbers']);
        }

        return $model;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title): void
    {
        $this->title = $title;
    }

    public function getNumbers()
    {
        return $this->numbers;
    }

    public function setNumbers($numbers): void
    {
        $this->numbers = $numbers;
    }

    public function toArray(): array
    {
        return ['title' => $this->title, 'numbers' => $this->numbers];
    }
}

class Foo
{
}

class Bar
{
}
