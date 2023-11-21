<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;

class AnnotationDirectoryLoaderTest extends AbstractAnnotationLoaderTest
{
    protected $loader;

    protected $reader;

    protected function setUp()
    {
        $this->reader = $this->getReader();
        $this->loader = new AnnotationDirectoryLoader(new FileLocator(), $this->getClassLoader($this->reader));
    }

    public function testLoad(): void
    {
        $this->reader->expects($this->exactly(4))->method('getClassAnnotation');

        $this->reader
            ->expects($this->any())
            ->method('getMethodAnnotations')
            ->willReturn([])
        ;

        $this->reader
            ->expects($this->any())
            ->method('getClassAnnotations')
            ->willReturn([])
        ;

        $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses');
    }

    public function testLoadIgnoresHiddenDirectories(): void
    {
        $this->expectAnnotationsToBeReadFrom([
            \Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass::class,
            \Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BazClass::class,
            \Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\FooClass::class,
            \Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\EncodingClass::class,
        ]);

        $this->reader
            ->expects($this->any())
            ->method('getMethodAnnotations')
            ->willReturn([])
        ;

        $this->reader
            ->expects($this->any())
            ->method('getClassAnnotations')
            ->willReturn([])
        ;

        $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses');
    }

    public function testSupports(): void
    {
        $fixturesDir = __DIR__.'/../Fixtures';

        $this->assertTrue($this->loader->supports($fixturesDir), '->supports() returns true if the resource is loadable');
        $this->assertFalse($this->loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($this->loader->supports($fixturesDir, 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports($fixturesDir, 'foo'), '->supports() checks the resource type if specified');
    }

    public function testItSupportsAnyAnnotation(): void
    {
        $this->assertTrue($this->loader->supports(__DIR__.'/../Fixtures/even-with-not-existing-folder', 'annotation'));
    }

    public function testLoadFileIfLocatedResourceIsFile(): void
    {
        $this->reader->expects($this->exactly(1))->method('getClassAnnotation');

        $this->reader
            ->expects($this->any())
            ->method('getMethodAnnotations')
            ->willReturn([])
        ;

        $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses/FooClass.php');
    }

    private function expectAnnotationsToBeReadFrom(array $classes): void
    {
        $this->reader->expects($this->exactly(\count($classes)))
            ->method('getClassAnnotation')
            ->with($this->callback(static fn(\ReflectionClass $class): bool => \in_array($class->getName(), $classes)));
    }
}
