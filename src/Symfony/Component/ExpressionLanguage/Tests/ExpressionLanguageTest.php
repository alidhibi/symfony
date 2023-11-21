<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\Tests\Fixtures\TestProvider;

class ExpressionLanguageTest extends TestCase
{
    public function testCachedParse(): void
    {
        $cacheMock = $this->getMockBuilder('Psr\Cache\CacheItemPoolInterface')->getMock();
        $cacheItemMock = $this->getMockBuilder('Psr\Cache\CacheItemInterface')->getMock();
        $savedParsedExpression = null;
        $expressionLanguage = new ExpressionLanguage($cacheMock);

        $cacheMock
            ->expects($this->exactly(2))
            ->method('getItem')
            ->with('1%20%2B%201%2F%2F')
            ->willReturn($cacheItemMock)
        ;

        $cacheItemMock
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(static function () use (&$savedParsedExpression) {
                return $savedParsedExpression;
            })
        ;

        $cacheItemMock
            ->expects($this->exactly(1))
            ->method('set')
            ->with($this->isInstanceOf(ParsedExpression::class))
            ->willReturnCallback(static function ($parsedExpression) use (&$savedParsedExpression) : void {
                $savedParsedExpression = $parsedExpression;
            })
        ;

        $cacheMock
            ->expects($this->exactly(1))
            ->method('save')
            ->with($cacheItemMock)
        ;

        $parsedExpression = $expressionLanguage->parse('1 + 1', []);
        $this->assertSame($savedParsedExpression, $parsedExpression);

        $parsedExpression = $expressionLanguage->parse('1 + 1', []);
        $this->assertSame($savedParsedExpression, $parsedExpression);
    }

    /**
     * @group legacy
     */
    public function testCachedParseWithDeprecatedParserCacheInterface(): void
    {
        $cacheMock = $this->getMockBuilder(\Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface::class)->getMock();

        $savedParsedExpression = null;
        $expressionLanguage = new ExpressionLanguage($cacheMock);

        $cacheMock
            ->expects($this->exactly(1))
            ->method('fetch')
            ->with('1%20%2B%201%2F%2F')
            ->willReturn($savedParsedExpression)
        ;

        $cacheMock
            ->expects($this->exactly(1))
            ->method('save')
            ->with('1%20%2B%201%2F%2F', $this->isInstanceOf(ParsedExpression::class))
            ->willReturnCallback(static function ($key, $expression) use (&$savedParsedExpression) : void {
                $savedParsedExpression = $expression;
            })
        ;

        $parsedExpression = $expressionLanguage->parse('1 + 1', []);
        $this->assertSame($savedParsedExpression, $parsedExpression);
    }

    public function testWrongCacheImplementation(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Cache argument has to implement "Psr\Cache\CacheItemPoolInterface".');
        $cacheMock = $this->getMockBuilder('Psr\Cache\CacheItemSpoolInterface')->getMock();
        new ExpressionLanguage($cacheMock);
    }

    public function testConstantFunction(): void
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertEquals(\PHP_VERSION, $expressionLanguage->evaluate('constant("PHP_VERSION")'));

