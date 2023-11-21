<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;

class ScalarNodeTest extends TestCase
{
    /**
     * @dataProvider getValidValues
     */
    public function testNormalize(bool|string|int|float|null $value): void
    {
        $node = new ScalarNode('test');
        $this->assertSame($value, $node->normalize($value));
    }

    public function getValidValues(): array
    {
        return [
            [false],
            [true],
            [null],
            [''],
            ['foo'],
            [0],
            [1],
            [0.0],
            [0.1],
        ];
    }

    public function testSetDeprecated(): void
    {
        $childNode = new ScalarNode('foo');
        $childNode->setDeprecated('"%node%" is deprecated');

        $this->assertTrue($childNode->isDeprecated());
        $this->assertSame('"foo" is deprecated', $childNode->getDeprecationMessage($childNode->getName(), $childNode->getPath()));

        $node = new ArrayNode('root');
        $node->addChild($childNode);

        $deprecationTriggered = 0;
        $deprecationHandler = static function ($level, $message, $file, $line) use (&$prevErrorHandler, &$deprecationTriggered) : int|bool {
            if (\E_USER_DEPRECATED === $level) {
                return ++$deprecationTriggered;
            }
            return $prevErrorHandler ? $prevErrorHandler($level, $message, $file, $line) : false;
        };

        $prevErrorHandler = set_error_handler($deprecationHandler);
        $node->finalize([]);
        restore_error_handler();
        $this->assertSame(0, $deprecationTriggered, '->finalize() should not trigger if the deprecated node is not set');

        $prevErrorHandler = set_error_handler($deprecationHandler);
        $node->finalize(['foo' => '']);
        restore_error_handler();
        $this->assertSame(1, $deprecationTriggered, '->finalize() should trigger if the deprecated node is set');
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testNormalizeThrowsExceptionOnInvalidValues(\stdClass|array $value): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidTypeException::class);
        $node = new ScalarNode('test');
        $node->normalize($value);
    }

    public function getInvalidValues(): array
    {
        return [
            [[]],
            [['foo' => 'bar']],
            [new \stdClass()],
        ];
    }

    public function testNormalizeThrowsExceptionWithoutHint(): void
    {
        $node = new ScalarNode('test');

        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidTypeException::class);
        $this->expectExceptionMessage('Invalid type for path "test". Expected scalar, but got array.');

        $node->normalize([]);
    }

    public function testNormalizeThrowsExceptionWithErrorMessage(): void
    {
        $node = new ScalarNode('test');
        $node->setInfo('"the test value"');

        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidTypeException::class);
        $this->expectExceptionMessage("Invalid type for path \"test\". Expected scalar, but got array.\nHint: \"the test value\"");

        $node->normalize([]);
    }

    /**
     * @dataProvider getValidNonEmptyValues
     *
     * @param mixed $value
     */
    public function testValidNonEmptyValues(bool|string|int|float $value): void
    {
        $node = new ScalarNode('test');
        $node->setAllowEmptyValue(false);

        $this->assertSame($value, $node->finalize($value));
    }

    public function getValidNonEmptyValues(): array
    {
        return [
            [false],
            [true],
            ['foo'],
            [0],
            [1],
            [0.0],
            [0.1],
        ];
    }

    /**
     * @dataProvider getEmptyValues
     *
     * @param mixed $value
     */
    public function testNotAllowedEmptyValuesThrowException(?string $value): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $node = new ScalarNode('test');
        $node->setAllowEmptyValue(false);
        $node->finalize($value);
    }

    public function getEmptyValues(): array
    {
        return [
            [null],
            [''],
        ];
    }
}
