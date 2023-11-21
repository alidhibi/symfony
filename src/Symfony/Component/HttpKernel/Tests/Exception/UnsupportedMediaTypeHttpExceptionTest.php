<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class UnsupportedMediaTypeHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(): \Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException
    {
        return new UnsupportedMediaTypeHttpException();
    }
}
