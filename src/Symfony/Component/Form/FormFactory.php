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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

class FormFactory implements FormFactoryInterface
{
    private readonly \Symfony\Component\Form\FormRegistryInterface $registry;

    public function __construct(FormRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function create($type = \Symfony\Component\Form\Extension\Core\Type\FormType::class, $data = null, array $options = [])
    {
        return $this->createBuilder($type, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createNamed($name, $type = \Symfony\Component\Form\Extension\Core\Type\FormType::class, $data = null, array $options = [])
    {
        return $this->createNamedBuilder($name, $type, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createForProperty($class, $property, $data = null, array $options = [])
    {
        return $this->createBuilderForProperty($class, $property, $data, $options)->getForm();
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilder($type = \Symfony\Component\Form\Extension\Core\Type\FormType::class, $data = null, array $options = [])
    {
        if (!\is_string($type)) {
            throw new UnexpectedTypeException($type, 'string');
        }

        return $this->createNamedBuilder($this->registry->getType($type)->getBlockPrefix(), $type, $data, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function createNamedBuilder($name, $type = \Symfony\Component\Form\Extension\Core\Type\FormType::class, $data = null, array $options = [])
    {
        if (null !== $data && !\array_key_exists('data', $options)) {
            $options['data'] = $data;
        }

        if (!\is_string($type)) {
            throw new UnexpectedTypeException($type, 'string');
        }

        $type = $this->registry->getType($type);

        $builder = $type->createBuilder($this, $name, $options);

        // Explicitly call buildForm() in order to be able to override either
        // createBuilder() or buildForm() in the resolved form type
        $type->buildForm($builder, $builder->getOptions());

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function createBuilderForProperty($class, $property, $data = null, array $options = [])
    {
        if (null === $guesser = $this->registry->getTypeGuesser()) {
            return $this->createNamedBuilder($property, \Symfony\Component\Form\Extension\Core\Type\TextType::class, $data, $options);
        }

        $typeGuess = $guesser->guessType($class, $property);
        $maxLengthGuess = $guesser->guessMaxLength($class, $property);
        $requiredGuess = $guesser->guessRequired($class, $property);
        $patternGuess = $guesser->guessPattern($class, $property);

        $type = $typeGuess !== null ? $typeGuess->getType() : \Symfony\Component\Form\Extension\Core\Type\TextType::class;

        $maxLength = $maxLengthGuess !== null ? $maxLengthGuess->getValue() : null;
        $pattern = $patternGuess !== null ? $patternGuess->getValue() : null;

        if (null !== $pattern) {
            $options = array_replace_recursive(['attr' => ['pattern' => $pattern]], $options);
        }

        if (null !== $maxLength) {
            $options = array_replace_recursive(['attr' => ['maxlength' => $maxLength]], $options);
        }

        if ($requiredGuess !== null) {
            $options = ['required' => $requiredGuess->getValue(), ...$options];
        }

        // user options may override guessed options
        if ($typeGuess !== null) {
            $attrs = [];
            $typeGuessOptions = $typeGuess->getOptions();
            if (isset($typeGuessOptions['attr']) && isset($options['attr'])) {
                $attrs = ['attr' => array_merge($typeGuessOptions['attr'], $options['attr'])];
            }

            $options = array_merge($typeGuessOptions, $options, $attrs);
        }

        return $this->createNamedBuilder($property, $type, $data, $options);
    }
}
