<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DataCollectorTranslatorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\TranslatorInterface;

class DataCollectorTranslatorPassTest extends TestCase
{
    private \Symfony\Component\DependencyInjection\ContainerBuilder $container;

    private \Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DataCollectorTranslatorPass $dataCollectorTranslatorPass;

    protected function setUp()
    {
        $this->container = new ContainerBuilder();
        $this->dataCollectorTranslatorPass = new DataCollectorTranslatorPass();

        $this->container->setParameter('translator_implementing_bag', 'Symfony\Component\Translation\Translator');
        $this->container->setParameter('translator_not_implementing_bag', \Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\TranslatorWithTranslatorBag::class);

        $this->container->register('translator.data_collector', 'Symfony\Component\Translation\DataCollectorTranslator')
            ->setPublic(false)
            ->setDecoratedService('translator')
            ->setArguments([new Reference('translator.data_collector.inner')])
        ;

        $this->container->register('data_collector.translation', 'Symfony\Component\Translation\DataCollector\TranslationDataCollector')
            ->setArguments([new Reference('translator.data_collector')])
        ;
    }

    /**
     * @dataProvider getImplementingTranslatorBagInterfaceTranslatorClassNames
     */
    public function testProcessKeepsDataCollectorTranslatorIfItImplementsTranslatorBagInterface(string $class): void
    {
        $this->container->register('translator', $class);

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('translator.data_collector'));
    }

    /**
     * @dataProvider getImplementingTranslatorBagInterfaceTranslatorClassNames
     */
    public function testProcessKeepsDataCollectorIfTranslatorImplementsTranslatorBagInterface(string $class): void
    {
        $this->container->register('translator', $class);

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('data_collector.translation'));
    }

    public function getImplementingTranslatorBagInterfaceTranslatorClassNames(): array
    {
        return [
            ['Symfony\Component\Translation\Translator'],
            ['%translator_implementing_bag%'],
        ];
    }

    /**
     * @dataProvider getNotImplementingTranslatorBagInterfaceTranslatorClassNames
     */
    public function testProcessRemovesDataCollectorTranslatorIfItDoesNotImplementTranslatorBagInterface(string $class): void
    {
        $this->container->register('translator', $class);

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('translator.data_collector'));
    }

    /**
     * @dataProvider getNotImplementingTranslatorBagInterfaceTranslatorClassNames
     */
    public function testProcessRemovesDataCollectorIfTranslatorDoesNotImplementTranslatorBagInterface(string $class): void
    {
        $this->container->register('translator', $class);

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('data_collector.translation'));
    }

    public function getNotImplementingTranslatorBagInterfaceTranslatorClassNames(): array
    {
        return [
            [\Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\TranslatorWithTranslatorBag::class],
            ['%translator_not_implementing_bag%'],
        ];
    }
}

class TranslatorWithTranslatorBag implements TranslatorInterface
{
    public function trans($id, array $parameters = [], $domain = null, $locale = null): void
    {
    }

    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null): void
    {
    }

    public function setLocale($locale): void
    {
    }

    public function getLocale(): void
    {
    }
}
