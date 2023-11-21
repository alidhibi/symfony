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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

class DefaultChoiceListFactoryTest extends TestCase
{
    private $obj1;

    private $obj2;

    private $obj3;

    private $obj4;

    private \Symfony\Component\Form\ChoiceList\ArrayChoiceList $list;

    private \Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory $factory;

    public function getValue($object)
    {
        return $object->value;
    }

    public function getScalarValue($choice)
    {
        switch ($choice) {
            case 'a': return 'a';
            case 'b': return 'b';
            case 'c': return '1';
            case 'd': return '2';
        }
    }

    public function getLabel($object)
    {
        return $object->label;
    }

    public function getFormIndex($object)
    {
        return $object->index;
    }

    public function isPreferred($object): bool
    {
        return $this->obj2 === $object || $this->obj3 === $object;
    }

    public function getAttr($object)
    {
        return $object->attr;
    }

    public function getGroup($object): string
    {
        return $this->obj1 === $object || $this->obj2 === $object ? 'Group 1' : 'Group 2';
    }

    public function getGroupAsObject($object): \Symfony\Component\Form\Tests\ChoiceList\Factory\DefaultChoiceListFactoryTest_Castable
    {
        return $this->obj1 === $object || $this->obj2 === $object
            ? new DefaultChoiceListFactoryTest_Castable('Group 1')
            : new DefaultChoiceListFactoryTest_Castable('Group 2');
    }

