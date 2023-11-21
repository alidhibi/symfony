<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;

class TestExtension implements FormExtensionInterface
{
    private array $types = [];

    private array $extensions = [];

    private readonly \Symfony\Component\Form\FormTypeGuesserInterface $guesser;

    public function __construct(FormTypeGuesserInterface $guesser)
    {
        $this->guesser = $guesser;
    }

    public function addType(FormTypeInterface $type): void
    {
        $this->types[\get_class($type)] = $type;
    }

    public function getType($name)
    {
        return isset($this->types[$name]) ? $this->types[$name] : null;
    }

    public function hasType($name): bool
    {
        return isset($this->types[$name]);
    }

    public function addTypeExtension(FormTypeExtensionInterface $extension): void
    {
        $type = $extension->getExtendedType();

        if (!isset($this->extensions[$type])) {
            $this->extensions[$type] = [];
        }

        $this->extensions[$type][] = $extension;
    }

    public function getTypeExtensions($name)
    {
        return isset($this->extensions[$name]) ? $this->extensions[$name] : [];
    }

    public function hasTypeExtensions($name): bool
    {
        return isset($this->extensions[$name]);
    }

    public function getTypeGuesser(): \Symfony\Component\Form\FormTypeGuesserInterface
    {
        return $this->guesser;
    }
}
