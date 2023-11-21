<?php

/*
 * foo
 */

namespace Namespaced;

class WithFileMagic
{
    public function getFile(): string
    {
        return __FILE__;
    }
}
