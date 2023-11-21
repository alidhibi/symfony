<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\AutowiringTypes;

use Doctrine\Common\Annotations\Reader;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface as FrameworkBundleEngineInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Templating\EngineInterface;

class AutowiredServices
{
    private ?\Doctrine\Common\Annotations\Reader $annotationReader = null;

    private readonly \Symfony\Bundle\FrameworkBundle\Templating\EngineInterface $frameworkBundleEngine;

    private readonly \Symfony\Component\Templating\EngineInterface $engine;

    private readonly \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher;

    private $cachePool;

    public function __construct(FrameworkBundleEngineInterface $frameworkBundleEngine, EngineInterface $engine, EventDispatcherInterface $dispatcher, CacheItemPoolInterface $cachePool, Reader $annotationReader = null)
    {
        $this->annotationReader = $annotationReader;
        $this->frameworkBundleEngine = $frameworkBundleEngine;
        $this->engine = $engine;
        $this->dispatcher = $dispatcher;
        $this->cachePool = $cachePool;
    }

    public function getAnnotationReader(): ?\Doctrine\Common\Annotations\Reader
    {
        return $this->annotationReader;
    }

    public function getFrameworkBundleEngine(): \Symfony\Bundle\FrameworkBundle\Templating\EngineInterface
    {
        return $this->frameworkBundleEngine;
    }

    public function getEngine(): \Symfony\Component\Templating\EngineInterface
    {
        return $this->engine;
    }

    public function getDispatcher(): \Symfony\Component\EventDispatcher\EventDispatcherInterface
    {
        return $this->dispatcher;
    }

    public function getCachePool()
    {
        return $this->cachePool;
    }
}
