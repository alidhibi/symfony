<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Debug\WrappedListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class WrappedListenerTest extends TestCase
{
    /**
     * @dataProvider provideListenersToDescribe
     */
    public function testListenerDescription(callable $listener, $expected): void
    {
        $wrappedListener = new WrappedListener($listener, null, $this->getMockBuilder(Stopwatch::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock());

        $this->assertStringMatchesFormat($expected, $wrappedListener->getPretty());
    }

    public function provideListenersToDescribe(): array
    {
        $listeners = [
            [new FooListener(), \Symfony\Component\EventDispatcher\Tests\Debug\FooListener::class . '::__invoke'],
            [static fn() => (new FooListener())->listen(), \Symfony\Component\EventDispatcher\Tests\Debug\FooListener::class . '::listen'],
            [static fn() => \Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listenStatic(), \Symfony\Component\EventDispatcher\Tests\Debug\FooListener::class . '::listenStatic'],
            ['var_dump', 'var_dump'],
            [static function () : void {
            }, 'closure'],
        ];
        $listeners[] = [\Closure::fromCallable(static fn() => (new FooListener())->listen()), \Symfony\Component\EventDispatcher\Tests\Debug\FooListener::class . '::listen'];
        $listeners[] = [\Closure::fromCallable(static fn() => \Symfony\Component\EventDispatcher\Tests\Debug\FooListener::listenStatic()), \Symfony\Component\EventDispatcher\Tests\Debug\FooListener::class . '::listenStatic'];
        $listeners[] = [\Closure::fromCallable(static function () : void {
        }), 'closure'];

        return $listeners;
    }
}

class FooListener
{
    public function listen(): void
    {
    }

    public function __invoke()
    {
    }

    public static function listenStatic(): void
    {
    }
}
