<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList\Factory;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyAccessDecoratorTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $decoratedFactory;

    private \Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator $factory;

    protected function setUp()
    {
        $this->decoratedFactory = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface::class)->getMock();
        $this->factory = new PropertyAccessDecorator($this->decoratedFactory);
    }

    public function testCreateFromChoicesPropertyPath(): void
    {
        $choices = [(object) ['property' => 'value']];

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($choices, $callback): \Symfony\Component\Form\ChoiceList\ArrayChoiceList => new ArrayChoiceList(array_map($callback, $choices)));

        $this->assertSame(['value' => 'value'], $this->factory->createListFromChoices($choices, 'property')->getChoices());
    }

    public function testCreateFromChoicesPropertyPathInstance(): void
    {
        $choices = [(object) ['property' => 'value']];

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($choices, $callback): \Symfony\Component\Form\ChoiceList\ArrayChoiceList => new ArrayChoiceList(array_map($callback, $choices)));

        $this->assertSame(['value' => 'value'], $this->factory->createListFromChoices($choices, new PropertyPath('property'))->getChoices());
    }

    /**
     * @group legacy
     */
    public function testCreateFromChoicesPropertyPathWithCallableString(): void
    {
        $choices = ['foo' => 'bar'];

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createListFromChoices($choices, 'end'));
    }

    public function testCreateFromLoaderPropertyPath(): void
    {
        $loader = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($loader, $callback): \Symfony\Component\Form\ChoiceList\ArrayChoiceList => new ArrayChoiceList((array) $callback((object) ['property' => 'value'])));

        $this->assertSame(['value' => 'value'], $this->factory->createListFromLoader($loader, 'property')->getChoices());
    }

    /**
     * @group legacy
     */
    public function testCreateFromLoaderPropertyPathWithCallableString(): void
    {
        $loader = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createListFromLoader($loader, 'end'));
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateFromChoicesAssumeNullIfValuePropertyPathUnreadable(): void
    {
        $choices = [null];

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromChoices')
            ->with($choices, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($choices, $callback): \Symfony\Component\Form\ChoiceList\ArrayChoiceList => new ArrayChoiceList(array_map($callback, $choices)));

        $this->assertSame([null], $this->factory->createListFromChoices($choices, 'property')->getChoices());
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateFromChoiceLoaderAssumeNullIfValuePropertyPathUnreadable(): void
    {
        $loader = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($loader, $callback): \Symfony\Component\Form\ChoiceList\ArrayChoiceList => new ArrayChoiceList((array) $callback(null)));

        $this->assertSame([], $this->factory->createListFromLoader($loader, 'property')->getChoices());
    }

    public function testCreateFromLoaderPropertyPathInstance(): void
    {
        $loader = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($loader, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($loader, $callback): \Symfony\Component\Form\ChoiceList\ArrayChoiceList => new ArrayChoiceList((array) $callback((object) ['property' => 'value'])));

        $this->assertSame(['value' => 'value'], $this->factory->createListFromLoader($loader, new PropertyPath('property'))->getChoices());
    }

    public function testCreateViewPreferredChoicesAsPropertyPath(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $preferred((object) ['property' => true])));

        $this->assertSame([true], $this->factory->createView($list, 'property')->choices);
    }

    /**
     * @group legacy
     */
    public function testCreateViewPreferredChoicesAsPropertyPathWithCallableString(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createView(
            $list,
            'end'
        ));
    }

    public function testCreateViewPreferredChoicesAsPropertyPathInstance(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $preferred((object) ['property' => true])));

        $this->assertSame([true], $this->factory->createView($list, 'property')->choices);
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateViewAssumeNullIfPreferredChoicesPropertyPathUnreadable(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $preferred((object) ['category' => null])));

        $this->assertSame([false], $this->factory->createView($list, 'category.preferred')->choices);
    }

    public function testCreateViewLabelsAsPropertyPath(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred, $label): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $label((object) ['property' => 'label'])));

        $this->assertSame(['label'], $this->factory->createView($list, null, 'property')->choices);
    }

    /**
     * @group legacy
     */
    public function testCreateViewLabelsAsPropertyPathWithCallableString(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createView(
            $list,
            null, // preferred choices
            'end'
        ));
    }

    public function testCreateViewLabelsAsPropertyPathInstance(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred, $label): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $label((object) ['property' => 'label'])));

        $this->assertSame(['label'], $this->factory->createView($list, null, new PropertyPath('property'))->choices);
    }

    public function testCreateViewIndicesAsPropertyPath(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred, $label, $index): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $index((object) ['property' => 'index'])));

        $this->assertSame(['index'], $this->factory->createView($list, null, null, 'property')->choices);
    }

    /**
     * @group legacy
     */
    public function testCreateViewIndicesAsPropertyPathWithCallableString(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            'end'
        ));
    }

    public function testCreateViewIndicesAsPropertyPathInstance(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred, $label, $index): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $index((object) ['property' => 'index'])));

        $this->assertSame(['index'], $this->factory->createView($list, null, null, new PropertyPath('property'))->choices);
    }

    public function testCreateViewGroupsAsPropertyPath(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred, $label, $index, $groupBy): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $groupBy((object) ['property' => 'group'])));

        $this->assertSame(['group'], $this->factory->createView($list, null, null, null, 'property')->choices);
    }

    /**
     * @group legacy
     */
    public function testCreateViewGroupsAsPropertyPathWithCallableString(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            null, // index
            'end'
        ));
    }

    public function testCreateViewGroupsAsPropertyPathInstance(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred, $label, $index, $groupBy): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $groupBy((object) ['property' => 'group'])));

        $this->assertSame(['group'], $this->factory->createView($list, null, null, null, new PropertyPath('property'))->choices);
    }

    // https://github.com/symfony/symfony/issues/5494
    public function testCreateViewAssumeNullIfGroupsPropertyPathUnreadable(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred, $label, $index, $groupBy): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $groupBy((object) ['group' => null])));

        $this->assertSame([], $this->factory->createView($list, null, null, null, 'group.name')->choices);
    }

    public function testCreateViewAttrAsPropertyPath(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred, $label, $index, $groupBy, $attr): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $attr((object) ['property' => 'attr'])));

        $this->assertSame(['attr'], $this->factory->createView($list, null, null, null, null, 'property')->choices);
    }

    /**
     * @group legacy
     */
    public function testCreateViewAttrAsPropertyPathWithCallableString(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, 'end')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createView(
            $list,
            null, // preferred choices
            null, // label
            null, // inde
            null, // groups
            'end'
        ));
    }

    public function testCreateViewAttrAsPropertyPathInstance(): void
    {
        $list = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\ChoiceListInterface::class)->getMock();

        $this->decoratedFactory->expects($this->once())
            ->method('createView')
            ->with($list, null, null, null, null, $this->isInstanceOf('\Closure'))
            ->willReturnCallback(static fn($list, $preferred, $label, $index, $groupBy, $attr): \Symfony\Component\Form\ChoiceList\View\ChoiceListView => new ChoiceListView((array) $attr((object) ['property' => 'attr'])));

        $this->assertSame(['attr'], $this->factory->createView($list, null, null, null, null, new PropertyPath('property'))->choices);
    }
}
