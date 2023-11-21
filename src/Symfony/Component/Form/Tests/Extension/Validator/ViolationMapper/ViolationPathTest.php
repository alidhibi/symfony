<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\ViolationMapper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationPath;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ViolationPathTest extends TestCase
{
    public function providePaths(): array
    {
        return [
            ['children[address]', [
                ['address', true, true],
            ]],
            ['children[address].children[street]', [
                ['address', true, true],
                ['street', true, true],
            ]],
            ['children[address][street]', [
                ['address', true, true],
                ['street', true, true],
            ], 'children[address].children[street]'],
            ['children[address].data', [
                ['address', true, true],
            ], 'children[address]'],
            ['children[address].data.street', [
                ['address', true, true],
                ['street', false, false],
            ]],
            ['children[address].data[street]', [
                ['address', true, true],
                ['street', false, true],
            ]],
            ['children[address].children[street].data.name', [
                ['address', true, true],
                ['street', true, true],
                ['name', false, false],
            ]],
            ['children[address].children[street].data[name]', [
                ['address', true, true],
                ['street', true, true],
                ['name', false, true],
            ]],
            ['data.address', [
                ['address', false, false],
            ]],
            ['data[address]', [
                ['address', false, true],
            ]],
            ['data.address.street', [
                ['address', false, false],
                ['street', false, false],
            ]],
            ['data[address].street', [
                ['address', false, true],
                ['street', false, false],
            ]],
            ['data.address[street]', [
                ['address', false, false],
                ['street', false, true],
            ]],
            ['data[address][street]', [
                ['address', false, true],
                ['street', false, true],
            ]],
            // A few invalid examples
            ['data', [], ''],
            ['children', [], ''],
            ['children.address', [], ''],
            ['children.address[street]', [], ''],
        ];
    }

    /**
     * @dataProvider providePaths
     */
    public function testCreatePath(string $string, array $entries, string $slicedPath = null): void
    {
        if (null === $slicedPath) {
            $slicedPath = $string;
        }

        $path = new ViolationPath($string);

        $this->assertSame($slicedPath, $path->__toString());
        $this->assertCount(\count($entries), $path->getElements());
        $this->assertSame(\count($entries), $path->getLength());

        foreach ($entries as $index => $entry) {
            $this->assertEquals($entry[0], $path->getElement($index));
            $this->assertSame($entry[1], $path->mapsForm($index));
            $this->assertSame($entry[2], $path->isIndex($index));
            $this->assertSame(!$entry[2], $path->isProperty($index));
        }
    }

    public function provideParents(): array
    {
        return [
            ['children[address]', null],
            ['children[address].children[street]', 'children[address]'],
            ['children[address].data.street', 'children[address]'],
            ['children[address].data[street]', 'children[address]'],
            ['data.address', null],
            ['data.address.street', 'data.address'],
            ['data.address[street]', 'data.address'],
            ['data[address].street', 'data[address]'],
            ['data[address][street]', 'data[address]'],
        ];
    }

    /**
     * @dataProvider provideParents
     */
    public function testGetParent(string $violationPath, ?string $parentPath): void
    {
        $path = new ViolationPath($violationPath);
        $parent = null === $parentPath ? null : new ViolationPath($parentPath);

        $this->assertEquals($parent, $path->getParent());
    }

    public function testGetElement(): void
    {
        $path = new ViolationPath('children[address].data[street].name');

        $this->assertEquals('street', $path->getElement(1));
    }

    public function testGetElementDoesNotAcceptInvalidIndices(): void
    {
        $this->expectException('OutOfBoundsException');
        $path = new ViolationPath('children[address].data[street].name');

        $path->getElement(3);
    }

    public function testGetElementDoesNotAcceptNegativeIndices(): void
    {
        $this->expectException('OutOfBoundsException');
        $path = new ViolationPath('children[address].data[street].name');

        $path->getElement(-1);
    }

    public function testIsProperty(): void
    {
        $path = new ViolationPath('children[address].data[street].name');

        $this->assertFalse($path->isProperty(1));
        $this->assertTrue($path->isProperty(2));
    }

    public function testIsPropertyDoesNotAcceptInvalidIndices(): void
    {
        $this->expectException('OutOfBoundsException');
        $path = new ViolationPath('children[address].data[street].name');

        $path->isProperty(3);
    }

    public function testIsPropertyDoesNotAcceptNegativeIndices(): void
    {
        $this->expectException('OutOfBoundsException');
        $path = new ViolationPath('children[address].data[street].name');

        $path->isProperty(-1);
    }

    public function testIsIndex(): void
    {
        $path = new ViolationPath('children[address].data[street].name');

        $this->assertTrue($path->isIndex(1));
        $this->assertFalse($path->isIndex(2));
    }

    public function testIsIndexDoesNotAcceptInvalidIndices(): void
    {
        $this->expectException('OutOfBoundsException');
        $path = new ViolationPath('children[address].data[street].name');

        $path->isIndex(3);
    }

    public function testIsIndexDoesNotAcceptNegativeIndices(): void
    {
        $this->expectException('OutOfBoundsException');
        $path = new ViolationPath('children[address].data[street].name');

        $path->isIndex(-1);
    }

    public function testMapsForm(): void
    {
        $path = new ViolationPath('children[address].data[street].name');

        $this->assertTrue($path->mapsForm(0));
        $this->assertFalse($path->mapsForm(1));
        $this->assertFalse($path->mapsForm(2));
    }

    public function testMapsFormDoesNotAcceptInvalidIndices(): void
    {
        $this->expectException('OutOfBoundsException');
        $path = new ViolationPath('children[address].data[street].name');

        $path->mapsForm(3);
    }

    public function testMapsFormDoesNotAcceptNegativeIndices(): void
    {
        $this->expectException('OutOfBoundsException');
        $path = new ViolationPath('children[address].data[street].name');

        $path->mapsForm(-1);
    }
}
