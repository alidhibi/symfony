<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\EventListener\DebugHandlersListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugHandlersListenerTest extends TestCase
{
    public function testConfigure(): void
    {
        $logger = $this->getMockBuilder(\Psr\Log\LoggerInterface::class)->getMock();
        $userHandler = static function () : void {
        };
        $listener = new DebugHandlersListener($userHandler, $logger);
        $xHandler = new ExceptionHandler();
        $eHandler = new ErrorHandler();
        $eHandler->setExceptionHandler(static fn(\Exception $exception) => $xHandler->handle($exception));

        $exception = null;
        set_error_handler(static fn(int $type, string $message, string $file, int $line): bool => $eHandler->handleError($type, $message, $file, $line));
        set_exception_handler(static fn(\Throwable $exception, ?array $error = null) => $eHandler->handleException($exception, $error));
        try {
            $listener->configure();
        } catch (\Exception $exception) {
        }

        restore_exception_handler();
        restore_error_handler();

        if ($exception instanceof \Exception) {
            throw $exception;
        }

        $this->assertSame($userHandler, $xHandler->setHandler('var_dump'));

        $loggers = $eHandler->setLoggers([]);

        $this->assertArrayHasKey(\E_DEPRECATED, $loggers);
        $this->assertSame([$logger, LogLevel::INFO], $loggers[\E_DEPRECATED]);
    }

    public function testConfigureForHttpKernelWithNoTerminateWithException(): void
    {
        $listener = new DebugHandlersListener(null);
        $eHandler = new ErrorHandler();
        $event = new KernelEvent(
            $this->getMockBuilder(\Symfony\Component\HttpKernel\HttpKernelInterface::class)->getMock(),
            Request::create('/'),
            HttpKernelInterface::MASTER_REQUEST
        );

        $exception = null;
        $h = set_exception_handler(static fn(\Throwable $exception, ?array $error = null) => $eHandler->handleException($exception, $error));
        try {
            $listener->configure($event);
        } catch (\Exception $exception) {
        }

        restore_exception_handler();

        if ($exception instanceof \Exception) {
            throw $exception;
        }

        $this->assertNull($h);
    }

    public function testConsoleEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new DebugHandlersListener(null);
        $app = $this->getMockBuilder(\Symfony\Component\Console\Application::class)->getMock();
        $app->expects($this->once())->method('getHelperSet')->willReturn(new HelperSet());
        $command = new Command(__FUNCTION__);
        $command->setApplication($app);

        $event = new ConsoleEvent($command, new ArgvInput(), new ConsoleOutput());

        $dispatcher->addSubscriber($listener);

        $xListeners = [
            KernelEvents::REQUEST => [static fn(?\Symfony\Component\EventDispatcher\Event $event = null) => $listener->configure($event)],
            ConsoleEvents::COMMAND => [static fn(?\Symfony\Component\EventDispatcher\Event $event = null) => $listener->configure($event)],
        ];
        $this->assertSame($xListeners, $dispatcher->getListeners());

        $exception = null;
        $eHandler = new ErrorHandler();
        set_error_handler(static fn(int $type, string $message, string $file, int $line): bool => $eHandler->handleError($type, $message, $file, $line));
        set_exception_handler(static fn(\Throwable $exception, ?array $error = null) => $eHandler->handleException($exception, $error));
        try {
            $dispatcher->dispatch(ConsoleEvents::COMMAND, $event);
        } catch (\Exception $exception) {
        }

        restore_exception_handler();
        restore_error_handler();

        if ($exception instanceof \Exception) {
            throw $exception;
        }

        $xHandler = $eHandler->setExceptionHandler('var_dump');
        $this->assertInstanceOf('Closure', $xHandler);

        $app->expects($this->once())
            ->method('renderException');

        $xHandler(new \Exception());
    }

    public function testReplaceExistingExceptionHandler(): void
    {
        $userHandler = static function () : void {
        };
        $listener = new DebugHandlersListener($userHandler);
        $eHandler = new ErrorHandler();
        $eHandler->setExceptionHandler('var_dump');

        $exception = null;
        set_exception_handler(static fn(\Throwable $exception, ?array $error = null) => $eHandler->handleException($exception, $error));
        try {
            $listener->configure();
        } catch (\Exception $exception) {
        }

        restore_exception_handler();

        if ($exception instanceof \Exception) {
            throw $exception;
        }

        $this->assertSame($userHandler, $eHandler->setExceptionHandler('var_dump'));
    }
}
