<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * FileResource represents a resource stored on the filesystem.
 *
 * The resource can be a file or a directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FileResource implements SelfCheckingResourceInterface, \Serializable
{
    private string $resource;

    /**
     * @param string $resource The file path to the resource
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($resource)
    {
        $this->resource = realpath($resource) ?: (file_exists($resource) ? $resource : false);

        if (false === $this->resource) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not exist.', $resource));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->resource;
    }

    /**
     * @return string The canonicalized, absolute path to the resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($timestamp): bool
    {
        return false !== ($filemtime = @filemtime($this->resource)) && $filemtime <= $timestamp;
    }

    /**
     * @internal
     */
    public function serialize(): string
    {
        return serialize($this->resource);
    }

    /**
     * @internal
     */
    public function unserialize($serialized): void
    {
        $this->resource = unserialize($serialized);
    }
}
