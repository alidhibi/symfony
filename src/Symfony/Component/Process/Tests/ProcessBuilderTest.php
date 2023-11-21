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
use Symfony\Component\Process\ProcessBuilder;

/**
 * @group legacy
 */
class ProcessBuilderTest extends TestCase
{
    public function testInheritEnvironmentVars(): void
    {
        $proc = ProcessBuilder::create()
            ->add('foo')
            ->getProcess();

        $this->assertTrue($proc->areEnvironmentVariablesInherited());

        $proc = ProcessBuilder::create()
            ->add('foo')
            ->inheritEnvironmentVariables(false)
            ->getProcess();

        $this->assertFalse($proc->areEnvironmentVariablesInherited());
    }

    public function testAddEnvironmentVariables(): void
    {
        $pb = new ProcessBuilder();
        $env = [
            'foo' => 'bar',
            'foo2' => 'bar2',
        ];
        $proc = $pb
            ->add('command')
            ->setEnv('foo', 'bar2')
            ->addEnvironmentVariables($env)
            ->getProcess()
        ;

        $this->assertSame($env, $proc->getEnv());
    }

    public function testNegativeTimeoutFromSetter(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\InvalidArgumentException::class);
        $pb = new ProcessBuilder();
        $pb->setTimeout(-1);
    }

    public function testNullTimeout(): void
    {
        $pb = new ProcessBuilder();
        $pb->setTimeout(10);
        $pb->setTimeout(null);

        $r = new \ReflectionObject($pb);
        $p = $r->getProperty('timeout');
        $p->setAccessible(true);

        $this->assertNull($p->getValue($pb));
    }

    public function testShouldSetArguments(): void
    {
        $pb = new ProcessBuilder(['initial']);
        $pb->setArguments(['second']);

        $proc = $pb->getProcess();

        $this->assertStringContainsString('second', $proc->getCommandLine());
    }

    public function testPrefixIsPrependedToAllGeneratedProcess(): void
    {
        $pb = new ProcessBuilder();
        $pb->setPrefix('/usr/bin/php');

        $proc = $pb->setArguments(['-v'])->getProcess();
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->assertEquals('"/usr/bin/php" -v', $proc->getCommandLine());
        } else {
            $this->assertEquals("'/usr/bin/php' '-v'", $proc->getCommandLine());
        }

        $proc = $pb->setArguments(['-i'])->getProcess();
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->assertEquals('"/usr/bin/php" -i', $proc->getCommandLine());
        } else {
            $this->assertEquals("'/usr/bin/php' '-i'", $proc->getCommandLine());
        }
    }

    public function testArrayPrefixesArePrependedToAllGeneratedProcess(): void
    {
        $pb = new ProcessBuilder();
        $pb->setPrefix(['/usr/bin/php', 'composer.phar']);

        $proc = $pb->setArguments(['-v'])->getProcess();
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->assertEquals('"/usr/bin/php" composer.phar -v', $proc->getCommandLine());
        } else {
            $this->assertEquals("'/usr/bin/php' 'composer.phar' '-v'", $proc->getCommandLine());
        }

        $proc = $pb->setArguments(['-i'])->getProcess();
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->assertEquals('"/usr/bin/php" composer.phar -i', $proc->getCommandLine());
        } else {
            $this->assertEquals("'/usr/bin/php' 'composer.phar' '-i'", $proc->getCommandLine());
        }
    }

    public function testShouldEscapeArguments(): void
    {
        $pb = new ProcessBuilder(['%path%', 'foo " bar', '%baz%baz']);
        $proc = $pb->getProcess();

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->assertSame('""^%"path"^%"" "foo "" bar" ""^%"baz"^%"baz"', $proc->getCommandLine());
        } else {
            $this->assertSame("'%path%' 'foo \" bar' '%baz%baz'", $proc->getCommandLine());
        }
    }

    public function testShouldEscapeArgumentsAndPrefix(): void
    {
        $pb = new ProcessBuilder(['arg']);
        $pb->setPrefix('%prefix%');

        $proc = $pb->getProcess();

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->assertSame('""^%"prefix"^%"" arg', $proc->getCommandLine());
        } else {
            $this->assertSame("'%prefix%' 'arg'", $proc->getCommandLine());
        }
    }

    public function testShouldThrowALogicExceptionIfNoPrefixAndNoArgument(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\LogicException::class);
        ProcessBuilder::create()->getProcess();
    }

    public function testShouldNotThrowALogicExceptionIfNoArgument(): void
    {
        $process = ProcessBuilder::create()
            ->setPrefix('/usr/bin/php')
            ->getProcess();

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->assertEquals('"/usr/bin/php"', $process->getCommandLine());
        } else {
            $this->assertEquals("'/usr/bin/php'", $process->getCommandLine());
        }
    }

    public function testShouldNotThrowALogicExceptionIfNoPrefix(): void
    {
        $process = ProcessBuilder::create(['/usr/bin/php'])
            ->getProcess();

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->assertEquals('"/usr/bin/php"', $process->getCommandLine());
        } else {
            $this->assertEquals("'/usr/bin/php'", $process->getCommandLine());
        }
    }

    public function testShouldReturnProcessWithDisabledOutput(): void
    {
        $process = ProcessBuilder::create(['/usr/bin/php'])
            ->disableOutput()
            ->getProcess();

        $this->assertTrue($process->isOutputDisabled());
    }

    public function testShouldReturnProcessWithEnabledOutput(): void
    {
        $process = ProcessBuilder::create(['/usr/bin/php'])
            ->disableOutput()
            ->enableOutput()
            ->getProcess();

        $this->assertFalse($process->isOutputDisabled());
    }

    public function testInvalidInput(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('"' . \Symfony\Component\Process\ProcessBuilder::class . '::setInput" only accepts strings, Traversable objects or stream resources.');
        $builder = ProcessBuilder::create();
        $builder->setInput([]);
    }

    public function testDoesNotPrefixExec(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot run on Windows.');
        }

        $builder = ProcessBuilder::create(['command', '-v', 'ls']);
        $process = $builder->getProcess();
        $process->run();

        $this->assertTrue($process->isSuccessful());
    }
}
