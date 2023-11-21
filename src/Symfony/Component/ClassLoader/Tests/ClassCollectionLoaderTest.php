<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ClassLoader\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ClassLoader\ClassCollectionLoader;
use Symfony\Component\ClassLoader\Tests\Fixtures\DeclaredClass;
use Symfony\Component\ClassLoader\Tests\Fixtures\WarmedClass;

require_once __DIR__.'/Fixtures/ClassesWithParents/GInterface.php';
require_once __DIR__.'/Fixtures/ClassesWithParents/CInterface.php';
require_once __DIR__.'/Fixtures/ClassesWithParents/B.php';
require_once __DIR__.'/Fixtures/ClassesWithParents/A.php';

/**
 * @group legacy
 */
class ClassCollectionLoaderTest extends TestCase
{
    public function testTraitDependencies(): void
    {
        require_once __DIR__.'/Fixtures/deps/traits.php';

        $r = new \ReflectionClass(\Symfony\Component\ClassLoader\ClassCollectionLoader::class);
        $m = $r->getMethod('getOrderedClasses');
        $m->setAccessible(true);

        $ordered = $m->invoke(null, ['CTFoo']);

        $this->assertEquals(
            ['TD', 'TC', 'TB', 'TA', 'TZ', 'CTFoo'],
            array_map(static fn($class) => $class->getName(), $ordered)
        );

        $ordered = $m->invoke(null, ['CTBar']);

        $this->assertEquals(
            ['TD', 'TZ', 'TC', 'TB', 'TA', 'CTBar'],
            array_map(static fn($class) => $class->getName(), $ordered)
        );
    }

    /**
     * @dataProvider getDifferentOrders
     */
    public function testClassReordering(array $classes): void
    {
        $expected = [
            \ClassesWithParents\GInterface::class,
            \ClassesWithParents\CInterface::class,
            \ClassesWithParents\B::class,
            \ClassesWithParents\A::class,
        ];

        $r = new \ReflectionClass(\Symfony\Component\ClassLoader\ClassCollectionLoader::class);
        $m = $r->getMethod('getOrderedClasses');
        $m->setAccessible(true);

        $ordered = $m->invoke(null, $classes);

        $this->assertEquals($expected, array_map(static fn($class) => $class->getName(), $ordered));
    }

    public function getDifferentOrders(): array
    {
        return [
            [[
                \ClassesWithParents\A::class,
                \ClassesWithParents\CInterface::class,
                \ClassesWithParents\GInterface::class,
                \ClassesWithParents\B::class,
            ]],
            [[
                \ClassesWithParents\B::class,
                \ClassesWithParents\A::class,
                \ClassesWithParents\CInterface::class,
            ]],
            [[
                \ClassesWithParents\CInterface::class,
                \ClassesWithParents\B::class,
                \ClassesWithParents\A::class,
            ]],
            [[
                \ClassesWithParents\A::class,
            ]],
        ];
    }

    /**
     * @dataProvider getDifferentOrdersForTraits
     */
    public function testClassWithTraitsReordering(array $classes): void
    {
        require_once __DIR__.'/Fixtures/ClassesWithParents/ATrait.php';
        require_once __DIR__.'/Fixtures/ClassesWithParents/BTrait.php';
        require_once __DIR__.'/Fixtures/ClassesWithParents/CTrait.php';
        require_once __DIR__.'/Fixtures/ClassesWithParents/D.php';
        require_once __DIR__.'/Fixtures/ClassesWithParents/E.php';

        $expected = [
            \ClassesWithParents\GInterface::class,
            \ClassesWithParents\CInterface::class,
            \ClassesWithParents\ATrait::class,
            \ClassesWithParents\BTrait::class,
            \ClassesWithParents\CTrait::class,
            \ClassesWithParents\B::class,
            \ClassesWithParents\A::class,
            \ClassesWithParents\D::class,
            \ClassesWithParents\E::class,
        ];

        $r = new \ReflectionClass(\Symfony\Component\ClassLoader\ClassCollectionLoader::class);
        $m = $r->getMethod('getOrderedClasses');
        $m->setAccessible(true);

        $ordered = $m->invoke(null, $classes);

        $this->assertEquals($expected, array_map(static fn($class) => $class->getName(), $ordered));
    }

    public function getDifferentOrdersForTraits(): array
    {
        return [
            [[
                \ClassesWithParents\E::class,
                \ClassesWithParents\ATrait::class,
            ]],
            [[
                \ClassesWithParents\E::class,
            ]],
        ];
    }

    public function testFixClassWithTraitsOrdering(): void
    {
        require_once __DIR__.'/Fixtures/ClassesWithParents/CTrait.php';
        require_once __DIR__.'/Fixtures/ClassesWithParents/F.php';
        require_once __DIR__.'/Fixtures/ClassesWithParents/G.php';

        $classes = [
            \ClassesWithParents\F::class,
            \ClassesWithParents\G::class,
        ];

        $expected = [
            \ClassesWithParents\CTrait::class,
            \ClassesWithParents\F::class,
            \ClassesWithParents\G::class,
        ];

        $r = new \ReflectionClass(\Symfony\Component\ClassLoader\ClassCollectionLoader::class);
        $m = $r->getMethod('getOrderedClasses');
        $m->setAccessible(true);

        $ordered = $m->invoke(null, $classes);

        $this->assertEquals($expected, array_map(static fn($class) => $class->getName(), $ordered));
    }

