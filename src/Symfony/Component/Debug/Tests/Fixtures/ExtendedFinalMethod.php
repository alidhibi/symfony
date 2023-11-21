<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

class ExtendedFinalMethod extends FinalMethod
{
    use FinalMethod2Trait;

    /**
     * {@inheritdoc}
     */
    public function finalMethod(): void
    {
    }

    public function anotherMethod(): void
    {
    }
}
