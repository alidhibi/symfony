<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * ExpressionVoter votes based on the evaluation of an expression.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionVoter implements VoterInterface
{
    private readonly \Symfony\Component\Security\Core\Authorization\ExpressionLanguage $expressionLanguage;

    private readonly \Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface $trustResolver;

    private ?\Symfony\Component\Security\Core\Role\RoleHierarchyInterface $roleHierarchy = null;

    public function __construct(ExpressionLanguage $expressionLanguage, AuthenticationTrustResolverInterface $trustResolver, RoleHierarchyInterface $roleHierarchy = null)
    {
        $this->expressionLanguage = $expressionLanguage;
        $this->trustResolver = $trustResolver;
        $this->roleHierarchy = $roleHierarchy;
    }

    public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider): void
    {
        $this->expressionLanguage->registerProvider($provider);
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes): int
    {
        $result = VoterInterface::ACCESS_ABSTAIN;
        $variables = null;
        foreach ($attributes as $attribute) {
            if (!$attribute instanceof Expression) {
                continue;
            }

            if (null === $variables) {
                $variables = $this->getVariables($token, $subject);
            }

            $result = VoterInterface::ACCESS_DENIED;
            if ($this->expressionLanguage->evaluate($attribute, $variables)) {
                return VoterInterface::ACCESS_GRANTED;
            }
        }

        return $result;
    }

    private function getVariables(TokenInterface $token, $subject): array
    {
        if ($this->roleHierarchy instanceof \Symfony\Component\Security\Core\Role\RoleHierarchyInterface) {
            $roles = $this->roleHierarchy->getReachableRoles($token->getRoles());
        } else {
            $roles = $token->getRoles();
        }

        $variables = [
            'token' => $token,
            'user' => $token->getUser(),
            'object' => $subject,
            'subject' => $subject,
            'roles' => array_map(static fn($role) => $role->getRole(), $roles),
            'trust_resolver' => $this->trustResolver,
        ];

        // this is mainly to propose a better experience when the expression is used
        // in an access control rule, as the developer does not know that it's going
        // to be handled by this voter
        if ($subject instanceof Request) {
            $variables['request'] = $subject;
        }

        return $variables;
    }
}
