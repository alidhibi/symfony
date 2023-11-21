<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\SubmitButtonBuilder;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class CompoundFormTest extends AbstractFormTest
{
    public function testValidIfAllChildrenAreValid(): void
    {
        $this->form->add($this->getBuilder('firstName')->getForm());
        $this->form->add($this->getBuilder('lastName')->getForm());

        $this->form->submit([
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ]);

        $this->assertTrue($this->form->isValid());
    }

    public function testInvalidIfChildIsInvalid(): void
    {
        $this->form->add($this->getBuilder('firstName')->getForm());
        $this->form->add($this->getBuilder('lastName')->getForm());

        $this->form->submit([
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ]);

        $this->form->get('lastName')->addError(new FormError('Invalid'));

        $this->assertFalse($this->form->isValid());
    }

    public function testDisabledFormsValidEvenIfChildrenInvalid(): void
    {
        $form = $this->getBuilder('person')
            ->setDisabled(true)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->add($this->getBuilder('name'))
            ->getForm();

        $form->submit(['name' => 'Jacques Doe']);

        $form->get('name')->addError(new FormError('Invalid'));

        $this->assertTrue($form->isValid());
    }

    public function testSubmitForwardsNullIfNotClearMissingButValueIsExplicitlyNull(): void
    {
        $child = $this->createForm('firstName', false);

        $this->form->add($child);

        $this->form->submit(['firstName' => null], false);

        $this->assertNull($this->form->get('firstName')->getData());
    }

    public function testSubmitForwardsNullIfValueIsMissing(): void
    {
        $child = $this->createForm('firstName', false);

        $this->form->add($child);

        $this->form->submit([]);

        $this->assertNull($this->form->get('firstName')->getData());
    }

    public function testSubmitDoesNotForwardNullIfNotClearMissing(): void
    {
        $child = $this->createForm('firstName', false);

        $this->form->add($child);

        $this->form->submit([], false);

        $this->assertFalse($child->isSubmitted());
    }

    public function testSubmitDoesNotAddExtraFieldForNullValues(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->getFormFactory();

        $child = $factory->createNamed('file', \Symfony\Component\Form\Extension\Core\Type\FileType::class, null, ['auto_initialize' => false]);

        $this->form->add($child);
        $this->form->submit(['file' => null], false);

        $this->assertCount(0, $this->form->getExtraData());
    }

    public function testClearMissingFlagIsForwarded(): void
    {
        $personForm = $this->createForm('person');

        $firstNameForm = $this->createForm('firstName', false);
        $personForm->add($firstNameForm);

        $lastNameForm = $this->createForm('lastName', false);
        $lastNameForm->setData('last name');

        $personForm->add($lastNameForm);

        $this->form->add($personForm);
        $this->form->submit(['person' => ['firstName' => 'foo']], false);

        $this->assertTrue($firstNameForm->isSubmitted());
        $this->assertSame('foo', $firstNameForm->getData());
        $this->assertFalse($lastNameForm->isSubmitted());
        $this->assertSame('last name', $lastNameForm->getData());
    }

    public function testCloneChildren(): void
    {
        $child = $this->getBuilder('child')->getForm();
        $this->form->add($child);

        $clone = clone $this->form;

        $this->assertNotSame($this->form, $clone);
        $this->assertNotSame($child, $clone['child']);
        $this->assertNotSame($this->form['child'], $clone['child']);
    }

    public function testNotEmptyIfChildNotEmpty(): void
    {
        $child = $this->createForm('name', false);
        $child->setData('foo');

        $this->form->setData(null);
        $this->form->add($child);

        $this->assertFalse($this->form->isEmpty());
    }

    public function testAdd(): void
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(['foo' => $child], $this->form->all());
    }

    public function testAddUsingNameAndType(): void
    {
        $child = $this->getBuilder('foo')->getForm();

        $this->factory->expects($this->once())
            ->method('createNamed')
            ->with('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class, null, [
                'bar' => 'baz',
                'auto_initialize' => false,
            ])
            ->willReturn($child);

        $this->form->add('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class, ['bar' => 'baz']);

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(['foo' => $child], $this->form->all());
    }

    public function testAddUsingIntegerNameAndType(): void
    {
        $child = $this->getBuilder(0)->getForm();

        $this->factory->expects($this->once())
            ->method('createNamed')
            ->with('0', \Symfony\Component\Form\Extension\Core\Type\TextType::class, null, [
                'bar' => 'baz',
                'auto_initialize' => false,
            ])
            ->willReturn($child);

        // in order to make casting unnecessary
        $this->form->add(0, \Symfony\Component\Form\Extension\Core\Type\TextType::class, ['bar' => 'baz']);

        $this->assertTrue($this->form->has(0));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame([0 => $child], $this->form->all());
    }

    public function testAddWithoutType(): void
    {
        $child = $this->getBuilder('foo')->getForm();

        $this->factory->expects($this->once())
            ->method('createNamed')
            ->with('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class)
            ->willReturn($child);

        $this->form->add('foo');

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(['foo' => $child], $this->form->all());
    }

    public function testAddUsingNameButNoType(): void
    {
        $this->form = $this->getBuilder('name', null, '\stdClass')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();

        $child = $this->getBuilder('foo')->getForm();

        $this->factory->expects($this->once())
            ->method('createForProperty')
            ->with('\stdClass', 'foo')
            ->willReturn($child);

        $this->form->add('foo');

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(['foo' => $child], $this->form->all());
    }

    public function testAddUsingNameButNoTypeAndOptions(): void
    {
        $this->form = $this->getBuilder('name', null, '\stdClass')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();

        $child = $this->getBuilder('foo')->getForm();

        $this->factory->expects($this->once())
            ->method('createForProperty')
            ->with('\stdClass', 'foo', null, [
                'bar' => 'baz',
                'auto_initialize' => false,
            ])
            ->willReturn($child);

        $this->form->add('foo', null, ['bar' => 'baz']);

        $this->assertTrue($this->form->has('foo'));
        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(['foo' => $child], $this->form->all());
    }

    public function testAddThrowsExceptionIfAlreadySubmitted(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\AlreadySubmittedException::class);
        $this->form->submit([]);
        $this->form->add($this->getBuilder('foo')->getForm());
    }

    public function testRemove(): void
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);
        $this->form->remove('foo');

        $this->assertNull($child->getParent());
        $this->assertCount(0, $this->form);
    }

    public function testRemoveThrowsExceptionIfAlreadySubmitted(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\AlreadySubmittedException::class);
        $this->form->add($this->getBuilder('foo')->setCompound(false)->getForm());
        $this->form->submit(['foo' => 'bar']);
        $this->form->remove('foo');
    }

    public function testRemoveIgnoresUnknownName(): void
    {
        $this->form->remove('notexisting');

        $this->assertCount(0, $this->form);
    }

    public function testArrayAccess(): void
    {
        $child = $this->getBuilder('foo')->getForm();

        $this->form[] = $child;

        $this->assertArrayHasKey('foo', $this->form);
        $this->assertSame($child, $this->form['foo']);

        unset($this->form['foo']);

        $this->assertArrayNotHasKey('foo', $this->form);
    }

    public function testCountable(): void
    {
        $this->form->add($this->getBuilder('foo')->getForm());
        $this->form->add($this->getBuilder('bar')->getForm());

        $this->assertCount(2, $this->form);
    }

    public function testIterator(): void
    {
        $this->form->add($this->getBuilder('foo')->getForm());
        $this->form->add($this->getBuilder('bar')->getForm());

        $this->assertSame($this->form->all(), iterator_to_array($this->form));
    }

    public function testAddMapsViewDataToFormIfInitialized(): void
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'foo' => 'bar',
            ]))
            ->setData('foo')
            ->getForm();

        $child = $this->getBuilder()->getForm();
        $mapper->expects($this->once())
            ->method('mapDataToForms')
            ->with('bar', $this->isInstanceOf('\RecursiveIteratorIterator'))
            ->willReturnCallback(function ($data, \RecursiveIteratorIterator $iterator) use ($child): void {
                $this->assertInstanceOf(\Symfony\Component\Form\Util\InheritDataAwareIterator::class, $iterator->getInnerIterator());
                $this->assertSame([$child->getName() => $child], iterator_to_array($iterator));
            });

        $form->initialize();
        $form->add($child);
    }

    public function testAddDoesNotMapViewDataToFormIfNotInitialized(): void
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->getForm();

        $child = $this->getBuilder()->getForm();
        $mapper->expects($this->never())
            ->method('mapDataToForms');

        $form->add($child);
    }

    public function testAddDoesNotMapViewDataToFormIfInheritData(): void
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->setInheritData(true)
            ->getForm();

        $child = $this->getBuilder()->getForm();
        $mapper->expects($this->never())
            ->method('mapDataToForms');

        $form->initialize();
        $form->add($child);
    }

    public function testSetDataSupportsDynamicAdditionAndRemovalOfChildren(): void
    {
        $form = $this->getBuilder()
            ->setCompound(true)
            // We test using PropertyPathMapper on purpose. The traversal logic
            // is currently contained in InheritDataAwareIterator, but even
            // if that changes, this test should still function.
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();

        $childToBeRemoved = $this->createForm('removed', false);
        $childToBeAdded = $this->createForm('added', false);
        $child = $this->getBuilder('child', new EventDispatcher())
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($form, $childToBeAdded) : void {
                $form->remove('removed');
                $form->add($childToBeAdded);
            })
            ->getForm();

        $form->add($child);
        $form->add($childToBeRemoved);

        // pass NULL to all children
        $form->setData([]);

        $this->assertFalse($form->has('removed'));
        $this->assertTrue($form->has('added'));
    }

    public function testSetDataMapsViewDataToChildren(): void
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'foo' => 'bar',
            ]))
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->getForm());

        $mapper->expects($this->once())
            ->method('mapDataToForms')
            ->with('bar', $this->isInstanceOf('\RecursiveIteratorIterator'))
            ->willReturnCallback(function ($data, \RecursiveIteratorIterator $iterator) use ($child1, $child2): void {
                $this->assertInstanceOf(\Symfony\Component\Form\Util\InheritDataAwareIterator::class, $iterator->getInnerIterator());
                $this->assertSame(['firstName' => $child1, 'lastName' => $child2], iterator_to_array($iterator));
            });

        $form->setData('foo');
    }

    public function testSetDataDoesNotMapViewDataToChildrenWithLockedSetData(): void
    {
        $mapper = new PropertyPathMapper();
        $viewData = [
            'firstName' => 'Fabien',
            'lastName' => 'Pot',
        ];
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'foo' => $viewData,
            ]))
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->setData('Potencier')->setDataLocked(true)->getForm());

        $form->setData('foo');

        $this->assertSame('Fabien', $form->get('firstName')->getData());
        $this->assertSame('Potencier', $form->get('lastName')->getData());
    }

    public function testSubmitSupportsDynamicAdditionAndRemovalOfChildren(): void
    {
        $form = $this->form;

        $childToBeRemoved = $this->createForm('removed');
        $childToBeAdded = $this->createForm('added');
        $child = $this->getBuilder('child')
            ->addEventListener(FormEvents::PRE_SUBMIT, static function () use ($form, $childToBeAdded) : void {
                $form->remove('removed');
                $form->add($childToBeAdded);
            })
            ->getForm();

        $this->form->add($child);
        $this->form->add($childToBeRemoved);

        // pass NULL to all children
        $this->form->submit([]);

        $this->assertFalse($childToBeRemoved->isSubmitted());
        $this->assertTrue($childToBeAdded->isSubmitted());
    }

    public function testSubmitMapsSubmittedChildrenOntoExistingViewData(): void
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'foo' => 'bar',
            ]))
            ->setData('foo')
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->setCompound(false)->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->setCompound(false)->getForm());

        $mapper->expects($this->once())
            ->method('mapFormsToData')
            ->with($this->isInstanceOf('\RecursiveIteratorIterator'), 'bar')
            ->willReturnCallback(function (\RecursiveIteratorIterator $iterator) use ($child1, $child2): void {
                $this->assertInstanceOf(\Symfony\Component\Form\Util\InheritDataAwareIterator::class, $iterator->getInnerIterator());
                $this->assertSame(['firstName' => $child1, 'lastName' => $child2], iterator_to_array($iterator));
                $this->assertEquals('Bernhard', $child1->getData());
                $this->assertEquals('Schussek', $child2->getData());
            });

        $form->submit([
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ]);
    }

    public function testMapFormsToDataIsNotInvokedIfInheritData(): void
    {
        $mapper = $this->getDataMapper();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->setInheritData(true)
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'foo' => 'bar',
            ]))
            ->getForm();

        $form->add($child1 = $this->getBuilder('firstName')->setCompound(false)->getForm());
        $form->add($child2 = $this->getBuilder('lastName')->setCompound(false)->getForm());

        $mapper->expects($this->never())
            ->method('mapFormsToData');

        $form->submit([
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ]);
    }

    /*
     * https://github.com/symfony/symfony/issues/4480
     */
    public function testSubmitRestoresViewDataIfCompoundAndEmpty(): void
    {
        $mapper = $this->getDataMapper();
        $object = new \stdClass();
        $form = $this->getBuilder('name', null, 'stdClass')
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->setData($object)
            ->getForm();

        $form->submit([]);

        $this->assertSame($object, $form->getData());
    }

    public function testSubmitMapsSubmittedChildrenOntoEmptyData(): void
    {
        $mapper = $this->getDataMapper();
        $object = new \stdClass();
        $form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($mapper)
            ->setEmptyData($object)
            ->setData(null)
            ->getForm();

        $form->add($child = $this->getBuilder('name')->setCompound(false)->getForm());

        $mapper->expects($this->once())
            ->method('mapFormsToData')
            ->with($this->isInstanceOf('\RecursiveIteratorIterator'), $object)
            ->willReturnCallback(function (\RecursiveIteratorIterator $iterator) use ($child): void {
                $this->assertInstanceOf(\Symfony\Component\Form\Util\InheritDataAwareIterator::class, $iterator->getInnerIterator());
                $this->assertSame(['name' => $child], iterator_to_array($iterator));
            });

        $form->submit([
            'name' => 'Bernhard',
        ]);
    }

    public function requestMethodProvider(): array
    {
        return [
            ['POST'],
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
        ];
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequest(string $method): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

        $values = [
            'author' => [
                'name' => 'Bernhard',
                'image' => ['filename' => 'foobar.png'],
            ],
        ];

        $files = [
            'author' => [
                'error' => ['image' => \UPLOAD_ERR_OK],
                'name' => ['image' => 'upload.png'],
                'size' => ['image' => 123],
                'tmp_name' => ['image' => $path],
                'type' => ['image' => 'image/png'],
            ],
        ];

        $request = new Request([], $values, [], [], $files, [
            'REQUEST_METHOD' => $method,
        ]);

        $form = $this->getBuilder('author')
            ->setMethod($method)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('name')->getForm());
        $form->add($this->getBuilder('image')->getForm());

        $form->handleRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', 123, \UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals($file, $form['image']->getData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequestWithEmptyRootFormName(string $method): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

        $values = [
            'name' => 'Bernhard',
            'extra' => 'data',
        ];

        $files = [
            'image' => [
                'error' => \UPLOAD_ERR_OK,
                'name' => 'upload.png',
                'size' => 123,
                'tmp_name' => $path,
                'type' => 'image/png',
            ],
        ];

        $request = new Request([], $values, [], [], $files, [
            'REQUEST_METHOD' => $method,
        ]);

        $form = $this->getBuilder('')
            ->setMethod($method)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('name')->getForm());
        $form->add($this->getBuilder('image')->getForm());

        $form->handleRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', 123, \UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals($file, $form['image']->getData());
        $this->assertEquals(['extra' => 'data'], $form->getExtraData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequestWithSingleChildForm(string $method): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

        $files = [
            'image' => [
                'error' => \UPLOAD_ERR_OK,
                'name' => 'upload.png',
                'size' => 123,
                'tmp_name' => $path,
                'type' => 'image/png',
            ],
        ];

        $request = new Request([], [], [], [], $files, [
            'REQUEST_METHOD' => $method,
        ]);

        $form = $this->getBuilder('image', null, null, ['allow_file_upload' => true])
            ->setMethod($method)
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();

        $form->handleRequest($request);

        $file = new UploadedFile($path, 'upload.png', 'image/png', 123, \UPLOAD_ERR_OK);

        $this->assertEquals($file, $form->getData());

        unlink($path);
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitPostOrPutRequestWithSingleChildFormUploadedFile(string $method): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

        $values = [
            'name' => 'Bernhard',
        ];

        $request = new Request([], $values, [], [], [], [
            'REQUEST_METHOD' => $method,
        ]);

        $form = $this->getBuilder('name')
            ->setMethod($method)
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();

        $form->handleRequest($request);

        $this->assertEquals('Bernhard', $form->getData());

        unlink($path);
    }

    public function testSubmitGetRequest(): void
    {
        $values = [
            'author' => [
                'firstName' => 'Bernhard',
                'lastName' => 'Schussek',
            ],
        ];

        $request = new Request($values, [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
        ]);

        $form = $this->getBuilder('author')
            ->setMethod('GET')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('firstName')->getForm());
        $form->add($this->getBuilder('lastName')->getForm());

        $form->handleRequest($request);

        $this->assertEquals('Bernhard', $form['firstName']->getData());
        $this->assertEquals('Schussek', $form['lastName']->getData());
    }

    public function testSubmitGetRequestWithEmptyRootFormName(): void
    {
        $values = [
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
            'extra' => 'data',
        ];

        $request = new Request($values, [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
        ]);

        $form = $this->getBuilder('')
            ->setMethod('GET')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        $form->add($this->getBuilder('firstName')->getForm());
        $form->add($this->getBuilder('lastName')->getForm());

        $form->handleRequest($request);

        $this->assertEquals('Bernhard', $form['firstName']->getData());
        $this->assertEquals('Schussek', $form['lastName']->getData());
        $this->assertEquals(['extra' => 'data'], $form->getExtraData());
    }

    public function testGetErrors(): void
    {
        $this->form->addError($error1 = new FormError('Error 1'));
        $this->form->addError($error2 = new FormError('Error 2'));

        $errors = $this->form->getErrors();

        $this->assertSame(
             'ERROR: Error 1
ERROR: Error 2
',
             (string) $errors
        );

        $this->assertSame([$error1, $error2], iterator_to_array($errors));
    }

    public function testGetErrorsDeep(): void
    {
        $this->form->addError($error1 = new FormError('Error 1'));
        $this->form->addError($error2 = new FormError('Error 2'));

        $childForm = $this->getBuilder('Child')->getForm();
        $childForm->addError($nestedError = new FormError('Nested Error'));

        $this->form->add($childForm);

        $errors = $this->form->getErrors(true);

        $this->assertSame(
             'ERROR: Error 1
ERROR: Error 2
ERROR: Nested Error
',
             (string) $errors
        );

        $this->assertSame(
             [$error1, $error2, $nestedError],
             iterator_to_array($errors)
        );
    }

    public function testGetErrorsDeepRecursive(): void
    {
        $this->form->addError($error1 = new FormError('Error 1'));
        $this->form->addError($error2 = new FormError('Error 2'));

        $childForm = $this->getBuilder('Child')->getForm();
        $childForm->addError($nestedError = new FormError('Nested Error'));

        $this->form->add($childForm);

        $errors = $this->form->getErrors(true, false);

        $this->assertSame(
             'ERROR: Error 1
ERROR: Error 2
Child:
    ERROR: Nested Error
',
             (string) $errors
        );

        $errorsAsArray = iterator_to_array($errors);

        $this->assertSame($error1, $errorsAsArray[0]);
        $this->assertSame($error2, $errorsAsArray[1]);
        $this->assertInstanceOf(\Symfony\Component\Form\FormErrorIterator::class, $errorsAsArray[2]);

        $nestedErrorsAsArray = iterator_to_array($errorsAsArray[2]);

        $this->assertCount(1, $nestedErrorsAsArray);
        $this->assertSame($nestedError, $nestedErrorsAsArray[0]);
    }

    // Basic cases are covered in SimpleFormTest
    public function testCreateViewWithChildren(): void
    {
        $type = $this->getMockBuilder(\Symfony\Component\Form\ResolvedFormTypeInterface::class)->getMock();
        $type1 = $this->getMockBuilder(\Symfony\Component\Form\ResolvedFormTypeInterface::class)->getMock();
        $type2 = $this->getMockBuilder(\Symfony\Component\Form\ResolvedFormTypeInterface::class)->getMock();
        $options = ['a' => 'Foo', 'b' => 'Bar'];
        $field1 = $this->getBuilder('foo')
            ->setType($type1)
            ->getForm();
        $field2 = $this->getBuilder('bar')
            ->setType($type2)
            ->getForm();
        $view = new FormView();
        $field1View = new FormView();
        $type1
            ->method('createView')
            ->willReturn($field1View);
        $field2View = new FormView();
        $type2
            ->method('createView')
            ->willReturn($field2View);

        $this->form = $this->getBuilder('form', null, null, $options)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->setType($type)
            ->getForm();
        $this->form->add($field1);
        $this->form->add($field2);

        $assertChildViewsEqual = fn(array $childViews): \Closure => function (FormView $view) use ($childViews): void {
            $this->assertSame($childViews, $view->children);
        };

        // First create the view
        $type->expects($this->once())
            ->method('createView')
            ->willReturn($view);

        // Then build it for the form itself
        $type->expects($this->once())
            ->method('buildView')
            ->with($view, $this->form, $options)
            ->willReturnCallback($assertChildViewsEqual([]));

        $this->assertSame($view, $this->form->createView());
        $this->assertSame(['foo' => $field1View, 'bar' => $field2View], $view->children);
    }

    public function testNoClickedButtonBeforeSubmission(): void
    {
        $this->assertNull($this->form->getClickedButton());
    }

    public function testNoClickedButton(): void
    {
        $button = $this->getMockBuilder(\Symfony\Component\Form\SubmitButton::class)
            ->setConstructorArgs([new SubmitButtonBuilder('submit')])
            ->setMethods(['isClicked'])
            ->getMock();

        $button->expects($this->any())
            ->method('isClicked')
            ->willReturn(false);

        $parentForm = $this->getBuilder('parent')->getForm();
        $nestedForm = $this->getBuilder('nested')->getForm();

        $this->form->setParent($parentForm);
        $this->form->add($button);
        $this->form->add($nestedForm);
        $this->form->submit([]);

        $this->assertNull($this->form->getClickedButton());
    }

    public function testClickedButton(): void
    {
        $button = $this->getMockBuilder(\Symfony\Component\Form\SubmitButton::class)
            ->setConstructorArgs([new SubmitButtonBuilder('submit')])
            ->setMethods(['isClicked'])
            ->getMock();

        $button->expects($this->any())
            ->method('isClicked')
            ->willReturn(true);

        $this->form->add($button);
        $this->form->submit([]);

        $this->assertSame($button, $this->form->getClickedButton());
    }

    public function testClickedButtonFromNestedForm(): void
    {
        $button = $this->getBuilder('submit')->getForm();

        $nestedForm = $this->getMockBuilder(\Symfony\Component\Form\Form::class)
            ->setConstructorArgs([$this->getBuilder('nested')])
            ->setMethods(['getClickedButton'])
            ->getMock();

        $nestedForm->expects($this->any())
            ->method('getClickedButton')
            ->willReturn($button);

        $this->form->add($nestedForm);
        $this->form->submit([]);

        $this->assertSame($button, $this->form->getClickedButton());
    }

    public function testClickedButtonFromParentForm(): void
    {
        $button = $this->getBuilder('submit')->getForm();

        $parentForm = $this->getMockBuilder(\Symfony\Component\Form\Form::class)
            ->setConstructorArgs([$this->getBuilder('parent')])
            ->setMethods(['getClickedButton'])
            ->getMock();

        $parentForm->expects($this->any())
            ->method('getClickedButton')
            ->willReturn($button);

        $this->form->setParent($parentForm);
        $this->form->submit([]);

        $this->assertSame($button, $this->form->getClickedButton());
    }

    public function testDisabledButtonIsNotSubmitted(): void
    {
        $button = new SubmitButtonBuilder('submit');
        $submit = $button
            ->setDisabled(true)
            ->getForm();

        $form = $this->createForm()
            ->add($this->createForm('text', false))
            ->add($submit)
        ;

        $form->submit([
            'text' => '',
            'submit' => '',
        ]);

        $this->assertTrue($submit->isDisabled());
        $this->assertFalse($submit->isClicked());
        $this->assertFalse($submit->isSubmitted());
    }

    public function testArrayTransformationFailureOnSubmit(): void
    {
        $this->form->add($this->getBuilder('foo')->setCompound(false)->getForm());
        $this->form->add($this->getBuilder('bar', null, null, ['multiple' => false])->setCompound(false)->getForm());

        $this->form->submit([
            'foo' => ['foo'],
            'bar' => ['bar'],
        ]);

        $this->assertNull($this->form->get('foo')->getData());
        $this->assertSame('Submitted data was expected to be text or number, array given.', $this->form->get('foo')->getTransformationFailure()->getMessage());

        $this->assertSame(['bar'], $this->form->get('bar')->getData());
    }

    public function testFileUpload(): void
    {
        $reqHandler = new HttpFoundationRequestHandler();
        $this->form->add($this->getBuilder('foo')->setRequestHandler($reqHandler)->getForm());
        $this->form->add($this->getBuilder('bar')->setRequestHandler($reqHandler)->getForm());

        $this->form->submit([
            'foo' => 'Foo',
            'bar' => new UploadedFile(__FILE__, 'upload.png', 'image/png', 123, \UPLOAD_ERR_OK),
        ]);

        $this->assertSame('Submitted data was expected to be text or number, file upload given.', $this->form->get('bar')->getTransformationFailure()->getMessage());
        $this->assertNull($this->form->get('bar')->getData());
    }

    protected function createForm($name = 'name', $compound = true)
    {
        $builder = $this->getBuilder($name);

        if ($compound) {
            $builder
                ->setCompound(true)
                ->setDataMapper($this->getDataMapper())
            ;
        }

        return $builder->getForm();
    }
}
