<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ApplicationTest extends TestCase
{
    protected static $fixturesPath;

    private string|array|bool $colSize;

    protected function setUp()
    {
        $this->colSize = getenv('COLUMNS');
    }

    protected function tearDown()
    {
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');
        putenv('SHELL_VERBOSITY');
        unset($_ENV['SHELL_VERBOSITY']);
        unset($_SERVER['SHELL_VERBOSITY']);
    }

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/Fixtures/');
        require_once self::$fixturesPath.'/FooCommand.php';
        require_once self::$fixturesPath.'/FooOptCommand.php';
        require_once self::$fixturesPath.'/Foo1Command.php';
        require_once self::$fixturesPath.'/Foo2Command.php';
        require_once self::$fixturesPath.'/Foo3Command.php';
        require_once self::$fixturesPath.'/Foo4Command.php';
        require_once self::$fixturesPath.'/Foo5Command.php';
        require_once self::$fixturesPath.'/FooSameCaseUppercaseCommand.php';
        require_once self::$fixturesPath.'/FooSameCaseLowercaseCommand.php';
        require_once self::$fixturesPath.'/FoobarCommand.php';
        require_once self::$fixturesPath.'/BarBucCommand.php';
        require_once self::$fixturesPath.'/FooSubnamespaced1Command.php';
        require_once self::$fixturesPath.'/FooSubnamespaced2Command.php';
        require_once self::$fixturesPath.'/TestAmbiguousCommandRegistering.php';
        require_once self::$fixturesPath.'/TestAmbiguousCommandRegistering2.php';
        require_once self::$fixturesPath.'/FooHiddenCommand.php';
    }

    protected function normalizeLineBreaks($text): array|string
    {
        return str_replace(\PHP_EOL, "\n", $text);
    }

    /**
     * Replaces the dynamic placeholders of the command help text with a static version.
     * The placeholder %command.full_name% includes the script path that is not predictable
     * and can not be tested against.
     */
    protected function ensureStaticCommandHelp(Application $application)
    {
        foreach ($application->all() as $command) {
            $command->setHelp(str_replace('%command.full_name%', 'app/console %command.name%', $command->getHelp()));
        }
    }

    public function testConstructor(): void
    {
        $application = new Application('foo', 'bar');
        $this->assertEquals('foo', $application->getName(), '__construct() takes the application name as its first argument');
        $this->assertEquals('bar', $application->getVersion(), '__construct() takes the application version as its second argument');
        $this->assertEquals(['help', 'list'], array_keys($application->all()), '__construct() registered the help and list commands by default');
    }

    public function testSetGetName(): void
    {
        $application = new Application();
        $application->setName('foo');
        $this->assertEquals('foo', $application->getName(), '->setName() sets the name of the application');
    }

    public function testSetGetVersion(): void
    {
        $application = new Application();
        $application->setVersion('bar');
        $this->assertEquals('bar', $application->getVersion(), '->setVersion() sets the version of the application');
    }

    public function testGetLongVersion(): void
    {
        $application = new Application('foo', 'bar');
        $this->assertEquals('foo <info>bar</info>', $application->getLongVersion(), '->getLongVersion() returns the long version of the application');
    }

    public function testHelp(): void
    {
        $application = new Application();
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_gethelp.txt', $this->normalizeLineBreaks($application->getHelp()), '->getHelp() returns a help message');
    }

    public function testAll(): void
    {
        $application = new Application();
        $commands = $application->all();
        $this->assertInstanceOf(\Symfony\Component\Console\Command\HelpCommand::class, $commands['help'], '->all() returns the registered commands');

        $application->add(new \FooCommand());
        $commands = $application->all('foo');
        $this->assertCount(1, $commands, '->all() takes a namespace as its first argument');
    }

    public function testAllWithCommandLoader(): void
    {
        $application = new Application();
        $commands = $application->all();
        $this->assertInstanceOf(\Symfony\Component\Console\Command\HelpCommand::class, $commands['help'], '->all() returns the registered commands');

        $application->add(new \FooCommand());
        $commands = $application->all('foo');
        $this->assertCount(1, $commands, '->all() takes a namespace as its first argument');

        $application->setCommandLoader(new FactoryCommandLoader([
            'foo:bar1' => static fn(): \Foo1Command => new \Foo1Command(),
        ]));
        $commands = $application->all('foo');
        $this->assertCount(2, $commands, '->all() takes a namespace as its first argument');
        $this->assertInstanceOf(\FooCommand::class, $commands['foo:bar'], '->all() returns the registered commands');
        $this->assertInstanceOf(\Foo1Command::class, $commands['foo:bar1'], '->all() returns the registered commands');
    }

    public function testRegister(): void
    {
        $application = new Application();
        $command = $application->register('foo');
        $this->assertEquals('foo', $command->getName(), '->register() registers a new command');
    }

    public function testRegisterAmbiguous(): void
    {
        $code = static function (InputInterface $input, OutputInterface $output) : void {
            $output->writeln('It works!');
        };

        $application = new Application();
        $application->setAutoExit(false);
        $application
            ->register('test-foo')
            ->setAliases(['test'])
            ->setCode($code);

        $application
            ->register('test-bar')
            ->setCode($code);

        $tester = new ApplicationTester($application);
        $tester->run(['test']);
        $this->assertStringContainsString('It works!', $tester->getDisplay(true));
    }

    public function testAdd(): void
    {
        $application = new Application();
        $application->add($foo = new \FooCommand());

        $commands = $application->all();
        $this->assertEquals($foo, $commands['foo:bar'], '->add() registers a command');

        $application = new Application();
        $application->addCommands([$foo = new \FooCommand(), $foo1 = new \Foo1Command()]);

        $commands = $application->all();
        $this->assertEquals([$foo, $foo1], [$commands['foo:bar'], $commands['foo:bar1']], '->addCommands() registers an array of commands');
    }

    public function testAddCommandWithEmptyConstructor(): void
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('Command class "Foo5Command" is not correctly initialized. You probably forgot to call the parent constructor.');
        $application = new Application();
        $application->add(new \Foo5Command());
    }

    public function testHasGet(): void
    {
        $application = new Application();
        $this->assertTrue($application->has('list'), '->has() returns true if a named command is registered');
        $this->assertFalse($application->has('afoobar'), '->has() returns false if a named command is not registered');

        $application->add($foo = new \FooCommand());
        $this->assertTrue($application->has('afoobar'), '->has() returns true if an alias is registered');
        $this->assertEquals($foo, $application->get('foo:bar'), '->get() returns a command by name');
        $this->assertEquals($foo, $application->get('afoobar'), '->get() returns a command by alias');

        $application = new Application();
        $application->add($foo = new \FooCommand());
        // simulate --help
        $r = new \ReflectionObject($application);
        $p = $r->getProperty('wantHelps');
        $p->setAccessible(true);
        $p->setValue($application, true);

        $command = $application->get('foo:bar');
        $this->assertInstanceOf(\Symfony\Component\Console\Command\HelpCommand::class, $command, '->get() returns the help command if --help is provided as the input');
    }

    public function testHasGetWithCommandLoader(): void
    {
        $application = new Application();
        $this->assertTrue($application->has('list'), '->has() returns true if a named command is registered');
        $this->assertFalse($application->has('afoobar'), '->has() returns false if a named command is not registered');

        $application->add($foo = new \FooCommand());
        $this->assertTrue($application->has('afoobar'), '->has() returns true if an alias is registered');
        $this->assertEquals($foo, $application->get('foo:bar'), '->get() returns a command by name');
        $this->assertEquals($foo, $application->get('afoobar'), '->get() returns a command by alias');

        $application->setCommandLoader(new FactoryCommandLoader([
            'foo:bar1' => static fn(): \Foo1Command => new \Foo1Command(),
        ]));

        $this->assertTrue($application->has('afoobar'), '->has() returns true if an instance is registered for an alias even with command loader');
        $this->assertEquals($foo, $application->get('foo:bar'), '->get() returns an instance by name even with command loader');
        $this->assertEquals($foo, $application->get('afoobar'), '->get() returns an instance by alias even with command loader');
        $this->assertTrue($application->has('foo:bar1'), '->has() returns true for commands registered in the loader');
        $this->assertInstanceOf(\Foo1Command::class, $foo1 = $application->get('foo:bar1'), '->get() returns a command by name from the command loader');
        $this->assertTrue($application->has('afoobar1'), '->has() returns true for commands registered in the loader');
        $this->assertEquals($foo1, $application->get('afoobar1'), '->get() returns a command by name from the command loader');
    }

    public function testSilentHelp(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $tester = new ApplicationTester($application);
        $tester->run(['-h' => true, '-q' => true], ['decorated' => false]);

        $this->assertEmpty($tester->getDisplay(true));
    }

    public function testGetInvalidCommand(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\CommandNotFoundException::class);
        $this->expectExceptionMessage('The command "foofoo" does not exist.');
        $application = new Application();
        $application->get('foofoo');
    }

    public function testGetNamespaces(): void
    {
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $this->assertEquals(['foo'], $application->getNamespaces(), '->getNamespaces() returns an array of unique used namespaces');
    }

    public function testFindNamespace(): void
    {
        $application = new Application();
        $application->add(new \FooCommand());
        $this->assertEquals('foo', $application->findNamespace('foo'), '->findNamespace() returns the given namespace if it exists');
        $this->assertEquals('foo', $application->findNamespace('f'), '->findNamespace() finds a namespace given an abbreviation');
        $application->add(new \Foo2Command());
        $this->assertEquals('foo', $application->findNamespace('foo'), '->findNamespace() returns the given namespace if it exists');
    }

    public function testFindNamespaceWithSubnamespaces(): void
    {
        $application = new Application();
        $application->add(new \FooSubnamespaced1Command());
        $application->add(new \FooSubnamespaced2Command());
        $this->assertEquals('foo', $application->findNamespace('foo'), '->findNamespace() returns commands even if the commands are only contained in subnamespaces');
    }

    public function testFindAmbiguousNamespace(): void
    {
        $application = new Application();
        $application->add(new \BarBucCommand());
        $application->add(new \FooCommand());
        $application->add(new \Foo2Command());

        $expectedMsg = "The namespace \"f\" is ambiguous.\nDid you mean one of these?\n    foo\n    foo1";

        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage($expectedMsg);

        $application->findNamespace('f');
    }

    public function testFindNonAmbiguous(): void
    {
        $application = new Application();
        $application->add(new \TestAmbiguousCommandRegistering());
        $application->add(new \TestAmbiguousCommandRegistering2());
        $this->assertEquals('test-ambiguous', $application->find('test')->getName());
    }

    public function testFindInvalidNamespace(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\CommandNotFoundException::class);
        $this->expectExceptionMessage('There are no commands defined in the "bar" namespace.');
        $application = new Application();
        $application->findNamespace('bar');
    }

    public function testFindUniqueNameButNamespaceName(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\CommandNotFoundException::class);
        $this->expectExceptionMessage('Command "foo1" is not defined');
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());

        $application->find($commandName = 'foo1');
    }

    public function testFind(): void
    {
        $application = new Application();
        $application->add(new \FooCommand());

        $this->assertInstanceOf('FooCommand', $application->find('foo:bar'), '->find() returns a command if its name exists');
        $this->assertInstanceOf(\Symfony\Component\Console\Command\HelpCommand::class, $application->find('h'), '->find() returns a command if its name exists');
        $this->assertInstanceOf('FooCommand', $application->find('f:bar'), '->find() returns a command if the abbreviation for the namespace exists');
        $this->assertInstanceOf('FooCommand', $application->find('f:b'), '->find() returns a command if the abbreviation for the namespace and the command name exist');
        $this->assertInstanceOf('FooCommand', $application->find('a'), '->find() returns a command if the abbreviation exists for an alias');
    }

    public function testFindCaseSensitiveFirst(): void
    {
        $application = new Application();
        $application->add(new \FooSameCaseUppercaseCommand());
        $application->add(new \FooSameCaseLowercaseCommand());

        $this->assertInstanceOf('FooSameCaseUppercaseCommand', $application->find('f:B'), '->find() returns a command if the abbreviation is the correct case');
        $this->assertInstanceOf('FooSameCaseUppercaseCommand', $application->find('f:BAR'), '->find() returns a command if the abbreviation is the correct case');
        $this->assertInstanceOf('FooSameCaseLowercaseCommand', $application->find('f:b'), '->find() returns a command if the abbreviation is the correct case');
        $this->assertInstanceOf('FooSameCaseLowercaseCommand', $application->find('f:bar'), '->find() returns a command if the abbreviation is the correct case');
    }

    public function testFindCaseInsensitiveAsFallback(): void
    {
        $application = new Application();
        $application->add(new \FooSameCaseLowercaseCommand());

        $this->assertInstanceOf('FooSameCaseLowercaseCommand', $application->find('f:b'), '->find() returns a command if the abbreviation is the correct case');
        $this->assertInstanceOf('FooSameCaseLowercaseCommand', $application->find('f:B'), '->find() will fallback to case insensitivity');
        $this->assertInstanceOf('FooSameCaseLowercaseCommand', $application->find('FoO:BaR'), '->find() will fallback to case insensitivity');
    }

    public function testFindCaseInsensitiveSuggestions(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\CommandNotFoundException::class);
        $this->expectExceptionMessage('Command "FoO:BaR" is ambiguous');
        $application = new Application();
        $application->add(new \FooSameCaseLowercaseCommand());
        $application->add(new \FooSameCaseUppercaseCommand());

        $this->assertInstanceOf('FooSameCaseLowercaseCommand', $application->find('FoO:BaR'), '->find() will find two suggestions with case insensitivity');
    }

    public function testFindWithCommandLoader(): void
    {
        $application = new Application();
        $application->setCommandLoader(new FactoryCommandLoader([
            'foo:bar' => $f = static fn(): \FooCommand => new \FooCommand(),
        ]));

        $this->assertInstanceOf('FooCommand', $application->find('foo:bar'), '->find() returns a command if its name exists');
        $this->assertInstanceOf(\Symfony\Component\Console\Command\HelpCommand::class, $application->find('h'), '->find() returns a command if its name exists');
        $this->assertInstanceOf('FooCommand', $application->find('f:bar'), '->find() returns a command if the abbreviation for the namespace exists');
        $this->assertInstanceOf('FooCommand', $application->find('f:b'), '->find() returns a command if the abbreviation for the namespace and the command name exist');
        $this->assertInstanceOf('FooCommand', $application->find('a'), '->find() returns a command if the abbreviation exists for an alias');
    }

    /**
     * @dataProvider provideAmbiguousAbbreviations
     */
    public function testFindWithAmbiguousAbbreviations(string $abbreviation, string $expectedExceptionMessage): void
    {
        putenv('COLUMNS=120');
        $this->expectException(\Symfony\Component\Console\Exception\CommandNotFoundException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());

        $application->find($abbreviation);
    }

    public function provideAmbiguousAbbreviations(): array
    {
        return [
            ['f', 'Command "f" is not defined.'],
            [
                'a',
                'Command "a" is ambiguous.
Did you mean one of these?
    afoobar  The foo:bar command
'.
                "    afoobar1 The foo:bar1 command\n".
                '    afoobar2 The foo1:bar command',
            ],
            [
                'foo:b',
                'Command "foo:b" is ambiguous.
Did you mean one of these?
    foo:bar  The foo:bar command
'.
                "    foo:bar1 The foo:bar1 command\n".
                '    foo1:bar The foo1:bar command',
            ],
        ];
    }

    public function testFindCommandEqualNamespace(): void
    {
        $application = new Application();
        $application->add(new \Foo3Command());
        $application->add(new \Foo4Command());

        $this->assertInstanceOf('Foo3Command', $application->find('foo3:bar'), '->find() returns the good command even if a namespace has same name');
        $this->assertInstanceOf('Foo4Command', $application->find('foo3:bar:toh'), '->find() returns a command even if its namespace equals another command name');
    }

    public function testFindCommandWithAmbiguousNamespacesButUniqueName(): void
    {
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \FoobarCommand());

        $this->assertInstanceOf('FoobarCommand', $application->find('f:f'));
    }

    public function testFindCommandWithMissingNamespace(): void
    {
        $application = new Application();
        $application->add(new \Foo4Command());

        $this->assertInstanceOf('Foo4Command', $application->find('f::t'));
    }

    /**
     * @dataProvider provideInvalidCommandNamesSingle
     */
    public function testFindAlternativeExceptionMessageSingle(string $name): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\CommandNotFoundException::class);
        $this->expectExceptionMessage('Did you mean this');
        $application = new Application();
        $application->add(new \Foo3Command());
        $application->find($name);
    }

    public function provideInvalidCommandNamesSingle(): array
    {
        return [
            ['foo3:barr'],
            ['fooo3:bar'],
        ];
    }

    public function testFindAlternativeExceptionMessageMultiple(): void
    {
        putenv('COLUMNS=120');
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());

        // Command + plural
        try {
            $application->find('foo:baR');
            $this->fail('->find() throws a CommandNotFoundException if command does not exist, with alternatives');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\Symfony\Component\Console\Exception\CommandNotFoundException::class, $exception, '->find() throws a CommandNotFoundException if command does not exist, with alternatives');
            $this->assertMatchesRegularExpression('/Did you mean one of these/', $exception->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, with alternatives');
            $this->assertMatchesRegularExpression('/foo1:bar/', $exception->getMessage());
            $this->assertMatchesRegularExpression('/foo:bar/', $exception->getMessage());
        }

        // Namespace + plural
        try {
            $application->find('foo2:bar');
            $this->fail('->find() throws a CommandNotFoundException if command does not exist, with alternatives');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\Symfony\Component\Console\Exception\CommandNotFoundException::class, $exception, '->find() throws a CommandNotFoundException if command does not exist, with alternatives');
            $this->assertMatchesRegularExpression('/Did you mean one of these/', $exception->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, with alternatives');
            $this->assertMatchesRegularExpression('/foo1/', $exception->getMessage());
        }

        $application->add(new \Foo3Command());
        $application->add(new \Foo4Command());

        // Subnamespace + plural
        try {
            $application->find('foo3:');
            $this->fail('->find() should throw an Symfony\Component\Console\Exception\CommandNotFoundException if a command is ambiguous because of a subnamespace, with alternatives');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\Symfony\Component\Console\Exception\CommandNotFoundException::class, $exception);
            $this->assertMatchesRegularExpression('/foo3:bar/', $exception->getMessage());
            $this->assertMatchesRegularExpression('/foo3:bar:toh/', $exception->getMessage());
        }
    }

    public function testFindAlternativeCommands(): void
    {
        $application = new Application();

        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());

        try {
            $application->find($commandName = 'Unknown command');
            $this->fail('->find() throws a CommandNotFoundException if command does not exist');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\Symfony\Component\Console\Exception\CommandNotFoundException::class, $exception, '->find() throws a CommandNotFoundException if command does not exist');
            $this->assertSame([], $exception->getAlternatives());
            $this->assertEquals(sprintf('Command "%s" is not defined.', $commandName), $exception->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, without alternatives');
        }

        // Test if "bar1" command throw a "CommandNotFoundException" and does not contain
        // "foo:bar" as alternative because "bar1" is too far from "foo:bar"
        try {
            $application->find($commandName = 'bar1');
            $this->fail('->find() throws a CommandNotFoundException if command does not exist');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\Symfony\Component\Console\Exception\CommandNotFoundException::class, $exception, '->find() throws a CommandNotFoundException if command does not exist');
            $this->assertSame(['afoobar1', 'foo:bar1'], $exception->getAlternatives());
            $this->assertMatchesRegularExpression(sprintf('/Command "%s" is not defined./', $commandName), $exception->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, with alternatives');
            $this->assertMatchesRegularExpression('/afoobar1/', $exception->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, with alternative : "afoobar1"');
            $this->assertMatchesRegularExpression('/foo:bar1/', $exception->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, with alternative : "foo:bar1"');
            $this->assertDoesNotMatchRegularExpression('/foo:bar(?!1)/', $exception->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, without "foo:bar" alternative');
        }
    }

    public function testFindAlternativeCommandsWithAnAlias(): void
    {
        $fooCommand = new \FooCommand();
        $fooCommand->setAliases(['foo2']);

        $application = new Application();
        $application->setCommandLoader(new FactoryCommandLoader([
            'foo3' => static fn(): \FooCommand => $fooCommand,
        ]));
        $application->add($fooCommand);

        $result = $application->find('foo');

        $this->assertSame($fooCommand, $result);
    }

    public function testFindAlternativeNamespace(): void
    {
        $application = new Application();

        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());
        $application->add(new \Foo3Command());

        try {
            $application->find('Unknown-namespace:Unknown-command');
            $this->fail('->find() throws a CommandNotFoundException if namespace does not exist');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\Symfony\Component\Console\Exception\CommandNotFoundException::class, $exception, '->find() throws a CommandNotFoundException if namespace does not exist');
            $this->assertSame([], $exception->getAlternatives());
            $this->assertEquals('There are no commands defined in the "Unknown-namespace" namespace.', $exception->getMessage(), '->find() throws a CommandNotFoundException if namespace does not exist, without alternatives');
        }

        try {
            $application->find('foo2:command');
            $this->fail('->find() throws a CommandNotFoundException if namespace does not exist');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\Symfony\Component\Console\Exception\CommandNotFoundException::class, $exception, '->find() throws a CommandNotFoundException if namespace does not exist');
            $this->assertCount(3, $exception->getAlternatives());
            $this->assertContains('foo', $exception->getAlternatives());
            $this->assertContains('foo1', $exception->getAlternatives());
            $this->assertContains('foo3', $exception->getAlternatives());
            $this->assertMatchesRegularExpression('/There are no commands defined in the "foo2" namespace./', $exception->getMessage(), '->find() throws a CommandNotFoundException if namespace does not exist, with alternative');
            $this->assertMatchesRegularExpression('/foo/', $exception->getMessage(), '->find() throws a CommandNotFoundException if namespace does not exist, with alternative : "foo"');
            $this->assertMatchesRegularExpression('/foo1/', $exception->getMessage(), '->find() throws a CommandNotFoundException if namespace does not exist, with alternative : "foo1"');
            $this->assertMatchesRegularExpression('/foo3/', $exception->getMessage(), '->find() throws a CommandNotFoundException if namespace does not exist, with alternative : "foo3"');
        }
    }

    public function testFindAlternativesOutput(): void
    {
        $application = new Application();

        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());
        $application->add(new \Foo3Command());
        $application->add(new \FooHiddenCommand());

        $expectedAlternatives = [
            'afoobar',
            'afoobar1',
            'afoobar2',
            'foo1:bar',
            'foo3:bar',
            'foo:bar',
            'foo:bar1',
        ];

        try {
            $application->find('foo');
            $this->fail('->find() throws a CommandNotFoundException if command is not defined');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\Symfony\Component\Console\Exception\CommandNotFoundException::class, $exception, '->find() throws a CommandNotFoundException if command is not defined');
            $this->assertSame($expectedAlternatives, $exception->getAlternatives());

            $this->assertMatchesRegularExpression('/Command "foo" is not defined\..*Did you mean one of these\?.*/Ums', $exception->getMessage());
        }
    }

    public function testFindNamespaceDoesNotFailOnDeepSimilarNamespaces(): void
    {
        $application = $this->getMockBuilder(\Symfony\Component\Console\Application::class)->setMethods(['getNamespaces'])->getMock();
        $application->expects($this->once())
            ->method('getNamespaces')
            ->willReturn(['foo:sublong', 'bar:sub']);

        $this->assertEquals('foo:sublong', $application->findNamespace('f:sub'));
    }

    public function testFindWithDoubleColonInNameThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\CommandNotFoundException::class);
        $this->expectExceptionMessage('Command "foo::bar" is not defined.');
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo4Command());
        $application->find('foo::bar');
    }

    public function testSetCatchExceptions(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=120');
        $tester = new ApplicationTester($application);

        $application->setCatchExceptions(true);
        $this->assertTrue($application->areExceptionsCaught());

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $tester->getDisplay(true), '->setCatchExceptions() sets the catch exception flag');

        $tester->run(['command' => 'foo'], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $tester->getErrorOutput(true), '->setCatchExceptions() sets the catch exception flag');
        $this->assertSame('', $tester->getDisplay(true));

        $application->setCatchExceptions(false);
        try {
            $tester->run(['command' => 'foo'], ['decorated' => false]);
            $this->fail('->setCatchExceptions() sets the catch exception flag');
        } catch (\Exception $exception) {
            $this->assertInstanceOf('\Exception', $exception, '->setCatchExceptions() sets the catch exception flag');
            $this->assertEquals('Command "foo" is not defined.', $exception->getMessage(), '->setCatchExceptions() sets the catch exception flag');
        }
    }

    public function testAutoExitSetting(): void
    {
        $application = new Application();
        $this->assertTrue($application->isAutoExitEnabled());

        $application->setAutoExit(false);
        $this->assertFalse($application->isAutoExitEnabled());
    }

    public function testRenderException(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=120');
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $tester->getErrorOutput(true), '->renderException() renders a pretty exception');

        $tester->run(['command' => 'foo'], ['decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE, 'capture_stderr_separately' => true]);
        $this->assertStringContainsString('Exception trace', $tester->getErrorOutput(), '->renderException() renders a pretty exception with a stack trace when verbosity is verbose');

        $tester->run(['command' => 'list', '--foo' => true], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception2.txt', $tester->getErrorOutput(true), '->renderException() renders the command synopsis when an exception occurs in the context of a command');

        $application->add(new \Foo3Command());
        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo3:bar'], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception3.txt', $tester->getErrorOutput(true), '->renderException() renders a pretty exceptions with previous exceptions');

        $tester->run(['command' => 'foo3:bar'], ['decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE]);
        $this->assertMatchesRegularExpression('/\[Exception\]\s*First exception/', $tester->getDisplay(), '->renderException() renders a pretty exception without code exception when code exception is default and verbosity is verbose');
        $this->assertMatchesRegularExpression('/\[Exception\]\s*Second exception/', $tester->getDisplay(), '->renderException() renders a pretty exception without code exception when code exception is 0 and verbosity is verbose');
        $this->assertMatchesRegularExpression('/\[Exception \(404\)\]\s*Third exception/', $tester->getDisplay(), '->renderException() renders a pretty exception with code exception when code exception is 404 and verbosity is verbose');

        $tester->run(['command' => 'foo3:bar'], ['decorated' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception3decorated.txt', $tester->getDisplay(true), '->renderException() renders a pretty exceptions with previous exceptions');

        $tester->run(['command' => 'foo3:bar'], ['decorated' => true, 'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception3decorated.txt', $tester->getErrorOutput(true), '->renderException() renders a pretty exceptions with previous exceptions');

        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=32');
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false,  'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception4.txt', $tester->getErrorOutput(true), '->renderException() wraps messages when they are bigger than the terminal');
        putenv('COLUMNS=120');
    }

    public function testRenderExceptionWithDoubleWidthCharacters(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=120');
        $application->register('foo')->setCode(static function () : never {
            throw new \Exception('エラーメッセージ');
        });
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_renderexception_doublewidth1.txt', $tester->getErrorOutput(true), '->renderException() renders a pretty exceptions with previous exceptions');

        $tester->run(['command' => 'foo'], ['decorated' => true, 'capture_stderr_separately' => true]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_renderexception_doublewidth1decorated.txt', $tester->getErrorOutput(true), '->renderException() renders a pretty exceptions with previous exceptions');

        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=32');
        $application->register('foo')->setCode(static function () : never {
            throw new \Exception('コマンドの実行中にエラーが発生しました。');
        });
        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo'], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_renderexception_doublewidth2.txt', $tester->getErrorOutput(true), '->renderException() wraps messages when they are bigger than the terminal');
        putenv('COLUMNS=120');
    }

    public function testRenderExceptionEscapesLines(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=22');
        $application->register('foo')->setCode(static function () : never {
            throw new \Exception('dont break here <info>!</info>');
        });
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_renderexception_escapeslines.txt', $tester->getDisplay(true), '->renderException() escapes lines containing formatting');
        putenv('COLUMNS=120');
    }

    public function testRenderExceptionLineBreaks(): void
    {
        $application = $this->getMockBuilder(\Symfony\Component\Console\Application::class)->setMethods(['getTerminalWidth'])->getMock();
        $application->setAutoExit(false);
        $application->expects($this->any())
            ->method('getTerminalWidth')
            ->willReturn(120);
        $application->register('foo')->setCode(static function () : never {
            throw new \InvalidArgumentException("\n\nline 1 with extra spaces        \nline 2\n\nline 4\n");
        });
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_renderexception_linebreaks.txt', $tester->getDisplay(true), '->renderException() keep multiple line breaks');
    }

    public function testRenderExceptionStackTraceContainsRootException(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->register('foo')->setCode(static function () : never {
            throw new \Exception('Verbose exception');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo'], ['decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE]);

        $this->assertStringContainsString(sprintf('() at %s:', __FILE__), $tester->getDisplay());
    }

    public function testRun(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add($command = new \Foo1Command());
        $_SERVER['argv'] = ['cli.php', 'foo:bar1'];

        ob_start();
        $application->run();
        ob_end_clean();

        $this->assertInstanceOf(\Symfony\Component\Console\Input\ArgvInput::class, $command->input, '->run() creates an ArgvInput by default if none is given');
        $this->assertInstanceOf(\Symfony\Component\Console\Output\ConsoleOutput::class, $command->output, '->run() creates a ConsoleOutput by default if none is given');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $this->ensureStaticCommandHelp($application);
        $tester = new ApplicationTester($application);

        $tester->run([], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run1.txt', $tester->getDisplay(true), '->run() runs the list command if no argument is passed');

        $tester->run(['--help' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run2.txt', $tester->getDisplay(true), '->run() runs the help command if --help is passed');

        $tester->run(['-h' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run2.txt', $tester->getDisplay(true), '->run() runs the help command if -h is passed');

        $tester->run(['command' => 'list', '--help' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run3.txt', $tester->getDisplay(true), '->run() displays the help if --help is passed');

        $tester->run(['command' => 'list', '-h' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run3.txt', $tester->getDisplay(true), '->run() displays the help if -h is passed');

        $tester->run(['--ansi' => true]);
        $this->assertTrue($tester->getOutput()->isDecorated(), '->run() forces color output if --ansi is passed');

        $tester->run(['--no-ansi' => true]);
        $this->assertFalse($tester->getOutput()->isDecorated(), '->run() forces color output to be disabled if --no-ansi is passed');

        $tester->run(['--version' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run4.txt', $tester->getDisplay(true), '->run() displays the program version if --version is passed');

        $tester->run(['-V' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run4.txt', $tester->getDisplay(true), '->run() displays the program version if -v is passed');

        $tester->run(['command' => 'list', '--quiet' => true]);
        $this->assertSame('', $tester->getDisplay(), '->run() removes all output if --quiet is passed');
        $this->assertFalse($tester->getInput()->isInteractive(), '->run() sets off the interactive mode if --quiet is passed');

        $tester->run(['command' => 'list', '-q' => true]);
        $this->assertSame('', $tester->getDisplay(), '->run() removes all output if -q is passed');
        $this->assertFalse($tester->getInput()->isInteractive(), '->run() sets off the interactive mode if -q is passed');

        $tester->run(['command' => 'list', '--verbose' => true]);
        $this->assertSame(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if --verbose is passed');

        $tester->run(['command' => 'list', '--verbose' => 1]);
        $this->assertSame(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if --verbose=1 is passed');

        $tester->run(['command' => 'list', '--verbose' => 2]);
        $this->assertSame(Output::VERBOSITY_VERY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to very verbose if --verbose=2 is passed');

        $tester->run(['command' => 'list', '--verbose' => 3]);
        $this->assertSame(Output::VERBOSITY_DEBUG, $tester->getOutput()->getVerbosity(), '->run() sets the output to debug if --verbose=3 is passed');

        $tester->run(['command' => 'list', '--verbose' => 4]);
        $this->assertSame(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if unknown --verbose level is passed');

        $tester->run(['command' => 'list', '-v' => true]);
        $this->assertSame(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if -v is passed');

        $tester->run(['command' => 'list', '-vv' => true]);
        $this->assertSame(Output::VERBOSITY_VERY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if -v is passed');

        $tester->run(['command' => 'list', '-vvv' => true]);
        $this->assertSame(Output::VERBOSITY_DEBUG, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if -v is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add(new \FooCommand());

        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo:bar', '--no-interaction' => true], ['decorated' => false]);
        $this->assertSame('called'.\PHP_EOL, $tester->getDisplay(), '->run() does not call interact() if --no-interaction is passed');

        $tester->run(['command' => 'foo:bar', '-n' => true], ['decorated' => false]);
        $this->assertSame('called'.\PHP_EOL, $tester->getDisplay(), '->run() does not call interact() if -n is passed');
    }

    public function testRunWithGlobalOptionAndNoCommand(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->getDefinition()->addOption(new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL));

        $output = new StreamOutput(fopen('php://memory', 'w', false));
        $input = new ArgvInput(['cli.php', '--foo', 'bar']);

        $this->assertSame(0, $application->run($input, $output));
    }

    /**
     * Issue #9285.
     *
     * If the "verbose" option is just before an argument in ArgvInput,
     * an argument value should not be treated as verbosity value.
     * This test will fail with "Not enough arguments." if broken
     */
    public function testVerboseValueNotBreakArguments(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add(new \FooCommand());

        $output = new StreamOutput(fopen('php://memory', 'w', false));

        $input = new ArgvInput(['cli.php', '-v', 'foo:bar']);
        $application->run($input, $output);

        $this->addToAssertionCount(1);

        $input = new ArgvInput(['cli.php', '--verbose', 'foo:bar']);
        $application->run($input, $output);

        $this->addToAssertionCount(1);
    }

    public function testRunReturnsIntegerExitCode(): void
    {
        $exception = new \Exception('', 4);

        $application = $this->getMockBuilder(\Symfony\Component\Console\Application::class)->setMethods(['doRun'])->getMock();
        $application->setAutoExit(false);
        $application->expects($this->once())
            ->method('doRun')
            ->willThrowException($exception);

        $exitCode = $application->run(new ArrayInput([]), new NullOutput());

        $this->assertSame(4, $exitCode, '->run() returns integer exit code extracted from raised exception');
    }

    public function testRunDispatchesIntegerExitCode(): void
    {
        $passedRightValue = false;

        // We can assume here that some other test asserts that the event is dispatched at all
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('console.terminate', static function (ConsoleTerminateEvent $event) use (&$passedRightValue) : void {
            $passedRightValue = (4 === $event->getExitCode());
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('test')->setCode(static function (InputInterface $input, OutputInterface $output) : never {
            throw new \Exception('', 4);
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'test']);

        $this->assertTrue($passedRightValue, '-> exit code 4 was passed in the console.terminate event');
    }

    public function testRunReturnsExitCodeOneForExceptionCodeZero(): void
    {
        $exception = new \Exception('', 0);

        $application = $this->getMockBuilder(\Symfony\Component\Console\Application::class)->setMethods(['doRun'])->getMock();
        $application->setAutoExit(false);
        $application->expects($this->once())
            ->method('doRun')
            ->willThrowException($exception);

        $exitCode = $application->run(new ArrayInput([]), new NullOutput());

        $this->assertSame(1, $exitCode, '->run() returns exit code 1 when exception code is 0');
    }

    public function testRunDispatchesExitCodeOneForExceptionCodeZero(): void
    {
        $passedRightValue = false;

        // We can assume here that some other test asserts that the event is dispatched at all
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('console.terminate', static function (ConsoleTerminateEvent $event) use (&$passedRightValue) : void {
            $passedRightValue = (1 === $event->getExitCode());
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('test')->setCode(static function (InputInterface $input, OutputInterface $output) : never {
            throw new \Exception();
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'test']);

        $this->assertTrue($passedRightValue, '-> exit code 1 was passed in the console.terminate event');
    }

    public function testAddingOptionWithDuplicateShortcut(): void
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('An option with shortcut "e" already exists.');
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher($dispatcher);

        $application->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'Environment'));

        $application
            ->register('foo')
            ->setAliases(['f'])
            ->setDefinition([new InputOption('survey', 'e', InputOption::VALUE_REQUIRED, 'My option with a shortcut.')])
            ->setCode(static function (InputInterface $input, OutputInterface $output) : void {
            })
        ;

        $input = new ArrayInput(['command' => 'foo']);
        $output = new NullOutput();

        $application->run($input, $output);
    }

    /**
     * @dataProvider getAddingAlreadySetDefinitionElementData
     */
    public function testAddingAlreadySetDefinitionElementData(\Symfony\Component\Console\Input\InputArgument|\Symfony\Component\Console\Input\InputOption $def): void
    {
        $this->expectException('LogicException');
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application
            ->register('foo')
            ->setDefinition([$def])
            ->setCode(static function (InputInterface $input, OutputInterface $output) : void {
            })
        ;

        $input = new ArrayInput(['command' => 'foo']);
        $output = new NullOutput();
        $application->run($input, $output);
    }

    public function getAddingAlreadySetDefinitionElementData(): array
    {
        return [
            [new InputArgument('command', InputArgument::REQUIRED)],
            [new InputOption('quiet', '', InputOption::VALUE_NONE)],
            [new InputOption('query', 'q', InputOption::VALUE_NONE)],
        ];
    }

    public function testGetDefaultHelperSetReturnsDefaultValues(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $helperSet = $application->getHelperSet();

        $this->assertTrue($helperSet->has('formatter'));
    }

    public function testAddingSingleHelperSetOverwritesDefaultValues(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->setHelperSet(new HelperSet([new FormatterHelper()]));

        $helperSet = $application->getHelperSet();

        $this->assertTrue($helperSet->has('formatter'));

        // no other default helper set should be returned
        $this->assertFalse($helperSet->has('dialog'));
        $this->assertFalse($helperSet->has('progress'));
    }

    public function testOverwritingDefaultHelperSetOverwritesDefaultValues(): void
    {
        $application = new CustomApplication();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->setHelperSet(new HelperSet([new FormatterHelper()]));

        $helperSet = $application->getHelperSet();

        $this->assertTrue($helperSet->has('formatter'));

        // no other default helper set should be returned
        $this->assertFalse($helperSet->has('dialog'));
        $this->assertFalse($helperSet->has('progress'));
    }

    public function testGetDefaultInputDefinitionReturnsDefaultValues(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $inputDefinition = $application->getDefinition();

        $this->assertTrue($inputDefinition->hasArgument('command'));

        $this->assertTrue($inputDefinition->hasOption('help'));
        $this->assertTrue($inputDefinition->hasOption('quiet'));
        $this->assertTrue($inputDefinition->hasOption('verbose'));
        $this->assertTrue($inputDefinition->hasOption('version'));
        $this->assertTrue($inputDefinition->hasOption('ansi'));
        $this->assertTrue($inputDefinition->hasOption('no-ansi'));
        $this->assertTrue($inputDefinition->hasOption('no-interaction'));
    }

    public function testOverwritingDefaultInputDefinitionOverwritesDefaultValues(): void
    {
        $application = new CustomApplication();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $inputDefinition = $application->getDefinition();

        // check whether the default arguments and options are not returned any more
        $this->assertFalse($inputDefinition->hasArgument('command'));

        $this->assertFalse($inputDefinition->hasOption('help'));
        $this->assertFalse($inputDefinition->hasOption('quiet'));
        $this->assertFalse($inputDefinition->hasOption('verbose'));
        $this->assertFalse($inputDefinition->hasOption('version'));
        $this->assertFalse($inputDefinition->hasOption('ansi'));
        $this->assertFalse($inputDefinition->hasOption('no-ansi'));
        $this->assertFalse($inputDefinition->hasOption('no-interaction'));

        $this->assertTrue($inputDefinition->hasOption('custom'));
    }

    public function testSettingCustomInputDefinitionOverwritesDefaultValues(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->setDefinition(new InputDefinition([new InputOption('--custom', '-c', InputOption::VALUE_NONE, 'Set the custom input definition.')]));

        $inputDefinition = $application->getDefinition();

        // check whether the default arguments and options are not returned any more
        $this->assertFalse($inputDefinition->hasArgument('command'));

        $this->assertFalse($inputDefinition->hasOption('help'));
        $this->assertFalse($inputDefinition->hasOption('quiet'));
        $this->assertFalse($inputDefinition->hasOption('verbose'));
        $this->assertFalse($inputDefinition->hasOption('version'));
        $this->assertFalse($inputDefinition->hasOption('ansi'));
        $this->assertFalse($inputDefinition->hasOption('no-ansi'));
        $this->assertFalse($inputDefinition->hasOption('no-interaction'));

        $this->assertTrue($inputDefinition->hasOption('custom'));
    }

    public function testRunWithDispatcher(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setDispatcher($this->getDispatcher());

        $application->register('foo')->setCode(static function (InputInterface $input, OutputInterface $output) : void {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
        $this->assertEquals('before.foo.after.'.\PHP_EOL, $tester->getDisplay());
    }

    public function testRunWithExceptionAndDispatcher(): void
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('error');
        $application = new Application();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->register('foo')->setCode(static function (InputInterface $input, OutputInterface $output) : never {
            throw new \RuntimeException('foo');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
    }

    public function testRunDispatchesAllEventsWithException(): void
    {
        $application = new Application();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);

        $application->register('foo')->setCode(static function (InputInterface $input, OutputInterface $output) : never {
            $output->write('foo.');
            throw new \RuntimeException('foo');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
        $this->assertStringContainsString('before.foo.error.after.', $tester->getDisplay());
    }

    public function testRunDispatchesAllEventsWithExceptionInListener(): void
    {
        $dispatcher = $this->getDispatcher();
        $dispatcher->addListener('console.command', static function () : never {
            throw new \RuntimeException('foo');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('foo')->setCode(static function (InputInterface $input, OutputInterface $output) : void {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
        $this->assertStringContainsString('before.error.after.', $tester->getDisplay());
    }

    /**
     * @requires PHP 7
     */
    public function testRunWithError(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->register('dym')->setCode(static function (InputInterface $input, OutputInterface $output) : never {
            $output->write('dym.');
            throw new \Error('dymerr');
        });

        $tester = new ApplicationTester($application);

        try {
            $tester->run(['command' => 'dym']);
            $this->fail('Error expected.');
        } catch (\Error $error) {
            $this->assertSame('dymerr', $error->getMessage());
        }
    }

    public function testRunAllowsErrorListenersToSilenceTheException(): void
    {
        $dispatcher = $this->getDispatcher();
        $dispatcher->addListener('console.error', static function (ConsoleErrorEvent $event) : void {
            $event->getOutput()->write('silenced.');
            $event->setExitCode(0);
        });

        $dispatcher->addListener('console.command', static function () : never {
            throw new \RuntimeException('foo');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('foo')->setCode(static function (InputInterface $input, OutputInterface $output) : void {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
        $this->assertStringContainsString('before.error.silenced.after.', $tester->getDisplay());
        $this->assertEquals(ConsoleCommandEvent::RETURN_CODE_DISABLED, $tester->getStatusCode());
    }

    public function testConsoleErrorEventIsTriggeredOnCommandNotFound(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('console.error', function (ConsoleErrorEvent $event): void {
            $this->assertNull($event->getCommand());
            $this->assertInstanceOf(CommandNotFoundException::class, $event->getError());
            $event->getOutput()->write('silenced command not found');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'unknown']);
        $this->assertStringContainsString('silenced command not found', $tester->getDisplay());
        $this->assertEquals(1, $tester->getStatusCode());
    }

    /**
     * @group legacy
     * @expectedDeprecation The "ConsoleEvents::EXCEPTION" event is deprecated since Symfony 3.3 and will be removed in 4.0. Listen to the "ConsoleEvents::ERROR" event instead.
     */
    public function testLegacyExceptionListenersAreStillTriggered(): void
    {
        $dispatcher = $this->getDispatcher();
        $dispatcher->addListener('console.exception', static function (ConsoleExceptionEvent $event) : void {
            $event->getOutput()->write('caught.');
            $event->setException(new \RuntimeException('replaced in caught.'));
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('foo')->setCode(static function (InputInterface $input, OutputInterface $output) : never {
            throw new \RuntimeException('foo');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
        $this->assertStringContainsString('before.caught.error.after.', $tester->getDisplay());
        $this->assertStringContainsString('replaced in caught.', $tester->getDisplay());
    }

    /**
     * @requires PHP 7
     */
    public function testErrorIsRethrownIfNotHandledByConsoleErrorEvent(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher(new EventDispatcher());

        $application->register('dym')->setCode(static function () : never {
            throw new \Error('Something went wrong.');
        });

        $tester = new ApplicationTester($application);

        try {
            $tester->run(['command' => 'dym']);
            $this->fail('->run() should rethrow PHP errors if not handled via ConsoleErrorEvent.');
        } catch (\Error $error) {
            $this->assertSame('Something went wrong.', $error->getMessage());
        }
    }

    /**
     * @requires PHP 7
     */
    public function testRunWithErrorAndDispatcher(): void
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('error');
        $application = new Application();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->register('dym')->setCode(static function (InputInterface $input, OutputInterface $output) : never {
            $output->write('dym.');
            throw new \Error('dymerr');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'dym']);
        $this->assertStringContainsString('before.dym.error.after.', $tester->getDisplay(), 'The PHP Error did not dispached events');
    }

    /**
     * @requires PHP 7
     */
    public function testRunDispatchesAllEventsWithError(): void
    {
        $application = new Application();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);

        $application->register('dym')->setCode(static function (InputInterface $input, OutputInterface $output) : never {
            $output->write('dym.');
            throw new \Error('dymerr');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'dym']);
        $this->assertStringContainsString('before.dym.error.after.', $tester->getDisplay(), 'The PHP Error did not dispached events');
    }

    /**
     * @requires PHP 7
     */
    public function testRunWithErrorFailingStatusCode(): void
    {
        $application = new Application();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);

        $application->register('dus')->setCode(static function (InputInterface $input, OutputInterface $output) : never {
            $output->write('dus.');
            throw new \Error('duserr');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'dus']);
        $this->assertSame(1, $tester->getStatusCode(), 'Status code should be 1');
    }

    public function testRunWithDispatcherSkippingCommand(): void
    {
        $application = new Application();
        $application->setDispatcher($this->getDispatcher(true));
        $application->setAutoExit(false);

        $application->register('foo')->setCode(static function (InputInterface $input, OutputInterface $output) : void {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $exitCode = $tester->run(['command' => 'foo']);
        $this->assertStringContainsString('before.after.', $tester->getDisplay());
        $this->assertEquals(ConsoleCommandEvent::RETURN_CODE_DISABLED, $exitCode);
    }

    public function testRunWithDispatcherAccessingInputOptions(): void
    {
        $noInteractionValue = null;
        $quietValue = null;

        $dispatcher = $this->getDispatcher();
        $dispatcher->addListener('console.command', static function (ConsoleCommandEvent $event) use (&$noInteractionValue, &$quietValue) : void {
            $input = $event->getInput();
            $noInteractionValue = $input->getOption('no-interaction');
            $quietValue = $input->getOption('quiet');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('foo')->setCode(static function (InputInterface $input, OutputInterface $output) : void {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo', '--no-interaction' => true]);

        $this->assertTrue($noInteractionValue);
        $this->assertFalse($quietValue);
    }

    public function testRunWithDispatcherAddingInputOptions(): void
    {
        $extraValue = null;

        $dispatcher = $this->getDispatcher();
        $dispatcher->addListener('console.command', static function (ConsoleCommandEvent $event) use (&$extraValue) : void {
            $definition = $event->getCommand()->getDefinition();
            $input = $event->getInput();
            $definition->addOption(new InputOption('extra', null, InputOption::VALUE_REQUIRED));
            $input->bind($definition);
            $extraValue = $input->getOption('extra');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('foo')->setCode(static function (InputInterface $input, OutputInterface $output) : void {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo', '--extra' => 'some test value']);

        $this->assertEquals('some test value', $extraValue);
    }

    /**
     * @group legacy
     */
    public function testTerminalDimensions(): void
    {
        $application = new Application();
        $originalDimensions = $application->getTerminalDimensions();
        $this->assertCount(2, $originalDimensions);

        $width = 80;
        if ($originalDimensions[0] == $width) {
            $width = 100;
        }

        $application->setTerminalDimensions($width, 80);
        $this->assertSame([$width, 80], $application->getTerminalDimensions());
    }

    public function testSetRunCustomDefaultCommand(): void
    {
        $command = new \FooCommand();

        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);
        $application->setDefaultCommand($command->getName());

        $tester = new ApplicationTester($application);
        $tester->run([], ['interactive' => false]);
        $this->assertEquals('called'.\PHP_EOL, $tester->getDisplay(), "Application runs the default set command if different from 'list' command");

        $application = new CustomDefaultCommandApplication();
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run([], ['interactive' => false]);

        $this->assertEquals('called'.\PHP_EOL, $tester->getDisplay(), "Application runs the default set command if different from 'list' command");
    }

    public function testSetRunCustomDefaultCommandWithOption(): void
    {
        $command = new \FooOptCommand();

        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);
        $application->setDefaultCommand($command->getName());

        $tester = new ApplicationTester($application);
        $tester->run(['--fooopt' => 'opt'], ['interactive' => false]);

        $this->assertEquals('called'.\PHP_EOL.'opt'.\PHP_EOL, $tester->getDisplay(), "Application runs the default set command if different from 'list' command");
    }

    public function testSetRunCustomSingleCommand(): void
    {
        $command = new \FooCommand();

        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);
        $application->setDefaultCommand($command->getName(), true);

        $tester = new ApplicationTester($application);

        $tester->run([]);
        $this->assertStringContainsString('called', $tester->getDisplay());

        $tester->run(['--help' => true]);
        $this->assertStringContainsString('The foo:bar command', $tester->getDisplay());
    }

    public function testRunLazyCommandService(): void
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddConsoleCommandPass());
        $container
            ->register('lazy-command', LazyCommand::class)
            ->addTag('console.command', ['command' => 'lazy:command'])
            ->addTag('console.command', ['command' => 'lazy:alias'])
            ->addTag('console.command', ['command' => 'lazy:alias2']);
        $container->compile();

        $application = new Application();
        $application->setCommandLoader($container->get('console.command_loader'));
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'lazy:command']);
        $this->assertSame("lazy-command called\n", $tester->getDisplay(true));

        $tester->run(['command' => 'lazy:alias']);
        $this->assertSame("lazy-command called\n", $tester->getDisplay(true));

        $tester->run(['command' => 'lazy:alias2']);
        $this->assertSame("lazy-command called\n", $tester->getDisplay(true));

        $command = $application->get('lazy:command');
        $this->assertSame(['lazy:alias', 'lazy:alias2'], $command->getAliases());
    }

    public function testGetDisabledLazyCommand(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\CommandNotFoundException::class);
        $application = new Application();
        $application->setCommandLoader(new FactoryCommandLoader(['disabled' => static fn(): \Symfony\Component\Console\Tests\DisabledCommand => new DisabledCommand()]));
        $application->get('disabled');
    }

    public function testHasReturnsFalseForDisabledLazyCommand(): void
    {
        $application = new Application();
        $application->setCommandLoader(new FactoryCommandLoader(['disabled' => static fn(): \Symfony\Component\Console\Tests\DisabledCommand => new DisabledCommand()]));
        $this->assertFalse($application->has('disabled'));
    }

    public function testAllExcludesDisabledLazyCommand(): void
    {
        $application = new Application();
        $application->setCommandLoader(new FactoryCommandLoader(['disabled' => static fn(): \Symfony\Component\Console\Tests\DisabledCommand => new DisabledCommand()]));
        $this->assertArrayNotHasKey('disabled', $application->all());
    }

    public function testFindAlternativesDoesNotLoadSameNamespaceCommandsOnExactMatch(): void
    {
        $application = new Application();
        $application->setAutoExit(false);

        $loaded = [];

        $application->setCommandLoader(new FactoryCommandLoader([
            'foo:bar' => static function () use (&$loaded) : static {
                $loaded['foo:bar'] = true;
                return (new Command('foo:bar'))->setCode(static function () : void {
                });
            },
            'foo' => static function () use (&$loaded) : static {
                $loaded['foo'] = true;
                return (new Command('foo'))->setCode(static function () : void {
                });
            },
        ]));

        $application->run(new ArrayInput(['command' => 'foo']), new NullOutput());

        $this->assertSame(['foo' => true], $loaded);
    }

    protected function getDispatcher($skipCommand = false): \Symfony\Component\EventDispatcher\EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('console.command', static function (ConsoleCommandEvent $event) use ($skipCommand) : void {
            $event->getOutput()->write('before.');
            if ($skipCommand) {
                $event->disableCommand();
            }
        });
        $dispatcher->addListener('console.terminate', static function (ConsoleTerminateEvent $event) use ($skipCommand) : void {
            $event->getOutput()->writeln('after.');
            if (!$skipCommand) {
                $event->setExitCode(ConsoleCommandEvent::RETURN_CODE_DISABLED);
            }
        });
        $dispatcher->addListener('console.error', static function (ConsoleErrorEvent $event) : void {
            $event->getOutput()->write('error.');
            $event->setError(new \LogicException('error.', $event->getExitCode(), $event->getError()));
        });

        return $dispatcher;
    }

    /**
     * @requires PHP 7
     */
    public function testErrorIsRethrownIfNotHandledByConsoleErrorEventWithCatchingEnabled(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setDispatcher(new EventDispatcher());

        $application->register('dym')->setCode(static function () : never {
            throw new \Error('Something went wrong.');
        });

        $tester = new ApplicationTester($application);

        try {
            $tester->run(['command' => 'dym']);
            $this->fail('->run() should rethrow PHP errors if not handled via ConsoleErrorEvent.');
        } catch (\Error $error) {
            $this->assertSame('Something went wrong.', $error->getMessage());
        }
    }

    public function testCommandNameMismatchWithCommandLoaderKeyThrows(): void
    {
        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('The "test" command cannot be found because it is registered under multiple names. Make sure you don\'t set a different name via constructor or "setName()".');

        $app = new Application();
        $loader = new FactoryCommandLoader([
            'test' => static fn(): \Symfony\Component\Console\Command\Command => new Command('test-command'),
        ]);

        $app->setCommandLoader($loader);
        $app->get('test');
    }
}

class CustomApplication extends Application
{
    /**
     * Overwrites the default input definition.
     *
     * @return InputDefinition An InputDefinition instance
     */
    protected function getDefaultInputDefinition(): \Symfony\Component\Console\Input\InputDefinition
    {
        return new InputDefinition([new InputOption('--custom', '-c', InputOption::VALUE_NONE, 'Set the custom input definition.')]);
    }

    /**
     * Gets the default helper set with the helpers that should always be available.
     *
     * @return HelperSet A HelperSet instance
     */
    protected function getDefaultHelperSet(): \Symfony\Component\Console\Helper\HelperSet
    {
        return new HelperSet([new FormatterHelper()]);
    }
}

class CustomDefaultCommandApplication extends Application
{
    /**
     * Overwrites the constructor in order to set a different default command.
     */
    public function __construct()
    {
        parent::__construct();

        $command = new \FooCommand();
        $this->add($command);
        $this->setDefaultCommand($command->getName());
    }
}

class LazyCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('lazy-command called');
    }
}

class DisabledCommand extends Command
{
    public function isEnabled(): bool
    {
        return false;
    }
}
