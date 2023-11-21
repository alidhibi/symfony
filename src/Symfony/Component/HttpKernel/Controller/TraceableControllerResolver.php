<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TraceableControllerResolver implements ControllerResolverInterface, ArgumentResolverInterface
{
    private readonly \Symfony\Component\HttpKernel\Controller\ControllerResolverInterface $resolver;

    private readonly \Symfony\Component\Stopwatch\Stopwatch $stopwatch;

    private \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface|null|\Symfony\Component\HttpKernel\Controller\ControllerResolverInterface|\Symfony\Component\HttpKernel\Controller\TraceableArgumentResolver $argumentResolver = null;

    public function __construct(ControllerResolverInterface $resolver, Stopwatch $stopwatch, ArgumentResolverInterface $argumentResolver = null)
    {
        $this->resolver = $resolver;
        $this->stopwatch = $stopwatch;
        $this->argumentResolver = $argumentResolver;

        // BC
        if (!$this->argumentResolver instanceof \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface) {
            $this->argumentResolver = $resolver;
        }

        if (!$this->argumentResolver instanceof TraceableArgumentResolver) {
            $this->argumentResolver = new TraceableArgumentResolver($this->argumentResolver, $this->stopwatch);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getController(Request $request)
    {
        $e = $this->stopwatch->start('controller.get_callable');

        $ret = $this->resolver->getController($request);

        $e->stop();

        return $ret;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated This method is deprecated as of 3.1 and will be removed in 4.0.
     */
    public function getArguments(Request $request, $controller)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated as of 3.1 and will be removed in 4.0. Please use the %s instead.', __METHOD__, TraceableArgumentResolver::class), \E_USER_DEPRECATED);

        return $this->argumentResolver->getArguments($request, $controller);
    }
}
