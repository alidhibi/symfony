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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResourceChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerParametersResourceCheckerTest extends TestCase
{
    private \Symfony\Component\DependencyInjection\Config\ContainerParametersResource $resource;

    /** @var ResourceCheckerInterface */
    private \Symfony\Component\DependencyInjection\Config\ContainerParametersResourceChecker $resourceChecker;

    /** @var ContainerInterface */
    private $container;

    protected function setUp()
    {
        $this->resource = new ContainerParametersResource(['locales' => ['fr', 'en'], 'default_locale' => 'fr']);
        $this->container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $this->resourceChecker = new ContainerParametersResourceChecker($this->container);
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->resourceChecker->supports($this->resource));
    }

    /**
     * @dataProvider isFreshProvider
     */
    public function testIsFresh(callable $mockContainer, bool $expected): void
    {
        $mockContainer($this->container);

        $this->assertSame($expected, $this->resourceChecker->isFresh($this->resource, time()));
    }

    public function isFreshProvider(): \Generator
    {
        yield 'not fresh on missing parameter' => [static function (MockObject $container) : void {
            $container->method('hasParameter')->with('locales')->willReturn(false);
        }, false];

        yield 'not fresh on different value' => [static function (MockObject $container) : void {
            $container->method('getParameter')->with('locales')->willReturn(['nl', 'es']);
        }, false];

        yield 'fresh on every identical parameters' => [function (MockObject $container): void {
            $container->expects($this->exactly(2))->method('hasParameter')->willReturn(true);
            $container->expects($this->exactly(2))->method('getParameter')
                ->withConsecutive(
                    [$this->equalTo('locales')],
                    [$this->equalTo('default_locale')]
                )
                ->willReturnMap([
                    ['locales', ['fr', 'en']],
                    ['default_locale', 'fr'],
                ])
            ;
        }, true];
    }
}
