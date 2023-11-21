<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Validator\Constraints;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class UserPasswordValidatorTest extends ConstraintValidatorTestCase
{
    final const PASSWORD = 's3Cr3t';

    final const SALT = '^S4lt$';

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var PasswordEncoderInterface
     */
    protected $encoder;

    /**
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    protected function createValidator()
    {
        return new UserPasswordValidator($this->tokenStorage, $this->encoderFactory);
    }

    protected function setUp()
    {
        $user = $this->createUser();
        $this->tokenStorage = $this->createTokenStorage($user);
        $this->encoder = $this->createPasswordEncoder();
        $this->encoderFactory = $this->createEncoderFactory($this->encoder);

        parent::setUp();
    }

    public function testPasswordIsValid(): void
    {
        $constraint = new UserPassword([
            'message' => 'myMessage',
        ]);

        $this->encoder->expects($this->once())
            ->method('isPasswordValid')
            ->with(static::PASSWORD, 'secret', static::SALT)
            ->willReturn(true);

        $this->validator->validate('secret', $constraint);

        $this->assertNoViolation();
    }

    public function testPasswordIsNotValid(): void
    {
        $constraint = new UserPassword([
            'message' => 'myMessage',
        ]);

        $this->encoder->expects($this->once())
            ->method('isPasswordValid')
            ->with(static::PASSWORD, 'secret', static::SALT)
            ->willReturn(false);

        $this->validator->validate('secret', $constraint);

        $this->buildViolation('myMessage')
            ->assertRaised();
    }

    /**
     * @dataProvider emptyPasswordData
     */
    public function testEmptyPasswordsAreNotValid(?string $password): void
    {
        $constraint = new UserPassword([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($password, $constraint);

        $this->buildViolation('myMessage')
            ->assertRaised();
    }

    public function emptyPasswordData()
    {
        return [
            [null],
            [''],
        ];
    }

    public function testUserIsNotValid(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);
        $user = $this->getMockBuilder('Foo\Bar\User')->getMock();

        $this->tokenStorage = $this->createTokenStorage($user);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate('secret', new UserPassword());
    }

    protected function createUser()
    {
        $mock = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();

        $mock
            ->expects($this->any())
            ->method('getPassword')
            ->willReturn(static::PASSWORD)
        ;

        $mock
            ->expects($this->any())
            ->method('getSalt')
            ->willReturn(static::SALT)
        ;

        return $mock;
    }

    protected function createPasswordEncoder($isPasswordValid = true)
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface::class)->getMock();
    }

    protected function createEncoderFactory($encoder = null)
    {
        $mock = $this->getMockBuilder(\Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface::class)->getMock();

        $mock
            ->expects($this->any())
            ->method('getEncoder')
            ->willReturn($encoder)
        ;

        return $mock;
    }

    protected function createTokenStorage($user = null)
    {
        $token = $this->createAuthenticationToken($user);

        $mock = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
        $mock
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        return $mock;
    }

    protected function createAuthenticationToken($user = null)
    {
        $mock = $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
        $mock
            ->expects($this->any())
            ->method('getUser')
            ->willReturn($user)
        ;

        return $mock;
    }
}
