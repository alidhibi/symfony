<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests\Field;

use Symfony\Component\DomCrawler\Field\FileFormField;

class FileFormFieldTest extends FormFieldTestCase
{
    public function testInitialize(): void
    {
        $node = $this->createNode('input', '', ['type' => 'file']);
        $field = new FileFormField($node);

        $this->assertEquals(['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0], $field->getValue(), '->initialize() sets the value of the field to no file uploaded');

        $node = $this->createNode('textarea', '');
        try {
            new FileFormField($node);
            $this->fail('->initialize() throws a \LogicException if the node is not an input field');
        } catch (\LogicException $logicException) {
            $this->assertTrue(true, '->initialize() throws a \LogicException if the node is not an input field');
        }

        $node = $this->createNode('input', '', ['type' => 'text']);
        try {
            new FileFormField($node);
            $this->fail('->initialize() throws a \LogicException if the node is not a file input field');
        } catch (\LogicException $logicException) {
            $this->assertTrue(true, '->initialize() throws a \LogicException if the node is not a file input field');
        }
    }

    /**
     * @dataProvider getSetValueMethods
     */
    public function testSetValue(string $method): void
    {
        $node = $this->createNode('input', '', ['type' => 'file']);
        $field = new FileFormField($node);

        $field->$method(null);
        $this->assertEquals(['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0], $field->getValue(), sprintf('->%s() clears the uploaded file if the value is null', $method));

        $field->$method(__FILE__);
        $value = $field->getValue();

        $this->assertEquals(basename(__FILE__), $value['name'], sprintf('->%s() sets the name of the file field', $method));
        $this->assertEquals('', $value['type'], sprintf('->%s() sets the type of the file field', $method));
        $this->assertIsString($value['tmp_name'], sprintf('->%s() sets the tmp_name of the file field', $method));
        $this->assertFileExists($value['tmp_name'], sprintf('->%s() creates a copy of the file at the tmp_name path', $method));
        $this->assertEquals(0, $value['error'], sprintf('->%s() sets the error of the file field', $method));
        $this->assertEquals(filesize(__FILE__), $value['size'], sprintf('->%s() sets the size of the file field', $method));

        $origInfo = pathinfo(__FILE__);
        $tmpInfo = pathinfo($value['tmp_name']);
        $this->assertEquals(
            $origInfo['extension'],
            $tmpInfo['extension'],
            sprintf('->%s() keeps the same file extension in the tmp_name copy', $method)
        );

        $field->$method(__DIR__.'/../Fixtures/no-extension');
        $value = $field->getValue();

        $this->assertArrayNotHasKey(
            'extension',
            pathinfo($value['tmp_name']),
            sprintf('->%s() does not add a file extension in the tmp_name copy', $method)
        );
    }

    public function getSetValueMethods(): array
    {
        return [
            ['setValue'],
            ['upload'],
        ];
    }

    public function testSetErrorCode(): void
    {
        $node = $this->createNode('input', '', ['type' => 'file']);
        $field = new FileFormField($node);

        $field->setErrorCode(\UPLOAD_ERR_FORM_SIZE);

        $value = $field->getValue();
        $this->assertEquals(\UPLOAD_ERR_FORM_SIZE, $value['error'], '->setErrorCode() sets the file input field error code');

        try {
            $field->setErrorCode('foobar');
            $this->fail('->setErrorCode() throws a \InvalidArgumentException if the error code is not valid');
        } catch (\InvalidArgumentException $invalidArgumentException) {
            $this->assertTrue(true, '->setErrorCode() throws a \InvalidArgumentException if the error code is not valid');
        }
    }

    public function testSetRawFilePath(): void
    {
        $node = $this->createNode('input', '', ['type' => 'file']);
        $field = new FileFormField($node);
        $field->setFilePath(__FILE__);

        $this->assertEquals(__FILE__, $field->getValue());
    }
}
