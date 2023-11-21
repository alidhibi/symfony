<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Context;

use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\MemberMetadata;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Util\PropertyPath;
use Symfony\Component\Validator\Validator\LazyProperty;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;

/**
 * The context used and created by {@link ExecutionContextFactory}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see ExecutionContextInterface
 *
 * @internal since version 2.5. Code against ExecutionContextInterface instead.
 */
class ExecutionContext implements ExecutionContextInterface
{
    private readonly \Symfony\Component\Validator\Validator\ValidatorInterface $validator;

    /**
     * The root value of the validated object graph.
     *
     * @var mixed
     */
    private $root;

    /**
     * @var TranslatorInterface
     */
    private readonly \Symfony\Contracts\Translation\TranslatorInterface $translator;

    private ?string $translationDomain = null;

    /**
     * The violations generated in the current context.
     *
     */
    private readonly \Symfony\Component\Validator\ConstraintViolationList $violations;

    /**
     * The currently validated value.
     *
     * @var mixed
     */
    private $value;

    /**
     * The currently validated object.
     *
     */
    private ?object $object = null;

    /**
     * The property path leading to the current value.
     *
     */
    private string $propertyPath = '';

    /**
     * The current validation metadata.
     *
     */
    private ?\Symfony\Component\Validator\Mapping\MetadataInterface $metadata = null;

    /**
     * The currently validated group.
     *
     */
    private ?string $group = null;

    /**
     * The currently validated constraint.
     *
     */
    private ?\Symfony\Component\Validator\Constraint $constraint = null;

    /**
     * Stores which objects have been validated in which group.
     *
     */
    private array $validatedObjects = [];

    /**
     * Stores which class constraint has been validated for which object.
     *
     */
    private array $validatedConstraints = [];

    /**
     * Stores which objects have been initialized.
     *
     */
    private ?array $initializedObjects = null;

    /**
     * Creates a new execution context.
     *
     * @param ValidatorInterface  $validator         The validator
     * @param mixed               $root              The root value of the
     *                                               validated object graph
     * @param TranslatorInterface $translator        The translator
     * @param string|null         $translationDomain The translation domain to
     *                                               use for translating
     *                                               violation messages
     *
     * @internal Called by {@link ExecutionContextFactory}. Should not be used
     *           in user code.
     */
    public function __construct(ValidatorInterface $validator, $root, TranslatorInterface $translator, ?string $translationDomain = null)
    {
        $this->validator = $validator;
        $this->root = $root;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->violations = new ConstraintViolationList();
    }

    /**
     * {@inheritdoc}
     */
    public function setNode($value, $object, $propertyPath, MetadataInterface $metadata = null): void
    {
        $this->value = $value;
        $this->object = $object;
        $this->metadata = $metadata;
        $this->propertyPath = (string) $propertyPath;
    }

    /**
     * {@inheritdoc}
     */
    public function setGroup($group): void
    {
        $this->group = $group;
    }

    /**
     * {@inheritdoc}
     */
    public function setConstraint(Constraint $constraint): void
    {
        $this->constraint = $constraint;
    }

    /**
     * {@inheritdoc}
     */
    public function addViolation($message, array $parameters = []): void
    {
        $this->violations->add(new ConstraintViolation(
            $this->translator->trans($message, $parameters, $this->translationDomain),
            $message,
            $parameters,
            $this->root,
            $this->propertyPath,
            $this->getValue(),
            null,
            null,
            $this->constraint
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildViolation($message, array $parameters = []): \Symfony\Component\Validator\Violation\ConstraintViolationBuilder
    {
        return new ConstraintViolationBuilder(
            $this->violations,
            $this->constraint,
            $message,
            $parameters,
            $this->root,
            $this->propertyPath,
            $this->getValue(),
            $this->translator,
            $this->translationDomain
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getViolations()
    {
        return $this->violations;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        if ($this->value instanceof LazyProperty) {
            return $this->value->getPropertyValue();
        }

        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function getObject(): ?object
    {
        return $this->object;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): ?\Symfony\Component\Validator\Mapping\MetadataInterface
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getConstraint(): ?\Symfony\Component\Validator\Constraint
    {
        return $this->constraint;
    }

    /**
     * {@inheritdoc}
     */
    public function getClassName()
    {
        return $this->metadata instanceof MemberMetadata || $this->metadata instanceof ClassMetadataInterface ? $this->metadata->getClassName() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyName()
    {
        return $this->metadata instanceof PropertyMetadataInterface ? $this->metadata->getPropertyName() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyPath($subPath = '')
    {
        return PropertyPath::append($this->propertyPath, $subPath);
    }

    /**
     * {@inheritdoc}
     */
    public function markGroupAsValidated($cacheKey, $groupHash): void
    {
        if (!isset($this->validatedObjects[$cacheKey])) {
            $this->validatedObjects[$cacheKey] = [];
        }

        $this->validatedObjects[$cacheKey][$groupHash] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isGroupValidated($cacheKey, $groupHash): bool
    {
        return isset($this->validatedObjects[$cacheKey][$groupHash]);
    }

    /**
     * {@inheritdoc}
     */
    public function markConstraintAsValidated($cacheKey, $constraintHash): void
    {
        $this->validatedConstraints[$cacheKey.':'.$constraintHash] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isConstraintValidated($cacheKey, $constraintHash): bool
    {
        return isset($this->validatedConstraints[$cacheKey.':'.$constraintHash]);
    }

    /**
     * {@inheritdoc}
     */
    public function markObjectAsInitialized($cacheKey): void
    {
        $this->initializedObjects[$cacheKey] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isObjectInitialized($cacheKey): bool
    {
        return isset($this->initializedObjects[$cacheKey]);
    }
}
