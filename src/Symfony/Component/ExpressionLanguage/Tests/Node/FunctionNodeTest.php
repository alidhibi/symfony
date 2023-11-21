<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests\Node;

use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\FunctionNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

class FunctionNodeTest extends AbstractNodeTest
{
    public function getEvaluateData(): array
    {
        return [
            ['bar', new FunctionNode('foo', new Node([new ConstantNode('bar')])), [], ['foo' => $this->getCallables()]],
        ];
    }

    public function getCompileData(): array
    {
        return [
            ['foo("bar")', new FunctionNode('foo', new Node([new ConstantNode('bar')])), ['foo' => $this->getCallables()]],
        ];
    }

    public function getDumpData(): array
    {
        return [
            ['foo("bar")', new FunctionNode('foo', new Node([new ConstantNode('bar')])), ['foo' => $this->getCallables()]],
        ];
    }

    protected function getCallables(): array
    {
        return [
            'compiler' => static fn($arg): string => sprintf('foo(%s)', $arg),
            'evaluator' => static fn($variables, $arg) => $arg,
        ];
    }
}
