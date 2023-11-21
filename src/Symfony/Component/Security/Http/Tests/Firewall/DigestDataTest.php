<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Firewall\DigestData;

/**
 * @group legacy
 */
class DigestDataTest extends TestCase
{
    public function testGetResponse(): void
    {
        $digestAuth = new DigestData(
            'username="user", realm="Welcome, robot!", '.
            'nonce="MTM0NzMyMTgyMy42NzkzOmRlZjM4NmIzOGNjMjE0OWJiNDU0MDAxNzJmYmM1MmZl", '.
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", '.
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $this->assertEquals('b52938fc9e6d7c01be7702ece9031b42', $digestAuth->getResponse());
    }

    public function testGetUsername(): void
    {
        $digestAuth = new DigestData(
            'username="user", realm="Welcome, robot!", '.
            'nonce="MTM0NzMyMTgyMy42NzkzOmRlZjM4NmIzOGNjMjE0OWJiNDU0MDAxNzJmYmM1MmZl", '.
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", '.
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $this->assertEquals('user', $digestAuth->getUsername());
    }

    public function testGetUsernameWithQuote(): void
    {
        $digestAuth = new DigestData(
            'username="\"user\"", realm="Welcome, robot!", '.
            'nonce="MTM0NzMyMTgyMy42NzkzOmRlZjM4NmIzOGNjMjE0OWJiNDU0MDAxNzJmYmM1MmZl", '.
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", '.
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $this->assertEquals('"user"', $digestAuth->getUsername());
    }

    public function testGetUsernameWithQuoteAndEscape(): void
    {
        $digestAuth = new DigestData(
            'username="\"u\\\\\"ser\"", realm="Welcome, robot!", '.
            'nonce="MTM0NzMyMTgyMy42NzkzOmRlZjM4NmIzOGNjMjE0OWJiNDU0MDAxNzJmYmM1MmZl", '.
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", '.
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $this->assertEquals('"u\\"ser"', $digestAuth->getUsername());
    }

    public function testGetUsernameWithSingleQuote(): void
    {
        $digestAuth = new DigestData(
            'username="\"u\'ser\"", realm="Welcome, robot!", '.
            'nonce="MTM0NzMyMTgyMy42NzkzOmRlZjM4NmIzOGNjMjE0OWJiNDU0MDAxNzJmYmM1MmZl", '.
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", '.
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $this->assertEquals('"u\'ser"', $digestAuth->getUsername());
    }

    public function testGetUsernameWithSingleQuoteAndEscape(): void
    {
        $digestAuth = new DigestData(
            'username="\"u\\\'ser\"", realm="Welcome, robot!", '.
            'nonce="MTM0NzMyMTgyMy42NzkzOmRlZjM4NmIzOGNjMjE0OWJiNDU0MDAxNzJmYmM1MmZl", '.
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", '.
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $this->assertEquals('"u\\\'ser"', $digestAuth->getUsername());
    }

    public function testGetUsernameWithEscape(): void
    {
        $digestAuth = new DigestData(
            'username="\"u\\ser\"", realm="Welcome, robot!", '.
            'nonce="MTM0NzMyMTgyMy42NzkzOmRlZjM4NmIzOGNjMjE0OWJiNDU0MDAxNzJmYmM1MmZl", '.
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", '.
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $this->assertEquals('"u\\ser"', $digestAuth->getUsername());
    }

    /**
     * @group time-sensitive
     */
    public function testValidateAndDecode(): void
    {
        $time = microtime(true);
        $key = 'ThisIsAKey';
        $nonce = base64_encode($time.':'.md5($time.':'.$key));

        $digestAuth = new DigestData(
            'username="user", realm="Welcome, robot!", nonce="'.$nonce.'", '.
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", '.
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $digestAuth->validateAndDecode($key, 'Welcome, robot!');

        sleep(1);

        $this->assertTrue($digestAuth->isNonceExpired());
    }

    public function testCalculateServerDigest(): void
    {
        $this->calculateServerDigest('user', 'Welcome, robot!', 'pass,word=password', 'ThisIsAKey', '00000001', 'MDIwODkz', 'auth', 'GET', '/path/info?p1=5&p2=5');
    }

    public function testCalculateServerDigestWithQuote(): void
    {
        $this->calculateServerDigest('\"user\"', 'Welcome, \"robot\"!', 'pass,word=password', 'ThisIsAKey', '00000001', 'MDIwODkz', 'auth', 'GET', '/path/info?p1=5&p2=5');
    }

    public function testCalculateServerDigestWithQuoteAndEscape(): void
    {
        $this->calculateServerDigest('\"u\\\\\"ser\"', 'Welcome, \"robot\"!', 'pass,word=password', 'ThisIsAKey', '00000001', 'MDIwODkz', 'auth', 'GET', '/path/info?p1=5&p2=5');
    }

    public function testCalculateServerDigestEscape(): void
    {
        $this->calculateServerDigest('\"u\\ser\"', 'Welcome, \"robot\"!', 'pass,word=password', 'ThisIsAKey', '00000001', 'MDIwODkz', 'auth', 'GET', '/path/info?p1=5&p2=5');
        $this->calculateServerDigest('\"u\\ser\\\\\"', 'Welcome, \"robot\"!', 'pass,word=password', 'ThisIsAKey', '00000001', 'MDIwODkz', 'auth', 'GET', '/path/info?p1=5&p2=5');
    }

    public function testIsNonceExpired(): void
    {
        $time = microtime(true) + 10;
        $key = 'ThisIsAKey';
        $nonce = base64_encode($time.':'.md5($time.':'.$key));

        $digestAuth = new DigestData(
            'username="user", realm="Welcome, robot!", nonce="'.$nonce.'", '.
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", '.
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $digestAuth->validateAndDecode($key, 'Welcome, robot!');

        $this->assertFalse($digestAuth->isNonceExpired());
    }

    protected function setUp()
    {
        class_exists(\Symfony\Component\Security\Http\Firewall\DigestAuthenticationListener::class, true);
    }

    private function calculateServerDigest(string $username, string $realm, string $password, string $key, string $nc, string $cnonce, string $qop, string $method, string $uri): void
    {
        $time = microtime(true);
        $nonce = base64_encode($time.':'.md5($time.':'.$key));

        $response = md5(
            md5($username.':'.$realm.':'.$password).':'.$nonce.':'.$nc.':'.$cnonce.':'.$qop.':'.md5($method.':'.$uri)
        );

        $digest = sprintf('username="%s", realm="%s", nonce="%s", uri="%s", cnonce="%s", nc=%s, qop="%s", response="%s"',
            $username, $realm, $nonce, $uri, $cnonce, $nc, $qop, $response
        );

        $digestAuth = new DigestData($digest);

        $this->assertEquals($digestAuth->getResponse(), $digestAuth->calculateServerDigest($password, $method));
    }
}
