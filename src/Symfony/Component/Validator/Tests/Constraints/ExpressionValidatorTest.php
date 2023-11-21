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

use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\ExpressionValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\ToString;

class ExpressionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): \Symfony\Component\Validator\Constraints\ExpressionValidator
    {
        return new ExpressionValidator();
    }

    public function testExpressionIsEvaluatedWithNullValue(): void
    {
        $constraint = new Expression([
            'expression' => 'false',
            'message' => 'myMessage',
        ]);

        $this->validator->validate(null, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'null')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testExpressionIsEvaluatedWithEmptyStringValue(): void
    {
        $constraint = new Expression([
            'expression' => 'false',
            'message' => 'myMessage',
        ]);

        $this->validator->validate('', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '""')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtObjectLevel(): void
    {
        $constraint = new Expression('this.data == 1');

        $object = new Entity();
        $object->data = '1';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtObjectLevel(): void
    {
        $constraint = new Expression([
            'expression' => 'this.data == 1',
            'message' => 'myMessage',
        ]);

        $object = new Entity();
        $object->data = '2';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'object')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtObjectLevelWithToString(): void
    {
        $constraint = new Expression('this.data == 1');

        $object = new ToString();
        $object->data = '1';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtObjectLevelWithToString(): void
    {
        $constraint = new Expression([
            'expression' => 'this.data == 1',
            'message' => 'myMessage',
        ]);

        $object = new ToString();
        $object->data = '2';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'toString')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtPropertyLevel(): void
    {
        $constraint = new Expression('value == this.data');

        $object = new Entity();
        $object->data = '1';

        $this->setRoot($object);
        $this->setPropertyPath('data');
        $this->setProperty($object, 'data');

        $this->validator->validate('1', $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtPropertyLevel(): void
    {
        $constraint = new Expression([
            'expression' => 'value == this.data',
            'message' => 'myMessage',
        ]);

        $object = new Entity();
        $object->data = '1';

        $this->setRoot($object);
        $this->setPropertyPath('data');
        $this->setProperty($object, 'data');

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->atPath('data')
            ->setParameter('{{ value }}', '"2"')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtNestedPropertyLevel(): void
    {
        $constraint = new Expression('value == this.data');

        $object = new Entity();
        $object->data = '1';

        $root = new Entity();
        $root->reference = $object;

        $this->setRoot($root);
        $this->setPropertyPath('reference.data');
        $this->setProperty($object, 'data');

        $this->validator->validate('1', $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtNestedPropertyLevel(): void
    {
        $constraint = new Expression([
            'expression' => 'value == this.data',
            'message' => 'myMessage',
        ]);

        $object = new Entity();
        $object->data = '1';

        $root = new Entity();
        $root->reference = $object;

        $this->setRoot($root);
        $this->setPropertyPath('reference.data');
        $this->setProperty($object, 'data');

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->atPath('reference.data')
            ->setParameter('{{ value }}', '"2"')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    /**
     * When validatePropertyValue() is called with a class name
     * https://github.com/symfony/symfony/pull/11498.
     */
    public function testSucceedingExpressionAtPropertyLevelWithoutRoot(): void
    {
        $constraint = new Expression('value == "1"');

        $this->setRoot('1');
        $this->setPropertyPath('');
        $this->setProperty(null, 'property');

        $this->validator->validate('1', $constraint);

        $this->assertNoViolation();
    }

    /**
     * When validatePropertyValue() is called with a class name
     * https://github.com/symfony/symfony/pull/11498.
     */
    public function testFailingExpressionAtPropertyLevelWithoutRoot(): void
    {
        $constraint = new Expression([
            'expression' => 'value == "1"',
            'message' => 'myMessage',
        ]);

        $this->setRoot('2');
        $this->setPropertyPath('');
        $this->setProperty(null, 'property');

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->atPath('')
            ->setParameter('{{ value }}', '"2"')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testExpressionLanguageUsage(): void
    {
        $constraint = new Expression([
            'expression' => 'false',
        ]);

        $expressionLanguage = $this->getMockBuilder(\Symfony\Component\ExpressionLanguage\ExpressionLanguage::class)->getMock();

        $used = false;

        $expressionLanguage->method('evaluate')
            ->willReturnCallback(static function () use (&$used) : bool {
                $used = true;
                return true;
            });

        $validator = new ExpressionValidator(null, $expressionLanguage);
        $validator->initialize($this->createContext());
        $validator->validate(null, $constraint);

        $this->assertTrue($used, 'Failed asserting that custom ExpressionLanguage instance is used.');
    }
}
