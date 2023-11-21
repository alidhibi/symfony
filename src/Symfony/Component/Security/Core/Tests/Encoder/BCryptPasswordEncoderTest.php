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
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;

/**
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 */
class BCryptPasswordEncoderTest extends TestCase
{
    final const PASSWORD = 'password';

    final const VALID_COST = '04';

    public function testCostBelowRange(): void
    {
        $this->expectException('InvalidArgumentException');
        new BCryptPasswordEncoder(3);
    }

    public function testCostAboveRange(): void
    {
        $this->expectException('InvalidArgumentException');
        new BCryptPasswordEncoder(32);
    }

    /**
     * @dataProvider validRangeData
     */
    public function testCostInRange($cost): void
    {
        $this->assertInstanceOf(\Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder::class, new BCryptPasswordEncoder($cost));
    }

    /**
     * @return mixed[]
     */
    public function validRangeData(): array
    {
        $costs = range(4, 31);
        array_walk($costs, static function (&$cost) : void {
            $cost = [$cost];
        });

        return $costs;
    }

    public function testResultLength(): void
    {
        $encoder = new BCryptPasswordEncoder(self::VALID_COST);
        $result = $encoder->encodePassword(self::PASSWORD, null);
        $this->assertEquals(60, \strlen($result));
    }

    public function testValidation(): void
    {
        $encoder = new BCryptPasswordEncoder(self::VALID_COST);
        $result = $encoder->encodePassword(self::PASSWORD, null);
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD, null));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword', null));
    }

    public function testEncodePasswordLength(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\BadCredentialsException::class);
        $encoder = new BCryptPasswordEncoder(self::VALID_COST);

        $encoder->encodePassword(str_repeat('a', 73), 'salt');
    }

    public function testCheckPasswordLength(): void
    {
        $encoder = new BCryptPasswordEncoder(self::VALID_COST);
        $result = $encoder->encodePassword(str_repeat('a', 72), null);

        $this->assertFalse($encoder->isPasswordValid($result, str_repeat('a', 73), 'salt'));
        $this->assertTrue($encoder->isPasswordValid($result, str_repeat('a', 72), 'salt'));
    }
}
