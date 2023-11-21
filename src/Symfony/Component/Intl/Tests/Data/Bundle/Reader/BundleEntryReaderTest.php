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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReader;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BundleEntryReaderTest extends TestCase
{
    final const RES_DIR = '/res/dir';

    private \Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReader $reader;

    /**
     * @var MockObject
     */
    private $readerImpl;

    private static array $data = [
        'Entries' => [
            'Foo' => 'Bar',
            'Bar' => 'Baz',
        ],
        'Foo' => 'Bar',
        'Version' => '2.0',
    ];

    private static array $fallbackData = [
        'Entries' => [
            'Foo' => 'Foo',
            'Bam' => 'Lah',
        ],
        'Baz' => 'Foo',
        'Version' => '1.0',
    ];

    private static array $mergedData = [
        // no recursive merging -> too complicated
        'Entries' => [
            'Foo' => 'Bar',
            'Bar' => 'Baz',
        ],
        'Baz' => 'Foo',
        'Version' => '2.0',
        'Foo' => 'Bar',
    ];

    protected function setUp()
    {
        $this->readerImpl = $this->getMockBuilder(\Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface::class)->getMock();
        $this->reader = new BundleEntryReader($this->readerImpl);
    }

    public function testForwardCallToRead(): void
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::$data);

        $this->assertSame(self::$data, $this->reader->read(self::RES_DIR, 'root'));
    }

    public function testReadEntireDataFileIfNoIndicesGiven(): void
    {
        $this->readerImpl->expects($this->exactly(2))
            ->method('read')
            ->withConsecutive(
                [self::RES_DIR, 'en'],
                [self::RES_DIR, 'root']
            )
            ->willReturnOnConsecutiveCalls(self::$data, self::$fallbackData);

        $this->assertSame(self::$mergedData, $this->reader->readEntry(self::RES_DIR, 'en', []));
    }

    public function testReadExistingEntry(): void
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::$data);

        $this->assertSame('Bar', $this->reader->readEntry(self::RES_DIR, 'root', ['Entries', 'Foo']));
    }

    public function testReadNonExistingEntry(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MissingResourceException::class);
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::$data);

        $this->reader->readEntry(self::RES_DIR, 'root', ['Entries', 'NonExisting']);
    }

    public function testFallbackIfEntryDoesNotExist(): void
    {
        $this->readerImpl->expects($this->exactly(2))
            ->method('read')
            ->withConsecutive(
                [self::RES_DIR, 'en_GB'],
                [self::RES_DIR, 'en']
            )
            ->willReturnOnConsecutiveCalls(self::$data, self::$fallbackData);

        $this->assertSame('Lah', $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam']));
    }

    public function testDontFallbackIfEntryDoesNotExistAndFallbackDisabled(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MissingResourceException::class);
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn(self::$data);

        $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam'], false);
    }

    public function testFallbackIfLocaleDoesNotExist(): void
    {
        $this->readerImpl->expects($this->exactly(2))
            ->method('read')
            ->withConsecutive(
                [self::RES_DIR, 'en_GB'],
                [self::RES_DIR, 'en']
            )
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new ResourceBundleNotFoundException()),
                self::$fallbackData
            );

        $this->assertSame('Lah', $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam']));
    }

    public function testDontFallbackIfLocaleDoesNotExistAndFallbackDisabled(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MissingResourceException::class);
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willThrowException(new ResourceBundleNotFoundException());

        $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam'], false);
    }

    public function provideMergeableValues(): array
    {
        return [
            ['foo', null, 'foo'],
            [null, 'foo', 'foo'],
            [['foo', 'bar'], null, ['foo', 'bar']],
            [['foo', 'bar'], [], ['foo', 'bar']],
            [null, ['baz'], ['baz']],
            [[], ['baz'], ['baz']],
            [['foo', 'bar'], ['baz'], ['baz', 'foo', 'bar']],
        ];
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeDataWithFallbackData(string|array|null $childData, string|array|null $parentData, string|array $result): void
    {
        if (null === $childData || \is_array($childData)) {
            $this->readerImpl->expects($this->exactly(2))
                ->method('read')
                ->withConsecutive(
                    [self::RES_DIR, 'en'],
                    [self::RES_DIR, 'root']
                )
                ->willReturnOnConsecutiveCalls($childData, $parentData);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->willReturn($childData);
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en', [], true));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testDontMergeDataIfFallbackDisabled(string|array|null $childData, string|array|null $parentData, string|array $result): void
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn($childData);

        $this->assertSame($childData, $this->reader->readEntry(self::RES_DIR, 'en_GB', [], false));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeExistingEntryWithExistingFallbackEntry(string|array|null $childData, string|array|null $parentData, string|array $result): void
    {
        if (null === $childData || \is_array($childData)) {
            $this->readerImpl->expects($this->exactly(2))
                ->method('read')
                ->withConsecutive(
                    [self::RES_DIR, 'en'],
                    [self::RES_DIR, 'root']
                )
                ->willReturnOnConsecutiveCalls(
                    ['Foo' => ['Bar' => $childData]],
                    ['Foo' => ['Bar' => $parentData]]
                );
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en', ['Foo', 'Bar'], true));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeNonExistingEntryWithExistingFallbackEntry(string|array|null $childData, string|array|null $parentData, string|array $result): void
    {
        $this->readerImpl
            ->method('read')
            ->withConsecutive(
                [self::RES_DIR, 'en_GB'],
                [self::RES_DIR, 'en']
            )
            ->willReturnOnConsecutiveCalls(['Foo' => 'Baz'], ['Foo' => ['Bar' => $parentData]]);

        $this->assertSame($parentData, $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeExistingEntryWithNonExistingFallbackEntry(string|array|null $childData, string|array|null $parentData, string|array $result): void
    {
        if (null === $childData || \is_array($childData)) {
            $this->readerImpl
                ->method('read')
                ->withConsecutive(
                    [self::RES_DIR, 'en_GB'],
                    [self::RES_DIR, 'en']
                )
                ->willReturnOnConsecutiveCalls(['Foo' => ['Bar' => $childData]], ['Foo' => 'Bar']);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($childData, $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
    }

    public function testFailIfEntryFoundNeitherInParentNorChild(): void
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MissingResourceException::class);
        $this->readerImpl
            ->method('read')
            ->withConsecutive(
                [self::RES_DIR, 'en_GB'],
                [self::RES_DIR, 'en']
            )
            ->willReturnOnConsecutiveCalls(['Foo' => 'Baz'], ['Foo' => 'Bar']);

        $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true);
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeTraversables(string|array|null $childData, string|array|null $parentData, string|array $result): void
    {
        $parentData = \is_array($parentData) ? new \ArrayObject($parentData) : $parentData;
        $childData = \is_array($childData) ? new \ArrayObject($childData) : $childData;

        if (null === $childData || $childData instanceof \ArrayObject) {
            $this->readerImpl
                ->method('read')
                ->withConsecutive(
                    [self::RES_DIR, 'en_GB'],
                    [self::RES_DIR, 'en']
                )
                ->willReturnOnConsecutiveCalls(['Foo' => ['Bar' => $childData]], ['Foo' => ['Bar' => $parentData]]);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testFollowLocaleAliases(string|array|null $childData, string|array|null $parentData, string|array $result): void
    {
        $this->reader->setLocaleAliases(['mo' => 'ro_MD']);

        if (null === $childData || \is_array($childData)) {
            $this->readerImpl
                ->method('read')
                ->withConsecutive(
                    [self::RES_DIR, 'ro_MD'],
                    // Read fallback locale of aliased locale ("ro_MD" -> "ro")
                    [self::RES_DIR, 'ro']
                )
                ->willReturnOnConsecutiveCalls(['Foo' => ['Bar' => $childData]], ['Foo' => ['Bar' => $parentData]]);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'ro_MD')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'mo', ['Foo', 'Bar'], true));
    }
}
