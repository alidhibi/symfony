<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\Command\DebugCommand;
use Symfony\Component\Form\DependencyInjection\FormPass;
use Symfony\Component\Form\FormRegistry;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormPassTest extends TestCase
{
    public function testDoNothingIfFormExtensionNotLoaded(): void
    {
        $container = $this->createContainerBuilder();

        $container->compile();

        $this->assertFalse($container->hasDefinition('form.extension'));
    }

    public function testDoNothingIfDebugCommandNotLoaded(): void
    {
        $container = $this->createContainerBuilder();

        $container->compile();

        $this->assertFalse($container->hasDefinition('console.command.form_debug'));
    }

    public function testAddTaggedTypes(): void
    {
        $container = $this->createContainerBuilder();

        $container->setDefinition('form.extension', $this->createExtensionDefinition());
        $container->register('my.type1', __CLASS__.'_Type1')->addTag('form.type')->setPublic(true);
        $container->register('my.type2', __CLASS__.'_Type2')->addTag('form.type')->setPublic(true);

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');

        $locator = $extDefinition->getArgument(0);
        $this->assertTrue(!$locator->isPublic() || $locator->isPrivate());
        $this->assertEquals(
            (new Definition(ServiceLocator::class, [[
                __CLASS__.'_Type1' => new ServiceClosureArgument(new Reference('my.type1')),
                __CLASS__.'_Type2' => new ServiceClosureArgument(new Reference('my.type2')),
            ]]))->addTag('container.service_locator')->setPublic(false),
            $locator->setPublic(false)
        );
    }

    public function testAddTaggedTypesToDebugCommand(): void
    {
        $container = $this->createContainerBuilder();

        $container->register('form.registry', FormRegistry::class);

        $commandDefinition = new Definition(DebugCommand::class, [new Reference('form.registry')]);
        $commandDefinition->setPublic(true);

        $container->setDefinition('form.extension', $this->createExtensionDefinition());
        $container->setDefinition('console.command.form_debug', $commandDefinition);
        $container->register('my.type1', __CLASS__.'_Type1')->addTag('form.type')->setPublic(true);
        $container->register('my.type2', __CLASS__.'_Type2')->addTag('form.type')->setPublic(true);

        $container->compile();

        $cmdDefinition = $container->getDefinition('console.command.form_debug');

        $this->assertEquals(
            [
                'Symfony\Component\Form\Extension\Core\Type',
                __NAMESPACE__,
            ],
            $cmdDefinition->getArgument(1)
        );
    }

    /**
     * @dataProvider addTaggedTypeExtensionsDataProvider
     */
    public function testAddTaggedTypeExtensions(array $extensions, array $expectedRegisteredExtensions): void
    {
        $container = $this->createContainerBuilder();

        $container->setDefinition('form.extension', $this->createExtensionDefinition());

        foreach ($extensions as $serviceId => $tag) {
            $container->register($serviceId, 'stdClass')->addTag('form.type_extension', $tag);
        }

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');
        $this->assertEquals($expectedRegisteredExtensions, $extDefinition->getArgument(1));
    }

    public function addTaggedTypeExtensionsDataProvider(): array
    {
        return [
            [
                [
                    'my.type_extension1' => ['extended_type' => 'type1'],
                    'my.type_extension2' => ['extended_type' => 'type1'],
                    'my.type_extension3' => ['extended_type' => 'type2'],
                ],
                [
                    'type1' => new IteratorArgument([
                        new Reference('my.type_extension1'),
                        new Reference('my.type_extension2'),
                    ]),
                    'type2' => new IteratorArgument([new Reference('my.type_extension3')]),
                ],
            ],
            [
                [
                    'my.type_extension1' => ['extended_type' => 'type1', 'priority' => 1],
                    'my.type_extension2' => ['extended_type' => 'type1', 'priority' => 2],
                    'my.type_extension3' => ['extended_type' => 'type1', 'priority' => -1],
                    'my.type_extension4' => ['extended_type' => 'type2', 'priority' => 2],
                    'my.type_extension5' => ['extended_type' => 'type2', 'priority' => 1],
                    'my.type_extension6' => ['extended_type' => 'type2', 'priority' => 1],
                ],
                [
                    'type1' => new IteratorArgument([
                        new Reference('my.type_extension2'),
                        new Reference('my.type_extension1'),
                        new Reference('my.type_extension3'),
                    ]),
                    'type2' => new IteratorArgument([
                        new Reference('my.type_extension4'),
                        new Reference('my.type_extension5'),
                        new Reference('my.type_extension6'),
                    ]),
                ],
            ],
        ];
    }

    public function testAddTaggedFormTypeExtensionWithoutExtendedTypeAttribute(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('extended-type attribute, none was configured for the "my.type_extension" service');
        $container = $this->createContainerBuilder();

        $container->setDefinition('form.extension', $this->createExtensionDefinition());
        $container->register('my.type_extension', 'stdClass')
            ->setPublic(true)
            ->addTag('form.type_extension');

        $container->compile();
    }

    public function testAddTaggedGuessers(): void
    {
        $container = $this->createContainerBuilder();

        $definition1 = new Definition('stdClass');
        $definition1->addTag('form.type_guesser');

        $definition2 = new Definition('stdClass');
        $definition2->addTag('form.type_guesser');

        $container->setDefinition('form.extension', $this->createExtensionDefinition());
        $container->setDefinition('my.guesser1', $definition1)->setPublic(true);
        $container->setDefinition('my.guesser2', $definition2)->setPublic(true);

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');

        $this->assertEquals(
            new IteratorArgument([
                new Reference('my.guesser1'),
                new Reference('my.guesser2'),
            ]),
            $extDefinition->getArgument(2)
        );
    }

    /**
     * @dataProvider privateTaggedServicesProvider
     */
    public function testPrivateTaggedServices(string $id, string $tagName, callable $assertion, array $tagAttributes = []): void
    {
        $formPass = new FormPass();
        $container = new ContainerBuilder();

        $container->setDefinition('form.extension', $this->createExtensionDefinition());
        $container->register($id, 'stdClass')->setPublic(false)->addTag($tagName, $tagAttributes);
        $formPass->process($container);

        $assertion($container);
    }

    public function privateTaggedServicesProvider(): array
    {
        return [
            [
                'my.type',
                'form.type',
                function (ContainerBuilder $container): void {
                    $formTypes = $container->getDefinition('form.extension')->getArgument(0);

                    $this->assertInstanceOf(Reference::class, $formTypes);

                    $locator = $container->getDefinition((string) $formTypes);
                    $expectedLocatorMap = [
                        'stdClass' => new ServiceClosureArgument(new Reference('my.type')),
                    ];

                    $this->assertInstanceOf(Definition::class, $locator);
                    $this->assertEquals($expectedLocatorMap, $locator->getArgument(0));
                },
            ],
            [
                'my.type_extension',
                'form.type_extension',
                function (ContainerBuilder $container): void {
                    $this->assertEquals(
                        [\Symfony\Component\Form\Extension\Core\Type\FormType::class => new IteratorArgument([new Reference('my.type_extension')])],
                        $container->getDefinition('form.extension')->getArgument(1)
                    );
                },
                ['extended_type' => \Symfony\Component\Form\Extension\Core\Type\FormType::class],
            ],
            ['my.guesser', 'form.type_guesser', function (ContainerBuilder $container): void {
                $this->assertEquals(new IteratorArgument([new Reference('my.guesser')]), $container->getDefinition('form.extension')->getArgument(2));
            }],
        ];
    }

    private function createExtensionDefinition(): \Symfony\Component\DependencyInjection\Definition
    {
        $definition = new Definition(\Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension::class);
        $definition->setPublic(true);
        $definition->setArguments([
            [],
            [],
            new IteratorArgument([]),
        ]);

        return $definition;
    }

    private function createContainerBuilder(): \Symfony\Component\DependencyInjection\ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new FormPass());

        return $container;
    }
}
