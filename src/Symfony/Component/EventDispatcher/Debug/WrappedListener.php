<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Debug;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class WrappedListener
{
    private readonly string|object|array $listener;

    private $name;

    private bool $called = false;

    private bool $stoppedPropagation = false;

    private readonly \Symfony\Component\Stopwatch\Stopwatch $stopwatch;

    private ?\Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher = null;

    private string $pretty = '';

    private $stub;

    private ?int $priority = null;

    private static $hasClassStub;

    public function __construct($listener, $name, Stopwatch $stopwatch, EventDispatcherInterface $dispatcher = null)
    {
        $this->listener = $listener;
        $this->stopwatch = $stopwatch;
        $this->dispatcher = $dispatcher;

        if (\is_array($listener)) {
            $this->name = \is_object($listener[0]) ? \get_class($listener[0]) : $listener[0];
            $this->pretty = $this->name.'::'.$listener[1];
        } elseif ($listener instanceof \Closure) {
            $r = new \ReflectionFunction($listener);
            if (false !== strpos($r->name, '{closure}')) {
                $this->pretty = 'closure';
                $this->name = 'closure';
            } elseif (($class = $r->getClosureScopeClass()) !== null) {
                $this->name = $class->name;
                $this->pretty = $this->name.'::'.$r->name;
            } else {
                $this->pretty = $r->name;
                $this->name = $r->name;
            }
        } elseif (\is_string($listener)) {
            $this->pretty = $listener;
            $this->name = $listener;
        } else {
            $this->name = \get_class($listener);
            $this->pretty = $this->name.'::__invoke';
        }

        if (null !== $name) {
            $this->name = $name;
        }

        if (null === self::$hasClassStub) {
            self::$hasClassStub = class_exists(ClassStub::class);
        }
    }

    public function getWrappedListener(): string|object|array
    {
        return $this->listener;
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }

    public function stoppedPropagation(): bool
    {
        return $this->stoppedPropagation;
    }

    public function getPretty(): string
    {
        return $this->pretty;
    }

    public function getInfo($eventName): array
    {
        if (null === $this->stub) {
            $this->stub = self::$hasClassStub ? new ClassStub($this->pretty.'()', $this->listener) : $this->pretty.'()';
        }

        return [
            'event' => $eventName,
            'priority' => null !== $this->priority ? $this->priority : ($this->dispatcher instanceof \Symfony\Component\EventDispatcher\EventDispatcherInterface ? $this->dispatcher->getListenerPriority($eventName, $this->listener) : null),
            'pretty' => $this->pretty,
            'stub' => $this->stub,
        ];
    }

    public function __invoke(Event $event, string $eventName, EventDispatcherInterface $dispatcher)
    {
        $dispatcher = $this->dispatcher ?: $dispatcher;

        $this->called = true;
        $this->priority = $dispatcher->getListenerPriority($eventName, $this->listener);

        $e = $this->stopwatch->start($this->name, 'event_listener');

        \call_user_func($this->listener, $event, $eventName, $dispatcher);

        if ($e->isStarted()) {
            $e->stop();
        }

        if ($event->isPropagationStopped()) {
            $this->stoppedPropagation = true;
        }
    }
}
