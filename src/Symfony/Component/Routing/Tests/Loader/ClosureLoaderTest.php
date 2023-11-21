<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ClosureLoaderTest extends TestCase
{
    public function testSupports(): void
    {
        $loader = new ClosureLoader();

        $closure = static function () : void {
        };

        $this->assertTrue($loader->supports($closure), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($loader->supports($closure, 'closure'), '->supports() checks the resource type if specified');
        $this->assertFalse($loader->supports($closure, 'foo'), '->supports() checks the resource type if specified');
    }

    public function testLoad(): void
    {
        $loader = new ClosureLoader();

        $route = new Route('/');
        $routes = $loader->load(static function () use ($route) : \Symfony\Component\Routing\RouteCollection {
            $routes = new RouteCollection();
            $routes->add('foo', $route);
            return $routes;
        });

        $this->assertEquals($route, $routes->get('foo'), '->load() loads a \Closure resource');
    }
}
