<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $c) : void {
    $c->services()
        ->set('parent_service', \stdClass::class)
        ->set('child_service')->parent('parent_service')->autoconfigure(true);
};
