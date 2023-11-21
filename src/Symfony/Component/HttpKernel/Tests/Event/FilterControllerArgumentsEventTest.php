<?php

namespace Symfony\Component\HttpKernel\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class FilterControllerArgumentsEventTest extends TestCase
{
    public function testFilterControllerArgumentsEvent(): void
    {
        $filterController = new FilterControllerArgumentsEvent(new TestHttpKernel(), static function () : void {
        }, ['test'], new Request(), 1);
        $this->assertEquals(['test'], $filterController->getArguments());
    }
}
