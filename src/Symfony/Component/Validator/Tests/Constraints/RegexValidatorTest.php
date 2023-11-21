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

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class RegexValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): \Symfony\Component\Validator\Constraints\RegexValidator
    {
        return new RegexValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new Regex(['pattern' => '/^\d+$/']));

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new Regex(['pattern' => '/^\d+$/']));

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\UnexpectedTypeException::class);
        $this->validator->validate(new \stdClass(), new Regex(['pattern' => '/^\d+$/']));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues(int|string $value): void
    {
        $constraint = new Regex(['pattern' => '/^\d+$/']);
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function getValidValues(): array
    {
        return [
            [0],
            ['0'],
            ['090909'],
            [90909],
        ];
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues(string $value): void
    {
        $constraint = new Regex([
            'pattern' => '/^\d+$/',
            'message' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode(Regex::REGEX_FAILED_ERROR)
            ->assertRaised();
    }

    public function getInvalidValues(): array
    {
        return [
            ['abcd'],
            ['090foo'],
        ];
    }
}
