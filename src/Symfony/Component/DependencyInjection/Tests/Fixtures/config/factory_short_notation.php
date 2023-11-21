<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $c) : void {
    $c->services()
        ->set('service', \stdClass::class)
        ->factory('factory:method');
};
