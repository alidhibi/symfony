<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\AuthorType;

class CollectionTypeTest extends BaseTypeTest
{
    final const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\CollectionType';

    public function testContainsNoChildByDefault(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ]);

        $this->assertCount(0, $form);
    }

    public function testSetDataAdjustsSize(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'entry_options' => [
                'attr' => ['maxlength' => 20],
            ],
        ]);
        $form->setData(['foo@foo.com', 'foo@bar.com']);

        $this->assertInstanceOf(\Symfony\Component\Form\Form::class, $form[0]);
        $this->assertInstanceOf(\Symfony\Component\Form\Form::class, $form[1]);
        $this->assertCount(2, $form);
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals('foo@bar.com', $form[1]->getData());
        $formAttrs0 = $form[0]->getConfig()->getOption('attr');
        $formAttrs1 = $form[1]->getConfig()->getOption('attr');
        $this->assertEquals(20, $formAttrs0['maxlength']);
        $this->assertEquals(20, $formAttrs1['maxlength']);

        $form->setData(['foo@baz.com']);
        $this->assertInstanceOf(\Symfony\Component\Form\Form::class, $form[0]);
        $this->assertArrayNotHasKey(1, $form);
        $this->assertCount(1, $form);
        $this->assertEquals('foo@baz.com', $form[0]->getData());
        $formAttrs0 = $form[0]->getConfig()->getOption('attr');
        $this->assertEquals(20, $formAttrs0['maxlength']);
    }

    public function testThrowsExceptionIfObjectIsNotTraversable(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ]);
        $this->expectException(\Symfony\Component\Form\Exception\UnexpectedTypeException::class);
        $form->setData(new \stdClass());
    }

    public function testNotResizedIfSubmittedWithMissingData(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ]);
        $form->setData(['foo@foo.com', 'bar@bar.com']);
        $form->submit(['foo@bar.com']);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals('foo@bar.com', $form[0]->getData());
        $this->assertEquals('', $form[1]->getData());
    }

    public function testResizedDownIfSubmittedWithMissingDataAndAllowDelete(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_delete' => true,
        ]);
        $form->setData(['foo@foo.com', 'bar@bar.com']);
        $form->submit(['foo@foo.com']);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(['foo@foo.com'], $form->getData());
    }

    public function testResizedDownIfSubmittedWithEmptyDataAndDeleteEmpty(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_delete' => true,
            'delete_empty' => true,
        ]);

        $form->setData(['foo@foo.com', 'bar@bar.com']);
        $form->submit(['foo@foo.com', '']);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(['foo@foo.com'], $form->getData());
    }

    public function testResizedDownWithDeleteEmptyCallable(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => AuthorType::class,
            'allow_delete' => true,
            'delete_empty' => static fn(Author $obj = null): bool => !$obj instanceof \Symfony\Component\Form\Tests\Fixtures\Author || empty($obj->firstName),
        ]);

        $form->setData([new Author('Bob'), new Author('Alice')]);
        $form->submit([['firstName' => 'Bob'], ['firstName' => '']]);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals(new Author('Bob'), $form[0]->getData());
        $this->assertEquals([new Author('Bob')], $form->getData());
    }

    public function testResizedDownIfSubmittedWithCompoundEmptyDataDeleteEmptyAndNoDataClass(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => AuthorType::class,
            // If the field is not required, no new Author will be created if the
            // form is completely empty
            'entry_options' => ['data_class' => null],
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => static fn($author): bool => empty($author['firstName']),
        ]);
        $form->setData([['firstName' => 'first', 'lastName' => 'last']]);
        $form->submit([
            ['firstName' => 's_first', 'lastName' => 's_last'],
            ['firstName' => '', 'lastName' => ''],
        ]);
        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals(['firstName' => 's_first', 'lastName' => 's_last'], $form[0]->getData());
        $this->assertEquals([['firstName' => 's_first', 'lastName' => 's_last']], $form->getData());
    }

    public function testDontAddEmptyDataIfDeleteEmpty(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'delete_empty' => true,
        ]);

        $form->setData(['foo@foo.com']);
        $form->submit(['foo@foo.com', '']);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(['foo@foo.com'], $form->getData());
    }

    public function testNoDeleteEmptyIfDeleteNotAllowed(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_delete' => false,
            'delete_empty' => true,
        ]);

        $form->setData(['foo@foo.com']);
        $form->submit(['']);

        $this->assertTrue($form->has('0'));
        $this->assertEquals('', $form[0]->getData());
    }

    public function testResizedDownIfSubmittedWithCompoundEmptyDataAndDeleteEmpty(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => \Symfony\Component\Form\Tests\Fixtures\AuthorType::class,
            // If the field is not required, no new Author will be created if the
            // form is completely empty
            'entry_options' => ['required' => false],
            'allow_add' => true,
            'delete_empty' => true,
        ]);

        $form->setData([new Author('first', 'last')]);
        $form->submit([
            ['firstName' => 's_first', 'lastName' => 's_last'],
            ['firstName' => '', 'lastName' => ''],
        ]);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals(new Author('s_first', 's_last'), $form[0]->getData());
        $this->assertEquals([new Author('s_first', 's_last')], $form->getData());
    }

    public function testNotResizedIfSubmittedWithExtraData(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ]);
        $form->setData(['foo@bar.com']);
        $form->submit(['foo@foo.com', 'bar@bar.com']);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
    }

    public function testResizedUpIfSubmittedWithExtraDataAndAllowAdd(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_add' => true,
        ]);
        $form->setData(['foo@bar.com']);
        $form->submit(['foo@bar.com', 'bar@bar.com']);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals('foo@bar.com', $form[0]->getData());
        $this->assertEquals('bar@bar.com', $form[1]->getData());
        $this->assertEquals(['foo@bar.com', 'bar@bar.com'], $form->getData());
    }

    public function testAllowAddButNoPrototype(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => FormTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => false,
        ]);

        $this->assertFalse($form->has('__name__'));
    }

    public function testPrototypeMultipartPropagation(): void
    {
        $form = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'entry_type' => FileTypeTest::TESTED_TYPE,
                'allow_add' => true,
                'prototype' => true,
            ])
        ;

        $this->assertTrue($form->createView()->vars['multipart']);
    }

    public function testGetDataDoesNotContainsPrototypeNameBeforeDataAreSet(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'prototype' => true,
            'allow_add' => true,
        ]);

        $data = $form->getData();
        $this->assertArrayNotHasKey('__name__', $data);
    }

    public function testGetDataDoesNotContainsPrototypeNameAfterDataAreSet(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
        ]);

        $form->setData(['foobar.png']);

        $data = $form->getData();
        $this->assertArrayNotHasKey('__name__', $data);
    }

    public function testPrototypeNameOption(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => FormTypeTest::TESTED_TYPE,
            'prototype' => true,
            'allow_add' => true,
        ]);

        $this->assertSame('__name__', $form->getConfig()->getAttribute('prototype')->getName(), '__name__ is the default');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => FormTypeTest::TESTED_TYPE,
            'prototype' => true,
            'allow_add' => true,
            'prototype_name' => '__test__',
        ]);

        $this->assertSame('__test__', $form->getConfig()->getAttribute('prototype')->getName());
    }

    public function testPrototypeDefaultLabel(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ]);

        $this->assertSame('__test__label__', $form->createView()->vars['prototype']->vars['label']);
    }

    public function testPrototypeData(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'allow_add' => true,
            'prototype' => true,
            'prototype_data' => 'foo',
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'entry_options' => [
                'data' => 'bar',
                'label' => false,
            ],
        ]);

        $this->assertSame('foo', $form->createView()->vars['prototype']->vars['value']);
        $this->assertFalse($form->createView()->vars['prototype']->vars['label']);
    }

    public function testPrototypeDefaultRequired(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ]);

        $this->assertTrue($form->createView()->vars['prototype']->vars['required']);
    }

    public function testPrototypeSetNotRequired(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'required' => false,
        ]);

        $this->assertFalse($form->createView()->vars['required'], 'collection is not required');
        $this->assertFalse($form->createView()->vars['prototype']->vars['required'], '"prototype" should not be required');
    }

    public function testPrototypeSetNotRequiredIfParentNotRequired(): void
    {
        $child = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ]);

        $parent = $this->factory->create(FormTypeTest::TESTED_TYPE, [], [
            'required' => false,
        ]);

        $child->setParent($parent);
        $this->assertFalse($parent->createView()->vars['required'], 'Parent is not required');
        $this->assertFalse($child->createView()->vars['required'], 'Child is not required');
        $this->assertFalse($child->createView()->vars['prototype']->vars['required'], '"Prototype" should not be required');
    }

    public function testPrototypeNotOverrideRequiredByEntryOptionsInFavorOfParent(): void
    {
        $child = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'entry_options' => [
                'required' => true,
            ],
        ]);

        $parent = $this->factory->create(FormTypeTest::TESTED_TYPE, [], [
            'required' => false,
        ]);

        $child->setParent($parent);

        $this->assertFalse($parent->createView()->vars['required'], 'Parent is not required');
        $this->assertFalse($child->createView()->vars['required'], 'Child is not required');
        $this->assertFalse($child->createView()->vars['prototype']->vars['required'], '"Prototype" should not be required');
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null): void
    {
        parent::testSubmitNull([], [], []);
    }

    public function testSubmitNullUsesDefaultEmptyData(array $emptyData = [], $expectedData = []): void
    {
        // resize form listener always set an empty array
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }
}
