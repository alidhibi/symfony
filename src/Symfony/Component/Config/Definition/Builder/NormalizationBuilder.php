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

/**
 * This class builds normalization conditions.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class NormalizationBuilder
{
    protected \Symfony\Component\Config\Definition\Builder\NodeDefinition $node;

    public $before = [];

    public $remappings = [];

    public function __construct(NodeDefinition $node)
    {
        $this->node = $node;
    }

    /**
     * Registers a key to remap to its plural form.
     *
     * @param string $key    The key to remap
     * @param string $plural The plural of the key in case of irregular plural
     *
     * @return $this
     */
    public function remap(string $key, $plural = null): static
    {
        $this->remappings[] = [$key, null === $plural ? $key.'s' : $plural];

        return $this;
    }

    /**
     * Registers a closure to run before the normalization or an expression builder to build it if null is provided.
     *
     * @return ExprBuilder|$this
     */
    public function before(\Closure $closure = null): static|\Symfony\Component\Config\Definition\Builder\ExprBuilder
    {
        if ($closure instanceof \Closure) {
            $this->before[] = $closure;

            return $this;
        }

        return $this->before[] = new ExprBuilder($this->node);
    }
}
