<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;

return static function (ContainerConfigurator $c) : void {
    $c->parameters()
        ('foo', 'Foo')
        ('bar', 'Bar')
    ;
    $c->services()
        (Foo::class)
            ->arg('$bar', ref('bar'))
            ->public()
        ('bar', Foo::class)
            ->call('setFoo')
    ;
};
