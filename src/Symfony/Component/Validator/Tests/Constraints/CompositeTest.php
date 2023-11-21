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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;

class ConcreteComposite extends Composite
{
    public $constraints = [];

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }

    public function getDefaultOption(): string
    {
        return 'constraints';
    }
}

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompositeTest extends TestCase
{
    public function testConstraintHasDefaultGroup(): void
    {
        $constraint = new ConcreteComposite([
            new NotNull(),
            new NotBlank(),
        ]);

        $this->assertEquals(['Default'], $constraint->groups);
        $this->assertEquals(['Default'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Default'], $constraint->constraints[1]->groups);
    }

    public function testNestedCompositeConstraintHasDefaultGroup(): void
    {
        $constraint = new ConcreteComposite([
            new ConcreteComposite(),
            new ConcreteComposite(),
        ]);

        $this->assertEquals(['Default'], $constraint->groups);
        $this->assertEquals(['Default'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Default'], $constraint->constraints[1]->groups);
    }

    public function testMergeNestedGroupsIfNoExplicitParentGroup(): void
    {
        $constraint = new ConcreteComposite([
            new NotNull(['groups' => 'Default']),
            new NotBlank(['groups' => ['Default', 'Strict']]),
        ]);

        $this->assertEquals(['Default', 'Strict'], $constraint->groups);
        $this->assertEquals(['Default'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Default', 'Strict'], $constraint->constraints[1]->groups);
    }

    public function testSetImplicitNestedGroupsIfExplicitParentGroup(): void
    {
        $constraint = new ConcreteComposite([
            'constraints' => [
                new NotNull(),
                new NotBlank(),
            ],
            'groups' => ['Default', 'Strict'],
        ]);

        $this->assertEquals(['Default', 'Strict'], $constraint->groups);
        $this->assertEquals(['Default', 'Strict'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Default', 'Strict'], $constraint->constraints[1]->groups);
    }

    public function testExplicitNestedGroupsMustBeSubsetOfExplicitParentGroups(): void
    {
        $constraint = new ConcreteComposite([
            'constraints' => [
                new NotNull(['groups' => 'Default']),
                new NotBlank(['groups' => 'Strict']),
            ],
            'groups' => ['Default', 'Strict'],
        ]);

        $this->assertEquals(['Default', 'Strict'], $constraint->groups);
        $this->assertEquals(['Default'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Strict'], $constraint->constraints[1]->groups);
    }

    public function testFailIfExplicitNestedGroupsNotSubsetOfExplicitParentGroups(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);
        new ConcreteComposite([
            'constraints' => [
                new NotNull(['groups' => ['Default', 'Foobar']]),
            ],
            'groups' => ['Default', 'Strict'],
        ]);
    }

    public function testImplicitGroupNamesAreForwarded(): void
    {
        $constraint = new ConcreteComposite([
            new NotNull(['groups' => 'Default']),
            new NotBlank(['groups' => 'Strict']),
        ]);

        $constraint->addImplicitGroupName('ImplicitGroup');

        $this->assertEquals(['Default', 'Strict', 'ImplicitGroup'], $constraint->groups);
        $this->assertEquals(['Default', 'ImplicitGroup'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Strict'], $constraint->constraints[1]->groups);
    }

    public function testSingleConstraintsAccepted(): void
    {
        $nestedConstraint = new NotNull();
        $constraint = new ConcreteComposite($nestedConstraint);

        $this->assertEquals([$nestedConstraint], $constraint->constraints);
    }

    public function testFailIfNoConstraint(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);
        new ConcreteComposite([
            new NotNull(['groups' => 'Default']),
            'NotBlank',
        ]);
    }

    public function testFailIfNoConstraintObject(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);
        new ConcreteComposite([
            new NotNull(['groups' => 'Default']),
            new \ArrayObject(),
        ]);
    }

    public function testValidCantBeNested(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);
        new ConcreteComposite([
            new Valid(),
        ]);
    }
}
