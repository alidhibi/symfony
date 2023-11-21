<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Templating\Loader\Loader;
use Symfony\Component\Templating\TemplateReferenceInterface;

class LoaderTest extends TestCase
{
    public function testGetSetLogger(): void
    {
        $loader = new ProjectTemplateLoader4();
        $logger = $this->getMockBuilder(\Psr\Log\LoggerInterface::class)->getMock();
        $loader->setLogger($logger);
        $this->assertSame($logger, $loader->getLogger(), '->setLogger() sets the logger instance');
    }
}

class ProjectTemplateLoader4 extends Loader
{
    public function load(TemplateReferenceInterface $template): void
    {
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function isFresh(TemplateReferenceInterface $template, $time): bool
    {
        return false;
    }
}
