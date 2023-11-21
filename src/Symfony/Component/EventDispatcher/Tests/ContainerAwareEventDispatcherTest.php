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

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @group legacy
 */
class ContainerAwareEventDispatcherTest extends AbstractEventDispatcherTest
{
    protected function createEventDispatcher(): \Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher
    {
        $container = new Container();

        return new ContainerAwareEventDispatcher($container);
    }

    public function testAddAListenerService(): void
    {
        $event = new Event();

        $service = $this->getMockBuilder(\Symfony\Component\EventDispatcher\Tests\Service::class)->getMock();

        $service
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', ['service.listener', 'onEvent']);

        $dispatcher->dispatch('onEvent', $event);
    }

    public function testAddASubscriberService(): void
    {
        $event = new Event();

        $service = $this->getMockBuilder(\Symfony\Component\EventDispatcher\Tests\SubscriberService::class)->getMock();

        $service
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $service
            ->expects($this->once())
            ->method('onEventWithPriority')
            ->with($event)
        ;

        $service
            ->expects($this->once())
            ->method('onEventNested')
            ->with($event)
        ;

        $container = new Container();
        $container->set('service.subscriber', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addSubscriberService('service.subscriber', \Symfony\Component\EventDispatcher\Tests\SubscriberService::class);

        $dispatcher->dispatch('onEvent', $event);
        $dispatcher->dispatch('onEventWithPriority', $event);
        $dispatcher->dispatch('onEventNested', $event);
    }

    public function testPreventDuplicateListenerService(): void
    {
        $event = new Event();

        $service = $this->getMockBuilder(\Symfony\Component\EventDispatcher\Tests\Service::class)->getMock();

        $service
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', ['service.listener', 'onEvent'], 5);
        $dispatcher->addListenerService('onEvent', ['service.listener', 'onEvent'], 10);

        $dispatcher->dispatch('onEvent', $event);
    }

    public function testHasListenersOnLazyLoad(): void
    {
        $event = new Event();

        $service = $this->getMockBuilder(\Symfony\Component\EventDispatcher\Tests\Service::class)->getMock();

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', ['service.listener', 'onEvent']);

        $service
            ->expects($this->once())
            ->method('onEvent')
            ->with($event)
        ;

        $this->assertTrue($dispatcher->hasListeners());

        if ($dispatcher->hasListeners('onEvent')) {
            $dispatcher->dispatch('onEvent');
        }
    }

    public function testGetListenersOnLazyLoad(): void
    {
        $service = $this->getMockBuilder(\Symfony\Component\EventDispatcher\Tests\Service::class)->getMock();

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', ['service.listener', 'onEvent']);

        $listeners = $dispatcher->getListeners();

        $this->assertArrayHasKey('onEvent', $listeners);

        $this->assertCount(1, $dispatcher->getListeners('onEvent'));
    }

    public function testRemoveAfterDispatch(): void
    {
        $service = $this->getMockBuilder(\Symfony\Component\EventDispatcher\Tests\Service::class)->getMock();

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', ['service.listener', 'onEvent']);

        $dispatcher->dispatch('onEvent', new Event());
        $dispatcher->removeListener('onEvent', [$container->get('service.listener'), 'onEvent']);
        $this->assertFalse($dispatcher->hasListeners('onEvent'));
    }

    public function testRemoveBeforeDispatch(): void
    {
        $service = $this->getMockBuilder(\Symfony\Component\EventDispatcher\Tests\Service::class)->getMock();

        $container = new Container();
        $container->set('service.listener', $service);

        $dispatcher = new ContainerAwareEventDispatcher($container);
        $dispatcher->addListenerService('onEvent', ['service.listener', 'onEvent']);

        $dispatcher->removeListener('onEvent', [$container->get('service.listener'), 'onEvent']);
        $this->assertFalse($dispatcher->hasListeners('onEvent'));
    }
}

class Service
{
    public function onEvent(Event $e): void
    {
    }
}

class SubscriberService implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onEvent' => 'onEvent',
            'onEventWithPriority' => ['onEventWithPriority', 10],
            'onEventNested' => [['onEventNested']],
        ];
    }

    public function onEvent(Event $e): void
    {
    }

    public function onEventWithPriority(Event $e): void
    {
    }

    public function onEventNested(Event $e): void
    {
    }
}
