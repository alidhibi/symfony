<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Intl\Util\IntlTestHelper;
use Symfony\Component\Validator\Constraints\Currency;
use Symfony\Component\Validator\Constraints\CurrencyValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class CurrencyValidatorTest extends ConstraintValidatorTestCase
{
    private $defaultLocale;

    protected function setUp()
    {
        parent::setUp();

        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown()
    {
        parent::tearDown();

        \Locale::setDefault($this->defaultLocale);
    }

    protected function createValidator(): \Symfony\Component\Validator\Constraints\CurrencyValidator
    {
        return new CurrencyValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new Currency());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new Currency());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\UnexpectedTypeException::class);
        $this->validator->validate(new \stdClass(), new Currency());
    }

    /**
     * @dataProvider getValidCurrencies
     */
    public function testValidCurrencies(string $currency): void
    {
        $this->validator->validate($currency, new Currency());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidCurrencies
     **/
    public function testValidCurrenciesWithCountrySpecificLocale(string $currency): void
    {
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('en_GB');

        $this->validator->validate($currency, new Currency());

        $this->assertNoViolation();
    }

    public function getValidCurrencies(): array
    {
        return [
            ['EUR'],
            ['USD'],
            ['SIT'],
            ['AUD'],
            ['CAD'],
        ];
    }

    /**
     * @dataProvider getInvalidCurrencies
     */
    public function testInvalidCurrencies(string $currency): void
    {
        $constraint = new Currency([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($currency, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$currency.'"')
            ->setCode(Currency::NO_SUCH_CURRENCY_ERROR)
            ->assertRaised();
    }

    public function getInvalidCurrencies(): array
    {
        return [
            ['EN'],
            ['foobar'],
        ];
    }
}
