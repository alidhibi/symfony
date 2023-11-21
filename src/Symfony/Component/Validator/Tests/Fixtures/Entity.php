<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @Symfony\Component\Validator\Tests\Fixtures\ConstraintA
 * @Assert\GroupSequence({"Foo", "Entity"})
 * @Assert\Callback({"Symfony\Component\Validator\Tests\Fixtures\CallbackClass", "callback"})
 */
class Entity extends EntityParent implements EntityInterfaceB
{
    /**
     * @Assert\NotNull
     * @Assert\Range(min=3)
     * @Assert\All({@Assert\NotNull, @Assert\Range(min=3)}),
     * @Assert\All(constraints={@Assert\NotNull, @Assert\Range(min=3)})
     * @Assert\Collection(fields={
     *   "foo" = {@Assert\NotNull, @Assert\Range(min=3)},
     *   "bar" = @Assert\Range(min=5)
     * })
     * @Assert\Choice(choices={"A", "B"}, message="Must be one of %choices%")
     */
    public $firstName;

    /**
     * @Assert\Valid
     */
    public $childA;

    /**
     * @Assert\Valid
     */
    public $childB;

    protected $lastName;

    public $reference;

    public $reference2;

    private $internal;

    public $data = 'Overridden data';

    public $initialized = false;

    public function __construct($internal = null)
    {
        $this->internal = $internal;
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function getInternal(): string
    {
        return $this->internal.' from getter';
    }

    public function setLastName($lastName): void
    {
        $this->lastName = $lastName;
    }

    /**
     * @Assert\NotNull
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    public function getValid(): void
    {
    }

    /**
     * @Assert\IsTrue
     */
    public function isValid(): string
    {
        return 'valid';
    }

    /**
     * @Assert\IsTrue
     */
    public function hasPermissions(): string
    {
        return 'permissions';
    }

    public function getData(): string
    {
        return 'Overridden data';
    }

    /**
     * @Assert\Callback(payload="foo")
     */
    public function validateMe(ExecutionContextInterface $context): void
    {
    }

    /**
     * @Assert\Callback
     */
    public static function validateMeStatic($object, ExecutionContextInterface $context): void
    {
    }

    /**
     * @return mixed
     */
    public function getChildA()
    {
        return $this->childA;
    }

    /**
     * @param mixed $childA
     */
    public function setChildA($childA): void
    {
        $this->childA = $childA;
    }

    /**
     * @return mixed
     */
    public function getChildB()
    {
        return $this->childB;
    }

    /**
     * @param mixed $childB
     */
    public function setChildB($childB): void
    {
        $this->childB = $childB;
    }

    public function getReference()
    {
        return $this->reference;
    }
}
