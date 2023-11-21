<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractEventDispatcherTest extends TestCase
{
    /* Some pseudo events */
    final const preFoo = 'pre.foo';

    final const postFoo = 'post.foo';

    final const preBar = 'pre.bar';

    final const postBar = 'post.bar';

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    private ?\Symfony\Component\EventDispatcher\Tests\TestEventListener $listener = null;

    protected function setUp()
    {
        $this->dispatcher = $this->createEventDispatcher();
        $this->listener = new TestEventListener();
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->listener = null;
    }

    abstract protected function createEventDispatcher();

    public function testInitialState(): void
    {
        $this->assertEquals([], $this->dispatcher->getListeners());
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
        $this->assertFalse($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testAddListener(): void
    {
        $this->dispatcher->addListener('pre.foo', [$this->listener, 'preFoo']);
        $this->dispatcher->addListener('post.foo', [$this->listener, 'postFoo']);
        $this->assertTrue($this->dispatcher->hasListeners());
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertTrue($this->dispatcher->hasListeners(self::postFoo));
        $this->assertCount(1, $this->dispatcher->getListeners(self::preFoo));
        $this->assertCount(1, $this->dispatcher->getListeners(self::postFoo));
        $this->assertCount(2, $this->dispatcher->getListeners());
    }

    public function testGetListenersSortsByPriority(): void
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();
        $listener3 = new TestEventListener();
        $listener1->name = '1';
        $listener2->name = '2';
        $listener3->name = '3';

        $this->dispatcher->addListener('pre.foo', static fn(\Symfony\Component\EventDispatcher\Event $e) => $listener1->preFoo($e), -10);
        $this->dispatcher->addListener('pre.foo', static fn(\Symfony\Component\EventDispatcher\Event $e) => $listener2->preFoo($e), 10);
        $this->dispatcher->addListener('pre.foo', static fn(\Symfony\Component\EventDispatcher\Event $e) => $listener3->preFoo($e));

        $expected = [
            static fn(\Symfony\Component\EventDispatcher\Event $e) => $listener2->preFoo($e),
            static fn(\Symfony\Component\EventDispatcher\Event $e) => $listener3->preFoo($e),
            static fn(\Symfony\Component\EventDispatcher\Event $e) => $listener1->preFoo($e),
        ];

        $this->assertSame($expected, $this->dispatcher->getListeners('pre.foo'));
    }

    public function testGetAllListenersSortsByPriority(): void
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();
        $listener3 = new TestEventListener();
        $listener4 = new TestEventListener();
        $listener5 = new TestEventListener();
        $listener6 = new TestEventListener();

        $this->dispatcher->addListener('pre.foo', $listener1, -10);
        $this->dispatcher->addListener('pre.foo', $listener2);
        $this->dispatcher->addListener('pre.foo', $listener3, 10);
        $this->dispatcher->addListener('post.foo', $listener4, -10);
        $this->dispatcher->addListener('post.foo', $listener5);
        $this->dispatcher->addListener('post.foo', $listener6, 10);

        $expected = [
            'pre.foo' => [$listener3, $listener2, $listener1],
            'post.foo' => [$listener6, $listener5, $listener4],
        ];

        $this->assertSame($expected, $this->dispatcher->getListeners());
    }

    public function testGetListenerPriority(): void
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();

        $this->dispatcher->addListener('pre.foo', $listener1, -10);
        $this->dispatcher->addListener('pre.foo', $listener2);

        $this->assertSame(-10, $this->dispatcher->getListenerPriority('pre.foo', $listener1));
        $this->assertSame(0, $this->dispatcher->getListenerPriority('pre.foo', $listener2));
        $this->assertNull($this->dispatcher->getListenerPriority('pre.bar', $listener2));
        $this->assertNull($this->dispatcher->getListenerPriority('pre.foo', static function () : void {
        }));
    }

    public function testDispatch(): void
    {
        $this->dispatcher->addListener('pre.foo', [$this->listener, 'preFoo']);
        $this->dispatcher->addListener('post.foo', [$this->listener, 'postFoo']);
        $this->dispatcher->dispatch(self::preFoo);
        $this->assertTrue($this->listener->preFooInvoked);
        $this->assertFalse($this->listener->postFooInvoked);
        $this->assertInstanceOf(\Symfony\Component\EventDispatcher\Event::class, $this->dispatcher->dispatch('noevent'));
        $this->assertInstanceOf(\Symfony\Component\EventDispatcher\Event::class, $this->dispatcher->dispatch(self::preFoo));
        $event = new Event();
        $return = $this->dispatcher->dispatch(self::preFoo, $event);
        $this->assertSame($event, $return);
    }

    public function testDispatchForClosure(): void
    {
        $invoked = 0;
        $listener = static function () use (&$invoked) : void {
            ++$invoked;
        };
        $this->dispatcher->addListener('pre.foo', $listener);
        $this->dispatcher->addListener('post.foo', $listener);
        $this->dispatcher->dispatch(self::preFoo);
        $this->assertEquals(1, $invoked);
    }

    public function testStopEventPropagation(): void
    {
        $otherListener = new TestEventListener();

        // postFoo() stops the propagation, so only one listener should
        // be executed
        // Manually set priority to enforce $this->listener to be called first
        $this->dispatcher->addListener('post.foo', [$this->listener, 'postFoo'], 10);
        $this->dispatcher->addListener('post.foo', static fn(\Symfony\Component\EventDispatcher\Event $e) => $otherListener->postFoo($e));
        $this->dispatcher->dispatch(self::postFoo);
        $this->assertTrue($this->listener->postFooInvoked);
        $this->assertFalse($otherListener->postFooInvoked);
    }

    public function testDispatchByPriority(): void
    {
        $invoked = [];
        $listener1 = static function () use (&$invoked) : void {
            $invoked[] = '1';
        };
        $listener2 = static function () use (&$invoked) : void {
            $invoked[] = '2';
        };
        $listener3 = static function () use (&$invoked) : void {
            $invoked[] = '3';
        };
        $this->dispatcher->addListener('pre.foo', $listener1, -10);
        $this->dispatcher->addListener('pre.foo', $listener2);
        $this->dispatcher->addListener('pre.foo', $listener3, 10);
        $this->dispatcher->dispatch(self::preFoo);
        $this->assertEquals(['3', '2', '1'], $invoked);
    }

    public function testRemoveListener(): void
    {
        $this->dispatcher->addListener('pre.bar', $this->listener);
        $this->assertTrue($this->dispatcher->hasListeners(self::preBar));
        $this->dispatcher->removeListener('pre.bar', $this->listener);
        $this->assertFalse($this->dispatcher->hasListeners(self::preBar));
        $this->dispatcher->removeListener('notExists', $this->listener);
    }

    public function testAddSubscriber(): void
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertTrue($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testAddSubscriberWithPriorities(): void
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $eventSubscriber = new TestEventSubscriberWithPriorities();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $listeners = $this->dispatcher->getListeners('pre.foo');
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertCount(2, $listeners);
        $this->assertInstanceOf(\Symfony\Component\EventDispatcher\Tests\TestEventSubscriberWithPriorities::class, $listeners[0][0]);
    }

    public function testAddSubscriberWithMultipleListeners(): void
    {
        $eventSubscriber = new TestEventSubscriberWithMultipleListeners();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $listeners = $this->dispatcher->getListeners('pre.foo');
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertCount(2, $listeners);
        $this->assertEquals('preFoo2', $listeners[0][1]);
    }

    public function testRemoveSubscriber(): void
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertTrue($this->dispatcher->hasListeners(self::postFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
        $this->assertFalse($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testRemoveSubscriberWithPriorities(): void
    {
        $eventSubscriber = new TestEventSubscriberWithPriorities();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
    }

    public function testRemoveSubscriberWithMultipleListeners(): void
    {
        $eventSubscriber = new TestEventSubscriberWithMultipleListeners();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertCount(2, $this->dispatcher->getListeners(self::preFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
    }

    public function testEventReceivesTheDispatcherInstanceAsArgument(): void
    {
        $listener = new TestWithDispatcher();
        $this->dispatcher->addListener('test', static fn(\Symfony\Component\EventDispatcher\Event $e, $name, $dispatcher) => $listener->foo($e, $name, $dispatcher));
        $this->assertNull($listener->name);
        $this->assertNull($listener->dispatcher);
        $this->dispatcher->dispatch('test');
        $this->assertEquals('test', $listener->name);
        $this->assertSame($this->dispatcher, $listener->dispatcher);
    }

    /**
     * @see https://bugs.php.net/62976
     *
     * This bug affects:
     *  - The PHP 5.3 branch for versions < 5.3.18
     *  - The PHP 5.4 branch for versions < 5.4.8
     *  - The PHP 5.5 branch is not affected
     */
    public function testWorkaroundForPhpBug62976(): void
    {
        $dispatcher = $this->createEventDispatcher();
        $dispatcher->addListener('bug.62976', new CallableClass());
        $dispatcher->removeListener('bug.62976', static function () : void {
        });
        $this->assertTrue($dispatcher->hasListeners('bug.62976'));
    }

    public function testHasListenersWhenAddedCallbackListenerIsRemoved(): void
    {
        $listener = static function () : void {
        };
        $this->dispatcher->addListener('foo', $listener);
        $this->dispatcher->removeListener('foo', $listener);
        $this->assertFalse($this->dispatcher->hasListeners());
    }

    public function testGetListenersWhenAddedCallbackListenerIsRemoved(): void
    {
        $listener = static function () : void {
        };
        $this->dispatcher->addListener('foo', $listener);
        $this->dispatcher->removeListener('foo', $listener);
        $this->assertSame([], $this->dispatcher->getListeners());
    }

    public function testHasListenersWithoutEventsReturnsFalseAfterHasListenersWithEventHasBeenCalled(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners('foo'));
        $this->assertFalse($this->dispatcher->hasListeners());
    }

    public function testHasListenersIsLazy(): void
    {
        $called = 0;
        $listener = [static function () use (&$called) : void {
            ++$called;
        }, 'onFoo'];
        $this->dispatcher->addListener('foo', $listener);
        $this->assertTrue($this->dispatcher->hasListeners());
        $this->assertTrue($this->dispatcher->hasListeners('foo'));
        $this->assertSame(0, $called);
    }

    public function testDispatchLazyListener(): void
    {
        $called = 0;
        $factory = static function () use (&$called) : \Symfony\Component\EventDispatcher\Tests\TestWithDispatcher {
            ++$called;
            return new TestWithDispatcher();
        };
        $this->dispatcher->addListener('foo', [$factory, 'foo']);
        $this->assertSame(0, $called);
        $this->dispatcher->dispatch('foo', new Event());
        $this->dispatcher->dispatch('foo', new Event());
        $this->assertSame(1, $called);
    }

    public function testRemoveFindsLazyListeners(): void
    {
        $test = new TestWithDispatcher();
        $factory = static fn(): \Symfony\Component\EventDispatcher\Tests\TestWithDispatcher => $test;

        $this->dispatcher->addListener('foo', [$factory, 'foo']);
        $this->assertTrue($this->dispatcher->hasListeners('foo'));
        $this->dispatcher->removeListener('foo', static fn(\Symfony\Component\EventDispatcher\Event $e, $name, $dispatcher) => $test->foo($e, $name, $dispatcher));
        $this->assertFalse($this->dispatcher->hasListeners('foo'));

        $this->dispatcher->addListener('foo', static fn(\Symfony\Component\EventDispatcher\Event $e, $name, $dispatcher) => $test->foo($e, $name, $dispatcher));
        $this->assertTrue($this->dispatcher->hasListeners('foo'));
        $this->dispatcher->removeListener('foo', [$factory, 'foo']);
        $this->assertFalse($this->dispatcher->hasListeners('foo'));
    }

    public function testPriorityFindsLazyListeners(): void
    {
        $test = new TestWithDispatcher();
        $factory = static fn(): \Symfony\Component\EventDispatcher\Tests\TestWithDispatcher => $test;

        $this->dispatcher->addListener('foo', [$factory, 'foo'], 3);
        $this->assertSame(3, $this->dispatcher->getListenerPriority('foo', static fn(\Symfony\Component\EventDispatcher\Event $e, $name, $dispatcher) => $test->foo($e, $name, $dispatcher)));
        $this->dispatcher->removeListener('foo', [$factory, 'foo']);

        $this->dispatcher->addListener('foo', static fn(\Symfony\Component\EventDispatcher\Event $e, $name, $dispatcher) => $test->foo($e, $name, $dispatcher), 5);
        $this->assertSame(5, $this->dispatcher->getListenerPriority('foo', [$factory, 'foo']));
    }

    public function testGetLazyListeners(): void
    {
        $test = new TestWithDispatcher();
        $factory = static fn(): \Symfony\Component\EventDispatcher\Tests\TestWithDispatcher => $test;

        $this->dispatcher->addListener('foo', [$factory, 'foo'], 3);
        $this->assertSame([static fn(\Symfony\Component\EventDispatcher\Event $e, $name, $dispatcher) => $test->foo($e, $name, $dispatcher)], $this->dispatcher->getListeners('foo'));

        $this->dispatcher->removeListener('foo', static fn(\Symfony\Component\EventDispatcher\Event $e, $name, $dispatcher) => $test->foo($e, $name, $dispatcher));
        $this->dispatcher->addListener('bar', [$factory, 'foo'], 3);
        $this->assertSame(['bar' => [static fn(\Symfony\Component\EventDispatcher\Event $e, $name, $dispatcher) => $test->foo($e, $name, $dispatcher)]], $this->dispatcher->getListeners());
    }
}

class CallableClass
{
    public function __invoke()
    {
    }
}

class TestEventListener
{
    public $preFooInvoked = false;

    public $postFooInvoked = false;

    /* Listener methods */

    public function preFoo(Event $e): void
    {
        $this->preFooInvoked = true;
    }

    public function postFoo(Event $e): void
    {
        $this->postFooInvoked = true;

        $e->stopPropagation();
    }
}

class TestWithDispatcher
{
    public $name;

    public $dispatcher;

    public function foo(Event $e, $name, $dispatcher): void
    {
        $this->name = $name;
        $this->dispatcher = $dispatcher;
    }
}

class TestEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['pre.foo' => 'preFoo', 'post.foo' => 'postFoo'];
    }
}

class TestEventSubscriberWithPriorities implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'pre.foo' => ['preFoo', 10],
            'post.foo' => ['postFoo'],
        ];
    }
}

class TestEventSubscriberWithMultipleListeners implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['pre.foo' => [
            ['preFoo1'],
            ['preFoo2', 10],
        ]];
    }
}
