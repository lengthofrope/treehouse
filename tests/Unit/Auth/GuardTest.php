<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use LengthOfRope\TreeHouse\Auth\Guard;
use LengthOfRope\TreeHouse\Auth\SessionGuard;
use LengthOfRope\TreeHouse\Auth\UserProvider;
use LengthOfRope\TreeHouse\Auth\DatabaseUserProvider;
use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Http\Cookie;
use LengthOfRope\TreeHouse\Security\Hash;
use Tests\TestCase;

/**
 * Guard Tests
 *
 * @package Tests\Unit\Auth
 */
class GuardTest extends TestCase
{
    protected SessionGuard $guard;
    protected Session $session;
    protected Cookie $cookie;
    protected UserProvider $provider;
    protected Hash $hash;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->session = new Session();
        $this->cookie = new Cookie('test', '');
        $this->hash = new Hash();
        $this->provider = new DatabaseUserProvider($this->hash, [
            'table' => 'users',
            'connection' => [
                'driver' => 'sqlite',
                'database' => ':memory:'
            ]
        ]);
        
        $this->guard = new SessionGuard(
            $this->session,
            $this->cookie,
            $this->provider,
            $this->hash
        );
    }

    public function testGuardImplementsInterface(): void
    {
        $this->assertInstanceOf(Guard::class, $this->guard);
    }

    public function testGuestReturnsTrueWhenNotAuthenticated(): void
    {
        $this->assertTrue($this->guard->guest());
        $this->assertFalse($this->guard->check());
    }

    public function testUserReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull($this->guard->user());
        $this->assertNull($this->guard->id());
    }

    public function testGetProvider(): void
    {
        $this->assertSame($this->provider, $this->guard->getProvider());
    }

    public function testSetProvider(): void
    {
        $newProvider = new DatabaseUserProvider($this->hash, [
            'table' => 'admins',
            'connection' => [
                'driver' => 'sqlite',
                'database' => ':memory:'
            ]
        ]);
        
        $this->guard->setProvider($newProvider);
        $this->assertSame($newProvider, $this->guard->getProvider());
    }

    public function testGetName(): void
    {
        $name = $this->guard->getName();
        $this->assertIsString($name);
        $this->assertStringStartsWith('login_', $name);
    }

    public function testGetRememberName(): void
    {
        $name = $this->guard->getRememberName();
        $this->assertIsString($name);
        $this->assertStringStartsWith('remember_', $name);
    }

    public function testViaRememberReturnsFalseInitially(): void
    {
        $this->assertFalse($this->guard->viaRemember());
    }
}