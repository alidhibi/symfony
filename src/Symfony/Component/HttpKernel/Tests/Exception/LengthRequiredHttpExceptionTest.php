<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;

class LengthRequiredHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(): \Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException
    {
        return new LengthRequiredHttpException();
    }
}
