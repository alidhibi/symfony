<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\CacheItem;

class CacheItemTest extends TestCase
{
    public function testValidKey(): void
    {
        $this->assertSame('foo', CacheItem::validateKey('foo'));
    }

    /**
     * @dataProvider provideInvalidKey
     */
    public function testInvalidKey(string|bool|int|float|\Exception|array|null $key): void
    {
        $this->expectException(\Symfony\Component\Cache\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache key');
        CacheItem::validateKey($key);
    }

    public function provideInvalidKey(): array
    {
        return [
            [''],
            ['{'],
            ['}'],
            ['('],
            [')'],
            ['/'],
            ['\\'],
            ['@'],
            [':'],
            [true],
            [null],
            [1],
            [1.1],
            [[[]]],
            [new \Exception('foo')],
        ];
    }

    public function testTag(): void
    {
        $item = new CacheItem();

        $this->assertSame($item, $item->tag('foo'));
        $this->assertSame($item, $item->tag(['bar', 'baz']));

        \call_user_func(\Closure::bind(function () use ($item): void {
            $this->assertSame(['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'], $item->tags);
        }, $this, CacheItem::class));
    }

    /**
     * @dataProvider provideInvalidKey
     */
    public function testInvalidTag(string|bool|int|float|\Exception|array|null $tag): void
    {
        $this->expectException(\Symfony\Component\Cache\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache tag');
        $item = new CacheItem();
        $item->tag($tag);
    }
}
