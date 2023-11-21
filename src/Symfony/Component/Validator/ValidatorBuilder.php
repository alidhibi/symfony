<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\ArrayCache;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\Cache\CacheInterface;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Validator\Validator\RecursiveValidator;

/**
 * The default implementation of {@link ValidatorBuilderInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorBuilder implements ValidatorBuilderInterface
{
    private $initializers = [];

    private $xmlMappings = [];

    private $yamlMappings = [];

    private $methodMappings = [];

    /**
     * @var Reader|null
     */
    private ?\Doctrine\Common\Annotations\Reader $annotationReader = null;

    private ?\Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface $metadataFactory = null;

    private ?\Symfony\Component\Validator\ConstraintValidatorFactoryInterface $validatorFactory = null;

    /**
     * @var CacheInterface|null
     */
    private $metadataCache;

    /**
     * @var TranslatorInterface|null
     */
    private ?\Symfony\Contracts\Translation\TranslatorInterface $translator = null;

    private ?string $translationDomain = null;

    /**
     * {@inheritdoc}
     */
    public function addObjectInitializer(ObjectInitializerInterface $initializer): static
    {
        $this->initializers[] = $initializer;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addObjectInitializers(array $initializers): static
    {
        $this->initializers = array_merge($this->initializers, $initializers);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addXmlMapping($path): static
    {
        if ($this->metadataFactory instanceof \Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->xmlMappings[] = $path;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addXmlMappings(array $paths): static
    {
        if ($this->metadataFactory instanceof \Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->xmlMappings = array_merge($this->xmlMappings, $paths);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addYamlMapping($path): static
    {
        if ($this->metadataFactory instanceof \Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->yamlMappings[] = $path;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addYamlMappings(array $paths): static
    {
        if ($this->metadataFactory instanceof \Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->yamlMappings = array_merge($this->yamlMappings, $paths);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addMethodMapping($methodName): static
    {
        if ($this->metadataFactory instanceof \Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->methodMappings[] = $methodName;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addMethodMappings(array $methodNames): static
    {
        if ($this->metadataFactory instanceof \Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface) {
            throw new ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->methodMappings = array_merge($this->methodMappings, $methodNames);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function enableAnnotationMapping(Reader $annotationReader = null): static
    {
        if ($this->metadataFactory instanceof \Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface) {
            throw new ValidatorException('You cannot enable annotation mapping after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        if (!$annotationReader instanceof \Doctrine\Common\Annotations\Reader) {
            if (!class_exists('Doctrine\Common\Annotations\AnnotationReader') || !class_exists('Doctrine\Common\Cache\ArrayCache')) {
                throw new \RuntimeException('Enabling annotation based constraint mapping requires the packages doctrine/annotations and doctrine/cache to be installed.');
            }

            $annotationReader = new CachedReader(new AnnotationReader(), new ArrayCache());
        }

        $this->annotationReader = $annotationReader;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function disableAnnotationMapping(): static
    {
        $this->annotationReader = null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMetadataFactory(MetadataFactoryInterface $metadataFactory): static
    {
        if (\count($this->xmlMappings) > 0 || \count($this->yamlMappings) > 0 || \count($this->methodMappings) > 0 || $this->annotationReader instanceof \Doctrine\Common\Annotations\Reader) {
            throw new ValidatorException('You cannot set a custom metadata factory after adding custom mappings. You should do either of both.');
        }

        $this->metadataFactory = $metadataFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMetadataCache(CacheInterface $cache): static
    {
        if ($this->metadataFactory instanceof \Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface) {
            throw new ValidatorException('You cannot set a custom metadata cache after setting a custom metadata factory. Configure your metadata factory instead.');
        }

        $this->metadataCache = $cache;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setConstraintValidatorFactory(ConstraintValidatorFactoryInterface $validatorFactory): static
    {
        $this->validatorFactory = $validatorFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTranslator(TranslatorInterface $translator): static
    {
        $this->translator = $translator;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTranslationDomain($translationDomain): static
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    /**
     * @return LoaderInterface[]
     */
    public function getLoaders(): array
    {
        $loaders = [];

        foreach ($this->xmlMappings as $xmlMapping) {
            $loaders[] = new XmlFileLoader($xmlMapping);
        }

        foreach ($this->yamlMappings as $yamlMappings) {
            $loaders[] = new YamlFileLoader($yamlMappings);
        }

        foreach ($this->methodMappings as $methodName) {
            $loaders[] = new StaticMethodLoader($methodName);
        }

        if ($this->annotationReader instanceof \Doctrine\Common\Annotations\Reader) {
            $loaders[] = new AnnotationLoader($this->annotationReader);
        }

        return $loaders;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidator(): \Symfony\Component\Validator\Validator\RecursiveValidator
    {
        $metadataFactory = $this->metadataFactory;

        if (!$metadataFactory instanceof \Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface) {
            $loaders = $this->getLoaders();
            $loader = null;

            if (\count($loaders) > 1) {
                $loader = new LoaderChain($loaders);
            } elseif (1 === \count($loaders)) {
                $loader = $loaders[0];
            }

            $metadataFactory = new LazyLoadingMetadataFactory($loader, $this->metadataCache);
        }

        $validatorFactory = $this->validatorFactory ?: new ConstraintValidatorFactory();
        $translator = $this->translator;

        if (!$translator instanceof \Symfony\Contracts\Translation\TranslatorInterface) {
            $translator = new IdentityTranslator();
            // Force the locale to be 'en' when no translator is provided rather than relying on the Intl default locale
            // This avoids depending on Intl or the stub implementation being available. It also ensures that Symfony
            // validation messages are pluralized properly even when the default locale gets changed because they are in
            // English.
            $translator->setLocale('en');
        }

        $contextFactory = new ExecutionContextFactory($translator, $this->translationDomain);

        return new RecursiveValidator($contextFactory, $metadataFactory, $validatorFactory, $this->initializers);
    }
}
