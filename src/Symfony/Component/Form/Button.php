<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\Form\Exception\BadMethodCallException;

/**
 * A form button.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Button implements \IteratorAggregate, FormInterface
{
    private ?\Symfony\Component\Form\FormInterface $parent = null;

    private readonly \Symfony\Component\Form\FormConfigInterface $config;

    private bool $submitted = false;

    /**
     * Creates a new button from a form configuration.
     */
    public function __construct(FormConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * Unsupported method.
     *
     * @param mixed $offset
     *
     * @return bool Always returns false
     */
    public function offsetExists($offset): bool
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param mixed $offset
     *
     * @throws BadMethodCallException
     */
    public function offsetGet($offset): never
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @throws BadMethodCallException
     */
    public function offsetSet($offset, $value): never
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param mixed $offset
     *
     * @throws BadMethodCallException
     */
    public function offsetUnset($offset): never
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * {@inheritdoc}
     */
    public function setParent(FormInterface $parent = null): static
    {
        if ($this->submitted) {
            throw new AlreadySubmittedException('You cannot set the parent of a submitted button.');
        }

        $this->parent = $parent;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?\Symfony\Component\Form\FormInterface
    {
        return $this->parent;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function add($child, $type = null, array $options = []): never
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param string $name
     *
     * @throws BadMethodCallException
     */
    public function get($name): never
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * @param string $name
     *
     * @return bool Always returns false
     */
    public function has($name): bool
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param string $name
     *
     * @throws BadMethodCallException
     */
    public function remove($name): never
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($deep = false, $flatten = true): \Symfony\Component\Form\FormErrorIterator
    {
        return new FormErrorIterator($this, []);
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param mixed $modelData
     *
     * @return $this
     */
    public function setData($modelData): static
    {
        // no-op, called during initialization of the form tree
        return $this;
    }

    /**
     * Unsupported method.
     */
    public function getData(): null
    {
        return null;
    }

    /**
     * Unsupported method.
     */
    public function getNormData(): null
    {
        return null;
    }

    /**
     * Unsupported method.
     */
    public function getViewData(): null
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return array Always returns an empty array
     */
    public function getExtraData(): array
    {
        return [];
    }

    /**
     * Returns the button's configuration.
     *
     * @return FormConfigInterface The configuration instance
     */
    public function getConfig(): \Symfony\Component\Form\FormConfigInterface
    {
        return $this->config;
    }

    /**
     * Returns whether the button is submitted.
     *
     * @return bool true if the button was submitted
     */
    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    /**
     * Returns the name by which the button is identified in forms.
     *
     * @return string The name of the button
     */
    public function getName()
    {
        return $this->config->getName();
    }

    /**
     * Unsupported method.
     */
    public function getPropertyPath(): null
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function addError(FormError $error): never
    {
        throw new BadMethodCallException('Buttons cannot have errors.');
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns true
     */
    public function isValid(): bool
    {
        return true;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function isRequired(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDisabled()
    {
        if ($this->parent && $this->parent->isDisabled()) {
            return true;
        }

        return $this->config->getDisabled();
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns true
     */
    public function isEmpty(): bool
    {
        return true;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns true
     */
    public function isSynchronized(): bool
    {
        return true;
    }

    /**
     * Unsupported method.
     */
    public function getTransformationFailure(): null
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function initialize(): never
    {
        throw new BadMethodCallException('Buttons cannot be initialized. Call initialize() on the root form instead.');
    }

    /**
     * Unsupported method.
     *
     * @param mixed $request
     *
     * @throws BadMethodCallException
     */
    public function handleRequest($request = null): never
    {
        throw new BadMethodCallException('Buttons cannot handle requests. Call handleRequest() on the root form instead.');
    }

    /**
     * Submits data to the button.
     *
     * @param string|null $submittedData Not used
     * @param bool        $clearMissing  Not used
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the button has already been submitted
     */
    public function submit($submittedData, $clearMissing = true): static
    {
        if ($this->submitted) {
            throw new AlreadySubmittedException('A form can only be submitted once.');
        }

        $this->submitted = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot()
    {
        return $this->parent instanceof \Symfony\Component\Form\FormInterface ? $this->parent->getRoot() : $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isRoot(): bool
    {
        return !$this->parent instanceof \Symfony\Component\Form\FormInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function createView(FormView $parent = null)
    {
        if (!$parent instanceof \Symfony\Component\Form\FormView && $this->parent) {
            $parent = $this->parent->createView();
        }

        $type = $this->config->getType();
        $options = $this->config->getOptions();

        $view = $type->createView($this, $parent);

        $type->buildView($view, $this, $options);
        $type->finishView($view, $this, $options);

        return $view;
    }

    /**
     * Unsupported method.
     *
     * @return int Always returns 0
     */
    public function count(): int
    {
        return 0;
    }

    /**
     * Unsupported method.
     *
     * @return \EmptyIterator Always returns an empty iterator
     */
    public function getIterator(): \EmptyIterator
    {
        return new \EmptyIterator();
    }
}
