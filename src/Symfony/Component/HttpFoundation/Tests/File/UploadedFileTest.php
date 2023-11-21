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
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadedFileTest extends TestCase
{
    protected function setUp()
    {
        if (!ini_get('file_uploads')) {
            $this->markTestSkipped('file_uploads is disabled in php.ini');
        }
    }

    public function testConstructWhenFileNotExists(): void
    {
        $this->expectException(\Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException::class);

        new UploadedFile(
            __DIR__.'/Fixtures/not_here',
            'original.gif',
            null
        );
    }

    public function testFileUploadsWithNoMimeType(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            null,
            filesize(__DIR__.'/Fixtures/test.gif'),
            \UPLOAD_ERR_OK
        );

        $this->assertEquals('application/octet-stream', $file->getClientMimeType());

        if (\extension_loaded('fileinfo')) {
            $this->assertEquals('image/gif', $file->getMimeType());
        }
    }

    public function testFileUploadsWithUnknownMimeType(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/.unknownextension',
            'original.gif',
            null,
            filesize(__DIR__.'/Fixtures/.unknownextension'),
            \UPLOAD_ERR_OK
        );

        $this->assertEquals('application/octet-stream', $file->getClientMimeType());
    }

    public function testGuessClientExtension(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            filesize(__DIR__.'/Fixtures/test.gif'),
            null
        );

        $this->assertEquals('gif', $file->guessClientExtension());
    }

    public function testGuessClientExtensionWithIncorrectMimeType(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/png',
            filesize(__DIR__.'/Fixtures/test.gif'),
            null
        );

        $this->assertEquals('png', $file->guessClientExtension());
    }

    public function testCaseSensitiveMimeType(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/case-sensitive-mime-type.xlsm',
            'test.xlsm',
            'application/vnd.ms-excel.sheet.macroEnabled.12',
            filesize(__DIR__.'/Fixtures/case-sensitive-mime-type.xlsm'),
            null
        );

        $this->assertEquals('xlsm', $file->guessClientExtension());
    }

    public function testErrorIsOkByDefault(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            filesize(__DIR__.'/Fixtures/test.gif'),
            null
        );

        $this->assertEquals(\UPLOAD_ERR_OK, $file->getError());
    }

    public function testGetClientOriginalName(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            filesize(__DIR__.'/Fixtures/test.gif'),
            null
        );

        $this->assertEquals('original.gif', $file->getClientOriginalName());
    }

    public function testGetClientOriginalExtension(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            filesize(__DIR__.'/Fixtures/test.gif'),
            null
        );

        $this->assertEquals('gif', $file->getClientOriginalExtension());
    }

    public function testMoveLocalFileIsNotAllowed(): void
    {
        $this->expectException(\Symfony\Component\HttpFoundation\File\Exception\FileException::class);
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            filesize(__DIR__.'/Fixtures/test.gif'),
            \UPLOAD_ERR_OK
        );

        $file->move(__DIR__.'/Fixtures/directory');
    }

    public function testMoveLocalFileIsAllowedInTestMode(): void
    {
        $path = __DIR__.'/Fixtures/test.copy.gif';
        $targetDir = __DIR__.'/Fixtures/directory';
        $targetPath = $targetDir.'/test.copy.gif';
        @unlink($path);
        @unlink($targetPath);
        copy(__DIR__.'/Fixtures/test.gif', $path);

        $file = new UploadedFile(
            $path,
            'original.gif',
            'image/gif',
            filesize($path),
            \UPLOAD_ERR_OK,
            true
        );

        $movedFile = $file->move(__DIR__.'/Fixtures/directory');

        $this->assertFileExists($targetPath);
        $this->assertFileDoesNotExist($path);
        $this->assertEquals(realpath($targetPath), $movedFile->getRealPath());

        @unlink($targetPath);
    }

    public function testGetClientOriginalNameSanitizeFilename(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            '../../original.gif',
            'image/gif',
            filesize(__DIR__.'/Fixtures/test.gif'),
            null
        );

        $this->assertEquals('original.gif', $file->getClientOriginalName());
    }

    public function testGetSize(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            'image/gif',
            filesize(__DIR__.'/Fixtures/test.gif'),
            null
        );

        $this->assertEquals(filesize(__DIR__.'/Fixtures/test.gif'), $file->getSize());

        $file = new UploadedFile(
            __DIR__.'/Fixtures/test',
            'original.gif',
            'image/gif'
        );

        $this->assertEquals(filesize(__DIR__.'/Fixtures/test'), $file->getSize());
    }

    public function testGetExtension(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            null
        );

        $this->assertEquals('gif', $file->getExtension());
    }

    public function testIsValid(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            null,
            filesize(__DIR__.'/Fixtures/test.gif'),
            \UPLOAD_ERR_OK,
            true
        );

        $this->assertTrue($file->isValid());
    }

    /**
     * @dataProvider uploadedFileErrorProvider
     */
    public function testIsInvalidOnUploadError(int $error): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            null,
            filesize(__DIR__.'/Fixtures/test.gif'),
            $error
        );

        $this->assertFalse($file->isValid());
    }

    public function uploadedFileErrorProvider(): array
    {
        return [
            [\UPLOAD_ERR_INI_SIZE],
            [\UPLOAD_ERR_FORM_SIZE],
            [\UPLOAD_ERR_PARTIAL],
            [\UPLOAD_ERR_NO_TMP_DIR],
            [\UPLOAD_ERR_EXTENSION],
        ];
    }

    public function testIsInvalidIfNotHttpUpload(): void
    {
        $file = new UploadedFile(
            __DIR__.'/Fixtures/test.gif',
            'original.gif',
            null,
            filesize(__DIR__.'/Fixtures/test.gif'),
            \UPLOAD_ERR_OK
        );

        $this->assertFalse($file->isValid());
    }

    public function testGetMaxFilesize(): void
    {
        $size = UploadedFile::getMaxFilesize();

        $this->assertIsInt($size);
        $this->assertGreaterThan(0, $size);

        if (0 === (int) ini_get('post_max_size') && 0 === (int) ini_get('upload_max_filesize')) {
            $this->assertSame(\PHP_INT_MAX, $size);
        } else {
            $this->assertLessThan(\PHP_INT_MAX, $size);
        }
    }
}