    /**
     * @dataProvider getFixNamespaceDeclarationsData
     */
    public function testFixNamespaceDeclarations(string $source, string $expected): void
    {
        $this->assertEquals('<?php '.$expected, ClassCollectionLoader::fixNamespaceDeclarations('<?php '.$source));
    }

    public function getFixNamespaceDeclarationsData(): array
    {
        return [
            ["namespace;\nclass Foo {}\n", "namespace\n{\nclass Foo {}\n}"],
            ["namespace Foo;\nclass Foo {}\n", "namespace Foo\n{\nclass Foo {}\n}"],
            ["namespace   Bar ;\nclass Foo {}\n", "namespace Bar\n{\nclass Foo {}\n}"],
            ["namespace Foo\Bar;\nclass Foo {}\n", "namespace Foo\Bar\n{\nclass Foo {}\n}"],
            ["namespace Foo\Bar\Bar\n{\nclass Foo {}\n}\n", "namespace Foo\Bar\Bar\n{\nclass Foo {}\n}"],
            ["namespace\n{\nclass Foo {}\n}\n", "namespace\n{\nclass Foo {}\n}"],
        ];
    }

    /**
     * @dataProvider getFixNamespaceDeclarationsDataWithoutTokenizer
     */
    public function testFixNamespaceDeclarationsWithoutTokenizer(string $source, string $expected): void
    {
        ClassCollectionLoader::enableTokenizer(false);
        $this->assertEquals('<?php '.$expected, ClassCollectionLoader::fixNamespaceDeclarations('<?php '.$source));
        ClassCollectionLoader::enableTokenizer(true);
    }

    public function getFixNamespaceDeclarationsDataWithoutTokenizer(): array
    {
        return [
            ["namespace;\nclass Foo {}\n", "namespace\n{\nclass Foo {}\n}\n"],
            ["namespace Foo;\nclass Foo {}\n", "namespace Foo\n{\nclass Foo {}\n}\n"],
            ["namespace   Bar ;\nclass Foo {}\n", "namespace   Bar\n{\nclass Foo {}\n}\n"],
            ["namespace Foo\Bar;\nclass Foo {}\n", "namespace Foo\Bar\n{\nclass Foo {}\n}\n"],
            ["namespace Foo\Bar\Bar\n{\nclass Foo {}\n}\n", "namespace Foo\Bar\Bar\n{\nclass Foo {}\n}\n"],
            ["\nnamespace\n{\nclass Foo {}\n\$namespace=123;}\n", "\nnamespace\n{\nclass Foo {}\n\$namespace=123;}\n"],
        ];
    }

    public function testUnableToLoadClassException(): void
    {
        $this->expectException('InvalidArgumentException');
        if (is_file($file = sys_get_temp_dir().'/foo.php')) {
            unlink($file);
        }

        ClassCollectionLoader::load(['SomeNotExistingClass'], sys_get_temp_dir(), 'foo', false);
    }

    public function testCommentStripping(): void
    {
        if (is_file($file = __DIR__.'/bar.php')) {
            unlink($file);
        }

        spl_autoload_register($r = static function ($class) : void {
            if (0 === strpos($class, 'Namespaced') || 0 === strpos($class, 'Pearlike_')) {
                @require_once __DIR__.'/Fixtures/'.str_replace(['\\', '_'], '/', $class).'.php';
            }
        });

        $strictTypes = \defined('HHVM_VERSION') ? '' : "\nnamespace {require __DIR__.'/Fixtures/Namespaced/WithStrictTypes.php';}";

        ClassCollectionLoader::load(
            [\Namespaced\WithComments::class, 'Pearlike_WithComments', \Namespaced\WithDirMagic::class, \Namespaced\WithFileMagic::class, \Namespaced\WithHaltCompiler::class, $strictTypes !== '' && $strictTypes !== '0' ? \Namespaced\WithStrictTypes::class : \Namespaced\WithComments::class],
            __DIR__,
            'bar',
            false
        );

        spl_autoload_unregister($r);

        $this->assertEquals(<<<'EOF'
namespace Namespaced
{
class WithComments
{
public static $loaded = true;
}
$string ='string should not be   modified {$string}';
$heredoc = (<<<HD


Heredoc should not be   modified {$string}


HD
);
$nowdoc =<<<'ND'


Nowdoc should not be   modified {$string}


ND
;
}
namespace
{
class Pearlike_WithComments
{
public static $loaded = true;
}
}
namespace {require __DIR__.'/Fixtures/Namespaced/WithDirMagic.php';}
namespace {require __DIR__.'/Fixtures/Namespaced/WithFileMagic.php';}
namespace {require __DIR__.'/Fixtures/Namespaced/WithHaltCompiler.php';}
EOF
            .$strictTypes,
            str_replace(["<?php \n", '\\\\'], ['', '/'], file_get_contents($file))
        );

        unlink($file);
    }

    public function testInline(): void
    {
        $this->assertTrue(class_exists(WarmedClass::class, true));

        @unlink($cache = sys_get_temp_dir().'/inline.php');

        $classes = [WarmedClass::class];
        $excluded = [DeclaredClass::class];

        ClassCollectionLoader::inline($classes, $cache, $excluded);

        $this->assertSame(<<<'EOTXT'
<?php 
namespace Symfony\Component\ClassLoader\Tests\Fixtures
{
interface WarmedInterface
{
}
}
namespace Symfony\Component\ClassLoader\Tests\Fixtures
{
class WarmedClass extends DeclaredClass implements WarmedInterface
{
}
}
EOTXT
            , file_get_contents($cache)
        );

        unlink($cache);
    }
}
