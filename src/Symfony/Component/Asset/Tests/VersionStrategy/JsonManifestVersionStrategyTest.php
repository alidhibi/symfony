<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\VersionStrategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;

class JsonManifestVersionStrategyTest extends TestCase
{
    public function testGetVersion(): void
    {
        $strategy = $this->createStrategy('manifest-valid.json');

        $this->assertSame('main.123abc.js', $strategy->getVersion('main.js'));
    }

    public function testApplyVersion(): void
    {
        $strategy = $this->createStrategy('manifest-valid.json');

        $this->assertSame('css/styles.555def.css', $strategy->getVersion('css/styles.css'));
    }

    public function testApplyVersionWhenKeyDoesNotExistInManifest(): void
    {
        $strategy = $this->createStrategy('manifest-valid.json');

        $this->assertSame('css/other.css', $strategy->getVersion('css/other.css'));
    }

    public function testMissingManifestFileThrowsException(): void
    {
        $this->expectException('RuntimeException');
        $strategy = $this->createStrategy('non-existent-file.json');
        $strategy->getVersion('main.js');
    }

    public function testManifestFileWithBadJSONThrowsException(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Error parsing JSON');
        $strategy = $this->createStrategy('manifest-invalid.json');
        $strategy->getVersion('main.js');
    }

    private function createStrategy(string $manifestFilename): \Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy
    {
        return new JsonManifestVersionStrategy(__DIR__.'/../fixtures/'.$manifestFilename);
    }
}
