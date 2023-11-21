<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

class ToStringThrower
{
    private readonly \Exception $exception;

    public function __construct(\Exception $e)
    {
        $this->exception = $e;
    }

    public function __toString(): string
    {
        try {
            throw $this->exception;
        } catch (\Exception $exception) {
            // Using user_error() here is on purpose so we do not forget
            // that this alias also should work alongside with trigger_error().
            return trigger_error($exception, E_USER_ERROR);
        }
    }
}
