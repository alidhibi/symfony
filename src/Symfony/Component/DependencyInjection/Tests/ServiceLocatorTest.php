<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;

class ServiceLocatorTest extends TestCase
{
    public function testHas(): void
    {
        $locator = new ServiceLocator([
            'foo' => static fn(): string => 'bar',
            'bar' => static fn(): string => 'baz',
            static fn(): string => 'dummy',
        ]);

        $this->assertTrue($locator->has('foo'));
        $this->assertTrue($locator->has('bar'));
        $this->assertFalse($locator->has('dummy'));
    }

    public function testGet(): void
    {
        $locator = new ServiceLocator([
            'foo' => static fn(): string => 'bar',
            'bar' => static fn(): string => 'baz',
        ]);

        $this->assertSame('bar', $locator->get('foo'));
        $this->assertSame('baz', $locator->get('bar'));
    }

    public function testGetDoesNotMemoize(): void
    {
        $i = 0;
        $locator = new ServiceLocator([
            'foo' => static function () use (&$i) : string {
                ++$i;
                return 'bar';
            },
        ]);

        $this->assertSame('bar', $locator->get('foo'));
        $this->assertSame('bar', $locator->get('foo'));
        $this->assertSame(2, $i);
    }

    public function testGetThrowsOnUndefinedService(): void
    {
        $this->expectException(\Psr\Container\NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Service "dummy" not found: the container inside "Symfony\Component\DependencyInjection\Tests\ServiceLocatorTest" is a smaller service locator that only knows about the "foo" and "bar" services.');
        $locator = new ServiceLocator([
            'foo' => static fn(): string => 'bar',
            'bar' => static fn(): string => 'baz',
        ]);

        $locator->get('dummy');
    }

    public function testThrowsOnUndefinedInternalService(): void
    {
        $this->expectException(\Psr\Container\NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('The service "foo" has a dependency on a non-existent service "bar". This locator only knows about the "foo" service.');
        $locator = new ServiceLocator([
            'foo' => static function () use (&$locator) {
                return $locator->get('bar');
            },
        ]);

        $locator->get('foo');
    }

    public function testThrowsOnCircularReference(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected for service "bar", path: "bar -> baz -> bar".');
        $locator = new ServiceLocator([
            'foo' => static function () use (&$locator) {
                return $locator->get('bar');
            },
            'bar' => static function () use (&$locator) {
                return $locator->get('baz');
            },
            'baz' => static function () use (&$locator) {
                return $locator->get('bar');
            },
        ]);

        $locator->get('foo');
    }

    public function testThrowsInServiceSubscriber(): void
    {
        $this->expectException(\Psr\Container\NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Service "foo" not found: even though it exists in the app\'s container, the container inside "caller" is a smaller service locator that only knows about the "bar" service. Unless you need extra laziness, try using dependency injection instead. Otherwise, you need to declare it using "SomeServiceSubscriber::getSubscribedServices()".');
        $container = new Container();
        $container->set('foo', new \stdClass());

        $subscriber = new SomeServiceSubscriber();
        $subscriber->container = new ServiceLocator(['bar' => static function () : void {
        }]);
        $subscriber->container = $subscriber->container->withContext('caller', $container);

        $subscriber->getFoo();
    }

    public function testGetThrowsServiceNotFoundException(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException::class);
        $this->expectExceptionMessage('Service "foo" not found: even though it exists in the app\'s container, the container inside "foo" is a smaller service locator that is empty... Try using dependency injection instead.');
        $container = new Container();
        $container->set('foo', new \stdClass());

        $locator = new ServiceLocator([]);
        $locator = $locator->withContext('foo', $container);
        $locator->get('foo');
    }

    public function testInvoke(): void
    {
        $locator = new ServiceLocator([
            'foo' => static fn(): string => 'bar',
            'bar' => static fn(): string => 'baz',
        ]);

        $this->assertSame('bar', $locator('foo'));
        $this->assertSame('baz', $locator('bar'));
        $this->assertNull($locator('dummy'), '->__invoke() should return null on invalid service');
    }
}

class SomeServiceSubscriber implements ServiceSubscriberinterface
{
    public $container;

    public function getFoo()
    {
        return $this->container->get('foo');
    }

    public static function getSubscribedServices(): array
    {
        return ['bar' => 'stdClass'];
    }
}
