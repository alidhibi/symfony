<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\ParserCache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\CacheItem;

/**
 * @author Alexandre GESLIN <alexandre@gesl.in>
 *
 * @internal and will be removed in Symfony 4.0.
 */
class ParserCacheAdapter implements CacheItemPoolInterface
{
    private readonly \Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface $pool;

    private ?\Closure $createCacheItem = null;

    public function __construct(ParserCacheInterface $pool)
    {
        $this->pool = $pool;

        $this->createCacheItem = \Closure::bind(
            static function ($key, $value, $isHit): \Symfony\Component\Cache\CacheItem {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;

                return $item;
            },
            null,
            CacheItem::class
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $value = $this->pool->fetch($key);
        $f = $this->createCacheItem;

        return $f($key, $value, null !== $value);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item): void
    {
        $this->pool->save($item->getKey(), $item->get());
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = []): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
