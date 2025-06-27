<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Auth\Guard;
use LengthOfRope\TreeHouse\Auth\SessionGuard;
use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Http\Cookie;
use LengthOfRope\TreeHouse\Security\Hash;
use Tests\TestCase;
use InvalidArgumentException;

/**
 * AuthManager Tests
 *
 * @package Tests\Unit\Auth
 */
class AuthManagerTest extends TestCase
{
    protected AuthManager $authManager;
    protected array $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->config = [
            'default' => 'web',
            'guards' => [
                'web' => [
                    'driver' => 'session',
                    'provider' => 'users',
                ],
            ],
            'providers' => [
                'default' => 'users',
                'users' => [
                    'driver' => 'database',
                    'table' => 'users',
                    'connection' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:'
                    ]
                ],
            ],
        ];
        
        $this->authManager = new AuthManager(
            $this->config,
            new Session(),
            new Cookie('test', ''),
            new Hash()
        );
        
        // Create users table for testing
        $this->createUsersTable();
    }
    
    protected function createUsersTable(): void
    {
        $provider = $this->authManager->createUserProvider('users');
        $connection = $provider->getConnection();
        
        $connection->statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            remember_token VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }

    public function testGetDefaultDriver(): void
    {
        $this->assertEquals('web', $this->authManager->getDefaultDriver());
    }

    public function testSetDefaultDriver(): void
    {
        $this->authManager->setDefaultDriver('api');
        $this->assertEquals('api', $this->authManager->getDefaultDriver());
    }

    public function testGuardReturnsGuardInstance(): void
    {
        $guard = $this->authManager->guard();
        $this->assertInstanceOf(Guard::class, $guard);
        $this->assertInstanceOf(SessionGuard::class, $guard);
    }

    public function testGuardWithSpecificName(): void
    {
        $guard = $this->authManager->guard('web');
        $this->assertInstanceOf(Guard::class, $guard);
    }

    public function testGuardThrowsExceptionForUndefinedGuard(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Auth guard [nonexistent] is not defined.');
        
        $this->authManager->guard('nonexistent');
    }

    public function testGuestReturnsTrueWhenNotAuthenticated(): void
    {
        $this->assertTrue($this->authManager->guest());
        $this->assertFalse($this->authManager->check());
    }

    public function testUserReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull($this->authManager->user());
        $this->assertNull($this->authManager->id());
    }

    public function testValidateReturnsFalseWithInvalidCredentials(): void
    {
        $result = $this->authManager->validate([
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ]);
        
        $this->assertFalse($result);
    }

    public function testAttemptReturnsFalseWithInvalidCredentials(): void
    {
        $result = $this->authManager->attempt([
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ]);
        
        $this->assertFalse($result);
    }

    public function testViaRememberReturnsFalseInitially(): void
    {
        $this->assertFalse($this->authManager->viaRemember());
    }

    public function testMagicCallDelegatesToDefaultGuard(): void
    {
        // Test that methods are properly delegated to the default guard
        $this->assertTrue($this->authManager->guest());
        $this->assertFalse($this->authManager->check());
    }

    public function testCreateUserProviderThrowsExceptionForUndefinedProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authentication user provider is not defined.');
        
        $this->authManager->createUserProvider('nonexistent');
    }
}