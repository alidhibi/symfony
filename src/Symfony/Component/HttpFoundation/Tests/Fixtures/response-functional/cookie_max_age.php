<?php

use Symfony\Component\HttpFoundation\Cookie;

$r = require __DIR__.'/common.inc';

$r->headers->setCookie(new Cookie('foo', 'bar', 253_402_310_800, '', null, false, false));
$r->sendHeaders();

setcookie('foo2', 'bar', 253_402_310_800, '/');
