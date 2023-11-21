<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Catalogue;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

abstract class AbstractOperationTest extends TestCase
{
    public function testGetEmptyDomains(): void
    {
        $this->assertEquals(
            [],
            $this->createOperation(
                new MessageCatalogue('en'),
                new MessageCatalogue('en')
            )->getDomains()
        );
    }

    public function testGetMergedDomains(): void
    {
        $this->assertEquals(
            ['a', 'b', 'c'],
            $this->createOperation(
                new MessageCatalogue('en', ['a' => [], 'b' => []]),
                new MessageCatalogue('en', ['b' => [], 'c' => []])
            )->getDomains()
        );
    }

    public function testGetMessagesFromUnknownDomain(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->createOperation(
            new MessageCatalogue('en'),
            new MessageCatalogue('en')
        )->getMessages('domain');
    }

    public function testGetEmptyMessages(): void
    {
        $this->assertEquals(
            [],
            $this->createOperation(
                new MessageCatalogue('en', ['a' => []]),
                new MessageCatalogue('en')
            )->getMessages('a')
        );
    }

    public function testGetEmptyResult(): void
    {
        $this->assertEquals(
            new MessageCatalogue('en'),
            $this->createOperation(
                new MessageCatalogue('en'),
                new MessageCatalogue('en')
            )->getResult()
        );
    }

    abstract protected function createOperation(MessageCatalogueInterface $source, MessageCatalogueInterface $target);
}
