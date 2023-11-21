<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
class CallbackChoiceLoaderTest extends TestCase
{
    private static ?\Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader $loader = null;

    /**
     * @var callable
     */
    private static $value;

    /**
     * @var array
     */
    private static $choices;

    /**
     * @var string[]
     */
    private static $choiceValues;

    private static ?\Symfony\Component\Form\ChoiceList\LazyChoiceList $lazyChoiceList = null;

    public static function setUpBeforeClass(): void
    {
        self::$loader = new CallbackChoiceLoader(static fn() => self::$choices);
        self::$value = static fn($choice) => isset($choice->value) ? $choice->value : null;
        self::$choices = [
            (object) ['value' => 'choice_one'],
            (object) ['value' => 'choice_two'],
        ];
        self::$choiceValues = ['choice_one', 'choice_two'];
        self::$lazyChoiceList = new LazyChoiceList(self::$loader, self::$value);
    }

    public function testLoadChoiceList(): void
    {
        $this->assertInstanceOf('\\' . \Symfony\Component\Form\ChoiceList\ChoiceListInterface::class, self::$loader->loadChoiceList(self::$value));
    }

    public function testLoadChoiceListOnlyOnce(): void
    {
        $loadedChoiceList = self::$loader->loadChoiceList(self::$value);

        $this->assertSame($loadedChoiceList, self::$loader->loadChoiceList(self::$value));
    }

    public function testLoadChoicesForValuesLoadsChoiceListOnFirstCall(): void
    {
        $this->assertSame(
            self::$loader->loadChoicesForValues(self::$choiceValues, self::$value),
            self::$lazyChoiceList->getChoicesForValues(self::$choiceValues),
            'Choice list should not be reloaded.'
        );
    }

    public function testLoadValuesForChoicesLoadsChoiceListOnFirstCall(): void
    {
        $this->assertSame(
            self::$loader->loadValuesForChoices(self::$choices, self::$value),
            self::$lazyChoiceList->getValuesForChoices(self::$choices),
            'Choice list should not be reloaded.'
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::$loader = null;
        self::$value = null;
        self::$choices = [];
        self::$choiceValues = [];
        self::$lazyChoiceList = null;
    }
}
