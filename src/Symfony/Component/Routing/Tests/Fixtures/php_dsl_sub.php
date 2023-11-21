<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return static function (RoutingConfigurator $routes) : void {
    $add = $routes->collection('c_')
        ->prefix('pub');
    $add('bar', '/bar');
    $add->collection('pub_')
        ->host('host')
        ->add('buz', 'buz');
};
