<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EmailValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @group dns-sensitive
 */
class EmailValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): \Symfony\Component\Validator\Constraints\EmailValidator
    {
        return new EmailValidator(false);
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new Email());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new Email());

        $this->assertNoViolation();
    }

    public function testObjectEmptyStringIsValid(): void
    {
        $this->validator->validate(new EmptyEmailObject(), new Email());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException(\Symfony\Component\Validator\Exception\UnexpectedTypeException::class);
        $this->validator->validate(new \stdClass(), new Email());
    }

    /**
     * @dataProvider getValidEmails
     */
    public function testValidEmails(string $email): void
    {
        $this->validator->validate($email, new Email());

        $this->assertNoViolation();
    }

    public function getValidEmails(): array
    {
        return [
            ['fabien@symfony.com'],
            ['example@example.co.uk'],
            ['fabien_potencier@example.fr'],
        ];
    }

    /**
     * @dataProvider getInvalidEmails
     */
    public function testInvalidEmails(string $email): void
    {
        $constraint = new Email([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($email, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$email.'"')
            ->setCode(Email::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function getInvalidEmails(): array
    {
        return [
            ['example'],
            ['example@'],
            ['example@localhost'],
            ['foo@example.com bar'],
        ];
    }

    public function testStrict(): void
    {
        $constraint = new Email(['strict' => true]);

        $this->validator->validate('example@localhost', $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidEmailsForStrictChecks
     */
    public function testStrictWithInvalidEmails(string $email): void
    {
        $constraint = new Email([
            'message' => 'myMessage',
            'strict' => true,
        ]);

        $this->validator->validate($email, $constraint);

        $this
            ->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$email.'"')
            ->setCode(Email::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    /**
     * @see https://github.com/egulias/EmailValidator/blob/1.2.8/tests/egulias/Tests/EmailValidator/EmailValidatorTest.php
     */
    public function getInvalidEmailsForStrictChecks(): array
    {
        return [
            ['test@example.com test'],
            ['user  name@example.com'],
            ['user   name@example.com'],
            ['example.@example.co.uk'],
            ['example@example@example.co.uk'],
            ['(test_exampel@example.fr)'],
            ['example(example)example@example.co.uk'],
            ['.example@localhost'],
            ['ex\ample@localhost'],
            ['example@local\host'],
            ['example@localhost.'],
            ['user name@example.com'],
            ['username@ example . com'],
            ['example@(fake).com'],
            ['example@(fake.com'],
            ['username@example,com'],
            ['usern,ame@example.com'],
            ['user[na]me@example.com'],
            ['"""@iana.org'],
            ['"\"@iana.org'],
            ['"test"test@iana.org'],
            ['"test""test"@iana.org'],
            ['"test"."test"@iana.org'],
            ['"test".test@iana.org'],
            ['"test"'.\chr(0).'@iana.org'],
            ['"test\"@iana.org'],
            [\chr(226).'@iana.org'],
            ['test@'.\chr(226).'.org'],
            ['\r\ntest@iana.org'],
            ['\r\n test@iana.org'],
            ['\r\n \r\ntest@iana.org'],
            ['\r\n \r\ntest@iana.org'],
            ['\r\n \r\n test@iana.org'],
            ['test@iana.org \r\n'],
            ['test@iana.org \r\n '],
            ['test@iana.org \r\n \r\n'],
            ['test@iana.org \r\n\r\n'],
            ['test@iana.org  \r\n\r\n '],
            ['test@iana/icann.org'],
            ['test@foo;bar.com'],
            ['test;123@foobar.com'],
            ['test@example..com'],
            ['email.email@email."'],
            ['test@email>'],
            ['test@email<'],
            ['test@email{'],
            [str_repeat('x', 254).'@example.com'], //email with warnings
        ];
    }

    /**
     * @dataProvider getDnsChecks
     * @requires function Symfony\Bridge\PhpUnit\DnsMock::withMockedHosts
     */
    public function testDnsChecks(string $type, $violation): void
    {
        DnsMock::withMockedHosts(['example.com' => [['type' => $violation ? false : $type]]]);

        $constraint = new Email([
            'message' => 'myMessage',
            'MX' === $type ? 'checkMX' : 'checkHost' => true,
        ]);

        $this->validator->validate('foo@example.com', $constraint);

        if (!$violation) {
            $this->assertNoViolation();
        } else {
            $this->buildViolation('myMessage')
                ->setParameter('{{ value }}', '"foo@example.com"')
                ->setCode($violation)
                ->assertRaised();
        }
    }

    public function getDnsChecks(): array
    {
        return [
            ['MX', false],
            ['MX', Email::MX_CHECK_FAILED_ERROR],
            ['A', false],
            ['A', Email::HOST_CHECK_FAILED_ERROR],
            ['AAAA', false],
            ['AAAA', Email::HOST_CHECK_FAILED_ERROR],
        ];
    }

    /**
     * @requires function Symfony\Bridge\PhpUnit\DnsMock::withMockedHosts
     */
    public function testHostnameIsProperlyParsed(): void
    {
        DnsMock::withMockedHosts(['baz.com' => [['type' => 'MX']]]);

        $this->validator->validate(
            '"foo@bar"@baz.com',
            new Email(['checkMX' => true])
        );

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideCheckTypes
     */
    public function testEmptyHostIsNotValid(string $checkType, $violation): void
    {
        $this->validator->validate(
            'foo@bar.fr@',
            new Email([
                'message' => 'myMessage',
                $checkType => true,
            ])
        );

        $this
            ->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"foo@bar.fr@"')
            ->setCode($violation)
            ->assertRaised();
    }

    public function provideCheckTypes(): array
    {
        return [
            ['checkMX', Email::MX_CHECK_FAILED_ERROR],
            ['checkHost', Email::HOST_CHECK_FAILED_ERROR],
        ];
    }
}

class EmptyEmailObject
{
    public function __toString(): string
    {
        return '';
    }
}
