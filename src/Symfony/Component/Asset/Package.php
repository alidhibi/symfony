<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset;

use Symfony\Component\Asset\Context\ContextInterface;
use Symfony\Component\Asset\Context\NullContext;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Basic package that adds a version to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Package implements PackageInterface
{
    private readonly \Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface $versionStrategy;

    private readonly \Symfony\Component\Asset\Context\ContextInterface $context;

    public function __construct(VersionStrategyInterface $versionStrategy, ContextInterface $context = null)
    {
        $this->versionStrategy = $versionStrategy;
        $this->context = $context ?: new NullContext();
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion($path)
    {
        return $this->versionStrategy->getVersion($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl($path)
    {
        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        return $this->versionStrategy->applyVersion($path);
    }

    protected function getContext(): \Symfony\Component\Asset\Context\ContextInterface
    {
        return $this->context;
    }

    protected function getVersionStrategy(): \Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface
    {
        return $this->versionStrategy;
    }

    protected function isAbsoluteUrl($url): bool
    {
        return false !== strpos($url, '://') || '//' === substr($url, 0, 2);
    }
}
