<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ConflictHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(): \Symfony\Component\HttpKernel\Exception\ConflictHttpException
    {
        return new ConflictHttpException();
    }
}
