<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Test;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\IsIdentical;
use PHPUnit\Framework\Constraint\IsInstanceOf;
use PHPUnit\Framework\Constraint\IsNull;
use PHPUnit\Framework\Constraint\LogicalOr;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;

/**
 * A test case to ease testing Constraint Validators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class ConstraintValidatorTestCase extends TestCase
{
    use ForwardCompatTestTrait;

    /**
     * @var ExecutionContextInterface
     */
    protected $context;

    /**
     * @var ConstraintValidatorInterface
     */
    protected $validator;

    protected $group;

    protected $metadata;

    protected $object;

    protected $value;

    protected $root;

    protected $propertyPath;

    protected $constraint;

    protected $defaultTimezone;

    private function doSetUp(): void
    {
        $this->group = 'MyGroup';
        $this->metadata = null;
        $this->object = null;
        $this->value = 'InvalidValue';
        $this->root = 'root';
        $this->propertyPath = 'property.path';

        // Initialize the context with some constraint so that we can
        // successfully build a violation.
        $this->constraint = new NotNull();

        $this->context = $this->createContext();
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        \Locale::setDefault('en');

        $this->setDefaultTimezone('UTC');
    }

    private function doTearDown(): void
    {
        $this->restoreDefaultTimezone();
    }

    protected function setDefaultTimezone($defaultTimezone)
    {
        // Make sure this method can not be called twice before calling
        // also restoreDefaultTimezone()
        if (null === $this->defaultTimezone) {
            $this->defaultTimezone = date_default_timezone_get();
            date_default_timezone_set($defaultTimezone);
        }
    }

    protected function restoreDefaultTimezone()
    {
        if (null !== $this->defaultTimezone) {
            date_default_timezone_set($this->defaultTimezone);
            $this->defaultTimezone = null;
        }
    }

    protected function createContext()
    {
        $translator = $this->getMockBuilder(\Symfony\Component\Translation\TranslatorInterface::class)->getMock();
        $validator = $this->getMockBuilder(\Symfony\Component\Validator\Validator\ValidatorInterface::class)->getMock();

        $context = new ExecutionContext($validator, $this->root, $translator);
        $context->setGroup($this->group);
        $context->setNode($this->value, $this->object, $this->propertyPath, $this->metadata);
        $context->setConstraint($this->constraint);

        $contextualValidator = $this->getMockBuilder(AssertingContextualValidator::class)
            ->setMethods([
                'atPath',
                'validate',
                'validateProperty',
                'validatePropertyValue',
                'getViolations',
            ])
            ->getMock();
        $contextualValidator->expects($this->any())
            ->method('atPath')
            ->willReturnCallback(static fn($path) => $contextualValidator->doAtPath($path));
        $contextualValidator->expects($this->any())
            ->method('validate')
            ->willReturnCallback(static fn($value, $constraints = null, $groups = null) => $contextualValidator->doValidate($value, $constraints, $groups));
        $contextualValidator->expects($this->any())
            ->method('validateProperty')
            ->willReturnCallback(static fn($object, $propertyName, $groups = null) => $contextualValidator->validateProperty($object, $propertyName, $groups));
        $contextualValidator->expects($this->any())
            ->method('validatePropertyValue')
            ->willReturnCallback(static fn($objectOrClass, $propertyName, $value, $groups = null) => $contextualValidator->doValidatePropertyValue($objectOrClass, $propertyName, $value, $groups));
        $contextualValidator->expects($this->any())
            ->method('getViolations')
            ->willReturnCallback(static fn() => $contextualValidator->doGetViolations());
        $validator->expects($this->any())
            ->method('inContext')
            ->with($context)
            ->willReturn($contextualValidator);

        return $context;
    }

    protected function setGroup(?string $group)
    {
        $this->group = $group;
        $this->context->setGroup($group);
    }

    protected function setObject($object)
    {
        $this->object = $object;
        $this->metadata = \is_object($object)
            ? new ClassMetadata(\get_class($object))
            : null;

        $this->context->setNode($this->value, $this->object, $this->propertyPath, $this->metadata);
    }

    protected function setProperty($object, $property)
    {
        $this->object = $object;
        $this->metadata = \is_object($object)
            ? new PropertyMetadata(\get_class($object), $property)
            : null;

        $this->context->setNode($this->value, $this->object, $this->propertyPath, $this->metadata);
    }

    protected function setValue($value)
    {
        $this->value = $value;
        $this->context->setNode($this->value, $this->object, $this->propertyPath, $this->metadata);
    }

    protected function setRoot($root)
    {
        $this->root = $root;
        $this->context = $this->createContext();
        $this->validator->initialize($this->context);
    }

    protected function setPropertyPath($propertyPath)
    {
        $this->propertyPath = $propertyPath;
        $this->context->setNode($this->value, $this->object, $this->propertyPath, $this->metadata);
    }

    protected function expectNoValidate()
    {
        $validator = $this->context->getValidator()->inContext($this->context);
        $validator->expectNoValidate();
    }

    protected function expectValidateAt($i, $propertyPath, $value, $group)
    {
        $validator = $this->context->getValidator()->inContext($this->context);
        $validator->expectValidation($i, $propertyPath, $value, $group, static function ($passedConstraints) : void {
            $expectedConstraints = new LogicalOr();
            $expectedConstraints->setConstraints([new IsNull(), new IsIdentical([]), new IsInstanceOf(Valid::class)]);
            Assert::assertThat($passedConstraints, $expectedConstraints);
        });
    }

    protected function expectValidateValueAt($i, $propertyPath, $value, $constraints, $group = null)
    {
        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        $contextualValidator->expectValidation($i, $propertyPath, $value, $group, static function ($passedConstraints) use ($constraints) : void {
            Assert::assertEquals($constraints, $passedConstraints);
        });
    }

    protected function assertNoViolation()
    {
        $this->assertSame(0, $violationsCount = \count($this->context->getViolations()), sprintf('0 violation expected. Got %u.', $violationsCount));
    }

    /**
     * @param $message
     *
     * @return ConstraintViolationAssertion
     */
    protected function buildViolation($message)
    {
        return new ConstraintViolationAssertion($this->context, $message, $this->constraint);
    }

    abstract protected function createValidator();
}

