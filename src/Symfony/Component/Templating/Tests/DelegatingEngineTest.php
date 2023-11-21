<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Templating\DelegatingEngine;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Templating\StreamingEngineInterface;

class DelegatingEngineTest extends TestCase
{
    public function testRenderDelegatesToSupportedEngine(): void
    {
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', true);

        $secondEngine->expects($this->once())
            ->method('render')
            ->with('template.php', ['foo' => 'bar'])
            ->willReturn('<html />');

        $delegatingEngine = new DelegatingEngine([$firstEngine, $secondEngine]);
        $result = $delegatingEngine->render('template.php', ['foo' => 'bar']);

        $this->assertSame('<html />', $result);
    }

    public function testRenderWithNoSupportedEngine(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('No engine is able to work with the template "template.php"');
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', false);

        $delegatingEngine = new DelegatingEngine([$firstEngine, $secondEngine]);
        $delegatingEngine->render('template.php', ['foo' => 'bar']);
    }

    public function testStreamDelegatesToSupportedEngine(): void
    {
        $streamingEngine = $this->getStreamingEngineMock('template.php', true);
        $streamingEngine->expects($this->once())
            ->method('stream')
            ->with('template.php', ['foo' => 'bar'])
            ->willReturn('<html />');

        $delegatingEngine = new DelegatingEngine([$streamingEngine]);
        $result = $delegatingEngine->stream('template.php', ['foo' => 'bar']);

        $this->assertNull($result);
    }

    public function testStreamRequiresStreamingEngine(): void
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('Template "template.php" cannot be streamed as the engine supporting it does not implement StreamingEngineInterface');
        $delegatingEngine = new DelegatingEngine([new TestEngine()]);
        $delegatingEngine->stream('template.php', ['foo' => 'bar']);
    }

    public function testExists(): void
    {
        $engine = $this->getEngineMock('template.php', true);
        $engine->expects($this->once())
            ->method('exists')
            ->with('template.php')
            ->willReturn(true);

        $delegatingEngine = new DelegatingEngine([$engine]);

        $this->assertTrue($delegatingEngine->exists('template.php'));
    }

    public function testSupports(): void
    {
        $engine = $this->getEngineMock('template.php', true);

        $delegatingEngine = new DelegatingEngine([$engine]);

        $this->assertTrue($delegatingEngine->supports('template.php'));
    }

    public function testSupportsWithNoSupportedEngine(): void
    {
        $engine = $this->getEngineMock('template.php', false);

        $delegatingEngine = new DelegatingEngine([$engine]);

        $this->assertFalse($delegatingEngine->supports('template.php'));
    }

    public function testGetExistingEngine(): void
    {
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', true);

        $delegatingEngine = new DelegatingEngine([$firstEngine, $secondEngine]);

        $this->assertSame($secondEngine, $delegatingEngine->getEngine('template.php'));
    }

    public function testGetInvalidEngine(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('No engine is able to work with the template "template.php"');
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', false);

        $delegatingEngine = new DelegatingEngine([$firstEngine, $secondEngine]);
        $delegatingEngine->getEngine('template.php');
    }

    private function getEngineMock(string $template, bool $supports)
    {
        $engine = $this->getMockBuilder(\Symfony\Component\Templating\EngineInterface::class)->getMock();

        $engine->expects($this->once())
            ->method('supports')
            ->with($template)
            ->willReturn($supports);

        return $engine;
    }

    private function getStreamingEngineMock(string $template, bool $supports)
    {
        $engine = $this->getMockForAbstractClass(\Symfony\Component\Templating\Tests\MyStreamingEngine::class);

        $engine->expects($this->once())
            ->method('supports')
            ->with($template)
            ->willReturn($supports);

        return $engine;
    }
}

interface MyStreamingEngine extends StreamingEngineInterface, EngineInterface
{
}

class TestEngine implements EngineInterface
{
    public function render($name, array $parameters = []): void
    {
    }

    public function exists($name): void
    {
    }

    public function supports($name): bool
    {
        return true;
    }

    public function stream(): void
    {
    }
}
