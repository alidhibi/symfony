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

use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

class TimeTypeTest extends BaseTypeTest
{
    final const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\TimeType';

    public function testSubmitDateTime(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'datetime',
        ]);

        $input = [
            'hour' => '3',
            'minute' => '4',
        ];

        $form->submit($input);

        $dateTime = new \DateTime('1970-01-01 03:04:00 UTC');

        $this->assertEquals($dateTime, $form->getData());
        $this->assertEquals($input, $form->getViewData());
    }

    public function testSubmitString(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
        ]);

        $input = [
            'hour' => '3',
            'minute' => '4',
        ];

        $form->submit($input);

        $this->assertEquals('03:04:00', $form->getData());
        $this->assertEquals($input, $form->getViewData());
    }

    public function testSubmitTimestamp(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'timestamp',
        ]);

        $input = [
            'hour' => '3',
            'minute' => '4',
        ];

        $form->submit($input);

        $dateTime = new \DateTime('1970-01-01 03:04:00 UTC');

        $this->assertEquals($dateTime->format('U'), $form->getData());
        $this->assertEquals($input, $form->getViewData());
    }

    public function testSubmitArray(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'array',
        ]);

        $input = [
            'hour' => '3',
            'minute' => '4',
        ];

        $form->submit($input);

        $this->assertEquals($input, $form->getData());
        $this->assertEquals($input, $form->getViewData());
    }

    public function testSubmitDatetimeSingleText(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'datetime',
            'widget' => 'single_text',
        ]);

        $form->submit('03:04');

        $this->assertEquals(new \DateTime('1970-01-01 03:04:00 UTC'), $form->getData());
        $this->assertEquals('03:04', $form->getViewData());
    }

    public function testSubmitDatetimeSingleTextWithoutMinutes(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'datetime',
            'widget' => 'single_text',
            'with_minutes' => false,
        ]);

        $form->submit('03');

        $this->assertEquals(new \DateTime('1970-01-01 03:00:00 UTC'), $form->getData());
        $this->assertEquals('03', $form->getViewData());
    }

    public function testSubmitArraySingleText(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'array',
            'widget' => 'single_text',
        ]);

        $data = [
            'hour' => '3',
            'minute' => '4',
        ];

        $form->submit('03:04');

        $this->assertEquals($data, $form->getData());
        $this->assertEquals('03:04', $form->getViewData());
    }

    public function testSubmitArraySingleTextWithoutMinutes(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'array',
            'widget' => 'single_text',
            'with_minutes' => false,
        ]);

        $data = [
            'hour' => '3',
        ];

        $form->submit('03');

        $this->assertEquals($data, $form->getData());
        $this->assertEquals('03', $form->getViewData());
    }

    public function testSubmitArraySingleTextWithSeconds(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'array',
            'widget' => 'single_text',
            'with_seconds' => true,
        ]);

        $data = [
            'hour' => '3',
            'minute' => '4',
            'second' => '5',
        ];

        $form->submit('03:04:05');

        $this->assertEquals($data, $form->getData());
        $this->assertEquals('03:04:05', $form->getViewData());
    }

    public function testSubmitStringSingleText(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
        ]);

        $form->submit('03:04');

        $this->assertEquals('03:04:00', $form->getData());
        $this->assertEquals('03:04', $form->getViewData());
    }

    public function testSubmitStringSingleTextWithoutMinutes(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
            'with_minutes' => false,
        ]);

        $form->submit('03');

        $this->assertEquals('03:00:00', $form->getData());
        $this->assertEquals('03', $form->getViewData());
    }

    public function testSubmitWithSecondsAndBrowserOmissionSeconds(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
            'with_seconds' => true,
        ]);

        $form->submit('03:04');

        $this->assertEquals('03:04:00', $form->getData());
        $this->assertEquals('03:04:00', $form->getViewData());
    }

    public function testSubmitWithoutSecondsAndBrowserAddingSeconds(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
            'with_seconds' => false,
        ]);

        $form->submit('03:04:00');

        $this->assertEquals('03:04:00', $form->getData());
        $this->assertEquals('03:04', $form->getViewData());
    }

    public function testSubmitWithSecondsAndBrowserAddingMicroseconds(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
            'with_seconds' => true,
        ]);

        $form->submit('03:04:00.000');

        $this->assertEquals('03:04:00', $form->getData());
        $this->assertEquals('03:04:00', $form->getViewData());
    }

    public function testSubmitWithoutSecondsAndBrowserAddingMicroseconds(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
            'with_seconds' => false,
        ]);

        $form->submit('03:04:00.000');

        $this->assertEquals('03:04:00', $form->getData());
        $this->assertEquals('03:04', $form->getViewData());
    }

    public function testSetDataWithoutMinutes(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'datetime',
            'with_minutes' => false,
        ]);

        $form->setData(new \DateTime('03:04:05 UTC'));

        $this->assertEquals(['hour' => 3], $form->getViewData());
    }

    public function testSetDataWithSeconds(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'datetime',
            'with_seconds' => true,
        ]);

        $form->setData(new \DateTime('03:04:05 UTC'));

        $this->assertEquals(['hour' => 3, 'minute' => 4, 'second' => 5], $form->getViewData());
    }

    public function testSetDataDifferentTimezones(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'America/New_York',
            'view_timezone' => 'Asia/Hong_Kong',
            'input' => 'string',
            'with_seconds' => true,
        ]);

        $dateTime = new \DateTime('2013-01-01 12:04:05');
        $dateTime->setTimezone(new \DateTimeZone('America/New_York'));

        $form->setData($dateTime->format('H:i:s'));

        $outputTime = clone $dateTime;
        $outputTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $displayedData = [
            'hour' => (int) $outputTime->format('H'),
            'minute' => (int) $outputTime->format('i'),
            'second' => (int) $outputTime->format('s'),
        ];

        $this->assertEquals($displayedData, $form->getViewData());
    }

    public function testSetDataDifferentTimezonesDateTime(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'America/New_York',
            'view_timezone' => 'Asia/Hong_Kong',
            'input' => 'datetime',
            'with_seconds' => true,
        ]);

        $dateTime = new \DateTime('12:04:05');
        $dateTime->setTimezone(new \DateTimeZone('America/New_York'));

        $form->setData($dateTime);

        $outputTime = clone $dateTime;
        $outputTime->setTimezone(new \DateTimeZone('Asia/Hong_Kong'));

        $displayedData = [
            'hour' => (int) $outputTime->format('H'),
            'minute' => (int) $outputTime->format('i'),
            'second' => (int) $outputTime->format('s'),
        ];

        $this->assertEquals($dateTime, $form->getData());
        $this->assertEquals($displayedData, $form->getViewData());
    }

    public function testHoursOption(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'hours' => [6, 7],
        ]);

        $view = $form->createView();

        $this->assertEquals([
            new ChoiceView('6', '6', '06'),
            new ChoiceView('7', '7', '07'),
        ], $view['hour']->vars['choices']);
    }

    public function testIsMinuteWithinRangeReturnsTrueIfWithin(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'minutes' => [6, 7],
        ]);

        $view = $form->createView();

        $this->assertEquals([
            new ChoiceView('6', '6', '06'),
            new ChoiceView('7', '7', '07'),
        ], $view['minute']->vars['choices']);
    }

    public function testIsSecondWithinRangeReturnsTrueIfWithin(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'seconds' => [6, 7],
            'with_seconds' => true,
        ]);

        $view = $form->createView();

        $this->assertEquals([
            new ChoiceView('6', '6', '06'),
            new ChoiceView('7', '7', '07'),
        ], $view['second']->vars['choices']);
    }

    public function testIsPartiallyFilledReturnsFalseIfCompletelyEmpty(): void
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
        ]);

        $form->submit([
            'hour' => '',
            'minute' => '',
        ]);

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilledReturnsFalseIfCompletelyEmptyWithSeconds(): void
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
            'with_seconds' => true,
        ]);

        $form->submit([
            'hour' => '',
            'minute' => '',
            'second' => '',
        ]);

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilledReturnsFalseIfCompletelyFilled(): void
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
        ]);

        $form->submit([
            'hour' => '0',
            'minute' => '0',
        ]);

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilledReturnsFalseIfCompletelyFilledWithSeconds(): void
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
            'with_seconds' => true,
        ]);

        $form->submit([
            'hour' => '0',
            'minute' => '0',
            'second' => '0',
        ]);

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilledReturnsTrueIfChoiceAndHourEmpty(): void
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
            'with_seconds' => true,
        ]);

        $form->submit([
            'hour' => '',
            'minute' => '0',
            'second' => '0',
        ]);

        $this->assertTrue($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilledReturnsTrueIfChoiceAndMinuteEmpty(): void
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
            'with_seconds' => true,
        ]);

        $form->submit([
            'hour' => '0',
            'minute' => '',
            'second' => '0',
        ]);

        $this->assertTrue($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilledReturnsTrueIfChoiceAndSecondsEmpty(): void
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
            'with_seconds' => true,
        ]);

        $form->submit([
            'hour' => '0',
            'minute' => '0',
            'second' => '',
        ]);

        $this->assertTrue($form->isPartiallyFilled());
    }

    public function testInitializeWithDateTime(): void
    {
        // Throws an exception if "data_class" option is not explicitly set
        // to null in the type
        $this->assertInstanceOf(\Symfony\Component\Form\FormInterface::class, $this->factory->create(static::TESTED_TYPE, new \DateTime()));
    }

    public function testSingleTextWidgetShouldUseTheRightInputType(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ]);

        $view = $form->createView();
        $this->assertEquals('time', $view->vars['type']);
    }

    public function testSingleTextWidgetWithSecondsShouldHaveRightStepAttribute(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertArrayHasKey('step', $view->vars['attr']);
        $this->assertEquals(1, $view->vars['attr']['step']);
    }

    public function testSingleTextWidgetWithSecondsShouldNotOverrideStepAttribute(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
            'with_seconds' => true,
            'attr' => [
                'step' => 30,
            ],
        ]);

        $view = $form->createView();
        $this->assertArrayHasKey('step', $view->vars['attr']);
        $this->assertEquals(30, $view->vars['attr']['step']);
    }

    public function testDontPassHtml5TypeIfHtml5NotAllowed(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
            'html5' => false,
        ]);

        $view = $form->createView();
        $this->assertArrayNotHasKey('type', $view->vars);
    }

    public function testPassDefaultPlaceholderToViewIfNotRequired(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertSame('', $view['hour']->vars['placeholder']);
        $this->assertSame('', $view['minute']->vars['placeholder']);
        $this->assertSame('', $view['second']->vars['placeholder']);
    }

    public function testPassNoPlaceholderToViewIfRequired(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => true,
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertNull($view['hour']->vars['placeholder']);
        $this->assertNull($view['minute']->vars['placeholder']);
        $this->assertNull($view['second']->vars['placeholder']);
    }

    public function testPassPlaceholderAsString(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => 'Empty',
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertSame('Empty', $view['hour']->vars['placeholder']);
        $this->assertSame('Empty', $view['minute']->vars['placeholder']);
        $this->assertSame('Empty', $view['second']->vars['placeholder']);
    }

    public function testPassPlaceholderAsArray(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => [
                'hour' => 'Empty hour',
                'minute' => 'Empty minute',
                'second' => 'Empty second',
            ],
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertSame('Empty hour', $view['hour']->vars['placeholder']);
        $this->assertSame('Empty minute', $view['minute']->vars['placeholder']);
        $this->assertSame('Empty second', $view['second']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddEmptyIfNotRequired(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'placeholder' => [
                'hour' => 'Empty hour',
                'second' => 'Empty second',
            ],
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertSame('Empty hour', $view['hour']->vars['placeholder']);
        $this->assertSame('', $view['minute']->vars['placeholder']);
        $this->assertSame('Empty second', $view['second']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddNullIfRequired(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => true,
            'placeholder' => [
                'hour' => 'Empty hour',
                'second' => 'Empty second',
            ],
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertSame('Empty hour', $view['hour']->vars['placeholder']);
        $this->assertNull($view['minute']->vars['placeholder']);
        $this->assertSame('Empty second', $view['second']->vars['placeholder']);
    }

    public function provideCompoundWidgets(): array
    {
        return [
            ['text'],
            ['choice'],
        ];
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testHourErrorsBubbleUp(string $widget): void
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => $widget,
        ]);
        $form['hour']->addError($error);

        $this->assertSame([], iterator_to_array($form['hour']->getErrors()));
        $this->assertSame([$error], iterator_to_array($form->getErrors()));
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testMinuteErrorsBubbleUp(string $widget): void
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => $widget,
        ]);
        $form['minute']->addError($error);

        $this->assertSame([], iterator_to_array($form['minute']->getErrors()));
        $this->assertSame([$error], iterator_to_array($form->getErrors()));
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testSecondErrorsBubbleUp(string $widget): void
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => $widget,
            'with_seconds' => true,
        ]);
        $form['second']->addError($error);

        $this->assertSame([], iterator_to_array($form['second']->getErrors()));
        $this->assertSame([$error], iterator_to_array($form->getErrors()));
    }

    public function testInitializeWithSecondsAndWithoutMinutes(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\InvalidConfigurationException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'with_minutes' => false,
            'with_seconds' => true,
        ]);
    }

    public function testThrowExceptionIfHoursIsInvalid(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'hours' => 'bad value',
        ]);
    }

    public function testThrowExceptionIfMinutesIsInvalid(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'minutes' => 'bad value',
        ]);
    }

    public function testThrowExceptionIfSecondsIsInvalid(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'seconds' => 'bad value',
        ]);
    }

    public function testPassDefaultChoiceTranslationDomain(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        $view = $form->createView();
        $this->assertFalse($view['hour']->vars['choice_translation_domain']);
        $this->assertFalse($view['minute']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsString(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'choice_translation_domain' => 'messages',
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertSame('messages', $view['hour']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['minute']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['second']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsArray(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'choice_translation_domain' => [
                'hour' => 'foo',
                'second' => 'test',
            ],
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertSame('foo', $view['hour']->vars['choice_translation_domain']);
        $this->assertFalse($view['minute']->vars['choice_translation_domain']);
        $this->assertSame('test', $view['second']->vars['choice_translation_domain']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null): void
    {
        $view = ['hour' => '', 'minute' => ''];

        parent::testSubmitNull($expected, $norm, $view);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = [], $expectedData = null): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        // view transformer writes back empty strings in the view data
        $this->assertSame(['hour' => '', 'minute' => ''], $form->getViewData());
        $this->assertSame($expectedData, $form->getNormData());
        $this->assertSame($expectedData, $form->getData());
    }

    /**
     * @dataProvider provideEmptyData
     */
    public function testSubmitNullUsesDateEmptyData($widget, $emptyData, $expectedData): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => $widget,
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        if ($emptyData instanceof \Closure) {
            $emptyData = $emptyData($form);
        }

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertEquals($expectedData, $form->getNormData());
        $this->assertEquals($expectedData, $form->getData());
    }

    public function provideEmptyData(): array
    {
        $expectedData = \DateTime::createFromFormat('Y-m-d H:i', '1970-01-01 21:23');
        $lazyEmptyData = static fn(FormInterface $form): array|string => $form->getConfig()->getCompound() ? ['hour' => '21', 'minute' => '23'] : '21:23';

        return [
            'Simple field' => ['single_text', '21:23', $expectedData],
            'Compound text field' => ['text', ['hour' => '21', 'minute' => '23'], $expectedData],
            'Compound choice field' => ['choice', ['hour' => '21', 'minute' => '23'], $expectedData],
            'Simple field lazy' => ['single_text', $lazyEmptyData, $expectedData],
            'Compound text field lazy' => ['text', $lazyEmptyData, $expectedData],
            'Compound choice field lazy' => ['choice', $lazyEmptyData, $expectedData],
        ];
    }
}
