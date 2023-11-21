<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\BarService;

return static function (ContainerConfigurator $c) : void {
    $s = $c->services();
    $s->set(BarService::class)
        ->args([inline('FooClass')]);
};
