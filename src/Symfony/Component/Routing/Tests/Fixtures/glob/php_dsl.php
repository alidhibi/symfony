<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return static fn(RoutingConfigurator $routes) => $routes->import('php_dsl_ba?.php');
