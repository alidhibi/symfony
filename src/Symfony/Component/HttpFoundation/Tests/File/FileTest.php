<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\File;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;

class FileTest extends TestCase
{
    protected $file;

    public function testGetMimeTypeUsesMimeTypeGuessers(): void
    {
        $file = new File(__DIR__.'/Fixtures/test.gif');
        $guesser = $this->createMockGuesser($file->getPathname(), 'image/gif');

        MimeTypeGuesser::getInstance()->register($guesser);

        $this->assertEquals('image/gif', $file->getMimeType());
    }

    public function testGuessExtensionWithoutGuesser(): void
    {
        $file = new File(__DIR__.'/Fixtures/directory/.empty');

        $this->assertNull($file->guessExtension());
    }

    public function testGuessExtensionIsBasedOnMimeType(): void
    {
        $file = new File(__DIR__.'/Fixtures/test');
        $guesser = $this->createMockGuesser($file->getPathname(), 'image/gif');

        MimeTypeGuesser::getInstance()->register($guesser);

        $this->assertEquals('gif', $file->guessExtension());
    }

    /**
     * @requires extension fileinfo
     */
    public function testGuessExtensionWithReset(): void
    {
        $file = new File(__DIR__.'/Fixtures/other-file.example');
        $guesser = $this->createMockGuesser($file->getPathname(), 'image/gif');
        MimeTypeGuesser::getInstance()->register($guesser);

        $this->assertEquals('gif', $file->guessExtension());

        MimeTypeGuesser::reset();

        $this->assertNull($file->guessExtension());
    }

    public function testConstructWhenFileNotExists(): void
    {
        $this->expectException(\Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException::class);

        new File(__DIR__.'/Fixtures/not_here');
    }

    public function testMove(): void
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetDir = __DIR__.'/Fixtures/directory';
        $targetPath = $targetDir.'/test.copy.gif';
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);
        $movedFile = $file->move($targetDir);
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\File\File::class, $movedFile);

        $this->assertFileExists($targetPath);
        $this->assertFileDoesNotExist($path);
        $this->assertEquals(realpath($targetPath), $movedFile->getRealPath());

        @unlink($targetPath);
    }

    public function testMoveWithNewName(): void
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetDir = __DIR__.'/Fixtures/directory';
        $targetPath = $targetDir.'/test.newname.gif';
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);
        $movedFile = $file->move($targetDir, 'test.newname.gif');

        $this->assertFileExists($targetPath);
        $this->assertFileDoesNotExist($path);
        $this->assertEquals(realpath($targetPath), $movedFile->getRealPath());

        @unlink($targetPath);
    }

    public function getFilenameFixtures(): array
    {
        return [
            ['original.gif', 'original.gif'],
            ['..\\..\\original.gif', 'original.gif'],
            ['../../original.gif', 'original.gif'],
            ['файлfile.gif', 'файлfile.gif'],
            ['..\\..\\файлfile.gif', 'файлfile.gif'],
            ['../../файлfile.gif', 'файлfile.gif'],
        ];
    }

    /**
     * @dataProvider getFilenameFixtures
     */
    public function testMoveWithNonLatinName(string $filename, string $sanitizedFilename): void
    {
        $path = __DIR__.'/Fixtures/'.$sanitizedFilename;
        $targetDir = __DIR__.'/Fixtures/directory/';
        $targetPath = $targetDir.$sanitizedFilename;
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new File($path);
        $movedFile = $file->move($targetDir, $filename);
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\File\File::class, $movedFile);

        $this->assertFileExists($targetPath);
        $this->assertFileDoesNotExist($path);
        $this->assertEquals(realpath($targetPath), $movedFile->getRealPath());

        @unlink($targetPath);
    }

    public function testMoveToAnUnexistentDirectory(): void
    {
        $sourcePath = __DIR__.'/Fixtures/test.copy.gif';
        $targetDir = __DIR__.'/Fixtures/directory/sub';
        $targetPath = $targetDir.'/test.copy.gif';
        @unlink($sourcePath);
        @unlink($targetPath);
        @rmdir($targetDir);
        copy(__DIR__.'/Fixtures/test.gif', $sourcePath);

        $file = new File($sourcePath);
        $movedFile = $file->move($targetDir);

        $this->assertFileExists($targetPath);
        $this->assertFileDoesNotExist($sourcePath);
        $this->assertEquals(realpath($targetPath), $movedFile->getRealPath());

        @unlink($sourcePath);
        @unlink($targetPath);
        @rmdir($targetDir);
    }

    protected function createMockGuesser($path, $mimeType)
    {
        $guesser = $this->getMockBuilder(\Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface::class)->getMock();
        $guesser
            ->expects($this->once())
            ->method('guess')
            ->with($this->equalTo($path))
            ->willReturn($mimeType)
        ;

        return $guesser;
    }
}
