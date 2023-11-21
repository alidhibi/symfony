<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\includes;

use Symfony\Component\DependencyInjection\Tests\Compiler\Foo;

class FooVariadic
{
    public function bar(...$arguments): void
    {
    }
}
