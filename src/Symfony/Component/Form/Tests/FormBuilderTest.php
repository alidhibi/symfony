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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ButtonBuilder;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\SubmitButtonBuilder;

class FormBuilderTest extends TestCase
{
    private $dispatcher;

    private $factory;

    private ?\Symfony\Component\Form\FormBuilder $builder = null;

    protected function setUp()
    {
        $this->dispatcher = $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class)->getMock();
        $this->factory = $this->getMockBuilder(\Symfony\Component\Form\FormFactoryInterface::class)->getMock();
        $this->builder = new FormBuilder('name', null, $this->dispatcher, $this->factory);
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->factory = null;
        $this->builder = null;
    }

    /**
     * Changing the name is not allowed, otherwise the name and property path
     * are not synchronized anymore.
     *
     * @see FormType::buildForm()
     */
    public function testNoSetName(): void
    {
        $this->assertFalse(method_exists($this->builder, 'setName'));
    }

    public function testAddNameNoStringAndNoInteger(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\UnexpectedTypeException::class);
        $this->builder->add(true);
    }

    public function testAddTypeNoString(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\UnexpectedTypeException::class);
        $this->builder->add('foo', 1234);
    }

    public function testAddWithGuessFluent(): void
    {
        $this->builder = new FormBuilder('name', 'stdClass', $this->dispatcher, $this->factory);
        $builder = $this->builder->add('foo');
        $this->assertSame($builder, $this->builder);
    }

    public function testAddIsFluent(): void
    {
        $builder = $this->builder->add('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class, ['bar' => 'baz']);
        $this->assertSame($builder, $this->builder);
    }

    public function testAdd(): void
    {
        $this->assertFalse($this->builder->has('foo'));
        $this->builder->add('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class);
        $this->assertTrue($this->builder->has('foo'));
    }

    public function testAddIntegerName(): void
    {
        $this->assertFalse($this->builder->has(0));
        $this->builder->add(0, \Symfony\Component\Form\Extension\Core\Type\TextType::class);
        $this->assertTrue($this->builder->has(0));
    }

    public function testAll(): void
    {
        $this->factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class)
            ->willReturn(new FormBuilder('foo', null, $this->dispatcher, $this->factory));

        $this->assertCount(0, $this->builder->all());
        $this->assertFalse($this->builder->has('foo'));

        $this->builder->add('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class);
        $children = $this->builder->all();

        $this->assertTrue($this->builder->has('foo'));
        $this->assertCount(1, $children);
        $this->assertArrayHasKey('foo', $children);
    }

    /*
     * https://github.com/symfony/symfony/issues/4693
     */
    public function testMaintainOrderOfLazyAndExplicitChildren(): void
    {
        $this->builder->add('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class);
        $this->builder->add($this->getFormBuilder('bar'));
        $this->builder->add('baz', \Symfony\Component\Form\Extension\Core\Type\TextType::class);

        $children = $this->builder->all();

        $this->assertSame(['foo', 'bar', 'baz'], array_keys($children));
    }

    public function testAddFormType(): void
    {
        $this->assertFalse($this->builder->has('foo'));
        $this->builder->add('foo', $this->getMockBuilder(\Symfony\Component\Form\FormTypeInterface::class)->getMock());
        $this->assertTrue($this->builder->has('foo'));
    }

    public function testRemove(): void
    {
        $this->builder->add('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class);
        $this->builder->remove('foo');
        $this->assertFalse($this->builder->has('foo'));
    }

    public function testRemoveUnknown(): void
    {
        $this->builder->remove('foo');
        $this->assertFalse($this->builder->has('foo'));
    }

    // https://github.com/symfony/symfony/pull/4826
    public function testRemoveAndGetForm(): void
    {
        $this->builder->add('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class);
        $this->builder->remove('foo');

        $form = $this->builder->getForm();
        $this->assertInstanceOf(\Symfony\Component\Form\Form::class, $form);
    }

    public function testCreateNoTypeNo(): void
    {
        $this->factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('foo', \Symfony\Component\Form\Extension\Core\Type\TextType::class, null, [])
        ;

        $this->builder->create('foo');
    }

    public function testAddButton(): void
    {
        $this->builder->add(new ButtonBuilder('reset'));
        $this->builder->add(new SubmitButtonBuilder('submit'));

        $this->assertCount(2, $this->builder->all());
    }

    public function testGetUnknown(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('The child with the name "foo" does not exist.');

        $this->builder->get('foo');
    }

    public function testGetExplicitType(): void
    {
        $expectedType = \Symfony\Component\Form\Extension\Core\Type\TextType::class;
        $expectedName = 'foo';
        $expectedOptions = ['bar' => 'baz'];

        $this->factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with($expectedName, $expectedType, null, $expectedOptions)
            ->willReturn($this->getFormBuilder());

        $this->builder->add($expectedName, $expectedType, $expectedOptions);
        $builder = $this->builder->get($expectedName);

        $this->assertNotSame($builder, $this->builder);
    }

    public function testGetGuessedType(): void
    {
        $expectedName = 'foo';
        $expectedOptions = ['bar' => 'baz'];

        $this->factory->expects($this->once())
            ->method('createBuilderForProperty')
            ->with('stdClass', $expectedName, null, $expectedOptions)
            ->willReturn($this->getFormBuilder());

        $this->builder = new FormBuilder('name', 'stdClass', $this->dispatcher, $this->factory);
        $this->builder->add($expectedName, null, $expectedOptions);
        $builder = $this->builder->get($expectedName);

        $this->assertNotSame($builder, $this->builder);
    }

    public function testGetFormConfigErasesReferences(): void
    {
        $builder = new FormBuilder('name', null, $this->dispatcher, $this->factory);
        $builder->add(new FormBuilder('child', null, $this->dispatcher, $this->factory));

        $config = $builder->getFormConfig();
        $reflClass = new \ReflectionClass($config);
        $children = $reflClass->getProperty('children');
        $unresolvedChildren = $reflClass->getProperty('unresolvedChildren');

        $children->setAccessible(true);
        $unresolvedChildren->setAccessible(true);

        $this->assertEmpty($children->getValue($config));
        $this->assertEmpty($unresolvedChildren->getValue($config));
    }

    private function getFormBuilder(string $name = 'name')
    {
        $mock = $this->getMockBuilder(\Symfony\Component\Form\FormBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->any())
            ->method('getName')
            ->willReturn($name);

        return $mock;
    }
}
