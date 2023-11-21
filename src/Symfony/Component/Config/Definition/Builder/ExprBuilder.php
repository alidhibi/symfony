<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\Exception\UnsetKeyException;

/**
 * This class builds an if expression.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class ExprBuilder
{
    protected \Symfony\Component\Config\Definition\Builder\NodeDefinition $node;

    public $ifPart;

    public $thenPart;

    public function __construct(NodeDefinition $node)
    {
        $this->node = $node;
    }

    /**
     * Marks the expression as being always used.
     *
     * @return $this
     */
    public function always(\Closure $then = null): static
    {
        $this->ifPart = static fn($v): true => true;

        if ($then instanceof \Closure) {
            $this->thenPart = $then;
        }

        return $this;
    }

    /**
     * Sets a closure to use as tests.
     *
     * The default one tests if the value is true.
     *
     * @return $this
     */
    public function ifTrue(\Closure $closure = null): static
    {
        if (!$closure instanceof \Closure) {
            $closure = static fn($v): bool => true === $v;
        }

        $this->ifPart = $closure;

        return $this;
    }

    /**
     * Tests if the value is a string.
     *
     * @return $this
     */
    public function ifString(): static
    {
        $this->ifPart = static fn($v): bool => \is_string($v);

        return $this;
    }

    /**
     * Tests if the value is null.
     *
     * @return $this
     */
    public function ifNull(): static
    {
        $this->ifPart = static fn($v): bool => null === $v;

        return $this;
    }

    /**
     * Tests if the value is empty.
     *
     */
    public function ifEmpty(): static
    {
        $this->ifPart = static fn($v): bool => empty($v);

        return $this;
    }

    /**
     * Tests if the value is an array.
     *
     * @return $this
     */
    public function ifArray(): static
    {
        $this->ifPart = static fn($v): bool => \is_array($v);

        return $this;
    }

    /**
     * Tests if the value is in an array.
     *
     * @return $this
     */
    public function ifInArray(array $array): static
    {
        $this->ifPart = static fn($v): bool => \in_array($v, $array, true);

        return $this;
    }

    /**
     * Tests if the value is not in an array.
     *
     * @return $this
     */
    public function ifNotInArray(array $array): static
    {
        $this->ifPart = static fn($v): bool => !\in_array($v, $array, true);

        return $this;
    }

    /**
     * Transforms variables of any type into an array.
     *
     * @return $this
     */
    public function castToArray(): static
    {
        $this->ifPart = static fn($v): bool => !\is_array($v);
        $this->thenPart = static fn($v): array => [$v];

        return $this;
    }

    /**
     * Sets the closure to run if the test pass.
     *
     * @return $this
     */
    public function then(\Closure $closure): static
    {
        $this->thenPart = $closure;

        return $this;
    }

    /**
     * Sets a closure returning an empty array.
     *
     * @return $this
     */
    public function thenEmptyArray(): static
    {
        $this->thenPart = static fn($v): array => [];

        return $this;
    }

    /**
     * Sets a closure marking the value as invalid at processing time.
     *
     * if you want to add the value of the node in your message just use a %s placeholder.
     *
     * @param string $message
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function thenInvalid($message): static
    {
        $this->thenPart = static function ($v) use ($message) : never {
            throw new \InvalidArgumentException(sprintf($message, json_encode($v)));
        };

        return $this;
    }

    /**
     * Sets a closure unsetting this key of the array at processing time.
     *
     * @return $this
     *
     * @throws UnsetKeyException
     */
    public function thenUnset(): static
    {
        $this->thenPart = static function ($v) : never {
            throw new UnsetKeyException('Unsetting key.');
        };

        return $this;
    }

    /**
     * Returns the related node.
     *
     * @return NodeDefinition|ArrayNodeDefinition|VariableNodeDefinition
     *
     * @throws \RuntimeException
     */
    public function end()
    {
        if (null === $this->ifPart) {
            throw new \RuntimeException('You must specify an if part.');
        }

        if (null === $this->thenPart) {
            throw new \RuntimeException('You must specify a then part.');
        }

        return $this->node;
    }

    /**
     * Builds the expressions.
     *
     * @param ExprBuilder[] $expressions An array of ExprBuilder instances to build
     *
     */
    public static function buildExpressions(array $expressions): array
    {
        foreach ($expressions as $k => $expr) {
            if ($expr instanceof self) {
                $if = $expr->ifPart;
                $then = $expr->thenPart;
                $expressions[$k] = static fn($v) => $if($v) ? $then($v) : $v;
            }
        }

        return $expressions;
    }
}
