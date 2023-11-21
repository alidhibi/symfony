<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

class FinalMethod
{
    /**
     * @final since version 3.3.
     */
    public function finalMethod(): void
    {
    }

    /**
     * @final
     *
     * @return int
     */
    public function finalMethod2(): void
    {
    }

    public function anotherMethod(): void
    {
    }
}
