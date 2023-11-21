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

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotBlankValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class NotBlankValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): \Symfony\Component\Validator\Constraints\NotBlankValidator
    {
        return new NotBlankValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues(string|int|float $value): void
    {
        $this->validator->validate($value, new NotBlank());

        $this->assertNoViolation();
    }

    public function getValidValues(): array
    {
        return [
            ['foobar'],
            [0],
            [0.0],
            ['0'],
            [1234],
        ];
    }

    public function testNullIsInvalid(): void
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
        ]);

        $this->validator->validate(null, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'null')
            ->setCode(NotBlank::IS_BLANK_ERROR)
            ->assertRaised();
    }

    public function testBlankIsInvalid(): void
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
        ]);

        $this->validator->validate('', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '""')
            ->setCode(NotBlank::IS_BLANK_ERROR)
            ->assertRaised();
    }

    public function testFalseIsInvalid(): void
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
        ]);

        $this->validator->validate(false, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'false')
            ->setCode(NotBlank::IS_BLANK_ERROR)
            ->assertRaised();
    }

    public function testEmptyArrayIsInvalid(): void
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
        ]);

        $this->validator->validate([], $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'array')
            ->setCode(NotBlank::IS_BLANK_ERROR)
            ->assertRaised();
    }
}
