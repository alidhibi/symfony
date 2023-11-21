<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class PackagesTest extends TestCase
{
    public function testGetterSetters(): void
    {
        $packages = new Packages();
        $packages->setDefaultPackage($default = $this->getMockBuilder(\Symfony\Component\Asset\PackageInterface::class)->getMock());
        $packages->addPackage('a', $a = $this->getMockBuilder(\Symfony\Component\Asset\PackageInterface::class)->getMock());

        $this->assertSame($default, $packages->getPackage());
        $this->assertSame($a, $packages->getPackage('a'));

        $packages = new Packages($default, ['a' => $a]);

        $this->assertSame($default, $packages->getPackage());
        $this->assertSame($a, $packages->getPackage('a'));
    }

    public function testGetVersion(): void
    {
        $packages = new Packages(
            new Package(new StaticVersionStrategy('default')),
            ['a' => new Package(new StaticVersionStrategy('a'))]
        );

        $this->assertSame('default', $packages->getVersion('/foo'));
        $this->assertSame('a', $packages->getVersion('/foo', 'a'));
    }

    public function testGetUrl(): void
    {
        $packages = new Packages(
            new Package(new StaticVersionStrategy('default')),
            ['a' => new Package(new StaticVersionStrategy('a'))]
        );

        $this->assertSame('/foo?default', $packages->getUrl('/foo'));
        $this->assertSame('/foo?a', $packages->getUrl('/foo', 'a'));
    }

    public function testNoDefaultPackage(): void
    {
        $this->expectException(\Symfony\Component\Asset\Exception\LogicException::class);
        $packages = new Packages();
        $packages->getPackage();
    }

    public function testUndefinedPackage(): void
    {
        $this->expectException(\Symfony\Component\Asset\Exception\InvalidArgumentException::class);
        $packages = new Packages();
        $packages->getPackage('a');
    }
}
