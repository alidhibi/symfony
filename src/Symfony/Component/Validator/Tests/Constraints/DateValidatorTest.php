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

use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\DateValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class DateValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): \Symfony\Component\Validator\Constraints\DateValidator
    {
        return new DateValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new Date());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new Date());

        $this->assertNoViolation();
    }

    public function testDateTimeClassIsValid(): void
    {
        $this->validator->validate(new \DateTime(), new Date());

        $this->assertNoViolation();
    }

    public function testDateTimeImmutableClassIsValid(): void
    {
        $this->validator->validate(new \DateTimeImmutable(), new Date());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\UnexpectedTypeException::class);
        $this->validator->validate(new \stdClass(), new Date());
    }

    /**
     * @dataProvider getValidDates
     */
    public function testValidDates(string $date): void
    {
        $this->validator->validate($date, new Date());

        $this->assertNoViolation();
    }

    public function getValidDates(): array
    {
        return [
            ['2010-01-01'],
            ['1955-12-12'],
            ['2030-05-31'],
        ];
    }

    /**
     * @dataProvider getInvalidDates
     */
    public function testInvalidDates(string $date, string $code): void
    {
        $constraint = new Date([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($date, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$date.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public function getInvalidDates(): array
    {
        return [
            ['foobar', Date::INVALID_FORMAT_ERROR],
            ['foobar 2010-13-01', Date::INVALID_FORMAT_ERROR],
            ['2010-13-01 foobar', Date::INVALID_FORMAT_ERROR],
            ['2010-13-01', Date::INVALID_DATE_ERROR],
            ['2010-04-32', Date::INVALID_DATE_ERROR],
            ['2010-02-29', Date::INVALID_DATE_ERROR],
        ];
    }
}