        $expressionLanguage = new ExpressionLanguage();
        $this->assertEquals('\constant("PHP_VERSION")', $expressionLanguage->compile('constant("PHP_VERSION")'));
    }

    public function testProviders(): void
    {
        $expressionLanguage = new ExpressionLanguage(null, [new TestProvider()]);
        $this->assertEquals('foo', $expressionLanguage->evaluate('identity("foo")'));
        $this->assertEquals('"foo"', $expressionLanguage->compile('identity("foo")'));
        $this->assertEquals('FOO', $expressionLanguage->evaluate('strtoupper("foo")'));
        $this->assertEquals('\strtoupper("foo")', $expressionLanguage->compile('strtoupper("foo")'));
        $this->assertEquals('foo', $expressionLanguage->evaluate('strtolower("FOO")'));
        $this->assertEquals('\strtolower("FOO")', $expressionLanguage->compile('strtolower("FOO")'));
        $this->assertTrue($expressionLanguage->evaluate('fn_namespaced()'));
        $this->assertEquals('\Symfony\Component\ExpressionLanguage\Tests\Fixtures\fn_namespaced()', $expressionLanguage->compile('fn_namespaced()'));
    }

    /**
     * @dataProvider shortCircuitProviderEvaluate
     */
    public function testShortCircuitOperatorsEvaluate(string $expression, array $values, bool $expected): void
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertEquals($expected, $expressionLanguage->evaluate($expression, $values));
    }

    /**
     * @dataProvider shortCircuitProviderCompile
     */
    public function testShortCircuitOperatorsCompile(string $expression, array $names, bool $expected): void
    {
        $result = null;
        $expressionLanguage = new ExpressionLanguage();
        eval(sprintf('$result = %s;', $expressionLanguage->compile($expression, $names)));
        $this->assertSame($expected, $result);
    }

    public function testParseThrowsInsteadOfNotice(): void
    {
        $this->expectException(\Symfony\Component\ExpressionLanguage\SyntaxError::class);
        $this->expectExceptionMessage('Unexpected end of expression around position 6 for expression `node.`.');
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->parse('node.', ['node']);
    }

    public function shortCircuitProviderEvaluate(): array
    {
        $object = $this->getMockBuilder('stdClass')->setMethods(['foo'])->getMock();
        $object->expects($this->never())->method('foo');

        return [
            ['false and object.foo()', ['object' => $object], false],
            ['false && object.foo()', ['object' => $object], false],
            ['true || object.foo()', ['object' => $object], true],
            ['true or object.foo()', ['object' => $object], true],
        ];
    }

    public function shortCircuitProviderCompile(): array
    {
        return [
            ['false and foo', ['foo' => 'foo'], false],
            ['false && foo', ['foo' => 'foo'], false],
            ['true || foo', ['foo' => 'foo'], true],
            ['true or foo', ['foo' => 'foo'], true],
        ];
    }

    public function testCachingForOverriddenVariableNames(): void
    {
        $expressionLanguage = new ExpressionLanguage();
        $expression = 'a + b';
        $expressionLanguage->evaluate($expression, ['a' => 1, 'b' => 1]);
        $result = $expressionLanguage->compile($expression, ['a', 'B' => 'b']);
        $this->assertSame('($a + $B)', $result);
    }

    public function testStrictEquality(): void
    {
        $expressionLanguage = new ExpressionLanguage();
        $expression = '123 === a';
        $result = $expressionLanguage->compile($expression, ['a']);
        $this->assertSame('(123 === $a)', $result);
    }

    public function testCachingWithDifferentNamesOrder(): void
    {
        $cacheMock = $this->getMockBuilder('Psr\Cache\CacheItemPoolInterface')->getMock();
        $cacheItemMock = $this->getMockBuilder('Psr\Cache\CacheItemInterface')->getMock();
        $expressionLanguage = new ExpressionLanguage($cacheMock);
        $savedParsedExpression = null;

        $cacheMock
            ->expects($this->exactly(2))
            ->method('getItem')
            ->with('a%20%2B%20b%2F%2Fa%7CB%3Ab')
            ->willReturn($cacheItemMock)
        ;

        $cacheItemMock
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(static function () use (&$savedParsedExpression) {
                return $savedParsedExpression;
            })
        ;

        $cacheItemMock
            ->expects($this->exactly(1))
            ->method('set')
            ->with($this->isInstanceOf(ParsedExpression::class))
            ->willReturnCallback(static function ($parsedExpression) use (&$savedParsedExpression) : void {
                $savedParsedExpression = $parsedExpression;
            })
        ;

        $cacheMock
            ->expects($this->exactly(1))
            ->method('save')
            ->with($cacheItemMock)
        ;

        $expression = 'a + b';
        $expressionLanguage->compile($expression, ['a', 'B' => 'b']);
        $expressionLanguage->compile($expression, ['B' => 'b', 'a']);
    }

    public function testOperatorCollisions(): void
    {
        $expressionLanguage = new ExpressionLanguage();
        $expression = 'foo.not in [bar]';
        $compiled = $expressionLanguage->compile($expression, ['foo', 'bar']);
        $this->assertSame('in_array($foo->not, [0 => $bar])', $compiled);

        $result = $expressionLanguage->evaluate($expression, ['foo' => (object) ['not' => 'test'], 'bar' => 'test']);
        $this->assertTrue($result);
    }

    /**
     * @dataProvider getRegisterCallbacks
     */
    public function testRegisterAfterParse(\Closure $registerCallback): void
    {
        $this->expectException('LogicException');
        $el = new ExpressionLanguage();
        $el->parse('1 + 1', []);
        $registerCallback($el);
    }

    /**
     * @dataProvider getRegisterCallbacks
     */
    public function testRegisterAfterEval(\Closure $registerCallback): void
    {
        $this->expectException('LogicException');
        $el = new ExpressionLanguage();
        $el->evaluate('1 + 1');
        $registerCallback($el);
    }

    public function testCallBadCallable(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessageMatches('/Unable to call method "\w+" of object "\w+"./');
        $el = new ExpressionLanguage();
        $el->evaluate('foo.myfunction()', ['foo' => new \stdClass()]);
    }

    /**
     * @dataProvider getRegisterCallbacks
     */
    public function testRegisterAfterCompile(\Closure $registerCallback): void
    {
        $this->expectException('LogicException');
        $el = new ExpressionLanguage();
        $el->compile('1 + 1');
        $registerCallback($el);
    }

    public function getRegisterCallbacks(): array
    {
        return [
            [
                static function (ExpressionLanguage $el) : void {
                    $el->register('fn', static function () : void {
                    }, static function () : void {
                    });
                },
            ],
            [
                static function (ExpressionLanguage $el) : void {
                    $el->addFunction(new ExpressionFunction('fn', static function () : void {
                    }, static function () : void {
                    }));
                },
            ],
            [
                static function (ExpressionLanguage $el) : void {
                    $el->registerProvider(new TestProvider());
                },
            ],
        ];
    }
}
