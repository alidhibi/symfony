<?php

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\EnvVarProcessor;

class EnvVarProcessorTest extends TestCase
{
    final const TEST_CONST = 'test';

    /**
     * @dataProvider validStrings
     */
    public function testGetEnvString(string $value, string $processed): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(foo)', $value);
        $container->compile();

        $processor = new EnvVarProcessor($container);

        $result = $processor->getEnv('string', 'foo', function (): void {
            $this->fail('Should not be called');
        });

        $this->assertSame($processed, $result);
    }

    public function validStrings(): array
    {
        return [
            ['hello', 'hello'],
            ['true', 'true'],
            ['false', 'false'],
            ['null', 'null'],
            ['1', '1'],
            ['0', '0'],
            ['1.1', '1.1'],
            ['1e1', '1e1'],
        ];
    }

    /**
     * @dataProvider validBools
     */
    public function testGetEnvBool(string $value, bool $processed): void
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('bool', 'foo', function ($name) use ($value): string {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validBools(): array
    {
        return [
            ['true', true],
            ['false', false],
            ['null', false],
            ['1', true],
            ['0', false],
            ['1.1', true],
            ['1e1', true],
        ];
    }

    /**
     * @dataProvider validInts
     */
    public function testGetEnvInt(string $value, int $processed): void
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('int', 'foo', function ($name) use ($value): string {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validInts(): array
    {
        return [
            ['1', 1],
            ['1.1', 1],
            ['1e1', 10],
        ];
    }

    /**
     * @dataProvider invalidInts
     */
    public function testGetEnvIntInvalid(string $value): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Non-numeric env var');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('int', 'foo', function ($name) use ($value): string {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidInts(): array
    {
        return [
            ['foo'],
            ['true'],
            ['null'],
        ];
    }

    /**
     * @dataProvider validFloats
     */
    public function testGetEnvFloat(string $value, float $processed): void
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('float', 'foo', function ($name) use ($value): string {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validFloats(): array
    {
        return [
            ['1', 1.0],
            ['1.1', 1.1],
            ['1e1', 10.0],
        ];
    }

    /**
     * @dataProvider invalidFloats
     */
    public function testGetEnvFloatInvalid(string $value): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Non-numeric env var');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('float', 'foo', function ($name) use ($value): string {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidFloats(): array
    {
        return [
            ['foo'],
            ['true'],
            ['null'],
        ];
    }

    /**
     * @dataProvider validConsts
     */
    public function testGetEnvConst(string $value, string|int $processed): void
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('const', 'foo', function ($name) use ($value): string {
            $this->assertSame('foo', $name);

            return $value;
        });

        $this->assertSame($processed, $result);
    }

    public function validConsts(): array
    {
        return [
            [\Symfony\Component\DependencyInjection\Tests\EnvVarProcessorTest::class . '::TEST_CONST', self::TEST_CONST],
            ['E_ERROR', \E_ERROR],
        ];
    }

    /**
     * @dataProvider invalidConsts
     */
    public function testGetEnvConstInvalid(string $value): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\RuntimeException::class);
        $this->expectExceptionMessage('undefined constant');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('const', 'foo', function ($name) use ($value): string {
            $this->assertSame('foo', $name);

            return $value;
        });
    }

    public function invalidConsts(): array
    {
        return [
            [\Symfony\Component\DependencyInjection\Tests\EnvVarProcessorTest::class . '::UNDEFINED_CONST'],
            ['UNDEFINED_CONST'],
        ];
    }

    public function testGetEnvBase64(): void
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('base64', 'foo', function ($name): string {
            $this->assertSame('foo', $name);

            return base64_encode('hello');
        });

        $this->assertSame('hello', $result);
    }

    public function testGetEnvJson(): void
    {
        $processor = new EnvVarProcessor(new Container());

        $result = $processor->getEnv('json', 'foo', function ($name) {
            $this->assertSame('foo', $name);

            return json_encode([1]);
        });

        $this->assertSame([1], $result);
    }

    public function testGetEnvInvalidJson(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Syntax error');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('json', 'foo', function ($name): string {
            $this->assertSame('foo', $name);

            return 'invalid_json';
        });
    }

    /**
     * @dataProvider otherJsonValues
     */
    public function testGetEnvJsonOther(int|float|bool $value): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON env var');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('json', 'foo', function ($name) use ($value) {
            $this->assertSame('foo', $name);

            return json_encode($value);
        });
    }

    public function otherJsonValues(): array
    {
        return [
            [1],
            [1.1],
            [true],
            [false],
        ];
    }

    public function testGetEnvUnknown(): void
    {
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported env var prefix');
        $processor = new EnvVarProcessor(new Container());

        $processor->getEnv('unknown', 'foo', function ($name): string {
            $this->assertSame('foo', $name);

            return 'foo';
        });
    }
}
