<?php

/*
 * foo
 */

namespace Namespaced;

class WithDirMagic
{
    public function getDir(): string
    {
        return __DIR__;
    }
}
