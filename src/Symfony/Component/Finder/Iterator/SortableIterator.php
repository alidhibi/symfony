<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

/**
 * SortableIterator applies a sort on a given Iterator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SortableIterator implements \IteratorAggregate
{
    final const SORT_BY_NAME = 1;

    final const SORT_BY_TYPE = 2;

    final const SORT_BY_ACCESSED_TIME = 3;

    final const SORT_BY_CHANGED_TIME = 4;

    final const SORT_BY_MODIFIED_TIME = 5;

    private readonly \Traversable $iterator;

    private \Closure|int|null $sort = null;

    /**
     * @param \Traversable $iterator The Iterator to filter
     * @param int|callable $sort     The sort type (SORT_BY_NAME, SORT_BY_TYPE, or a PHP callback)
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(\Traversable $iterator, $sort)
    {
        $this->iterator = $iterator;

        if (self::SORT_BY_NAME === $sort) {
            $this->sort = static fn($a, $b): int => strcmp($a->getRealPath() ?: $a->getPathname(), $b->getRealPath() ?: $b->getPathname());
        } elseif (self::SORT_BY_TYPE === $sort) {
            $this->sort = static function ($a, $b) {
                if ($a->isDir() && $b->isFile()) {
                    return -1;
                } elseif ($a->isFile() && $b->isDir()) {
                    return 1;
                }

                return strcmp($a->getRealPath() ?: $a->getPathname(), $b->getRealPath() ?: $b->getPathname());
            };
        } elseif (self::SORT_BY_ACCESSED_TIME === $sort) {
            $this->sort = static fn($a, $b): int|float => $a->getATime() - $b->getATime();
        } elseif (self::SORT_BY_CHANGED_TIME === $sort) {
            $this->sort = static fn($a, $b): int|float => $a->getCTime() - $b->getCTime();
        } elseif (self::SORT_BY_MODIFIED_TIME === $sort) {
            $this->sort = static fn($a, $b): int|float => $a->getMTime() - $b->getMTime();
        } elseif (\is_callable($sort)) {
            $this->sort = $sort;
        } else {
            throw new \InvalidArgumentException('The SortableIterator takes a PHP callable or a valid built-in sort algorithm as an argument.');
        }
    }

    public function getIterator(): \ArrayIterator
    {
        $array = iterator_to_array($this->iterator, true);
        uasort($array, $this->sort);

        return new \ArrayIterator($array);
    }
}
