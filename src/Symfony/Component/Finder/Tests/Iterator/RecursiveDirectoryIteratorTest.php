<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;

use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class RecursiveDirectoryIteratorTest extends IteratorTestCase
{
    /**
     * @group network
     */
    public function testRewindOnFtp(): void
    {
        try {
            $i = new RecursiveDirectoryIterator('ftp://speedtest.tele2.net/', \RecursiveDirectoryIterator::SKIP_DOTS | \FilesystemIterator::SKIP_DOTS);
        } catch (\UnexpectedValueException $unexpectedValueException) {
            $this->markTestSkipped('Unsupported stream "ftp".');
        }

        $i->rewind();

        $this->assertTrue(true);
    }

    /**
     * @group network
     */
    public function testSeekOnFtp(): void
    {
        try {
            $i = new RecursiveDirectoryIterator('ftp://speedtest.tele2.net/', \RecursiveDirectoryIterator::SKIP_DOTS | \FilesystemIterator::SKIP_DOTS);
        } catch (\UnexpectedValueException $unexpectedValueException) {
            $this->markTestSkipped('Unsupported stream "ftp".');
        }

        $contains = [
            'ftp://speedtest.tele2.net'.\DIRECTORY_SEPARATOR.'1000GB.zip',
            'ftp://speedtest.tele2.net'.\DIRECTORY_SEPARATOR.'100GB.zip',
        ];
        $actual = [];

        $i->seek(0);
        $actual[] = $i->getPathname();

        $i->seek(1);
        $actual[] = $i->getPathname();

        $this->assertEquals($contains, $actual);
    }
}
