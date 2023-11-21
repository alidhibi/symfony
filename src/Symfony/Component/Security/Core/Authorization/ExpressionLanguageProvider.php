<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Define some ExpressionLanguage functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('is_anonymous', static fn(): string => '$trust_resolver->isAnonymous($token)', static fn(array $variables) => $variables['trust_resolver']->isAnonymous($variables['token'])),

            new ExpressionFunction('is_authenticated', static fn(): string => '$token && !$trust_resolver->isAnonymous($token)', static fn(array $variables): bool => $variables['token'] && !$variables['trust_resolver']->isAnonymous($variables['token'])),

            new ExpressionFunction('is_fully_authenticated', static fn(): string => '$trust_resolver->isFullFledged($token)', static fn(array $variables) => $variables['trust_resolver']->isFullFledged($variables['token'])),

            new ExpressionFunction('is_remember_me', static fn(): string => '$trust_resolver->isRememberMe($token)', static fn(array $variables) => $variables['trust_resolver']->isRememberMe($variables['token'])),

            new ExpressionFunction('has_role', static fn($role): string => sprintf('in_array(%s, $roles)', $role), static fn(array $variables, $role): bool => \in_array($role, $variables['roles'])),
        ];
    }
}
