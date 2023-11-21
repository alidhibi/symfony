<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Resources;

use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Xml\Loader;

class TranslationFilesTest extends TestCase
{
    /**
     * @dataProvider provideTranslationFiles
     */
    public function testTranslationFileIsValid($filePath): void
    {
        $loader = class_exists(Loader::class)
            ? [new Loader(), 'loadFile']
            : ['PHPUnit\Util\XML', 'loadfile'];

        $loader($filePath, false, false, true);

        $this->addToAssertionCount(1);
    }

    public function provideTranslationFiles(): array
    {
        return array_map(
            static fn($filePath): array => (array) $filePath,
            glob(\dirname(\dirname(__DIR__)).'/Resources/translations/*.xlf')
        );
    }

    public function testNorwegianAlias(): void
    {
        $this->assertFileEquals(
            \dirname(\dirname(__DIR__)).'/Resources/translations/validators.nb.xlf',
            \dirname(\dirname(__DIR__)).'/Resources/translations/validators.no.xlf',
            'The NO locale should be an alias for the NB variant of the Norwegian language.'
        );
    }
}
