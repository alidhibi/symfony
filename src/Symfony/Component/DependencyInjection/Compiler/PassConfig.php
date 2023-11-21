<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Compiler Pass Configuration.
 *
 * This class has a default configuration embedded.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PassConfig
{
    final const TYPE_AFTER_REMOVING = 'afterRemoving';

    final const TYPE_BEFORE_OPTIMIZATION = 'beforeOptimization';

    final const TYPE_BEFORE_REMOVING = 'beforeRemoving';

    final const TYPE_OPTIMIZE = 'optimization';

    final const TYPE_REMOVE = 'removing';

    private \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface $mergePass;

    private array $afterRemovingPasses = [];

    private $beforeOptimizationPasses = [];

    private $beforeRemovingPasses = [];

    private $optimizationPasses;

    private $removingPasses;

    public function __construct()
    {
        $this->mergePass = new MergeExtensionConfigurationPass();

        $this->beforeOptimizationPasses = [
            100 => [
                $resolveClassPass = new ResolveClassPass(),
                new ResolveInstanceofConditionalsPass(),
                new RegisterEnvVarProcessorsPass(),
            ],
            -1000 => [new ExtensionCompilerPass()],
        ];

        $this->optimizationPasses = [[
            new ResolveChildDefinitionsPass(),
            new ServiceLocatorTagPass(),
            new RegisterServiceSubscribersPass(),
            new DecoratorServicePass(),
            new ResolveParameterPlaceHoldersPass(false, false),
            new ResolveFactoryClassPass(),
            new FactoryReturnTypePass($resolveClassPass),
            new CheckDefinitionValidityPass(),
            new ResolveNamedArgumentsPass(),
            new AutowireRequiredMethodsPass(),
            new ResolveBindingsPass(),
            new AutowirePass(false),
            new ResolveTaggedIteratorArgumentPass(),
            new ResolveServiceSubscribersPass(),
            new ResolveReferencesToAliasesPass(),
            new ResolveInvalidReferencesPass(),
            new AnalyzeServiceReferencesPass(true),
            new CheckCircularReferencesPass(),
            new CheckReferenceValidityPass(),
            new CheckArgumentsValidityPass(false),
        ]];

        $this->beforeRemovingPasses = [
            -100 => [
                new ResolvePrivatesPass(),
            ],
        ];

        $this->removingPasses = [[
            new RemovePrivateAliasesPass(),
            new ReplaceAliasByActualDefinitionPass(),
            new RemoveAbstractDefinitionsPass(),
            new RepeatedPass([
                new AnalyzeServiceReferencesPass(),
                new InlineServiceDefinitionsPass(),
                new AnalyzeServiceReferencesPass(),
                new RemoveUnusedDefinitionsPass(),
            ]),
            new DefinitionErrorExceptionPass(),
            new CheckExceptionOnInvalidReferenceBehaviorPass(),
            new ResolveHotPathPass(),
        ]];
    }

    /**
     * Returns all passes in order to be processed.
     *
     * @return CompilerPassInterface[]
     */
    public function getPasses(): array
    {
        return array_merge(
            [$this->mergePass],
            $this->getBeforeOptimizationPasses(),
            $this->getOptimizationPasses(),
            $this->getBeforeRemovingPasses(),
            $this->getRemovingPasses(),
            $this->getAfterRemovingPasses()
        );
    }

    /**
     * Adds a pass.
     *
     * @param CompilerPassInterface $pass A Compiler pass
     * @param string                $type The pass type
     *
     * @throws InvalidArgumentException when a pass type doesn't exist
     */
    public function addPass(CompilerPassInterface $pass, string $type = self::TYPE_BEFORE_OPTIMIZATION/*, int $priority = 0*/): void
    {
        if (\func_num_args() >= 3) {
            $priority = func_get_arg(2);
        } else {
            if (__CLASS__ !== static::class) {
                $r = new \ReflectionMethod($this, __FUNCTION__);
                if (__CLASS__ !== $r->getDeclaringClass()->getName()) {
                    @trigger_error(sprintf('Method %s() will have a third `int $priority = 0` argument in version 4.0. Not defining it is deprecated since Symfony 3.2.', __METHOD__), \E_USER_DEPRECATED);
                }
            }

            $priority = 0;
        }

        $property = $type.'Passes';
        if (!isset($this->$property)) {
            throw new InvalidArgumentException(sprintf('Invalid type "%s".', $type));
        }

        $passes = &$this->$property;

        if (!isset($passes[$priority])) {
            $passes[$priority] = [];
        }

        $passes[$priority][] = $pass;
    }

    /**
     * Gets all passes for the AfterRemoving pass.
     *
     * @return CompilerPassInterface[]
     */
    public function getAfterRemovingPasses()
    {
        return $this->sortPasses($this->afterRemovingPasses);
    }

    /**
     * Gets all passes for the BeforeOptimization pass.
     *
     * @return CompilerPassInterface[]
     */
    public function getBeforeOptimizationPasses()
    {
        return $this->sortPasses($this->beforeOptimizationPasses);
    }

    /**
     * Gets all passes for the BeforeRemoving pass.
     *
     * @return CompilerPassInterface[]
     */
    public function getBeforeRemovingPasses()
    {
        return $this->sortPasses($this->beforeRemovingPasses);
    }

    /**
     * Gets all passes for the Optimization pass.
     *
     * @return CompilerPassInterface[]
     */
    public function getOptimizationPasses()
    {
        return $this->sortPasses($this->optimizationPasses);
    }

    /**
     * Gets all passes for the Removing pass.
     *
     * @return CompilerPassInterface[]
     */
    public function getRemovingPasses()
    {
        return $this->sortPasses($this->removingPasses);
    }

    /**
     * Gets the Merge pass.
     *
     */
    public function getMergePass(): \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface
    {
        return $this->mergePass;
    }

    public function setMergePass(CompilerPassInterface $pass): void
    {
        $this->mergePass = $pass;
    }

    /**
     * Sets the AfterRemoving passes.
     *
     * @param CompilerPassInterface[] $passes
     */
    public function setAfterRemovingPasses(array $passes): void
    {
        $this->afterRemovingPasses = [$passes];
    }

    /**
     * Sets the BeforeOptimization passes.
     *
     * @param CompilerPassInterface[] $passes
     */
    public function setBeforeOptimizationPasses(array $passes): void
    {
        $this->beforeOptimizationPasses = [$passes];
    }

    /**
     * Sets the BeforeRemoving passes.
     *
     * @param CompilerPassInterface[] $passes
     */
    public function setBeforeRemovingPasses(array $passes): void
    {
        $this->beforeRemovingPasses = [$passes];
    }

    /**
     * Sets the Optimization passes.
     *
     * @param CompilerPassInterface[] $passes
     */
    public function setOptimizationPasses(array $passes): void
    {
        $this->optimizationPasses = [$passes];
    }

    /**
     * Sets the Removing passes.
     *
     * @param CompilerPassInterface[] $passes
     */
    public function setRemovingPasses(array $passes): void
    {
        $this->removingPasses = [$passes];
    }

    /**
     * Sort passes by priority.
     *
     * @param array $passes CompilerPassInterface instances with their priority as key
     *
     * @return CompilerPassInterface[]
     */
    private function sortPasses(array $passes): array
    {
        if ([] === $passes) {
            return [];
        }

        krsort($passes);

        // Flatten the array
        return array_merge(...$passes);
    }
}
