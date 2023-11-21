<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;

class XmlFileLoaderTest extends TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful(): void
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata(\Symfony\Component\Validator\Tests\Fixtures\Entity::class);

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseIfNotSuccessful(): void
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata('\stdClass');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadata(): void
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata(\Symfony\Component\Validator\Tests\Fixtures\Entity::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(\Symfony\Component\Validator\Tests\Fixtures\Entity::class);
        $expected->setGroupSequence(['Foo', 'Entity']);
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new ConstraintB());
        $expected->addConstraint(new Callback('validateMe'));
        $expected->addConstraint(new Callback('validateMeStatic'));
        $expected->addConstraint(new Callback(static fn($object, \Symfony\Component\Validator\Context\ExecutionContextInterface $context) => \Symfony\Component\Validator\Tests\Fixtures\CallbackClass::callback($object, $context)));
        $expected->addConstraint(new Traverse(false));
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Range(['min' => 3]));
        $expected->addPropertyConstraint('firstName', new Choice(['A', 'B']));
        $expected->addPropertyConstraint('firstName', new All([new NotNull(), new Range(['min' => 3])]));
        $expected->addPropertyConstraint('firstName', new All(['constraints' => [new NotNull(), new Range(['min' => 3])]]));
        $expected->addPropertyConstraint('firstName', new Collection(['fields' => [
            'foo' => [new NotNull(), new Range(['min' => 3])],
            'bar' => [new Range(['min' => 5])],
        ]]));
        $expected->addPropertyConstraint('firstName', new Choice([
            'message' => 'Must be one of %choices%',
            'choices' => ['A', 'B'],
        ]));
        $expected->addGetterConstraint('lastName', new NotNull());
        $expected->addGetterConstraint('valid', new IsTrue());
        $expected->addGetterConstraint('permissions', new IsTrue());

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadClassMetadataWithNonStrings(): void
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping-non-strings.xml');
        $metadata = new ClassMetadata(\Symfony\Component\Validator\Tests\Fixtures\Entity::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(\Symfony\Component\Validator\Tests\Fixtures\Entity::class);
        $expected->addPropertyConstraint('firstName', new Regex(['pattern' => '/^1/', 'match' => false]));

        $properties = $metadata->getPropertyMetadata('firstName');
        $constraints = $properties[0]->getConstraints();

        $this->assertFalse($constraints[0]->match);
    }

    public function testLoadGroupSequenceProvider(): void
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata(\Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(\Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity::class);
        $expected->setGroupSequenceProvider(true);

        $this->assertEquals($expected, $metadata);
    }

    public function testThrowExceptionIfDocTypeIsSet(): void
    {
        $loader = new XmlFileLoader(__DIR__.'/withdoctype.xml');
        $metadata = new ClassMetadata(\Symfony\Component\Validator\Tests\Fixtures\Entity::class);

        $this->expectException('\\' . \Symfony\Component\Validator\Exception\MappingException::class);
        $loader->loadClassMetadata($metadata);
    }

    /**
     * @see https://github.com/symfony/symfony/pull/12158
     */
    public function testDoNotModifyStateIfExceptionIsThrown(): void
    {
        $loader = new XmlFileLoader(__DIR__.'/withdoctype.xml');
        $metadata = new ClassMetadata(\Symfony\Component\Validator\Tests\Fixtures\Entity::class);

        try {
            $loader->loadClassMetadata($metadata);
        } catch (MappingException $mappingException) {
            $this->expectException('\\' . \Symfony\Component\Validator\Exception\MappingException::class);
            $loader->loadClassMetadata($metadata);
        }
    }
}
