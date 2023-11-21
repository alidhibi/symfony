<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Encoder\EncoderAwareInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class EncoderFactoryTest extends TestCase
{
    public function testGetEncoderWithMessageDigestEncoder(): void
    {
        $factory = new EncoderFactory([\Symfony\Component\Security\Core\User\UserInterface::class => [
            'class' => \Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder::class,
            'arguments' => ['sha512', true, 5],
        ]]);

        $encoder = $factory->getEncoder($this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock());
        $expectedEncoder = new MessageDigestPasswordEncoder('sha512', true, 5);

        $this->assertEquals($expectedEncoder->encodePassword('foo', 'moo'), $encoder->encodePassword('foo', 'moo'));
    }

    public function testGetEncoderWithService(): void
    {
        $factory = new EncoderFactory([
            \Symfony\Component\Security\Core\User\UserInterface::class => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder($this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock());
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));

        $encoder = $factory->getEncoder(new User('user', 'pass'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetEncoderWithClassName(): void
    {
        $factory = new EncoderFactory([
            \Symfony\Component\Security\Core\User\UserInterface::class => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder(\Symfony\Component\Security\Core\Tests\Encoder\SomeChildUser::class);
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetEncoderConfiguredForConcreteClassWithService(): void
    {
        $factory = new EncoderFactory([
            \Symfony\Component\Security\Core\User\User::class => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder(new User('user', 'pass'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetEncoderConfiguredForConcreteClassWithClassName(): void
    {
        $factory = new EncoderFactory([
            \Symfony\Component\Security\Core\Tests\Encoder\SomeUser::class => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder(\Symfony\Component\Security\Core\Tests\Encoder\SomeChildUser::class);
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetNamedEncoderForEncoderAware(): void
    {
        $factory = new EncoderFactory([
            \Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser::class => new MessageDigestPasswordEncoder('sha256'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder(new EncAwareUser('user', 'pass'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetNullNamedEncoderForEncoderAware(): void
    {
        $factory = new EncoderFactory([
            \Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser::class => new MessageDigestPasswordEncoder('sha1'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha256'),
        ]);

        $user = new EncAwareUser('user', 'pass');
        $user->encoderName = null;
        $encoder = $factory->getEncoder($user);
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetInvalidNamedEncoderForEncoderAware(): void
    {
        $this->expectException('RuntimeException');
        $factory = new EncoderFactory([
            \Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser::class => new MessageDigestPasswordEncoder('sha1'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha256'),
        ]);

        $user = new EncAwareUser('user', 'pass');
        $user->encoderName = 'invalid_encoder_name';
        $factory->getEncoder($user);
    }

    public function testGetEncoderForEncoderAwareWithClassName(): void
    {
        $factory = new EncoderFactory([
            \Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser::class => new MessageDigestPasswordEncoder('sha1'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha256'),
        ]);

        $encoder = $factory->getEncoder(\Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser::class);
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }
}

class SomeUser implements UserInterface
{
    public function getRoles(): void
    {
    }

    public function getPassword(): void
    {
    }

    public function getSalt(): void
    {
    }

    public function getUsername(): void
    {
    }

    public function eraseCredentials(): void
    {
    }
}

class SomeChildUser extends SomeUser
{
}

class EncAwareUser extends SomeUser implements EncoderAwareInterface
{
    public $encoderName = 'encoder_name';

    public function getEncoderName()
    {
        return $this->encoderName;
    }
}
