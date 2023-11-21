<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Descriptor;

use Symfony\Component\Console\Descriptor\MarkdownDescriptor;
use Symfony\Component\Console\Tests\Fixtures\DescriptorApplicationMbString;
use Symfony\Component\Console\Tests\Fixtures\DescriptorCommandMbString;

class MarkdownDescriptorTest extends AbstractDescriptorTest
{
    public function getDescribeCommandTestData()
    {
        return $this->getDescriptionTestData(array_merge(
            ObjectsProvider::getCommands(),
            ['command_mbstring' => new DescriptorCommandMbString()]
        ));
    }

    public function getDescribeApplicationTestData()
    {
        return $this->getDescriptionTestData(array_merge(
            ObjectsProvider::getApplications(),
            ['application_mbstring' => new DescriptorApplicationMbString()]
        ));
    }

    protected function getDescriptor(): \Symfony\Component\Console\Descriptor\MarkdownDescriptor
    {
        return new MarkdownDescriptor();
    }

    protected function getFormat(): string
    {
        return 'md';
    }
}
