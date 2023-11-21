<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Config;

use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;

/**
 * EnvParametersResource represents resources stored in prefixed environment variables.
 *
 * @author Chris Wilkinson <chriswilkinson84@gmail.com>
 *
 * @deprecated since version 3.4, to be removed in 4.0
 */
class EnvParametersResource implements SelfCheckingResourceInterface, \Serializable
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @var string
     */
    private $variables;

    /**
     * @param string $prefix
     */
    public function __construct($prefix)
    {
        $this->prefix = $prefix;
        $this->variables = $this->findVariables();
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return serialize($this->getResource());
    }

    /**
     * @return array An array with two keys: 'prefix' for the prefix used and 'variables' containing all the variables watched by this resource
     */
    public function getResource(): array
    {
        return ['prefix' => $this->prefix, 'variables' => $this->variables];
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($timestamp): bool
    {
        return $this->findVariables() === $this->variables;
    }

    /**
     * @internal
     */
    public function serialize(): string
    {
        return serialize(['prefix' => $this->prefix, 'variables' => $this->variables]);
    }

    /**
     * @internal
     */
    public function unserialize($serialized): void
    {
        $unserialized = unserialize($serialized, ['allowed_classes' => false]);

        $this->prefix = $unserialized['prefix'];
        $this->variables = $unserialized['variables'];
    }

    /**
     * @return mixed[]
     */
    private function findVariables(): array
    {
        $variables = [];

        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, $this->prefix)) {
                $variables[$key] = $value;
            }
        }

        ksort($variables);

        return $variables;
    }
}
