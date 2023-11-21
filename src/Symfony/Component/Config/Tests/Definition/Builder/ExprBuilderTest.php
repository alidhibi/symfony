<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition\Builder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ExprBuilderTest extends TestCase
{
    public function testAlwaysExpression(): void
    {
        $test = $this->getTestBuilder()
            ->always($this->returnClosure('new_value'))
        ->end();

        $this->assertFinalizedValueIs('new_value', $test);
    }

    public function testIfTrueExpression(): void
    {
        $test = $this->getTestBuilder()
            ->ifTrue()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, ['key' => true]);

        $test = $this->getTestBuilder()
            ->ifTrue(static fn($v): true => true)
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifTrue(static fn($v): false => false)
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfStringExpression(): void
    {
        $test = $this->getTestBuilder()
            ->ifString()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifString()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs(45, $test, ['key' => 45]);
    }

    public function testIfNullExpression(): void
    {
        $test = $this->getTestBuilder()
            ->ifNull()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, ['key' => null]);

        $test = $this->getTestBuilder()
            ->ifNull()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfEmptyExpression(): void
    {
        $test = $this->getTestBuilder()
            ->ifEmpty()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, ['key' => []]);

        $test = $this->getTestBuilder()
            ->ifEmpty()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfArrayExpression(): void
    {
        $test = $this->getTestBuilder()
            ->ifArray()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, ['key' => []]);

        $test = $this->getTestBuilder()
            ->ifArray()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfInArrayExpression(): void
    {
        $test = $this->getTestBuilder()
            ->ifInArray(['foo', 'bar', 'value'])
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifInArray(['foo', 'bar'])
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfNotInArrayExpression(): void
    {
        $test = $this->getTestBuilder()
            ->ifNotInArray(['foo', 'bar'])
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifNotInArray(['foo', 'bar', 'value_from_config'])
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);
    }

    public function testThenEmptyArrayExpression(): void
    {
        $test = $this->getTestBuilder()
            ->ifString()
            ->thenEmptyArray()
        ->end();
        $this->assertFinalizedValueIs([], $test);
    }

    /**
     * @dataProvider castToArrayValues
     */
    public function testCastToArrayExpression(string|float|array|null $configValue, array $expectedValue): void
    {
        $test = $this->getTestBuilder()
            ->castToArray()
        ->end();
        $this->assertFinalizedValueIs($expectedValue, $test, ['key' => $configValue]);
    }

    public function castToArrayValues(): \Generator
    {
        yield ['value', ['value']];
        yield [-3.14, [-3.14]];
        yield [null, [null]];
        yield [['value'], ['value']];
    }

    public function testThenInvalid(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $test = $this->getTestBuilder()
            ->ifString()
            ->thenInvalid('Invalid value')
        ->end();
        $this->finalizeTestBuilder($test);
    }

    public function testThenUnsetExpression(): void
    {
        $test = $this->getTestBuilder()
            ->ifString()
            ->thenUnset()
        ->end();
        $this->assertEquals([], $this->finalizeTestBuilder($test));
    }

    public function testEndIfPartNotSpecified(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('You must specify an if part.');
        $this->getTestBuilder()->end();
    }

    public function testEndThenPartNotSpecified(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('You must specify a then part.');
        $builder = $this->getTestBuilder();
        $builder->ifPart = 'test';
        $builder->end();
    }

    /**
     * Create a test treebuilder with a variable node, and init the validation.
     *
     * @return TreeBuilder
     */
    protected function getTestBuilder()
    {
        $builder = new TreeBuilder();

        return $builder
            ->root('test')
            ->children()
            ->variableNode('key')
            ->validate()
        ;
    }

    /**
     * Close the validation process and finalize with the given config.
     *
     * @param TreeBuilder $testBuilder The tree builder to finalize
     * @param array       $config      The config you want to use for the finalization, if nothing provided
     *                                 a simple ['key'=>'value'] will be used
     *
     * @return array The finalized config values
     */
    protected function finalizeTestBuilder($testBuilder, $config = null)
    {
        return $testBuilder
            ->end()
            ->end()
            ->end()
            ->buildTree()
            ->finalize(null === $config ? ['key' => 'value'] : $config)
        ;
    }

    /**
     * Return a closure that will return the given value.
     *
     * @param mixed $val The value that the closure must return
     *
     * @return \Closure
     */
    protected function returnClosure($val)
    {
        return static fn($v) => $val;
    }

    /**
     * Assert that the given test builder, will return the given value.
     *
     * @param mixed       $value       The value to test
     * @param TreeBuilder $treeBuilder The tree builder to finalize
     * @param mixed       $config      The config values that new to be finalized
     */
    protected function assertFinalizedValueIs($value, $treeBuilder, $config = null)
    {
        $this->assertEquals(['key' => $value], $this->finalizeTestBuilder($treeBuilder, $config));
    }
}
