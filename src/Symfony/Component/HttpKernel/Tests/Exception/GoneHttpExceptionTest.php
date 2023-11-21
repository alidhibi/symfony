<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\GoneHttpException;

class GoneHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(): \Symfony\Component\HttpKernel\Exception\GoneHttpException
    {
        return new GoneHttpException();
    }
}
