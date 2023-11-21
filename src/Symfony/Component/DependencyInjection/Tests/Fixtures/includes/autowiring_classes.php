<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

require __DIR__.'/uniontype_classes.php';

class Foo
{
}

class Bar
{
}

interface AInterface
{
}

class A implements AInterface
{
    public static function create(Foo $foo): void
    {
    }
}

class B extends A
{
}

class C
{
}

interface DInterface
{
}

interface EInterface extends DInterface
{
}

interface IInterface
{
}

class I implements IInterface
{
}

class F extends I implements EInterface
{
}

class G
{
}

class H
{
}

class D
{
}

class E
{
}

class J
{
}

class K
{
}

interface CollisionInterface
{
}

class CollisionA implements CollisionInterface
{
}

class CollisionB implements CollisionInterface
{
}

class CannotBeAutowired
{
}

class Lille
{
}

class Dunglas
{
}

class LesTilleuls
{
}

class OptionalParameter
{
}

class BadTypeHintedArgument
{
}

class BadParentTypeHintedArgument
{
}

class NotGuessableArgument
{
}

class NotGuessableArgumentForSubclass
{
}

class MultipleArguments
{
}

class MultipleArgumentsOptionalScalar
{
}

class MultipleArgumentsOptionalScalarLast
{
}

/*
 * Classes used for testing createResourceForClass
 */
class ClassForResource
{
    public function setBar(Bar $bar): void
    {
    }
}

class IdenticalClassResource extends ClassForResource
{
}

class ClassChangedConstructorArgs extends ClassForResource
{
    public function __construct()
    {
    }
}

class SetterInjectionCollision
{
    /**
     * @required
     */
    public function setMultipleInstancesForOneArg(CollisionInterface $collision): void
    {
        // The CollisionInterface cannot be autowired - there are multiple

        // should throw an exception
    }
}

class SetterInjection extends SetterInjectionParent
{
    /**
     * @required
     */
    public function setFoo(Foo $foo): void
    {
        // should be called
    }

    /** @inheritdoc*/ // <- brackets are missing on purpose
    public function setDependencies(Foo $foo, A $a): void
    {
        // should be called
    }

    /** {@inheritdoc} */
    public function setWithCallsConfigured(A $a): void
    {
        // this method has a calls configured on it
    }

    public function notASetter(A $a): void
    {
        // should be called only when explicitly specified
    }

    /**
     * @required*/
    public function setChildMethodWithoutDocBlock(A $a): void
    {
    }
}

class SetterInjectionParent
{
    /** @required*/
    public function setDependencies(Foo $foo, A $a): void
    {
        // should be called
    }

    public function notASetter(A $a): void
    {
        // @required should be ignored when the child does not add @inheritdoc
    }

    /**	@required <tab> prefix is on purpose */
    public function setWithCallsConfigured(A $a): void
    {
    }

    /** @required */
    public function setChildMethodWithoutDocBlock(A $a): void
    {
    }
}

class NotWireable
{
    public function setNotAutowireable(NotARealClass $n): void
    {
    }

    public function setBar(): void
    {
    }

    public function setOptionalNotAutowireable(NotARealClass $n = null): void
    {
    }

    public function setDifferentNamespace(\stdClass $n): void
    {
    }

    public function setOptionalNoTypeHint($foo = null): void
    {
    }

    public function setOptionalArgNoAutowireable($other = 'default_val'): void
    {
    }

    /** @required */
    protected function setProtectedMethod(A $a)
    {
    }
}

class PrivateConstructor
{
    private function __construct()
    {
    }
}

class ScalarSetter
{
    /**
     * @required
     */
    public function setDefaultLocale($defaultLocale): void
    {
    }
}
