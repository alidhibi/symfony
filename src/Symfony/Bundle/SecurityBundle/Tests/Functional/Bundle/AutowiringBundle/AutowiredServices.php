<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AutowiringBundle;

use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

class AutowiredServices
{
    private readonly \Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface $accessDecisionManager;

    public function __construct(AccessDecisionManagerInterface $accessDecisionManager)
    {
        $this->accessDecisionManager = $accessDecisionManager;
    }

    public function getAccessDecisionManager(): \Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface
    {
        return $this->accessDecisionManager;
    }
}
