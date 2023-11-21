<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Data\Bundle\Reader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Data\Bundle\Reader\PhpBundleReader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PhpBundleReaderTest extends TestCase
{
    private \Symfony\Component\Intl\Data\Bundle\Reader\PhpBundleReader $reader;

    protected function setUp()
    {
        $this->reader = new PhpBundleReader();
    }

    public function testReadReturnsArray(): void
    {
        $data = $this->reader->read(__DIR__.'/Fixtures/php', 'en');

        $this->assertIsArray($data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadFailsIfNonExistingLocale(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/php', 'foo');
    }

    public function testReadFailsIfNonExistingDirectory(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\RuntimeException::class);
        $this->reader->read(__DIR__.'/foo', 'en');
    }

    public function testReadFailsIfNotAFile(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\RuntimeException::class);
        $this->reader->read(__DIR__.'/Fixtures/NotAFile', 'en');
    }

    public function testReaderDoesNotBreakOutOfGivenPath(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/php', '../invalid_directory/en');
    }
}
