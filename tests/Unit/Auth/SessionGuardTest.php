<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Auth\SessionGuard;
use LengthOfRope\TreeHouse\Auth\UserProvider;
use LengthOfRope\TreeHouse\Auth\GenericUser;
use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Http\Cookie;
use LengthOfRope\TreeHouse\Security\Hash;
use InvalidArgumentException;

/**
 * Session Guard Tests
 *
 * Tests for the session-based authentication guard.
 * Focuses on core session functionality.
 */
class SessionGuardTest extends TestCase
{
    protected SessionGuard $guard;
    protected TestUserProvider $provider;
    protected TestSession $session;
    protected Cookie $cookie;
    protected Hash $hash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new TestSession();
        $this->cookie = new Cookie('test');
        $this->provider = new TestUserProvider();
        $this->hash = new Hash();

        $this->guard = new SessionGuard(
            $this->session,
            $this->cookie,
            $this->provider,
            $this->hash
        );
    }

    public function testCheckReturnsTrueWhenUserIsAuthenticated(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com']);
        $this->guard->setUser($user);

        $this->assertTrue($this->guard->check());
    }

    public function testCheckReturnsFalseWhenUserIsNotAuthenticated(): void
    {
        $this->assertFalse($this->guard->check());
    }

    public function testGuestReturnsTrueWhenUserIsNotAuthenticated(): void
    {
        $this->assertTrue($this->guard->guest());
    }

    public function testGuestReturnsFalseWhenUserIsAuthenticated(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com']);
        $this->guard->setUser($user);

        $this->assertFalse($this->guard->guest());
    }

    public function testUserReturnsCurrentUser(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com']);
        $this->guard->setUser($user);

        $retrievedUser = $this->guard->user();
        $this->assertSame($user, $retrievedUser);
    }

    public function testUserReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull($this->guard->user());
    }

    public function testUserRetrievesFromSession(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com']);
        $this->provider->addUser($user);
        $this->session->set($this->guard->getName(), 1);

        $retrievedUser = $this->guard->user();
        $this->assertEquals(1, $retrievedUser->getAuthIdentifier());
        $this->assertEquals('test@example.com', $retrievedUser->email);
    }

    public function testIdReturnsCurrentUserId(): void
    {
        $user = new GenericUser(['id' => 42, 'email' => 'test@example.com']);
        $this->guard->setUser($user);

        $this->assertEquals(42, $this->guard->id());
    }

    public function testIdReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull($this->guard->id());
    }

    public function testValidateWithCorrectCredentials(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com', 'password' => 'hashed_password']);
        $this->provider->addUser($user);
        $this->provider->setValidCredentials(['email' => 'test@example.com', 'password' => 'password123']);

        $isValid = $this->guard->validate(['email' => 'test@example.com', 'password' => 'password123']);
        $this->assertTrue($isValid);
    }

    public function testValidateWithIncorrectCredentials(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com', 'password' => 'hashed_password']);
        $this->provider->addUser($user);

        $isValid = $this->guard->validate(['email' => 'test@example.com', 'password' => 'wrong_password']);
        $this->assertFalse($isValid);
    }

    public function testAttemptWithCorrectCredentials(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com', 'password' => 'hashed_password']);
        $this->provider->addUser($user);
        $this->provider->setValidCredentials(['email' => 'test@example.com', 'password' => 'password123']);

        $success = $this->guard->attempt(['email' => 'test@example.com', 'password' => 'password123']);
        
        $this->assertTrue($success);
        $this->assertTrue($this->guard->check());
        $this->assertEquals(1, $this->guard->id());
    }

    public function testAttemptWithIncorrectCredentials(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com', 'password' => 'hashed_password']);
        $this->provider->addUser($user);

        $success = $this->guard->attempt(['email' => 'test@example.com', 'password' => 'wrong_password']);
        
        $this->assertFalse($success);
        $this->assertFalse($this->guard->check());
    }

    public function testOnce(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com']);
        
        $success = $this->guard->once($user);
        
        $this->assertTrue($success);
        $this->assertTrue($this->guard->check());
        
        // Verify no session was set
        $this->assertNull($this->session->get($this->guard->getName()));
    }

    public function testOnceWithNullUser(): void
    {
        $success = $this->guard->once(null);
        
        $this->assertFalse($success);
        $this->assertFalse($this->guard->check());
    }

    public function testLogin(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com']);
        
        $this->guard->login($user);
        
        $this->assertTrue($this->guard->check());
        $this->assertEquals(1, $this->session->get($this->guard->getName()));
        $this->assertFalse($this->guard->viaRemember());
    }

    public function testLoginUsingId(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com']);
        $this->provider->addUser($user);
        
        $result = $this->guard->loginUsingId(1);
        
        $this->assertSame($user, $result);
        $this->assertTrue($this->guard->check());
        $this->assertEquals(1, $this->guard->id());
    }

    public function testLoginUsingIdWithNonexistentUser(): void
    {
        $result = $this->guard->loginUsingId(999);
        
        $this->assertFalse($result);
        $this->assertFalse($this->guard->check());
    }

    public function testOnceUsingId(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com']);
        $this->provider->addUser($user);
        
        $result = $this->guard->onceUsingId(1);
        
        $this->assertSame($user, $result);
        $this->assertTrue($this->guard->check());
        
        // Verify no session was set
        $this->assertNull($this->session->get($this->guard->getName()));
    }

    public function testOnceUsingIdWithNonexistentUser(): void
    {
        $result = $this->guard->onceUsingId(999);
        
        $this->assertFalse($result);
        $this->assertFalse($this->guard->check());
    }

    public function testLogout(): void
    {
        $user = new GenericUser(['id' => 1, 'email' => 'test@example.com']);
        $this->guard->login($user);
        
        $this->guard->logout();
        
        $this->assertFalse($this->guard->check());
        $this->assertNull($this->session->get($this->guard->getName()));
        $this->assertFalse($this->guard->viaRemember());
    }

    public function testLogoutOtherDevicesWhenNotAuthenticated(): void
    {
        $success = $this->guard->logoutOtherDevices('password123');
        
        $this->assertFalse($success);
    }

    public function testGetAndSetProvider(): void
    {
        $newProvider = new TestUserProvider();
        
        $this->assertSame($this->provider, $this->guard->getProvider());
        
        $this->guard->setProvider($newProvider);
        $this->assertSame($newProvider, $this->guard->getProvider());
    }

    public function testGetSessionKeyName(): void
    {
        $name = $this->guard->getName();
        $this->assertStringStartsWith('login_', $name);
        $this->assertEquals(32 + 6, strlen($name)); // 'login_' + 32 char MD5 hash
    }

    public function testGetRememberCookieName(): void
    {
        $name = $this->guard->getRememberName();
        $this->assertStringStartsWith('remember_', $name);
        $this->assertEquals(32 + 9, strlen($name)); // 'remember_' + 32 char MD5 hash
    }

    public function testGetUserIdThrowsExceptionForInvalidUser(): void
    {
        $this->expectException(\LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException::class);
        $this->expectExceptionMessage('User must have an ID or implement getAuthIdentifier method');
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->guard);
        $method = $reflection->getMethod('getUserId');
        $method->setAccessible(true);
        
        $method->invoke($this->guard, ['name' => 'No ID']);
    }

    public function testViaRememberReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->guard->viaRemember());
    }
}