    protected function setUp()
    {
        $this->obj1 = (object) ['label' => 'A', 'index' => 'w', 'value' => 'a', 'preferred' => false, 'group' => 'Group 1', 'attr' => []];
        $this->obj2 = (object) ['label' => 'B', 'index' => 'x', 'value' => 'b', 'preferred' => true, 'group' => 'Group 1', 'attr' => ['attr1' => 'value1']];
        $this->obj3 = (object) ['label' => 'C', 'index' => 'y', 'value' => 1, 'preferred' => true, 'group' => 'Group 2', 'attr' => ['attr2' => 'value2']];
        $this->obj4 = (object) ['label' => 'D', 'index' => 'z', 'value' => 2, 'preferred' => false, 'group' => 'Group 2', 'attr' => []];
        $this->list = new ArrayChoiceList(
            ['A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4]
        );
        $this->factory = new DefaultChoiceListFactory();
    }

    public function testCreateFromChoicesEmpty(): void
    {
        $list = $this->factory->createListFromChoices([]);

        $this->assertSame([], $list->getChoices());
        $this->assertSame([], $list->getValues());
    }

    public function testCreateFromChoicesFlat(): void
    {
        $list = $this->factory->createListFromChoices(
            ['A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4]
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    public function testCreateFromChoicesFlatTraversable(): void
    {
        $list = $this->factory->createListFromChoices(
            new \ArrayIterator(['A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4])
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    public function testCreateFromChoicesFlatValuesAsCallable(): void
    {
        $list = $this->factory->createListFromChoices(
            ['A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4],
            fn($object) => $this->getValue($object)
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromChoicesFlatValuesAsClosure(): void
    {
        $list = $this->factory->createListFromChoices(
            ['A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4],
            static fn($object) => $object->value
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromChoicesGrouped(): void
    {
        $list = $this->factory->createListFromChoices([
            'Group 1' => ['A' => $this->obj1, 'B' => $this->obj2],
            'Group 2' => ['C' => $this->obj3, 'D' => $this->obj4],
        ]);

        $this->assertObjectListWithGeneratedValues($list);
    }

    public function testCreateFromChoicesGroupedTraversable(): void
    {
        $list = $this->factory->createListFromChoices(
            new \ArrayIterator([
                    'Group 1' => ['A' => $this->obj1, 'B' => $this->obj2],
                    'Group 2' => ['C' => $this->obj3, 'D' => $this->obj4],
                ])
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    public function testCreateFromChoicesGroupedValuesAsCallable(): void
    {
        $list = $this->factory->createListFromChoices(
            [
                'Group 1' => ['A' => $this->obj1, 'B' => $this->obj2],
                'Group 2' => ['C' => $this->obj3, 'D' => $this->obj4],
            ],
            fn($object) => $this->getValue($object)
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromChoicesGroupedValuesAsClosure(): void
    {
        $list = $this->factory->createListFromChoices(
            [
                'Group 1' => ['A' => $this->obj1, 'B' => $this->obj2],
                'Group 2' => ['C' => $this->obj3, 'D' => $this->obj4],
            ],
            static fn($object) => $object->value
        );

        $this->assertObjectListWithCustomValues($list);
    }

    public function testCreateFromLoader(): void
    {
        $loader = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface::class)->getMock();

        $list = $this->factory->createListFromLoader($loader);

        $this->assertEquals(new LazyChoiceList($loader), $list);
    }

    public function testCreateFromLoaderWithValues(): void
    {
        $loader = $this->getMockBuilder(\Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface::class)->getMock();

        $value = static function () : void {
        };
        $list = $this->factory->createListFromLoader($loader, $value);

        $this->assertEquals(new LazyChoiceList($loader, $value), $list);
    }

    public function testCreateViewFlat(): void
    {
        $view = $this->factory->createView($this->list);

        $this->assertEquals(new ChoiceListView(
                [
                    0 => new ChoiceView($this->obj1, '0', 'A'),
                    1 => new ChoiceView($this->obj2, '1', 'B'),
                    2 => new ChoiceView($this->obj3, '2', 'C'),
                    3 => new ChoiceView($this->obj4, '3', 'D'),
                ], []
        ), $view);
    }

    public function testCreateViewFlatPreferredChoices(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3]
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatPreferredChoicesEmptyArray(): void
    {
        $view = $this->factory->createView(
            $this->list,
            []
        );

        $this->assertEquals(new ChoiceListView(
                [
                    0 => new ChoiceView($this->obj1, '0', 'A'),
                    1 => new ChoiceView($this->obj2, '1', 'B'),
                    2 => new ChoiceView($this->obj3, '2', 'C'),
                    3 => new ChoiceView($this->obj4, '3', 'D'),
                ], []
        ), $view);
    }

    public function testCreateViewFlatPreferredChoicesAsCallable(): void
    {
        $view = $this->factory->createView(
            $this->list,
            fn($object) => $this->isPreferred($object)
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatPreferredChoicesAsClosure(): void
    {
        $obj2 = $this->obj2;
        $obj3 = $this->obj3;

        $view = $this->factory->createView(
            $this->list,
            static fn($object): bool => $obj2 === $object || $obj3 === $object
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatPreferredChoicesClosureReceivesKey(): void
    {
        $view = $this->factory->createView(
            $this->list,
            static fn($object, $key): bool => 'B' === $key || 'C' === $key
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatPreferredChoicesClosureReceivesValue(): void
    {
        $view = $this->factory->createView(
            $this->list,
            static fn($object, $key, $value): bool => '1' === $value || '2' === $value
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatLabelAsCallable(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            fn($object) => $this->getLabel($object)
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatLabelAsClosure(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            static fn($object) => $object->label
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatLabelClosureReceivesKey(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            static fn($object, $key) => $key
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatLabelClosureReceivesValue(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            static function ($object, $key, $value) {
                switch ($value) {
                    case '0': return 'A';
                    case '1': return 'B';
                    case '2': return 'C';
                    case '3': return 'D';
                }
            }
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatIndexAsCallable(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            fn($object) => $this->getFormIndex($object)
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    public function testCreateViewFlatIndexAsClosure(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            static fn($object) => $object->index
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    public function testCreateViewFlatIndexClosureReceivesKey(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            static function ($object, $key) {
                switch ($key) {
                    case 'A': return 'w';
                    case 'B': return 'x';
                    case 'C': return 'y';
                    case 'D': return 'z';
                }
            }
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    public function testCreateViewFlatIndexClosureReceivesValue(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            static function ($object, $key, $value) {
                switch ($value) {
                    case '0': return 'w';
                    case '1': return 'x';
                    case '2': return 'y';
                    case '3': return 'z';
                }
            }
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    public function testCreateViewFlatGroupByOriginalStructure(): void
    {
        $list = new ArrayChoiceList([
            'Group 1' => ['A' => $this->obj1, 'B' => $this->obj2],
            'Group 2' => ['C' => $this->obj3, 'D' => $this->obj4],
            'Group empty' => [],
        ]);

        $view = $this->factory->createView(
            $list,
            [$this->obj2, $this->obj3]
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatGroupByEmpty(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null  // group
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatGroupByAsCallable(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            fn($object) => $this->getGroup($object)
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatGroupByObjectThatCanBeCastToString(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            fn($object) => $this->getGroupAsObject($object)
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatGroupByAsClosure(): void
    {
        $obj1 = $this->obj1;
        $obj2 = $this->obj2;

        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            static fn($object): string => $obj1 === $object || $obj2 === $object ? 'Group 1' : 'Group 2'
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatGroupByClosureReceivesKey(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            static fn($object, $key): string => 'A' === $key || 'B' === $key ? 'Group 1' : 'Group 2'
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatGroupByClosureReceivesValue(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            static fn($object, $key, $value): string => '0' === $value || '1' === $value ? 'Group 1' : 'Group 2'
        );

        $this->assertGroupedView($view);
    }

    public function testCreateViewFlatAttrAsArray(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            [
                'B' => ['attr1' => 'value1'],
                'C' => ['attr2' => 'value2'],
            ]
        );

        $this->assertFlatViewWithAttr($view);
    }

    public function testCreateViewFlatAttrEmpty(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            []
        );

        $this->assertFlatView($view);
    }

    public function testCreateViewFlatAttrAsCallable(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            fn($object) => $this->getAttr($object)
        );

        $this->assertFlatViewWithAttr($view);
    }

    public function testCreateViewFlatAttrAsClosure(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            static fn($object) => $object->attr
        );

        $this->assertFlatViewWithAttr($view);
    }

    public function testCreateViewFlatAttrClosureReceivesKey(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            static function ($object, $key) {
                switch ($key) {
                    case 'B': return ['attr1' => 'value1'];
                    case 'C': return ['attr2' => 'value2'];
                    default: return [];
                }
            }
        );

        $this->assertFlatViewWithAttr($view);
    }

    public function testCreateViewFlatAttrClosureReceivesValue(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            static function ($object, $key, $value) {
                switch ($value) {
                    case '1': return ['attr1' => 'value1'];
                    case '2': return ['attr2' => 'value2'];
                    default: return [];
                }
            }
        );

        $this->assertFlatViewWithAttr($view);
    }

    private function assertObjectListWithGeneratedValues(ChoiceListInterface $list): void
    {
        $this->assertSame(['0', '1', '2', '3'], $list->getValues());

        $this->assertSame([
            0 => $this->obj1,
            1 => $this->obj2,
            2 => $this->obj3,
            3 => $this->obj4,
        ], $list->getChoices());

        $this->assertSame([
            0 => 'A',
            1 => 'B',
            2 => 'C',
            3 => 'D',
        ], $list->getOriginalKeys());
    }

    private function assertObjectListWithCustomValues(ChoiceListInterface $list): void
    {
        $this->assertSame(['a', 'b', '1', '2'], $list->getValues());

        $this->assertSame([
            'a' => $this->obj1,
            'b' => $this->obj2,
            1 => $this->obj3,
            2 => $this->obj4,
        ], $list->getChoices());

        $this->assertSame([
            'a' => 'A',
            'b' => 'B',
            1 => 'C',
            2 => 'D',
        ], $list->getOriginalKeys());
    }

    private function assertFlatView($view): void
    {
        $this->assertEquals(new ChoiceListView(
                [
                    0 => new ChoiceView($this->obj1, '0', 'A'),
                    3 => new ChoiceView($this->obj4, '3', 'D'),
                ], [
                    1 => new ChoiceView($this->obj2, '1', 'B'),
                    2 => new ChoiceView($this->obj3, '2', 'C'),
                ]
        ), $view);
    }

    private function assertFlatViewWithCustomIndices($view): void
    {
        $this->assertEquals(new ChoiceListView(
                [
                    'w' => new ChoiceView($this->obj1, '0', 'A'),
                    'z' => new ChoiceView($this->obj4, '3', 'D'),
                ], [
                    'x' => new ChoiceView($this->obj2, '1', 'B'),
                    'y' => new ChoiceView($this->obj3, '2', 'C'),
                ]
        ), $view);
    }

    private function assertFlatViewWithAttr($view): void
    {
        $this->assertEquals(new ChoiceListView(
                [
                    0 => new ChoiceView($this->obj1, '0', 'A'),
                    3 => new ChoiceView($this->obj4, '3', 'D'),
                ], [
                    1 => new ChoiceView(
                        $this->obj2,
                        '1',
                        'B',
                        ['attr1' => 'value1']
                    ),
                    2 => new ChoiceView(
                        $this->obj3,
                        '2',
                        'C',
                        ['attr2' => 'value2']
                    ),
                ]
        ), $view);
    }

    private function assertGroupedView($view): void
    {
        $this->assertEquals(new ChoiceListView(
                [
                    'Group 1' => new ChoiceGroupView(
                        'Group 1',
                        [0 => new ChoiceView($this->obj1, '0', 'A')]
                    ),
                    'Group 2' => new ChoiceGroupView(
                        'Group 2',
                        [3 => new ChoiceView($this->obj4, '3', 'D')]
                    ),
                ], [
                    'Group 1' => new ChoiceGroupView(
                        'Group 1',
                        [1 => new ChoiceView($this->obj2, '1', 'B')]
                    ),
                    'Group 2' => new ChoiceGroupView(
                        'Group 2',
                        [2 => new ChoiceView($this->obj3, '2', 'C')]
                    ),
                ]
        ), $view);
    }
}

class DefaultChoiceListFactoryTest_Castable
{
    private readonly string $property;

    public function __construct($property)
    {
        $this->property = $property;
    }

    public function __toString(): string
    {
        return $this->property;
    }
}
