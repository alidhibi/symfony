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

use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\IsTrueValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class IsTrueValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): \Symfony\Component\Validator\Constraints\IsTrueValidator
    {
        return new IsTrueValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new IsTrue());

        $this->assertNoViolation();
    }

    public function testTrueIsValid(): void
    {
        $this->validator->validate(true, new IsTrue());

        $this->assertNoViolation();
    }

    public function testFalseIsInvalid(): void
    {
        $constraint = new IsTrue([
            'message' => 'myMessage',
        ]);

        $this->validator->validate(false, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'false')
            ->setCode(IsTrue::NOT_TRUE_ERROR)
            ->assertRaised();
    }
}
