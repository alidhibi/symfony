<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Define some ExpressionLanguage functions.
 *
 * To get a service, use service('request').
 * To get a parameter, use parameter('kernel.debug').
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    private $serviceCompiler;

    public function __construct(callable $serviceCompiler = null)
    {
        $this->serviceCompiler = $serviceCompiler;
    }

    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('service', $this->serviceCompiler ?: static fn($arg): string => sprintf('$this->get(%s)', $arg), static fn(array $variables, $value) => $variables['container']->get($value)),

            new ExpressionFunction('parameter', static fn($arg): string => sprintf('$this->getParameter(%s)', $arg), static fn(array $variables, $value) => $variables['container']->getParameter($value)),
        ];
    }
}
