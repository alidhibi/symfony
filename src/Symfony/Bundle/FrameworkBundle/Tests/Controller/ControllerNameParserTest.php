<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Composer\Autoload\ClassLoader;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpKernel\Kernel;

class ControllerNameParserTest extends TestCase
{
    protected $loader;

    protected function setUp()
    {
        $this->loader = new ClassLoader();
        $this->loader->add('TestBundle', __DIR__.'/../Fixtures');
        $this->loader->add('TestApplication', __DIR__.'/../Fixtures');
        $this->loader->register();
    }

    protected function tearDown()
    {
        $this->loader->unregister();
        $this->loader = null;
    }

    public function testParse(): void
    {
        $parser = $this->createParser();

        $this->assertEquals(\TestBundle\FooBundle\Controller\DefaultController::class . '::indexAction', $parser->parse('FooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals(\TestBundle\FooBundle\Controller\Sub\DefaultController::class . '::indexAction', $parser->parse('FooBundle:Sub\Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals(\TestBundle\Fabpot\FooBundle\Controller\DefaultController::class . '::indexAction', $parser->parse('SensioFooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals(\TestBundle\Sensio\Cms\FooBundle\Controller\DefaultController::class . '::indexAction', $parser->parse('SensioCmsFooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals(\TestBundle\FooBundle\Controller\Test\DefaultController::class . '::indexAction', $parser->parse('FooBundle:Test\\Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals(\TestBundle\FooBundle\Controller\Test\DefaultController::class . '::indexAction', $parser->parse('FooBundle:Test/Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');

        try {
            $parser->parse('foo:');
            $this->fail('->parse() throws an \InvalidArgumentException if the controller is not an a:b:c string');
        } catch (\Exception $exception) {
            $this->assertInstanceOf('\InvalidArgumentException', $exception, '->parse() throws an \InvalidArgumentException if the controller is not an a:b:c string');
        }
    }

    public function testBuild(): void
    {
        $parser = $this->createParser();

        $this->assertEquals('FoooooBundle:Default:index', $parser->build(\TestBundle\FooBundle\Controller\DefaultController::class . '::indexAction'), '->parse() converts a class::method string to a short a:b:c notation string');
        $this->assertEquals('FoooooBundle:Sub\Default:index', $parser->build(\TestBundle\FooBundle\Controller\Sub\DefaultController::class . '::indexAction'), '->parse() converts a class::method string to a short a:b:c notation string');

        try {
            $parser->build(\TestBundle\FooBundle\Controller\DefaultController::class . '::index');
            $this->fail('->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        } catch (\Exception $exception) {
            $this->assertInstanceOf('\InvalidArgumentException', $exception, '->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        }

        try {
            $parser->build('TestBundle\FooBundle\Controller\Default::indexAction');
            $this->fail('->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        } catch (\Exception $exception) {
            $this->assertInstanceOf('\InvalidArgumentException', $exception, '->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        }

        try {
            $parser->build('Foo\Controller\DefaultController::indexAction');
            $this->fail('->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        } catch (\Exception $exception) {
            $this->assertInstanceOf('\InvalidArgumentException', $exception, '->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        }
    }

    /**
     * @dataProvider getMissingControllersTest
     */
    public function testMissingControllers($name): void
    {
        $parser = $this->createParser();

        try {
            $parser->parse($name);
            $this->fail('->parse() throws a \InvalidArgumentException if the class is found but does not exist');
        } catch (\Exception $exception) {
            $this->assertInstanceOf('\InvalidArgumentException', $exception, '->parse() throws a \InvalidArgumentException if the class is found but does not exist');
        }
    }

    public function getMissingControllersTest(): array
    {
        // a normal bundle
        $bundles = [
            ['FooBundle:Fake:index'],
        ];

        // a bundle with children
        if (Kernel::VERSION_ID < 40000) {
            $bundles[] = ['SensioFooBundle:Fake:index'];
        }

        return $bundles;
    }

    /**
     * @dataProvider getInvalidBundleNameTests
     */
    public function testInvalidBundleName(string $bundleName, string|bool $suggestedBundleName): void
    {
        $parser = $this->createParser();

        try {
            $parser->parse($bundleName);
            $this->fail('->parse() throws a \InvalidArgumentException if the bundle does not exist');
        } catch (\Exception $exception) {
            $this->assertInstanceOf('\InvalidArgumentException', $exception, '->parse() throws a \InvalidArgumentException if the bundle does not exist');

            if (false === $suggestedBundleName) {
                // make sure we don't have a suggestion
                $this->assertStringNotContainsString('Did you mean', $exception->getMessage());
            } else {
                $this->assertStringContainsString(sprintf('Did you mean "%s"', $suggestedBundleName), $exception->getMessage());
            }
        }
    }

    public function getInvalidBundleNameTests(): array
    {
        return [
            'Alternative will be found using levenshtein' => ['FoodBundle:Default:index', 'FooBundle:Default:index'],
            'Alternative will be found using partial match' => ['FabpotFooBund:Default:index', 'FabpotFooBundle:Default:index'],
            'Bundle does not exist at all' => ['CrazyBundle:Default:index', false],
        ];
    }

    private function createParser(): \Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser
    {
        $bundles = [
            'SensioFooBundle' => [$this->getBundle('TestBundle\Fabpot\FooBundle', 'FabpotFooBundle'), $this->getBundle('TestBundle\Sensio\FooBundle', 'SensioFooBundle')],
            'SensioCmsFooBundle' => [$this->getBundle('TestBundle\Sensio\Cms\FooBundle', 'SensioCmsFooBundle')],
            'FooBundle' => [$this->getBundle('TestBundle\FooBundle', 'FooBundle')],
            'FabpotFooBundle' => [$this->getBundle('TestBundle\Fabpot\FooBundle', 'FabpotFooBundle'), $this->getBundle('TestBundle\Sensio\FooBundle', 'SensioFooBundle')],
        ];

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
        $kernel
            ->expects($this->any())
            ->method('getBundle')
            ->willReturnCallback(static function ($bundle) use ($bundles) {
                if (!isset($bundles[$bundle])) {
                    throw new \InvalidArgumentException(sprintf('Invalid bundle name "%s"', $bundle));
                }
                return $bundles[$bundle];
            })
        ;

        $bundles = [
            'SensioFooBundle' => $this->getBundle('TestBundle\Fabpot\FooBundle', 'FabpotFooBundle'),
            'SensioCmsFooBundle' => $this->getBundle('TestBundle\Sensio\Cms\FooBundle', 'SensioCmsFooBundle'),
            'FoooooBundle' => $this->getBundle('TestBundle\FooBundle', 'FoooooBundle'),
            'FooBundle' => $this->getBundle('TestBundle\FooBundle', 'FooBundle'),
            'FabpotFooBundle' => $this->getBundle('TestBundle\Fabpot\FooBundle', 'FabpotFooBundle'),
        ];
        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->willReturn($bundles)
        ;

        return new ControllerNameParser($kernel);
    }

    private function getBundle(string $namespace, string $name)
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\BundleInterface')->getMock();
        $bundle->expects($this->any())->method('getName')->willReturn($name);
        $bundle->expects($this->any())->method('getNamespace')->willReturn($namespace);

        return $bundle;
    }
}
