<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests\TokenStorage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SessionTokenStorageTest extends TestCase
{
    final const SESSION_NAMESPACE = 'foobar';

    private \Symfony\Component\HttpFoundation\Session\Session $session;

    private \Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage $storage;

    protected function setUp()
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->storage = new SessionTokenStorage($this->session, self::SESSION_NAMESPACE);
    }

    public function testStoreTokenInNotStartedSessionStartsTheSession(): void
    {
        $this->storage->setToken('token_id', 'TOKEN');

        $this->assertTrue($this->session->isStarted());
    }

    public function testStoreTokenInActiveSession(): void
    {
        $this->session->start();
        $this->storage->setToken('token_id', 'TOKEN');

        $this->assertSame('TOKEN', $this->session->get(self::SESSION_NAMESPACE.'/token_id'));
    }

    public function testCheckTokenInClosedSession(): void
    {
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        $this->assertTrue($this->storage->hasToken('token_id'));
        $this->assertTrue($this->session->isStarted());
    }

    public function testCheckTokenInActiveSession(): void
    {
        $this->session->start();
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        $this->assertTrue($this->storage->hasToken('token_id'));
    }

    public function testGetExistingTokenFromClosedSession(): void
    {
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        $this->assertSame('RESULT', $this->storage->getToken('token_id'));
        $this->assertTrue($this->session->isStarted());
    }

    public function testGetExistingTokenFromActiveSession(): void
    {
        $this->session->start();
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        $this->assertSame('RESULT', $this->storage->getToken('token_id'));
    }

    public function testGetNonExistingTokenFromClosedSession(): void
    {
        $this->expectException(\Symfony\Component\Security\Csrf\Exception\TokenNotFoundException::class);
        $this->storage->getToken('token_id');
    }

    public function testGetNonExistingTokenFromActiveSession(): void
    {
        $this->expectException(\Symfony\Component\Security\Csrf\Exception\TokenNotFoundException::class);
        $this->session->start();
        $this->storage->getToken('token_id');
    }

    public function testRemoveNonExistingTokenFromClosedSession(): void
    {
        $this->assertNull($this->storage->removeToken('token_id'));
    }

    public function testRemoveNonExistingTokenFromActiveSession(): void
    {
        $this->session->start();

        $this->assertNull($this->storage->removeToken('token_id'));
    }

    public function testRemoveExistingTokenFromClosedSession(): void
    {
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'TOKEN');

        $this->assertSame('TOKEN', $this->storage->removeToken('token_id'));
    }

    public function testRemoveExistingTokenFromActiveSession(): void
    {
        $this->session->start();
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'TOKEN');

        $this->assertSame('TOKEN', $this->storage->removeToken('token_id'));
    }

    public function testClearRemovesAllTokensFromTheConfiguredNamespace(): void
    {
        $this->storage->setToken('foo', 'bar');
        $this->storage->clear();

        $this->assertFalse($this->storage->hasToken('foo'));
        $this->assertFalse($this->session->has(self::SESSION_NAMESPACE.'/foo'));
    }

    public function testClearDoesNotRemoveSessionValuesFromOtherNamespaces(): void
    {
        $this->session->set('foo/bar', 'baz');
        $this->storage->clear();

        $this->assertTrue($this->session->has('foo/bar'));
        $this->assertSame('baz', $this->session->get('foo/bar'));
    }

    public function testClearDoesNotRemoveNonNamespacedSessionValues(): void
    {
        $this->session->set('foo', 'baz');
        $this->storage->clear();

        $this->assertTrue($this->session->has('foo'));
        $this->assertSame('baz', $this->session->get('foo'));
    }
}
