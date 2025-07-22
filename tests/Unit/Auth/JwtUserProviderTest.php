<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use LengthOfRope\TreeHouse\Auth\JwtUserProvider;
use LengthOfRope\TreeHouse\Auth\UserProvider;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager;
use LengthOfRope\TreeHouse\Security\Hash;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * JwtUserProvider Test Suite
 *
 * Comprehensive tests for JWT user provider including:
 * - Stateless and hybrid mode operations
 * - User resolution from JWT tokens
 * - Credential validation
 * - Integration with fallback providers
 * - Error handling and edge cases
 *
 * @package Tests\Unit\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtUserProviderTest extends TestCase
{
    private JwtUserProvider $provider;
    private JwtConfig $jwtConfig;
    private MockObject|Hash $mockHash;
    private MockObject|UserProvider $mockFallbackProvider;
    private TokenGenerator $tokenGenerator;
    private array $testUser;
    private string $validToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create JWT config for testing
        $this->jwtConfig = new JwtConfig([
            'secret' => 'test-secret-key-32-characters-long',
            'algorithm' => 'HS256',
            'ttl' => 900,
            'issuer' => 'test-app',
            'audience' => 'test-users',
        ]);

        // Create mock hash
        $this->mockHash = $this->createMock(Hash::class);

        // Create mock fallback provider
        $this->mockFallbackProvider = $this->createMock(UserProvider::class);

        // Test user data
        $this->testUser = [
            'id' => 1,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'password' => password_hash('password', PASSWORD_DEFAULT)
        ];

        // Generate a valid token for testing
        $this->tokenGenerator = new TokenGenerator($this->jwtConfig);
        $this->validToken = $this->tokenGenerator->generateAuthToken(1, [
            'user_data' => $this->testUser
        ]);

        // Create provider instance in stateless mode by default
        $this->provider = new JwtUserProvider(
            $this->jwtConfig,
            $this->mockHash,
            ['mode' => 'stateless'],
            $this->mockFallbackProvider
        );
    }

    public function testImplementsUserProviderInterface(): void
    {
        $this->assertInstanceOf(UserProvider::class, $this->provider);
    }

    public function testIsStatelessModeByDefault(): void
    {
        $this->assertTrue($this->provider->isStatelessMode());
        $this->assertFalse($this->provider->isHybridMode());
    }

    public function testHybridModeConfiguration(): void
    {
        $provider = new JwtUserProvider(
            $this->jwtConfig,
            $this->mockHash,
            ['mode' => 'hybrid']
        );

        $this->assertTrue($provider->isHybridMode());
        $this->assertFalse($provider->isStatelessMode());
    }

    public function testRetrieveByIdWithJwtTokenInStatelessMode(): void
    {
        $user = $this->provider->retrieveById($this->validToken);
        
        $this->assertIsArray($user);
        $this->assertEquals(1, $user['id']);
        $this->assertArrayHasKey('jwt_claims', $user);
    }

    public function testRetrieveByIdWithInvalidTokenReturnsNull(): void
    {
        $user = $this->provider->retrieveById('invalid-token');
        $this->assertNull($user);
    }

    public function testRetrieveByIdWithNonTokenInStatelessMode(): void
    {
        $user = $this->provider->retrieveById(123);
        $this->assertNull($user);
    }

    public function testRetrieveByIdInHybridModeWithUserId(): void
    {
        $provider = new JwtUserProvider(
            $this->jwtConfig,
            $this->mockHash,
            ['mode' => 'hybrid'],
            $this->mockFallbackProvider
        );

        $this->mockFallbackProvider->method('retrieveById')
            ->with(123)
            ->willReturn($this->testUser);

        $user = $provider->retrieveById(123);
        $this->assertEquals($this->testUser, $user);
    }

    public function testRetrieveByIdInHybridModeWithJwtToken(): void
    {
        $provider = new JwtUserProvider(
            $this->jwtConfig,
            $this->mockHash,
            ['mode' => 'hybrid'],
            $this->mockFallbackProvider
        );

        $this->mockFallbackProvider->method('retrieveById')
            ->with(1)
            ->willReturn($this->testUser);

        $user = $provider->retrieveById($this->validToken);
        
        $this->assertEquals($this->testUser['id'], $user['id']);
        $this->assertArrayHasKey('jwt_claims', $user);
    }

    public function testRetrieveByTokenDelegatesToFallback(): void
    {
        $this->mockFallbackProvider->method('retrieveByToken')
            ->with(1, 'remember-token')
            ->willReturn($this->testUser);

        $user = $this->provider->retrieveByToken(1, 'remember-token');
        $this->assertEquals($this->testUser, $user);
    }

    public function testRetrieveByTokenWithoutFallbackReturnsNull(): void
    {
        $provider = new JwtUserProvider($this->jwtConfig, $this->mockHash);
        $user = $provider->retrieveByToken(1, 'remember-token');
        $this->assertNull($user);
    }

    public function testUpdateRememberTokenDelegatesToFallback(): void
    {
        $this->mockFallbackProvider->expects($this->once())
            ->method('updateRememberToken')
            ->with($this->testUser, 'new-token');

        $this->provider->updateRememberToken($this->testUser, 'new-token');
    }

    public function testUpdateRememberTokenWithoutFallbackDoesNothing(): void
    {
        $provider = new JwtUserProvider($this->jwtConfig, $this->mockHash);
        
        // Should not throw an exception
        $provider->updateRememberToken($this->testUser, 'new-token');
        $this->assertTrue(true); // Assertion to prevent risky test warning
    }

    public function testRetrieveByCredentialsWithJwtToken(): void
    {
        $credentials = ['token' => $this->validToken];
        $user = $this->provider->retrieveByCredentials($credentials);
        
        $this->assertIsArray($user);
        $this->assertEquals(1, $user['id']);
    }

    public function testRetrieveByCredentialsWithEmailDelegatesToFallback(): void
    {
        $credentials = ['email' => 'test@example.com', 'password' => 'password'];

        $this->mockFallbackProvider->method('retrieveByCredentials')
            ->with($credentials)
            ->willReturn($this->testUser);

        $user = $this->provider->retrieveByCredentials($credentials);
        $this->assertEquals($this->testUser, $user);
    }

    public function testRetrieveByCredentialsWithoutTokenOrEmailReturnsNull(): void
    {
        $credentials = ['username' => 'test'];
        $user = $this->provider->retrieveByCredentials($credentials);
        $this->assertNull($user);
    }

    public function testValidateCredentialsWithJwtToken(): void
    {
        $credentials = ['token' => $this->validToken];
        $user = ['id' => 1, 'email' => 'test@example.com'];

        $isValid = $this->provider->validateCredentials($user, $credentials);
        $this->assertTrue($isValid);
    }

    public function testValidateCredentialsWithInvalidJwtToken(): void
    {
        $credentials = ['token' => 'invalid-token'];
        $user = ['id' => 1, 'email' => 'test@example.com'];

        $isValid = $this->provider->validateCredentials($user, $credentials);
        $this->assertFalse($isValid);
    }

    public function testValidateCredentialsWithMismatchedUserId(): void
    {
        $credentials = ['token' => $this->validToken];
        $user = ['id' => 999, 'email' => 'test@example.com']; // Different ID

        $isValid = $this->provider->validateCredentials($user, $credentials);
        $this->assertFalse($isValid);
    }

    public function testValidateCredentialsWithPasswordDelegatesToFallback(): void
    {
        $credentials = ['password' => 'password'];

        $this->mockFallbackProvider->method('validateCredentials')
            ->with($this->testUser, $credentials)
            ->willReturn(true);

        $isValid = $this->provider->validateCredentials($this->testUser, $credentials);
        $this->assertTrue($isValid);
    }

    public function testValidateCredentialsWithPasswordDirectly(): void
    {
        $provider = new JwtUserProvider($this->jwtConfig, $this->mockHash);
        $credentials = ['password' => 'password'];
        $user = ['password' => 'hashed-password'];

        $this->mockHash->method('check')
            ->with('password', 'hashed-password')
            ->willReturn(true);

        $isValid = $provider->validateCredentials($user, $credentials);
        $this->assertTrue($isValid);
    }

    public function testValidateCredentialsWithPasswordButNoUserPassword(): void
    {
        $provider = new JwtUserProvider($this->jwtConfig, $this->mockHash);
        $credentials = ['password' => 'password'];
        $user = ['email' => 'test@example.com']; // No password field

        $isValid = $provider->validateCredentials($user, $credentials);
        $this->assertFalse($isValid);
    }

    public function testValidateCredentialsWithNoPasswordOrToken(): void
    {
        $credentials = ['email' => 'test@example.com'];
        $isValid = $this->provider->validateCredentials($this->testUser, $credentials);
        $this->assertFalse($isValid);
    }

    public function testRehashPasswordIfRequiredDelegatesToFallback(): void
    {
        $credentials = ['password' => 'password'];

        $this->mockFallbackProvider->expects($this->once())
            ->method('rehashPasswordIfRequired')
            ->with($this->testUser, $credentials, false);

        $this->provider->rehashPasswordIfRequired($this->testUser, $credentials);
    }

    public function testRehashPasswordIfRequiredWithoutFallbackDoesNothing(): void
    {
        $provider = new JwtUserProvider($this->jwtConfig, $this->mockHash);
        $credentials = ['password' => 'password'];
        
        // Should not throw an exception
        $provider->rehashPasswordIfRequired($this->testUser, $credentials);
        $this->assertTrue(true); // Assertion to prevent risky test warning
    }

    public function testSetFallbackProvider(): void
    {
        $newProvider = $this->createMock(UserProvider::class);
        $this->provider->setFallbackProvider($newProvider);
        $this->assertSame($newProvider, $this->provider->getFallbackProvider());
    }

    public function testGetFallbackProvider(): void
    {
        $this->assertSame($this->mockFallbackProvider, $this->provider->getFallbackProvider());
    }

    public function testCreateUserFromClaims(): void
    {
        $claims = new ClaimsManager([
            'sub' => '123',
            'user_data' => [
                'id' => 123,
                'email' => 'test@example.com',
                'name' => 'Test User'
            ],
            'role' => 'admin'
        ]);

        $user = $this->provider->createUserFromClaims($claims);
        
        $this->assertIsArray($user);
        $this->assertEquals(123, $user['id']);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertEquals('Test User', $user['name']);
    }

    public function testCreateUserFromClaimsWithoutUserData(): void
    {
        $claims = new ClaimsManager([
            'sub' => '456',
            'role' => 'user',
            'permissions' => ['read']
        ]);

        $user = $this->provider->createUserFromClaims($claims);
        
        $this->assertIsArray($user);
        $this->assertEquals('456', $user['id']);
        $this->assertEquals('user', $user['role']);
        $this->assertEquals(['read'], $user['permissions']);
        $this->assertArrayHasKey('jwt_claims', $user);
    }

    public function testCreateUserFromClaimsWithoutSubject(): void
    {
        $claims = new ClaimsManager([
            'role' => 'user'
        ]);

        $user = $this->provider->createUserFromClaims($claims);
        $this->assertNull($user);
    }

    public function testCreateUserFromClaimsWithMissingRequiredFields(): void
    {
        $provider = new JwtUserProvider(
            $this->jwtConfig,
            $this->mockHash,
            ['required_user_fields' => ['id', 'email']]
        );

        $claims = new ClaimsManager([
            'sub' => '123',
            'user_data' => [
                'id' => 123,
                // Missing email
                'name' => 'Test User'
            ]
        ]);

        $user = $provider->createUserFromClaims($claims);
        $this->assertNull($user);
    }

    public function testIsJwtTokenDetection(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('isJwtToken');
        $method->setAccessible(true);

        // Valid JWT format
        $this->assertTrue($method->invoke($this->provider, 'header.payload.signature'));
        
        // Invalid formats
        $this->assertFalse($method->invoke($this->provider, 'not-a-jwt'));
        $this->assertFalse($method->invoke($this->provider, 'header.payload'));
        $this->assertFalse($method->invoke($this->provider, 'header..signature'));
        $this->assertFalse($method->invoke($this->provider, 123));
        $this->assertFalse($method->invoke($this->provider, null));
    }

    public function testGetUserIdFromArrayUser(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUserId');
        $method->setAccessible(true);

        $user = ['id' => 123];
        $this->assertEquals(123, $method->invoke($this->provider, $user));

        $userWithoutId = ['email' => 'test@example.com'];
        $this->assertNull($method->invoke($this->provider, $userWithoutId));
    }

    public function testGetUserIdFromObjectUser(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUserId');
        $method->setAccessible(true);

        $user = new class {
            public int $id = 456;
            public function getAuthIdentifier(): int {
                return $this->id;
            }
        };

        $this->assertEquals(456, $method->invoke($this->provider, $user));

        $userWithoutMethod = new class {
            public int $id = 789;
        };

        $this->assertEquals(789, $method->invoke($this->provider, $userWithoutMethod));

        $userWithoutId = new class {};
        $this->assertNull($method->invoke($this->provider, $userWithoutId));
    }

    public function testGetUserPasswordFromArrayUser(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUserPassword');
        $method->setAccessible(true);

        $user = ['password' => 'hashed-password'];
        $this->assertEquals('hashed-password', $method->invoke($this->provider, $user));

        $userWithoutPassword = ['email' => 'test@example.com'];
        $this->assertNull($method->invoke($this->provider, $userWithoutPassword));
    }

    public function testGetUserPasswordFromObjectUser(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUserPassword');
        $method->setAccessible(true);

        $user = new class {
            public string $password = 'hashed-password';
            public function getAuthPassword(): string {
                return $this->password;
            }
        };

        $this->assertEquals('hashed-password', $method->invoke($this->provider, $user));

        $userWithoutMethod = new class {
            public string $password = 'other-password';
        };

        $this->assertEquals('other-password', $method->invoke($this->provider, $userWithoutMethod));

        $userWithoutPassword = new class {};
        $this->assertNull($method->invoke($this->provider, $userWithoutPassword));
    }

    public function testConfigurationMerging(): void
    {
        $customConfig = [
            'mode' => 'hybrid',
            'user_claim' => 'custom_user',
            'embed_user_data' => true,
            'required_user_fields' => ['id', 'email', 'name']
        ];

        $provider = new JwtUserProvider($this->jwtConfig, $this->mockHash, $customConfig);
        
        $this->assertTrue($provider->isHybridMode());
        
        // Test custom user claim
        $claims = new ClaimsManager([
            'sub' => '123',
            'custom_user' => [
                'id' => 123,
                'email' => 'test@example.com',
                'name' => 'Test User'
            ]
        ]);

        $user = $provider->createUserFromClaims($claims);
        $this->assertIsArray($user);
        $this->assertEquals(123, $user['id']);
    }

    public function testTokenValidationWithObjectUser(): void
    {
        $user = new class {
            public int $id = 1;
            public function getAuthIdentifier(): int {
                return $this->id;
            }
        };

        $credentials = ['token' => $this->validToken];
        $isValid = $this->provider->validateCredentials($user, $credentials);
        $this->assertTrue($isValid);
    }

    public function testPasswordValidationWithObjectUser(): void
    {
        $provider = new JwtUserProvider($this->jwtConfig, $this->mockHash);
        
        $user = new class {
            public string $password = 'hashed-password';
            public function getAuthPassword(): string {
                return $this->password;
            }
        };

        $credentials = ['password' => 'plain-password'];

        $this->mockHash->method('check')
            ->with('plain-password', 'hashed-password')
            ->willReturn(true);

        $isValid = $provider->validateCredentials($user, $credentials);
        $this->assertTrue($isValid);
    }
}