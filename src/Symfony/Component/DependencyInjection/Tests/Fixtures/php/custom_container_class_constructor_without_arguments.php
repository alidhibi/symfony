<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Container;

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class ProjectServiceContainer extends \Symfony\Component\DependencyInjection\Tests\Fixtures\Container\ConstructorWithoutArgumentsContainer
{
    /**
     * @var null
     */
    public $parameterBag;
    /**
     * @var never[]
     */
    public $services = [];
    /**
     * @var never[]
     */
    public $aliases = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function getRemovedIds(): array
    {
        return [
            \Psr\Container\ContainerInterface::class => true,
            \Symfony\Component\DependencyInjection\ContainerInterface::class => true,
        ];
    }

    public function compile(): never
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled(): bool
    {
        return true;
    }

    public function isFrozen(): bool
    {
        @trigger_error(sprintf('The %s() method is deprecated since Symfony 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);

        return true;
    }
}
