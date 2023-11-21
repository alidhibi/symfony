<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Annotation;

/**
 * Annotation class for @Route().
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Route
{
    private $path;

    private $name;

    private array $requirements = [];

    private array $options = [];

    private array $defaults = [];

    private $host;

    private array $methods = [];

    private array $schemes = [];

    private $condition;

    /**
     * @param array $data An array of key/value parameters
     *
     * @throws \BadMethodCallException
     */
    public function __construct(array $data)
    {
        if (isset($data['value'])) {
            $data['path'] = $data['value'];
            unset($data['value']);
        }

        foreach ($data as $key => $value) {
            $method = 'set'.str_replace('_', '', $key);
            if (!method_exists($this, $method)) {
                throw new \BadMethodCallException(sprintf('Unknown property "%s" on annotation "%s".', $key, static::class));
            }

            $this->$method($value);
        }
    }

    public function setPath($path): void
    {
        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setHost($pattern): void
    {
        $this->host = $pattern;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setRequirements(array $requirements): void
    {
        $this->requirements = $requirements;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setDefaults(array $defaults): void
    {
        $this->defaults = $defaults;
    }

    public function getDefaults(): array
    {
        return $this->defaults;
    }

    public function setSchemes($schemes): void
    {
        $this->schemes = \is_array($schemes) ? $schemes : [$schemes];
    }

    public function getSchemes(): array
    {
        return $this->schemes;
    }

    public function setMethods($methods): void
    {
        $this->methods = \is_array($methods) ? $methods : [$methods];
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function setCondition($condition): void
    {
        $this->condition = $condition;
    }

    public function getCondition()
    {
        return $this->condition;
    }
}
