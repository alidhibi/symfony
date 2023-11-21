<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug;

function headers_sent(): bool
{
    return false;
}

function header($str, $replace = true, $status = null): void
{
    Tests\testHeader($str, $replace, $status);
}

namespace Symfony\Component\Debug\Tests;

function testHeader(...$args)
{
    static $headers = [];

    if (!$h = \func_get_args()) {
        $h = $headers;
        $headers = [];

        return $h;
    }

    $headers[] = $args;
}