/**
 * Test Session implementation
 */
class TestSession extends Session
{
    private array $data = [];
    private bool $regenerated = false;

    public function __construct()
    {
        // Don't call parent constructor to avoid actual session start
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(bool $destroy = false): bool
    {
        $this->regenerated = true;
        return true;
    }

    public function wasRegenerated(): bool
    {
        return $this->regenerated;
    }
}

/**
 * Test UserProvider implementation
 */
class TestUserProvider implements UserProvider
{
    private array $users = [];
    private array $rememberTokens = [];
    private array $validCredentials = [];

    public function addUser(GenericUser $user): void
    {
        $this->users[$user->getAuthIdentifier()] = $user;
    }

    public function addRememberToken(mixed $userId, string $token): void
    {
        $this->rememberTokens[$userId] = $token;
    }

    public function setValidCredentials(array $credentials): void
    {
        $this->validCredentials = $credentials;
    }

    public function retrieveById(mixed $identifier): mixed
    {
        return $this->users[$identifier] ?? null;
    }

    public function retrieveByToken(mixed $identifier, string $token): mixed
    {
        if (isset($this->rememberTokens[$identifier]) && $this->rememberTokens[$identifier] === $token) {
            return $this->users[$identifier] ?? null;
        }
        return null;
    }

    public function updateRememberToken(mixed $user, string $token): void
    {
        $id = $user->getAuthIdentifier();
        $this->rememberTokens[$id] = $token;
        if (isset($this->users[$id])) {
            $this->users[$id]->remember_token = $token;
        }
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        foreach ($this->users as $user) {
            if (isset($credentials['email']) && $user->email === $credentials['email']) {
                return $user;
            }
        }
        return null;
    }

    public function validateCredentials(mixed $user, array $credentials): bool
    {
        return array_intersect_assoc($credentials, $this->validCredentials) === $credentials;
    }

    public function rehashPasswordIfRequired(mixed $user, array $credentials, bool $force = false): void
    {
        // Mock implementation - no-op for testing
    }
}