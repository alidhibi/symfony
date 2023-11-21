<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

class TestClass
{
    public $publicProperty;

    protected $protectedProperty;


    private $publicAccessor;


    private $publicGetSetter;

    private $publicAccessorWithDefaultValue;

    private $publicAccessorWithRequiredAndDefaultValue;

    private $publicAccessorWithMoreRequiredParameters;

    private $publicIsAccessor;

    private $publicHasAccessor;

    private $publicGetter;

    private ?\DateTimeInterface $date = null;

    public function __construct($value)
    {
        $this->publicProperty = $value;
        $this->publicAccessor = $value;
        $this->publicGetSetter = $value;
        $this->publicAccessorWithDefaultValue = $value;
        $this->publicAccessorWithRequiredAndDefaultValue = $value;
        $this->publicAccessorWithMoreRequiredParameters = $value;
        $this->publicIsAccessor = $value;
        $this->publicHasAccessor = $value;
        $this->publicGetter = $value;
    }

    public function setPublicAccessor($value): void
    {
        $this->publicAccessor = $value;
    }

    public function setPublicAccessorWithDefaultValue($value = null): void
    {
        $this->publicAccessorWithDefaultValue = $value;
    }

    public function setPublicAccessorWithRequiredAndDefaultValue($value, $optional = null): void
    {
        $this->publicAccessorWithRequiredAndDefaultValue = $value;
    }

    public function setPublicAccessorWithMoreRequiredParameters($value, $needed): void
    {
        $this->publicAccessorWithMoreRequiredParameters = $value;
    }

    public function getPublicAccessor()
    {
        return $this->publicAccessor;
    }

    public function getPublicAccessorWithDefaultValue()
    {
        return $this->publicAccessorWithDefaultValue;
    }

    public function getPublicAccessorWithRequiredAndDefaultValue()
    {
        return $this->publicAccessorWithRequiredAndDefaultValue;
    }

    public function getPublicAccessorWithMoreRequiredParameters()
    {
        return $this->publicAccessorWithMoreRequiredParameters;
    }

    public function setPublicIsAccessor($value): void
    {
        $this->publicIsAccessor = $value;
    }

    public function isPublicIsAccessor()
    {
        return $this->publicIsAccessor;
    }

    public function setPublicHasAccessor($value): void
    {
        $this->publicHasAccessor = $value;
    }

    public function hasPublicHasAccessor()
    {
        return $this->publicHasAccessor;
    }

    public function publicGetSetter($value = null)
    {
        if (null !== $value) {
            $this->publicGetSetter = $value;
        }

        return $this->publicGetSetter;
    }

    public function getPublicMethodMutator()
    {
        return $this->publicGetSetter;
    }

    protected function setProtectedAccessor($value)
    {
    }

    protected function getProtectedAccessor(): string
    {
        return 'foobar';
    }

    protected function setProtectedIsAccessor($value)
    {
    }

    protected function isProtectedIsAccessor(): string
    {
        return 'foobar';
    }

    protected function setProtectedHasAccessor($value)
    {
    }

    protected function hasProtectedHasAccessor(): string
    {
        return 'foobar';
    }

    public function getPublicGetter()
    {
        return $this->publicGetter;
    }

    public function setDate(\DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }
}