/**
 * @internal
 */
class ConstraintViolationAssertion
{
    private readonly \Symfony\Component\Validator\Context\ExecutionContextInterface $context;

    /**
     * @var ConstraintViolationAssertion[]
     */
    private array $assertions;

    private $message;

    private array $parameters = [];

    private string $invalidValue = 'InvalidValue';

    private string $propertyPath = 'property.path';

    private $plural;

    private $code;

    private ?\Symfony\Component\Validator\Constraint $constraint = null;

    private $cause;

    public function __construct(ExecutionContextInterface $context, $message, Constraint $constraint = null, array $assertions = [])
    {
        $this->context = $context;
        $this->message = $message;
        $this->constraint = $constraint;
        $this->assertions = $assertions;
    }

    public function atPath(string $path): static
    {
        $this->propertyPath = $path;

        return $this;
    }

    public function setParameter($key, $value): static
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function setTranslationDomain($translationDomain): static
    {
        // no-op for BC

        return $this;
    }

    public function setInvalidValue(string $invalidValue): static
    {
        $this->invalidValue = $invalidValue;

        return $this;
    }

    public function setPlural($number): static
    {
        $this->plural = $number;

        return $this;
    }

    public function setCode($code): static
    {
        $this->code = $code;

        return $this;
    }

    public function setCause($cause): static
    {
        $this->cause = $cause;

        return $this;
    }

    public function buildNextViolation($message): self
    {
        $assertions = $this->assertions;
        $assertions[] = $this;

        return new self($this->context, $message, $this->constraint, $assertions);
    }

    public function assertRaised(): void
    {
        $expected = [];
        foreach ($this->assertions as $assertion) {
            $expected[] = $assertion->getViolation();
        }

        $expected[] = $this->getViolation();

        $violations = iterator_to_array($this->context->getViolations());

        Assert::assertSame($expectedCount = \count($expected), $violationsCount = \count($violations), sprintf('%u violation(s) expected. Got %u.', $expectedCount, $violationsCount));

        reset($violations);

        foreach ($expected as $violation) {
            Assert::assertEquals($violation, current($violations));
            next($violations);
        }
    }

    private function getViolation(): \Symfony\Component\Validator\ConstraintViolation
    {
        return new ConstraintViolation(
            null,
            $this->message,
            $this->parameters,
            $this->context->getRoot(),
            $this->propertyPath,
            $this->invalidValue,
            $this->plural,
            $this->code,
            $this->constraint,
            $this->cause
        );
    }
}

/**
 * @internal
 */
class AssertingContextualValidator implements ContextualValidatorInterface
{
    private bool $expectNoValidate = false;

    private int $atPathCalls = -1;

    private array $expectedAtPath = [];

    private int $validateCalls = -1;

    private array $expectedValidate = [];

    public function atPath($path): void
    {
    }

    public function doAtPath($path): static
    {
        Assert::assertFalse($this->expectNoValidate, 'No validation calls have been expected.');

        if (!isset($this->expectedAtPath[++$this->atPathCalls])) {
            throw new ExpectationFailedException(sprintf('Validation for property path "%s" was not expected.', $path));
        }

        Assert::assertSame($this->expectedAtPath[$this->atPathCalls], $path);

        return $this;
    }

    public function validate($value, $constraints = null, $groups = null): void
    {
    }

    public function doValidate($value, $constraints = null, $groups = null): static
    {
        Assert::assertFalse($this->expectNoValidate, 'No validation calls have been expected.');

        list($expectedValue, $expectedGroup, $expectedConstraints) = $this->expectedValidate[++$this->validateCalls];

        Assert::assertSame($expectedValue, $value);
        $expectedConstraints($constraints);
        Assert::assertSame($expectedGroup, $groups);

        return $this;
    }

    public function validateProperty($object, $propertyName, $groups = null): void
    {
    }

    public function doValidateProperty($object, $propertyName, $groups = null): static
    {
        return $this;
    }

    public function validatePropertyValue($objectOrClass, $propertyName, $value, $groups = null): void
    {
    }

    public function doValidatePropertyValue($objectOrClass, $propertyName, $value, $groups = null): static
    {
        return $this;
    }

    public function getViolations(): void
    {
    }

    public function doGetViolations(): void
    {
    }

    public function expectNoValidate(): void
    {
        $this->expectNoValidate = true;
    }

    public function expectValidation($call, $propertyPath, $value, $group, $constraints): void
    {
        $this->expectedAtPath[$call] = $propertyPath;
        $this->expectedValidate[$call] = [$value, $group, $constraints];
    }
}
