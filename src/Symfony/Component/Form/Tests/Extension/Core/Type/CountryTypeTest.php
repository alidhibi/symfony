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
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Intl\Util\IntlTestHelper;

class CountryTypeTest extends BaseTypeTest
{
    final const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\CountryType';

    protected function setUp()
    {
        IntlTestHelper::requireIntl($this, false);

        parent::setUp();
    }

    public function testCountriesAreSelectable(): void
    {
        $choices = $this->factory->create(static::TESTED_TYPE)
            ->createView()->vars['choices'];

        // Don't check objects for identity
        $this->assertContainsEquals(new ChoiceView('DE', 'DE', 'Germany'), $choices);
        $this->assertContainsEquals(new ChoiceView('GB', 'GB', 'United Kingdom'), $choices);
        $this->assertContainsEquals(new ChoiceView('US', 'US', 'United States'), $choices);
        $this->assertContainsEquals(new ChoiceView('FR', 'FR', 'France'), $choices);
        $this->assertContainsEquals(new ChoiceView('MY', 'MY', 'Malaysia'), $choices);
    }

    public function testUnknownCountryIsNotIncluded(): void
    {
        $choices = $this->factory->create(static::TESTED_TYPE, 'country')
            ->createView()->vars['choices'];

        $countryCodes = [];

        foreach ($choices as $choice) {
            $countryCodes[] = $choice->value;
        }

        $this->assertNotContains('ZZ', $countryCodes);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null): void
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullUsesDefaultEmptyData(array $emptyData = 'FR', $expectedData = 'FR'): void
    {
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }

    public function testInvalidChoiceValuesAreDropped(): void
    {
        $type = new CountryType();

        $this->assertSame([], $type->loadChoicesForValues(['foo']));
    }
}
