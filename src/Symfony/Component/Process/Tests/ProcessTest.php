<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Pipes\PipesInterface;
use Symfony\Component\Process\Process;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class ProcessTest extends TestCase
{
    private static $phpBin;

    private static ?\Symfony\Component\Process\Process $process = null;

    private static bool $sigchild;

    private static bool $notEnhancedSigchild = false;

    public static function setUpBeforeClass(): void
    {
        $phpBin = new PhpExecutableFinder();
        self::$phpBin = getenv('SYMFONY_PROCESS_PHP_TEST_BINARY') ?: ('phpdbg' === \PHP_SAPI ? 'php' : $phpBin->find());

        ob_start();
        phpinfo(\INFO_GENERAL);
        self::$sigchild = false !== strpos(ob_get_clean(), '--enable-sigchild');
    }

    protected function tearDown()
    {
        if (self::$process instanceof \Symfony\Component\Process\Process) {
            self::$process->stop(0);
            self::$process = null;
        }
    }

    /**
     * @group legacy
     * @expectedDeprecation The provided cwd does not exist. Command is currently ran against getcwd(). This behavior is deprecated since Symfony 3.4 and will be removed in 4.0.
     */
    public function testInvalidCwd(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('False-positive on Windows/appveyor.');
        }

        // Check that it works fine if the CWD exists
        $cmd = new Process('echo test', __DIR__);
        $cmd->run();

        $cmd = new Process('echo test', __DIR__.'/notfound/');
        $cmd->run();
    }

    public function testThatProcessDoesNotThrowWarningDuringRun(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test is transient on Windows');
        }

        @trigger_error('Test Error', \E_USER_NOTICE);
        $process = $this->getProcessForCode('sleep(3)');
        $process->run();

        $actualError = error_get_last();
        $this->assertEquals('Test Error', $actualError['message']);
        $this->assertEquals(\E_USER_NOTICE, $actualError['type']);
    }

    public function testNegativeTimeoutFromConstructor(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\InvalidArgumentException::class);
        $this->getProcess('', null, null, null, -1);
    }

    public function testNegativeTimeoutFromSetter(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\InvalidArgumentException::class);
        $p = $this->getProcess('');
        $p->setTimeout(-1);
    }

    public function testFloatAndNullTimeout(): void
    {
        $p = $this->getProcess('');

        $p->setTimeout(10);
        $this->assertSame(10.0, $p->getTimeout());

        $p->setTimeout(null);
        $this->assertNull($p->getTimeout());

        $p->setTimeout(0.0);
        $this->assertNull($p->getTimeout());
    }

    /**
     * @requires extension pcntl
     */
    public function testStopWithTimeoutIsActuallyWorking(): void
    {
        $p = $this->getProcess([self::$phpBin, __DIR__.'/NonStopableProcess.php', 30]);
        $p->start();

        while (false === strpos($p->getOutput(), 'received')) {
            usleep(1000);
        }

        $start = microtime(true);
        $p->stop(0.1);

        $p->wait();

        $this->assertLessThan(15, microtime(true) - $start);
    }

    public function testAllOutputIsActuallyReadOnTermination(): void
    {
        // this code will result in a maximum of 2 reads of 8192 bytes by calling
        // start() and isRunning().  by the time getOutput() is called the process
        // has terminated so the internal pipes array is already empty. normally
        // the call to start() will not read any data as the process will not have
        // generated output, but this is non-deterministic so we must count it as
        // a possibility.  therefore we need 2 * PipesInterface::CHUNK_SIZE plus
        // another byte which will never be read.
        $expectedOutputSize = PipesInterface::CHUNK_SIZE * 2 + 2;

        $code = sprintf("echo str_repeat('*', %d);", $expectedOutputSize);
        $p = $this->getProcessForCode($code);

        $p->start();

        // Don't call Process::run nor Process::wait to avoid any read of pipes
        $h = new \ReflectionProperty($p, 'process');
        $h->setAccessible(true);
        $h = $h->getValue($p);

        $s = @proc_get_status($h);

        while (isset($s['running']) && $s['running'] !== '') {
            usleep(1000);
            $s = proc_get_status($h);
        }

        $o = $p->getOutput();

        $this->assertEquals($expectedOutputSize, \strlen($o));
    }

    public function testCallbacksAreExecutedWithStart(): void
    {
        $process = $this->getProcess('echo foo');
        $process->start(static function ($type, string $buffer) use (&$data) : void {
            $data .= $buffer;
        });

        $process->wait();

        $this->assertSame('foo'.\PHP_EOL, $data);
    }

    /**
     * tests results from sub processes.
     *
     * @dataProvider responsesCodeProvider
     */
    public function testProcessResponses(string $expected, string $getter, string $code): void
    {
        $p = $this->getProcessForCode($code);
        $p->run();

        $this->assertSame($expected, $p->$getter());
    }

    /**
     * tests results from sub processes.
     *
     * @dataProvider pipesCodeProvider
     */
    public function testProcessPipes($code, $size): void
    {
        $expected = str_repeat(str_repeat('*', 1024), $size).'!';
        $expectedLength = (1024 * $size) + 1;

        $p = $this->getProcessForCode($code);
        $p->setInput($expected);
        $p->run();

        $this->assertEquals($expectedLength, \strlen($p->getOutput()));
        $this->assertEquals($expectedLength, \strlen($p->getErrorOutput()));
    }

    /**
     * @dataProvider pipesCodeProvider
     */
    public function testSetStreamAsInput($code, $size): void
    {
        $expected = str_repeat(str_repeat('*', 1024), $size).'!';
        $expectedLength = (1024 * $size) + 1;

        $stream = fopen('php://temporary', 'w+');
        fwrite($stream, $expected);
        rewind($stream);

        $p = $this->getProcessForCode($code);
        $p->setInput($stream);
        $p->run();

        fclose($stream);

        $this->assertEquals($expectedLength, \strlen($p->getOutput()));
        $this->assertEquals($expectedLength, \strlen($p->getErrorOutput()));
    }

    public function testLiveStreamAsInput(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'hello');
        rewind($stream);

        $p = $this->getProcessForCode('stream_copy_to_stream(STDIN, STDOUT);');
        $p->setInput($stream);
        $p->start(static function ($type, $data) use ($stream) : void {
            if ('hello' === $data) {
                fclose($stream);
            }
        });
        $p->wait();

        $this->assertSame('hello', $p->getOutput());
    }

    public function testSetInputWhileRunningThrowsAnException(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\LogicException::class);
        $this->expectExceptionMessage('Input can not be set while the process is running.');
        $process = $this->getProcessForCode('sleep(30);');
        $process->start();
        try {
            $process->setInput('foobar');
            $process->stop();
            $this->fail('A LogicException should have been raised.');
        } catch (LogicException $logicException) {
        }

        $process->stop();

        throw $e;
    }

    /**
     * @dataProvider provideInvalidInputValues
     */
    public function testInvalidInput(mixed $value): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('"' . \Symfony\Component\Process\Process::class . '::setInput" only accepts strings, Traversable objects or stream resources.');
        $process = $this->getProcess('foo');
        $process->setInput($value);
    }

    public function provideInvalidInputValues(): array
    {
        return [
            [[]],
            [new NonStringifiable()],
        ];
    }

    /**
     * @dataProvider provideInputValues
     */
    public function testValidInput(?string $expected, mixed $value): void
    {
        $process = $this->getProcess('foo');
        $process->setInput($value);
        $this->assertSame($expected, $process->getInput());
    }

    public function provideInputValues(): array
    {
        return [
            [null, null],
            ['24.5', 24.5],
            ['input data', 'input data'],
        ];
    }

    public function chainedCommandsOutputProvider(): array
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            return [
                ["2 \r\n2\r\n", '&&', '2'],
            ];
        }

        return [
            ["1\n1\n", ';', '1'],
            ["2\n2\n", '&&', '2'],
        ];
    }

    /**
     * @dataProvider chainedCommandsOutputProvider
     */
    public function testChainedCommandsOutput(string $expected, string $operator, string $input): void
    {
        $process = $this->getProcess(sprintf('echo %s %s echo %s', $input, $operator, $input));
        $process->run();
        $this->assertEquals($expected, $process->getOutput());
    }

    public function testCallbackIsExecutedForOutput(): void
    {
        $p = $this->getProcessForCode("echo 'foo';");

        $called = false;
        $p->run(static function ($type, $buffer) use (&$called) : void {
            $called = 'foo' === $buffer;
        });

        $this->assertTrue($called, 'The callback should be executed with the output');
    }

    public function testCallbackIsExecutedForOutputWheneverOutputIsDisabled(): void
    {
        $p = $this->getProcessForCode("echo 'foo';");
        $p->disableOutput();

        $called = false;
        $p->run(static function ($type, $buffer) use (&$called) : void {
            $called = 'foo' === $buffer;
        });

        $this->assertTrue($called, 'The callback should be executed with the output');
    }

    public function testGetErrorOutput(): void
    {
        $p = $this->getProcessForCode('$n = 0; while ($n < 3) { file_put_contents(\'php://stderr\', \'ERROR\'); $n++; }');

        $p->run();
        $this->assertEquals(3, preg_match_all('/ERROR/', $p->getErrorOutput(), $matches));
    }

    public function testFlushErrorOutput(): void
    {
        $p = $this->getProcessForCode('$n = 0; while ($n < 3) { file_put_contents(\'php://stderr\', \'ERROR\'); $n++; }');

        $p->run();
        $p->clearErrorOutput();
        $this->assertEmpty($p->getErrorOutput());
    }

    /**
     * @dataProvider provideIncrementalOutput
     */
    public function testIncrementalOutput(string $getOutput, string $getIncrementalOutput, string $uri): void
    {
        $lock = tempnam(sys_get_temp_dir(), __FUNCTION__);

        $p = $this->getProcessForCode('file_put_contents($s = \''.$uri."', 'foo'); flock(fopen(".var_export($lock, true).', \'r\'), LOCK_EX); file_put_contents($s, \'bar\');');

        $h = fopen($lock, 'w');
        flock($h, \LOCK_EX);

        $p->start();

        foreach (['foo', 'bar'] as $s) {
            while (false === strpos($p->$getOutput(), $s)) {
                usleep(1000);
            }

            $this->assertSame($s, $p->$getIncrementalOutput());
            $this->assertSame('', $p->$getIncrementalOutput());

            flock($h, \LOCK_UN);
        }

        fclose($h);
    }

    public function provideIncrementalOutput(): array
    {
        return [
            ['getOutput', 'getIncrementalOutput', 'php://stdout'],
            ['getErrorOutput', 'getIncrementalErrorOutput', 'php://stderr'],
        ];
    }

    public function testGetOutput(): void
    {
        $p = $this->getProcessForCode('$n = 0; while ($n < 3) { echo \' foo \'; $n++; }');

        $p->run();
        $this->assertEquals(3, preg_match_all('/foo/', $p->getOutput(), $matches));
    }

    public function testFlushOutput(): void
    {
        $p = $this->getProcessForCode('$n=0;while ($n<3) {echo \' foo \';$n++;}');

        $p->run();
        $p->clearOutput();
        $this->assertEmpty($p->getOutput());
    }

    public function testZeroAsOutput(): void
    {
        $p = '\\' === \DIRECTORY_SEPARATOR ? $this->getProcess('echo | set /p dummyName=0') : $this->getProcess('printf 0');

        $p->run();
        $this->assertSame('0', $p->getOutput());
    }

    public function testExitCodeCommandFailed(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX exit code');
        }

        $this->skipIfNotEnhancedSigchild();

        // such command run in bash return an exitcode 127
        $process = $this->getProcess('nonexistingcommandIhopeneversomeonewouldnameacommandlikethis');
        $process->run();

        $this->assertGreaterThan(0, $process->getExitCode());
    }

    public function testTTYCommand(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not have /dev/tty support');
        }

        $process = $this->getProcess('echo "foo" >> /dev/null && '.$this->getProcessForCode('usleep(100000);')->getCommandLine());
        $process->setTty(true);
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->wait();

        $this->assertSame(Process::STATUS_TERMINATED, $process->getStatus());
    }

    public function testTTYCommandExitCode(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does have /dev/tty support');
        }

        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo "foo" >> /dev/null');
        $process->setTty(true);
        $process->run();

        $this->assertTrue($process->isSuccessful());
    }

    public function testTTYInWindowsEnvironment(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\RuntimeException::class);
        $this->expectExceptionMessage('TTY mode is not supported on Windows platform.');
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test is for Windows platform only');
        }

        $process = $this->getProcess('echo "foo" >> /dev/null');
        $process->setTty(false);
        $process->setTty(true);
    }

    public function testExitCodeTextIsNullWhenExitCodeIsNull(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('');
        $this->assertNull($process->getExitCodeText());
    }

    public function testPTYCommand(): void
    {
        if (!Process::isPtySupported()) {
            $this->markTestSkipped('PTY is not supported on this operating system.');
        }

        $process = $this->getProcess('echo "foo"');
        $process->setPty(true);
        $process->run();

        $this->assertSame(Process::STATUS_TERMINATED, $process->getStatus());
        $this->assertEquals("foo\r\n", $process->getOutput());
    }

    public function testMustRun(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo');

        $this->assertSame($process, $process->mustRun());
        $this->assertEquals('foo'.\PHP_EOL, $process->getOutput());
    }

    public function testSuccessfulMustRunHasCorrectExitCode(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo')->mustRun();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testMustRunThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\ProcessFailedException::class);
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('exit 1');
        $process->mustRun();
    }

    public function testExitCodeText(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('');
        $r = new \ReflectionObject($process);
        $p = $r->getProperty('exitcode');
        $p->setAccessible(true);

        $p->setValue($process, 2);
        $this->assertEquals('Misuse of shell builtins', $process->getExitCodeText());
    }

    public function testStartIsNonBlocking(): void
    {
        $process = $this->getProcessForCode('usleep(500000);');
        $start = microtime(true);
        $process->start();
        $end = microtime(true);
        $this->assertLessThan(0.4, $end - $start);
        $process->stop();
    }

    public function testUpdateStatus(): void
    {
        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertGreaterThan(0, \strlen($process->getOutput()));
    }

    public function testGetExitCodeIsNullOnStart(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcessForCode('usleep(100000);');
        $this->assertNull($process->getExitCode());
        $process->start();
        $this->assertNull($process->getExitCode());
        $process->wait();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testGetExitCodeIsNullOnWhenStartingAgain(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcessForCode('usleep(100000);');
        $process->run();
        $this->assertEquals(0, $process->getExitCode());
        $process->start();
        $this->assertNull($process->getExitCode());
        $process->wait();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testGetExitCode(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertSame(0, $process->getExitCode());
    }

    public function testStatus(): void
    {
        $process = $this->getProcessForCode('usleep(100000);');
        $this->assertFalse($process->isRunning());
        $this->assertFalse($process->isStarted());
        $this->assertFalse($process->isTerminated());
        $this->assertSame(Process::STATUS_READY, $process->getStatus());
        $process->start();
        $this->assertTrue($process->isRunning());
        $this->assertTrue($process->isStarted());
        $this->assertFalse($process->isTerminated());
        $this->assertSame(Process::STATUS_STARTED, $process->getStatus());
        $process->wait();
        $this->assertFalse($process->isRunning());
        $this->assertTrue($process->isStarted());
        $this->assertTrue($process->isTerminated());
        $this->assertSame(Process::STATUS_TERMINATED, $process->getStatus());
    }

    public function testStop(): void
    {
        $process = $this->getProcessForCode('sleep(31);');
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->stop();
        $this->assertFalse($process->isRunning());
    }

    public function testIsSuccessful(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertTrue($process->isSuccessful());
    }

    public function testIsSuccessfulOnlyAfterTerminated(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcessForCode('usleep(100000);');
        $process->start();

        $this->assertFalse($process->isSuccessful());

        $process->wait();

        $this->assertTrue($process->isSuccessful());
    }

    public function testIsNotSuccessful(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcessForCode('throw new \Exception(\'BOUM\');');
        $process->run();
        $this->assertFalse($process->isSuccessful());
    }

    public function testProcessIsNotSignaled(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertFalse($process->hasBeenSignaled());
    }

    public function testProcessWithoutTermSignal(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertEquals(0, $process->getTermSignal());
    }

    public function testProcessIsSignaledIfStopped(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcessForCode('sleep(32);');
        $process->start();
        $process->stop();
        $this->assertTrue($process->hasBeenSignaled());
        $this->assertEquals(15, $process->getTermSignal()); // SIGTERM
    }

    public function testProcessThrowsExceptionWhenExternallySignaled(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\RuntimeException::class);
        $this->expectExceptionMessage('The process has been signaled');
        if (!\function_exists('posix_kill')) {
            $this->markTestSkipped('Function posix_kill is required.');
        }

        $this->skipIfNotEnhancedSigchild(false);

        $process = $this->getProcessForCode('sleep(32.1);');
        $process->start();
        posix_kill($process->getPid(), 9); // SIGKILL

        $process->wait();
    }

    public function testRestart(): void
    {
        $process1 = $this->getProcessForCode('echo getmypid();');
        $process1->run();

        $process2 = $process1->restart();

        $process2->wait(); // wait for output

        // Ensure that both processed finished and the output is numeric
        $this->assertFalse($process1->isRunning());
        $this->assertFalse($process2->isRunning());
        $this->assertIsNumeric($process1->getOutput());
        $this->assertIsNumeric($process2->getOutput());

        // Ensure that restart returned a new process by check that the output is different
        $this->assertNotEquals($process1->getOutput(), $process2->getOutput());
    }

    public function testRunProcessWithTimeout(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\ProcessTimedOutException::class);
        $this->expectExceptionMessage('exceeded the timeout of 0.1 seconds.');
        $process = $this->getProcessForCode('sleep(30);');
        $process->setTimeout(0.1);

        $start = microtime(true);
        try {
            $process->run();
            $this->fail('A RuntimeException should have been raised');
        } catch (RuntimeException $runtimeException) {
        }

        $this->assertLessThan(15, microtime(true) - $start);

        throw $runtimeException;
    }

    public function testIterateOverProcessWithTimeout(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\ProcessTimedOutException::class);
        $this->expectExceptionMessage('exceeded the timeout of 0.1 seconds.');
        $process = $this->getProcessForCode('sleep(30);');
        $process->setTimeout(0.1);

        $start = microtime(true);
        try {
            $process->start();

            $this->fail('A RuntimeException should have been raised');
        } catch (RuntimeException $runtimeException) {
        }

        $this->assertLessThan(15, microtime(true) - $start);

        throw $runtimeException;
    }

    public function testCheckTimeoutOnNonStartedProcess(): void
    {
        $process = $this->getProcess('echo foo');
        $this->assertNull($process->checkTimeout());
    }

    public function testCheckTimeoutOnTerminatedProcess(): void
    {
        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertNull($process->checkTimeout());
    }

    public function testCheckTimeoutOnStartedProcess(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\ProcessTimedOutException::class);
        $this->expectExceptionMessage('exceeded the timeout of 0.1 seconds.');
        $process = $this->getProcessForCode('sleep(33);');
        $process->setTimeout(0.1);

        $process->start();

        $start = microtime(true);

        try {
            while ($process->isRunning()) {
                $process->checkTimeout();
                usleep(100000);
            }

            $this->fail('A ProcessTimedOutException should have been raised');
        } catch (ProcessTimedOutException $processTimedOutException) {
        }

        $this->assertLessThan(15, microtime(true) - $start);

        throw $processTimedOutException;
    }

    public function testIdleTimeout(): void
    {
        $process = $this->getProcessForCode('sleep(34);');
        $process->setTimeout(60);
        $process->setIdleTimeout(0.1);

        try {
            $process->run();

            $this->fail('A timeout exception was expected.');
        } catch (ProcessTimedOutException $processTimedOutException) {
            $this->assertTrue($processTimedOutException->isIdleTimeout());
            $this->assertFalse($processTimedOutException->isGeneralTimeout());
            $this->assertEquals(0.1, $processTimedOutException->getExceededTimeout());
        }
    }

    public function testIdleTimeoutNotExceededWhenOutputIsSent(): void
    {
        $process = $this->getProcessForCode("while (true) {echo 'foo '; usleep(1000);}");
        $process->setTimeout(1);
        $process->start();

        while (false === strpos($process->getOutput(), 'foo')) {
            usleep(1000);
        }

        $process->setIdleTimeout(0.5);

        try {
            $process->wait();
            $this->fail('A timeout exception was expected.');
        } catch (ProcessTimedOutException $processTimedOutException) {
            $this->assertTrue($processTimedOutException->isGeneralTimeout(), 'A general timeout is expected.');
            $this->assertFalse($processTimedOutException->isIdleTimeout(), 'No idle timeout is expected.');
            $this->assertEquals(1, $processTimedOutException->getExceededTimeout());
        }
    }

    public function testStartAfterATimeout(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\ProcessTimedOutException::class);
        $this->expectExceptionMessage('exceeded the timeout of 0.1 seconds.');
        $process = $this->getProcessForCode('sleep(35);');
        $process->setTimeout(0.1);

        try {
            $process->run();
            $this->fail('A ProcessTimedOutException should have been raised.');
        } catch (ProcessTimedOutException $processTimedOutException) {
        }

        $this->assertFalse($process->isRunning());
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->stop(0);

        throw $processTimedOutException;
    }

    public function testGetPid(): void
    {
        $process = $this->getProcessForCode('sleep(36);');
        $process->start();
        $this->assertGreaterThan(0, $process->getPid());
        $process->stop(0);
    }

    public function testGetPidIsNullBeforeStart(): void
    {
        $process = $this->getProcess('foo');
        $this->assertNull($process->getPid());
    }

    public function testGetPidIsNullAfterRun(): void
    {
        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertNull($process->getPid());
    }

    /**
     * @requires extension pcntl
     */
    public function testSignal(): void
    {
        $process = $this->getProcess([self::$phpBin, __DIR__.'/SignalListener.php']);
        $process->start();

        while (false === strpos($process->getOutput(), 'Caught')) {
            usleep(1000);
        }

        $process->signal(\SIGUSR1);
        $process->wait();

        $this->assertEquals('Caught SIGUSR1', $process->getOutput());
    }

    /**
     * @requires extension pcntl
     */
    public function testExitCodeIsAvailableAfterSignal(): void
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('sleep 4');
        $process->start();
        $process->signal(\SIGKILL);

        while ($process->isRunning()) {
            usleep(10000);
        }

        $this->assertFalse($process->isRunning());
        $this->assertTrue($process->hasBeenSignaled());
        $this->assertFalse($process->isSuccessful());
        $this->assertEquals(137, $process->getExitCode());
    }

    public function testSignalProcessNotRunning(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\LogicException::class);
        $this->expectExceptionMessage('Can not send signal on a non running process.');
        $process = $this->getProcess('foo');
        $process->signal(1); // SIGHUP
    }

    /**
     * @dataProvider provideMethodsThatNeedARunningProcess
     */
    public function testMethodsThatNeedARunningProcess(string $method): void
    {
        $process = $this->getProcess('foo');

        $this->expectException(\Symfony\Component\Process\Exception\LogicException::class);
        $this->expectExceptionMessage(sprintf('Process must be started before calling "%s()".', $method));

        $process->{$method}();
    }

    public function provideMethodsThatNeedARunningProcess(): array
    {
        return [
            ['getOutput'],
            ['getIncrementalOutput'],
            ['getErrorOutput'],
            ['getIncrementalErrorOutput'],
            ['wait'],
        ];
    }

    /**
     * @dataProvider provideMethodsThatNeedATerminatedProcess
     */
    public function testMethodsThatNeedATerminatedProcess(string $method): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\LogicException::class);
        $this->expectExceptionMessage('Process must be terminated before calling');
        $process = $this->getProcessForCode('sleep(37);');
        $process->start();
        try {
            $process->{$method}();
            $process->stop(0);
            $this->fail('A LogicException must have been thrown');
        } catch (\Exception $exception) {
        }

        $process->stop(0);

        throw $e;
    }

    public function provideMethodsThatNeedATerminatedProcess(): array
    {
        return [
            ['hasBeenSignaled'],
            ['getTermSignal'],
            ['hasBeenStopped'],
            ['getStopSignal'],
        ];
    }

    /**
     * @dataProvider provideWrongSignal
     */
    public function testWrongSignal(int|string $signal): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('POSIX signals do not work on Windows');
        }

        if (\PHP_VERSION_ID < 80000 || \is_int($signal)) {
            $this->expectException(RuntimeException::class);
        } else {
            $this->expectException('TypeError');
        }

        $process = $this->getProcessForCode('sleep(38);');
        $process->start();
        try {
            $process->signal($signal);
            $this->fail('A RuntimeException must have been thrown');
        } catch (\TypeError $e) {
            $process->stop(0);
        } catch (RuntimeException $e) {
            $process->stop(0);
        }

        throw $e;
    }

    public function provideWrongSignal(): array
    {
        return [
            [-4],
            ['Céphalopodes'],
        ];
    }

    public function testDisableOutputDisablesTheOutput(): void
    {
        $p = $this->getProcess('foo');
        $this->assertFalse($p->isOutputDisabled());
        $p->disableOutput();
        $this->assertTrue($p->isOutputDisabled());
        $p->enableOutput();
        $this->assertFalse($p->isOutputDisabled());
    }

    public function testDisableOutputWhileRunningThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Disabling output while the process is running is not possible.');
        $p = $this->getProcessForCode('sleep(39);');
        $p->start();
        $p->disableOutput();
    }

    public function testEnableOutputWhileRunningThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Enabling output while the process is running is not possible.');
        $p = $this->getProcessForCode('sleep(40);');
        $p->disableOutput();
        $p->start();
        $p->enableOutput();
    }

    public function testEnableOrDisableOutputAfterRunDoesNotThrowException(): void
    {
        $p = $this->getProcess('echo foo');
        $p->disableOutput();
        $p->run();
        $p->enableOutput();
        $p->disableOutput();
        $this->assertTrue($p->isOutputDisabled());
    }

    public function testDisableOutputWhileIdleTimeoutIsSet(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\LogicException::class);
        $this->expectExceptionMessage('Output can not be disabled while an idle timeout is set.');
        $process = $this->getProcess('foo');
        $process->setIdleTimeout(1);
        $process->disableOutput();
    }

    public function testSetIdleTimeoutWhileOutputIsDisabled(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\LogicException::class);
        $this->expectExceptionMessage('timeout can not be set while the output is disabled.');
        $process = $this->getProcess('foo');
        $process->disableOutput();
        $process->setIdleTimeout(1);
    }

    public function testSetNullIdleTimeoutWhileOutputIsDisabled(): void
    {
        $process = $this->getProcess('foo');
        $process->disableOutput();
        $this->assertSame($process, $process->setIdleTimeout(null));
    }

    /**
     * @dataProvider provideOutputFetchingMethods
     */
    public function testGetOutputWhileDisabled(string $fetchMethod): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\LogicException::class);
        $this->expectExceptionMessage('Output has been disabled.');
        $p = $this->getProcessForCode('sleep(41);');
        $p->disableOutput();
        $p->start();
        $p->{$fetchMethod}();
    }

    public function provideOutputFetchingMethods(): array
    {
        return [
            ['getOutput'],
            ['getIncrementalOutput'],
            ['getErrorOutput'],
            ['getIncrementalErrorOutput'],
        ];
    }

    public function testStopTerminatesProcessCleanly(): void
    {
        $process = $this->getProcessForCode('echo 123; sleep(42);');
        $process->run(static function () use ($process) : void {
            $process->stop();
        });
        $this->assertTrue(true, 'A call to stop() is not expected to cause wait() to throw a RuntimeException');
    }

    public function testKillSignalTerminatesProcessCleanly(): void
    {
        $process = $this->getProcessForCode('echo 123; sleep(43);');
        $process->run(static function () use ($process) : void {
            $process->signal(9);
            // SIGKILL
        });
        $this->assertTrue(true, 'A call to signal() is not expected to cause wait() to throw a RuntimeException');
    }

    public function testTermSignalTerminatesProcessCleanly(): void
    {
        $process = $this->getProcessForCode('echo 123; sleep(44);');
        $process->run(static function () use ($process) : void {
            $process->signal(15);
            // SIGTERM
        });
        $this->assertTrue(true, 'A call to signal() is not expected to cause wait() to throw a RuntimeException');
    }

    public function responsesCodeProvider(): array
    {
        return [
            //expected output / getter / code to execute
            //[1,'getExitCode','exit(1);'],
            //[true,'isSuccessful','exit();'],
            ['output', 'getOutput', "echo 'output';"],
        ];
    }

    public function pipesCodeProvider(): array
    {
        $variations = [
            'fwrite(STDOUT, $in = file_get_contents(\'php://stdin\')); fwrite(STDERR, $in);',
            "include '".__DIR__."/PipeStdinInStdoutStdErrStreamSelect.php';",
        ];

        $sizes = '\\' === \DIRECTORY_SEPARATOR ? [1, 2, 4, 8] : [1, 16, 64, 1024, 4096];

        $codes = [];
        foreach ($sizes as $size) {
            foreach ($variations as $code) {
                $codes[] = [$code, $size];
            }
        }

        return $codes;
    }

    /**
     * @dataProvider provideVariousIncrementals
     */
    public function testIncrementalOutputDoesNotRequireAnotherCall(string $stream, string $method): void
    {
        $process = $this->getProcessForCode('$n = 0; while ($n < 3) { file_put_contents(\''.$stream.'\', $n, 1); $n++; usleep(1000); }', null, null, null, null);
        $process->start();

        $result = '';
        $limit = microtime(true) + 3;
        $expected = '012';

        while ($result !== $expected && microtime(true) < $limit) {
            $result .= $process->$method();
        }

        $this->assertSame($expected, $result);
        $process->stop();
    }

    public function provideVariousIncrementals(): array
    {
        return [
            ['php://stdout', 'getIncrementalOutput'],
            ['php://stderr', 'getIncrementalErrorOutput'],
        ];
    }

    public function testIteratorInput(): void
    {
        $input = static function () : \Generator {
            yield 'ping';
            yield 'pong';
        };

        $process = $this->getProcessForCode('stream_copy_to_stream(STDIN, STDOUT);', null, null, $input());
        $process->run();
        $this->assertSame('pingpong', $process->getOutput());
    }

    public function testSimpleInputStream(): void
    {
        $input = new InputStream();

        $process = $this->getProcessForCode("echo 'ping'; echo fread(STDIN, 4); echo fread(STDIN, 4);");
        $process->setInput($input);

        $process->start(static function ($type, $data) use ($input) : void {
            if ('ping' === $data) {
                $input->write('pang');
            } elseif (!$input->isClosed()) {
                $input->write('pong');
                $input->close();
            }
        });

        $process->wait();
        $this->assertSame('pingpangpong', $process->getOutput());
    }

    public function testInputStreamWithCallable(): void
    {
        $i = 0;
        $stream = fopen('php://memory', 'w+');
        $stream = static function () use ($stream, &$i) {
            if ($i < 3) {
                rewind($stream);
                fwrite($stream, ++$i);
                rewind($stream);

                return $stream;
            }
            return null;
        };

        $input = new InputStream();
        $input->onEmpty($stream);
        $input->write($stream());

        $process = $this->getProcessForCode('echo fread(STDIN, 3);');
        $process->setInput($input);
        $process->start(static function ($type, $data) use ($input) : void {
            $input->close();
        });

        $process->wait();
        $this->assertSame('123', $process->getOutput());
    }

    public function testInputStreamWithGenerator(): void
    {
        $input = new InputStream();
        $input->onEmpty(static function ($input) : \Generator {
            yield 'pong';
            $input->close();
        });

        $process = $this->getProcessForCode('stream_copy_to_stream(STDIN, STDOUT);');
        $process->setInput($input);
        $process->start();

        $input->write('ping');
        $process->wait();
        $this->assertSame('pingpong', $process->getOutput());
    }

    public function testInputStreamOnEmpty(): void
    {
        $i = 0;
        $input = new InputStream();
        $input->onEmpty(static function () use (&$i) : void {
            ++$i;
        });

        $process = $this->getProcessForCode('echo 123; echo fread(STDIN, 1); echo 456;');
        $process->setInput($input);
        $process->start(static function ($type, $data) use ($input) : void {
            if ('123' === $data) {
                $input->close();
            }
        });
        $process->wait();

        $this->assertSame(0, $i, 'InputStream->onEmpty callback should be called only when the input *becomes* empty');
        $this->assertSame('123456', $process->getOutput());
    }

    public function testIteratorOutput(): void
    {
        $input = new InputStream();

        $process = $this->getProcessForCode('fwrite(STDOUT, 123); fwrite(STDERR, 234); flush(); usleep(10000); fwrite(STDOUT, fread(STDIN, 3)); fwrite(STDERR, 456);');
        $process->setInput($input);
        $process->start();

        $output = [];

        foreach ($process as $type => $data) {
            $output[] = [$type, $data];
            break;
        }

        $expectedOutput = [
            [$process::OUT, '123'],
        ];
        $this->assertSame($expectedOutput, $output);

        $input->write(345);

        foreach ($process as $type => $data) {
            $output[] = [$type, $data];
        }

        $this->assertSame('', $process->getOutput());
        $this->assertFalse($process->isRunning());

        $expectedOutput = [
            [$process::OUT, '123'],
            [$process::ERR, '234'],
            [$process::OUT, '345'],
            [$process::ERR, '456'],
        ];
        $this->assertSame($expectedOutput, $output);
    }

    public function testNonBlockingNorClearingIteratorOutput(): void
    {
        $input = new InputStream();

        $process = $this->getProcessForCode('fwrite(STDOUT, fread(STDIN, 3));');
        $process->setInput($input);
        $process->start();

        $output = [];

        foreach ($process->getIterator($process::ITER_NON_BLOCKING | $process::ITER_KEEP_OUTPUT) as $type => $data) {
            $output[] = [$type, $data];
            break;
        }

        $expectedOutput = [
            [$process::OUT, ''],
        ];
        $this->assertSame($expectedOutput, $output);

        $input->write(123);

        foreach ($process->getIterator($process::ITER_NON_BLOCKING | $process::ITER_KEEP_OUTPUT) as $type => $data) {
            if ('' !== $data) {
                $output[] = [$type, $data];
            }
        }

        $this->assertSame('123', $process->getOutput());
        $this->assertFalse($process->isRunning());

        $expectedOutput = [
            [$process::OUT, ''],
            [$process::OUT, '123'],
        ];
        $this->assertSame($expectedOutput, $output);
    }

    public function testChainedProcesses(): void
    {
        $p1 = $this->getProcessForCode('fwrite(STDERR, 123); fwrite(STDOUT, 456);');
        $p2 = $this->getProcessForCode('stream_copy_to_stream(STDIN, STDOUT);');
        $p2->setInput($p1);

        $p1->start();
        $p2->run();

        $this->assertSame('123', $p1->getErrorOutput());
        $this->assertSame('', $p1->getOutput());
        $this->assertSame('', $p2->getErrorOutput());
        $this->assertSame('456', $p2->getOutput());
    }

    public function testSetBadEnv(): void
    {
        $process = $this->getProcess('echo hello');
        $process->setEnv(['bad%%' => '123']);
        $process->inheritEnvironmentVariables(true);

        $process->run();

        $this->assertSame('hello'.\PHP_EOL, $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    public function testEnvBackupDoesNotDeleteExistingVars(): void
    {
        putenv('existing_var=foo');
        $_ENV['existing_var'] = 'foo';
        $process = $this->getProcess('php -r "echo getenv(\'new_test_var\');"');
        $process->setEnv(['existing_var' => 'bar', 'new_test_var' => 'foo']);
        $process->inheritEnvironmentVariables();

        $process->run();

        $this->assertSame('foo', $process->getOutput());
        $this->assertSame('foo', getenv('existing_var'));
        $this->assertFalse(getenv('new_test_var'));

        putenv('existing_var');
        unset($_ENV['existing_var']);
    }

    public function testEnvIsInherited(): void
    {
        $process = $this->getProcessForCode('echo serialize($_SERVER);', null, ['BAR' => 'BAZ', 'EMPTY' => '']);

        putenv('FOO=BAR');
        $_ENV['FOO'] = 'BAR';

        $process->run();

        $expected = ['BAR' => 'BAZ', 'EMPTY' => '', 'FOO' => 'BAR'];
        $env = array_intersect_key(unserialize($process->getOutput()), $expected);

        $this->assertEquals($expected, $env);

        putenv('FOO');
        unset($_ENV['FOO']);
    }

    /**
     * @group legacy
     */
    public function testInheritEnvDisabled(): void
    {
        $process = $this->getProcessForCode('echo serialize($_SERVER);', null, ['BAR' => 'BAZ']);

        putenv('FOO=BAR');
        $_ENV['FOO'] = 'BAR';

        $this->assertSame($process, $process->inheritEnvironmentVariables(false));
        $this->assertFalse($process->areEnvironmentVariablesInherited());

        $process->run();

        $expected = ['BAR' => 'BAZ', 'FOO' => 'BAR'];
        $env = array_intersect_key(unserialize($process->getOutput()), $expected);
        unset($expected['FOO']);

        $this->assertSame($expected, $env);

        putenv('FOO');
        unset($_ENV['FOO']);
    }

    public function testGetCommandLine(): void
    {
        $p = new Process(['/usr/bin/php']);

        $expected = '\\' === \DIRECTORY_SEPARATOR ? '"/usr/bin/php"' : "'/usr/bin/php'";
        $this->assertSame($expected, $p->getCommandLine());
    }

    /**
     * @dataProvider provideEscapeArgument
     */
    public function testEscapeArgument(string|int|float|null $arg): void
    {
        $p = new Process([self::$phpBin, '-r', 'echo $argv[1];', $arg]);
        $p->run();

        $this->assertSame((string) $arg, $p->getOutput());
    }

    /**
     * @dataProvider provideEscapeArgument
     * @group legacy
     */
    public function testEscapeArgumentWhenInheritEnvDisabled(string|int|float|null $arg): void
    {
        $p = new Process([self::$phpBin, '-r', 'echo $argv[1];', $arg], null, ['BAR' => 'BAZ']);
        $p->inheritEnvironmentVariables(false);
        $p->run();

        $this->assertSame((string) $arg, $p->getOutput());
    }

    public function testRawCommandLine(): void
    {
        $p = new Process(sprintf('"%s" -r %s "a" "" "b"', self::$phpBin, escapeshellarg('print_r($argv);')));
        $p->run();

        $expected = <<<EOTXT
Array
(
    [0] => -
    [1] => a
    [2] => 
    [3] => b
)

EOTXT;
        $this->assertSame($expected, str_replace('Standard input code', '-', $p->getOutput()));
    }

    public function provideEscapeArgument(): \Generator
    {
        yield ['a"b%c%'];
        yield ['a"b^c^'];
        yield ["a\nb'c"];
        yield ['a^b c!'];
        yield ["a!b\tc"];
        yield ['a\\\\"\\"'];
        yield ['éÉèÈàÀöä'];
        yield [null];
        yield [1];
        yield [1.1];
    }

    public function testEnvArgument(): void
    {
        $env = ['FOO' => 'Foo', 'BAR' => 'Bar'];
        $cmd = '\\' === \DIRECTORY_SEPARATOR ? 'echo !FOO! !BAR! !BAZ!' : 'echo $FOO $BAR $BAZ';
        $p = new Process($cmd, null, $env);
        $p->run(null, ['BAR' => 'baR', 'BAZ' => 'baZ']);

        $this->assertSame('Foo baR baZ', rtrim($p->getOutput()));
        $this->assertSame($env, $p->getEnv());
    }

    public function testWaitStoppedDeadProcess(): void
    {
        $process = $this->getProcess(self::$phpBin.' '.__DIR__.'/ErrorProcessInitiator.php -e '.self::$phpBin);
        $process->start();
        $process->setTimeout(2);
        $process->wait();
        $this->assertFalse($process->isRunning());
    }

    /**
     * @param string      $commandline
     * @param string|null $cwd
     * @param string|null $input
     * @param int         $timeout
     *
     * @return Process
     */
    private function getProcess(string|array $commandline, $cwd = null, array $env = null, ?\Generator $input = null, $timeout = 60)
    {
        $process = new Process($commandline, $cwd, $env, $input, $timeout);
        $process->inheritEnvironmentVariables();

        if (false !== $enhance = getenv('ENHANCE_SIGCHLD')) {
            try {
                $process->setEnhanceSigchildCompatibility(false);
                $process->getExitCode();
                $this->fail('ENHANCE_SIGCHLD must be used together with a sigchild-enabled PHP.');
            } catch (RuntimeException $e) {
                $this->assertSame('This PHP has been compiled with --enable-sigchild. You must use setEnhanceSigchildCompatibility() to use this method.', $e->getMessage());
                if ($enhance !== '' && $enhance !== '0') {
                    $process->setEnhanceSigchildCompatibility(true);
                } else {
                    self::$notEnhancedSigchild = true;
                }
            }
        }

        if (self::$process instanceof \Symfony\Component\Process\Process) {
            self::$process->stop(0);
        }

        return self::$process = $process;
    }

    /**
     * @return Process
     */
    private function getProcessForCode($code, $cwd = null, ?array $env = null, ?\Generator $input = null, $timeout = 60)
    {
        return $this->getProcess([self::$phpBin, '-r', $code], $cwd, $env, $input, $timeout);
    }

    private function skipIfNotEnhancedSigchild(bool $expectException = true): void
    {
        if (self::$sigchild) {
            if (!$expectException) {
                $this->markTestSkipped('PHP is compiled with --enable-sigchild.');
            } elseif (self::$notEnhancedSigchild) {
                $this->expectException(\Symfony\Component\Process\Exception\RuntimeException::class);
                $this->expectExceptionMessage('This PHP has been compiled with --enable-sigchild.');
            }
        }
    }
}

class NonStringifiable
{
}
