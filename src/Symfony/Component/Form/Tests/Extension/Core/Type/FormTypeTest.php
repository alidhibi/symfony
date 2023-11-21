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

use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;
use Symfony\Component\PropertyAccess\PropertyPath;

class FormTest_AuthorWithoutRefSetter
{
    protected $reference;

    protected $referenceCopy;

    public function __construct($reference)
    {
        $this->reference = $reference;
        $this->referenceCopy = $reference;
    }

    // The returned object should be modified by reference without having
    // to provide a setReference() method
    public function getReference()
    {
        return $this->reference;
    }

    // The returned object is a copy, so setReferenceCopy() must be used
    // to update it
    public function getReferenceCopy()
    {
        return \is_object($this->referenceCopy) ? clone $this->referenceCopy : $this->referenceCopy;
    }

    public function setReferenceCopy($reference): void
    {
        $this->referenceCopy = $reference;
    }
}

class FormTypeTest extends BaseTypeTest
{
    final const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\FormType';

    public function testCreateFormInstances(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Form\Form::class, $this->factory->create(static::TESTED_TYPE));
    }

    public function testPassRequiredAsOption(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['required' => false]);

        $this->assertFalse($form->isRequired());

        $form = $this->factory->create(static::TESTED_TYPE, null, ['required' => true]);

        $this->assertTrue($form->isRequired());
    }

    public function testSubmittedDataIsTrimmedBeforeTransforming(): void
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'reverse[a]' => 'a',
            ]))
            ->setCompound(false)
            ->getForm();

        $form->submit(' a ');

        $this->assertEquals('a', $form->getViewData());
        $this->assertEquals('reverse[a]', $form->getData());
    }

    public function testSubmittedDataIsNotTrimmedBeforeTransformingIfNoTrimming(): void
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, ['trim' => false])
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'reverse[ a ]' => ' a ',
            ]))
            ->setCompound(false)
            ->getForm();

        $form->submit(' a ');

        $this->assertEquals(' a ', $form->getViewData());
        $this->assertEquals('reverse[ a ]', $form->getData());
    }

    public function testNonReadOnlyFormWithReadOnlyParentIsReadOnly(): void
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE, null, ['attr' => ['readonly' => true]])
            ->add('child', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertTrue($view['child']->vars['attr']['readonly']);
    }

    public function testReadOnlyFormWithNonReadOnlyParentIsReadOnly(): void
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE)
            ->add('child', static::TESTED_TYPE, ['attr' => ['readonly' => true]])
            ->getForm()
            ->createView();

        $this->assertTrue($view['child']->vars['attr']['readonly']);
    }

    public function testNonReadOnlyFormWithNonReadOnlyParentIsNotReadOnly(): void
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE)
            ->add('child', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertArrayNotHasKey('readonly', $view['child']->vars['attr']);
    }

    public function testPassMaxLengthToView(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, ['attr' => ['maxlength' => 10]])
            ->createView();

        $this->assertSame(10, $view->vars['attr']['maxlength']);
    }

    public function testDataClassMayBeNull(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Form\FormBuilderInterface::class, $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => null,
        ]));
    }

    public function testDataClassMayBeAbstractClass(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Form\FormBuilderInterface::class, $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => \Symfony\Component\Form\Tests\Fixtures\AbstractAuthor::class,
        ]));
    }

    public function testDataClassMayBeInterface(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Form\FormBuilderInterface::class, $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => \Symfony\Component\Form\Tests\Fixtures\AuthorInterface::class,
        ]));
    }

    public function testDataClassMustBeValidClassOrInterface(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\InvalidArgumentException::class);
        $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => 'foobar',
        ]);
    }

    public function testSubmitNullUsesDefaultEmptyData(array $emptyData = [], $expectedData = []): void
    {
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }

    public function testSubmitWithEmptyDataCreatesObjectIfClassAvailable(): void
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => \Symfony\Component\Form\Tests\Fixtures\Author::class,
            'required' => false,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        // partially empty, still an object is created
        $form->submit(['firstName' => 'Bernhard', 'lastName' => '']);

        $author = new Author();
        $author->firstName = 'Bernhard';
        $author->setLastName('');

        $this->assertEquals($author, $form->getData());
    }

    public function testSubmitWithDefaultDataDontCreateObject(): void
    {
        $defaultAuthor = new Author();
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            // data class is inferred from the passed object
            'data' => $defaultAuthor,
            'required' => false,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        // partially empty
        $form->submit(['firstName' => 'Bernhard', 'lastName' => '']);

        $author = new Author();
        $author->firstName = 'Bernhard';
        $author->setLastName('');

        $this->assertEquals($author, $form->getData());
        $this->assertSame($defaultAuthor, $form->getData());
    }

    public function testSubmitWithEmptyDataCreatesArrayIfDataClassIsNull(): void
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => null,
            'required' => false,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(['firstName' => 'Bernhard']);

        $this->assertSame(['firstName' => 'Bernhard'], $form->getData());
    }

    public function testSubmitEmptyWithEmptyDataDontCreateObjectIfNotRequired(): void
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => \Symfony\Component\Form\Tests\Fixtures\Author::class,
            'required' => false,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(['firstName' => '', 'lastName' => '']);

        $this->assertNull($form->getData());
    }

    public function testSubmitEmptyWithEmptyDataCreatesObjectIfRequired(): void
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => \Symfony\Component\Form\Tests\Fixtures\Author::class,
            'required' => true,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(['firstName' => '', 'lastName' => '']);

        $this->assertEquals(new Author(), $form->getData());
    }

    /*
     * We need something to write the field values into
     */
    public function testSubmitWithEmptyDataStoresArrayIfNoClassAvailable(): void
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(['firstName' => 'Bernhard']);

        $this->assertSame(['firstName' => 'Bernhard'], $form->getData());
    }

    public function testSubmitWithEmptyDataPassesEmptyStringToTransformerIfNotCompound(): void
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addViewTransformer(new FixedDataTransformer([
                // required for the initial, internal setData(null)
                '' => 'null',
                // required to test that submit(null) is converted to ''
                'empty' => '',
            ]))
            ->setCompound(false)
            ->getForm();

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('null', $form->getViewData());

        $form->submit(null);

        $this->assertSame('empty', $form->getData());
        $this->assertSame('empty', $form->getNormData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitWithEmptyDataUsesEmptyDataOption(): void
    {
        $author = new Author();

        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => \Symfony\Component\Form\Tests\Fixtures\Author::class,
            'empty_data' => $author,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());
        $this->assertNull($form->getViewData());

        $form->submit(['firstName' => 'Bernhard']);

        $this->assertSame($author, $form->getData());
        $this->assertEquals('Bernhard', $author->firstName);
    }

    public function testAttributesException(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, ['attr' => '']);
    }

    public function testNameCanBeEmptyString(): void
    {
        $form = $this->factory->createNamed('', static::TESTED_TYPE);

        $this->assertEquals('', $form->getName());
    }

    public function testSubformDoesntCallSettersForReferences(): void
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder(static::TESTED_TYPE, $author);
        $builder->add('reference', static::TESTED_TYPE, [
            'data_class' => \Symfony\Component\Form\Tests\Fixtures\Author::class,
        ]);
        $builder->get('reference')->add('firstName', TextTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $form->submit([
            // reference has a getter, but no setter
            'reference' => [
                'firstName' => 'Foo',
            ],
        ]);

        $this->assertEquals('Foo', $author->getReference()->firstName);
    }

    public function testSubformCallsSettersIfTheObjectChanged(): void
    {
        // no reference
        $author = new FormTest_AuthorWithoutRefSetter(null);
        $newReference = new Author();

        $builder = $this->factory->createBuilder(static::TESTED_TYPE, $author);
        $builder->add('referenceCopy', static::TESTED_TYPE, [
            'data_class' => \Symfony\Component\Form\Tests\Fixtures\Author::class,
        ]);
        $builder->get('referenceCopy')->add('firstName', TextTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $form['referenceCopy']->setData($newReference); // new author object

        $form->submit([
            // referenceCopy has a getter that returns a copy
            'referenceCopy' => [
                'firstName' => 'Foo',
        ],
        ]);

        $this->assertEquals('Foo', $author->getReferenceCopy()->firstName);
    }

    public function testSubformCallsSettersIfByReferenceIsFalse(): void
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder(static::TESTED_TYPE, $author);
        $builder->add('referenceCopy', static::TESTED_TYPE, [
            'data_class' => \Symfony\Component\Form\Tests\Fixtures\Author::class,
            'by_reference' => false,
        ]);
        $builder->get('referenceCopy')->add('firstName', TextTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $form->submit([
            // referenceCopy has a getter that returns a copy
            'referenceCopy' => [
                'firstName' => 'Foo',
            ],
        ]);

        // firstName can only be updated if setReferenceCopy() was called
        $this->assertEquals('Foo', $author->getReferenceCopy()->firstName);
    }

    public function testSubformCallsSettersIfReferenceIsScalar(): void
    {
        $author = new FormTest_AuthorWithoutRefSetter('scalar');

        $builder = $this->factory->createBuilder(static::TESTED_TYPE, $author);
        $builder->add('referenceCopy', static::TESTED_TYPE);
        $builder->get('referenceCopy')->addViewTransformer(new CallbackTransformer(
            static function () : void {
            },
            static fn($value): string => 'foobar'
        ));
        $form = $builder->getForm();

        $form->submit([
            'referenceCopy' => [], // doesn't matter actually
        ]);

        // firstName can only be updated if setReferenceCopy() was called
        $this->assertEquals('foobar', $author->getReferenceCopy());
    }

    public function testSubformAlwaysInsertsIntoArrays(): void
    {
        $ref1 = new Author();
        $ref2 = new Author();
        $author = ['referenceCopy' => $ref1];

        $builder = $this->factory->createBuilder(static::TESTED_TYPE);
        $builder->setData($author);
        $builder->add('referenceCopy', static::TESTED_TYPE);
        $builder->get('referenceCopy')->addViewTransformer(new CallbackTransformer(
            static function () : void {
            },
            static fn($value): \Symfony\Component\Form\Tests\Fixtures\Author => $ref2
        ));
        $form = $builder->getForm();

        $form->submit([
            'referenceCopy' => [], // doesn't matter actually
        ]);

        // the new reference was inserted into the array
        $author = $form->getData();
        $this->assertSame($ref2, $author['referenceCopy']);
    }

    public function testPassMultipartTrueIfAnyChildIsMultipartToView(): void
    {
        $view = $this->factory->createBuilder(static::TESTED_TYPE)
            ->add('foo', TextTypeTest::TESTED_TYPE)
            ->add('bar', FileTypeTest::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertTrue($view->vars['multipart']);
    }

    public function testViewIsNotRenderedByDefault(): void
    {
        $view = $this->factory->createBuilder(static::TESTED_TYPE)
            ->add('foo', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertFalse($view->isRendered());
    }

    public function testErrorBubblingIfCompound(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        $this->assertTrue($form->getConfig()->getErrorBubbling());
    }

    public function testNoErrorBubblingIfNotCompound(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'compound' => false,
        ]);

        $this->assertFalse($form->getConfig()->getErrorBubbling());
    }

    public function testOverrideErrorBubbling(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'compound' => false,
            'error_bubbling' => true,
        ]);

        $this->assertTrue($form->getConfig()->getErrorBubbling());
    }

    public function testPropertyPath(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'property_path' => 'foo',
        ]);

        $this->assertEquals(new PropertyPath('foo'), $form->getPropertyPath());
        $this->assertTrue($form->getConfig()->getMapped());
    }

    public function testPropertyPathNullImpliesDefault(): void
    {
        $form = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'property_path' => null,
        ]);

        $this->assertEquals(new PropertyPath('name'), $form->getPropertyPath());
        $this->assertTrue($form->getConfig()->getMapped());
    }

    public function testNotMapped(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'property_path' => 'foo',
            'mapped' => false,
        ]);

        $this->assertEquals(new PropertyPath('foo'), $form->getPropertyPath());
        $this->assertFalse($form->getConfig()->getMapped());
    }

    public function testViewValidNotSubmitted(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->createView();

        $this->assertTrue($view->vars['valid']);
    }

    public function testViewNotValidSubmitted(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit([]);
        $form->addError(new FormError('An error'));

        $this->assertFalse($form->createView()->vars['valid']);
    }

    public function testViewSubmittedNotSubmitted(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->createView();

        $this->assertFalse($view->vars['submitted']);
    }

    public function testViewSubmittedSubmitted(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit([]);

        $this->assertTrue($form->createView()->vars['submitted']);
    }

    public function testDataOptionSupersedesSetDataCalls(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'data' => 'default',
            'compound' => false,
        ]);

        $form->setData('foobar');

        $this->assertSame('default', $form->getData());
    }

    public function testPassedDataSupersedesSetDataCalls(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, 'default', [
            'compound' => false,
        ]);

        $form->setData('foobar');

        $this->assertSame('default', $form->getData());
    }

    public function testDataOptionSupersedesSetDataCallsIfNull(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'data' => null,
            'compound' => false,
        ]);

        $form->setData('foobar');

        $this->assertNull($form->getData());
    }

    public function testNormDataIsPassedToView(): void
    {
        $view = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addModelTransformer(new FixedDataTransformer([
                'foo' => 'bar',
            ]))
            ->addViewTransformer(new FixedDataTransformer([
                'bar' => 'baz',
            ]))
            ->setData('foo')
            ->getForm()
            ->createView();

        $this->assertSame('bar', $view->vars['data']);
        $this->assertSame('baz', $view->vars['value']);
    }

    // https://github.com/symfony/symfony/issues/6862
    public function testPassZeroLabelToView(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
                'label' => '0',
            ])
            ->createView();

        $this->assertSame('0', $view->vars['label']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null): void
    {
        parent::testSubmitNull([], [], []);
    }
}
