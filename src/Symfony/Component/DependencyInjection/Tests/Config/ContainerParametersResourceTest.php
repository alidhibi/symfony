<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;

class ContainerParametersResourceTest extends TestCase
{
    private \Symfony\Component\DependencyInjection\Config\ContainerParametersResource $resource;

    protected function setUp()
    {
        $this->resource = new ContainerParametersResource(['locales' => ['fr', 'en'], 'default_locale' => 'fr']);
    }

    public function testToString(): void
    {
        $this->assertSame('container_parameters_9893d3133814ab03cac3490f36dece77', (string) $this->resource);
    }

    public function testSerializeUnserialize(): void
    {
        $unserialized = unserialize(serialize($this->resource));

        $this->assertEquals($this->resource, $unserialized);
    }

    public function testGetParameters(): void
    {
        $this->assertSame(['locales' => ['fr', 'en'], 'default_locale' => 'fr'], $this->resource->getParameters());
    }
}
