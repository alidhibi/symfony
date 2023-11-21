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

use Symfony\Component\Validator\Constraints\IsFalse;
use Symfony\Component\Validator\Constraints\IsFalseValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class IsFalseValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): \Symfony\Component\Validator\Constraints\IsFalseValidator
    {
        return new IsFalseValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new IsFalse());

        $this->assertNoViolation();
    }

    public function testFalseIsValid(): void
    {
        $this->validator->validate(false, new IsFalse());

        $this->assertNoViolation();
    }

    public function testTrueIsInvalid(): void
    {
        $constraint = new IsFalse([
            'message' => 'myMessage',
        ]);

        $this->validator->validate(true, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'true')
            ->setCode(IsFalse::NOT_FALSE_ERROR)
            ->assertRaised();
    }
}
