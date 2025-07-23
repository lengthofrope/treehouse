<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use LengthOfRope\TreeHouse\Auth\JwtUserProvider;
use LengthOfRope\TreeHouse\Auth\UserProvider;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager;
use PHPUnit\Framework\TestCase;

/**
 * JwtUserProvider Test Suite
 *
 * Comprehensive tests for the simplified stateless JWT user provider including:
 * - Stateless user resolution from JWT tokens
 * - Credential validation for JWT tokens only
 * - User creation from JWT claims
 * - Configuration handling
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

        // Test user data
        $this->testUser = [
            'id' => 1,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'role' => 'admin'
        ];

        // Generate a valid token for testing
        $this->tokenGenerator = new TokenGenerator($this->jwtConfig);
        $this->validToken = $this->tokenGenerator->generateAuthToken(1, $this->testUser);

        // Create provider instance (always stateless)
        $this->provider = new JwtUserProvider($this->jwtConfig);
    }

    public function testImplementsUserProviderInterface(): void
    {
        $this->assertInstanceOf(UserProvider::class, $this->provider);
    }

    public function testIsAlwaysStatelessMode(): void
    {
        $this->assertTrue($this->provider->isStatelessMode());
    }

    public function testConstructorWithCustomConfig(): void
    {
        $customConfig = [
            'user_claim' => 'custom_user',
            'embed_user_data' => false,
            'required_user_fields' => ['id', 'email', 'name']
        ];

        $provider = new JwtUserProvider($this->jwtConfig, $customConfig);
        
        $this->assertTrue($provider->isStatelessMode());
        $this->assertEquals($customConfig, array_intersect_key($provider->getConfig(), $customConfig));
    }

    public function testGetJwtConfig(): void
    {
        $this->assertSame($this->jwtConfig, $this->provider->getJwtConfig());
    }

    public function testGetConfig(): void
    {
        $config = $this->provider->getConfig();
        
        $this->assertIsArray($config);
        $this->assertEquals('user', $config['user_claim']);
        $this->assertTrue($config['embed_user_data']);
        $this->assertEquals(['id', 'email'], $config['required_user_fields']);
    }

    // Test retrieveById method
    public function testRetrieveByIdWithJwtTokenInStatelessMode(): void
    {
        $user = $this->provider->retrieveById($this->validToken);
        
        $this->assertIsArray($user);
        $this->assertEquals(1, $user['id']);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertEquals('Test User', $user['name']);
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

    public function testRetrieveByIdWithExpiredToken(): void
    {
        // Create an expired token
        $expiredToken = $this->tokenGenerator->generateCustomToken(['sub' => '1'], -3600); // Expired 1 hour ago
        $user = $this->provider->retrieveById($expiredToken);
        $this->assertNull($user);
    }

    // Test retrieveByToken method (should always return null)
    public function testRetrieveByTokenAlwaysReturnsNull(): void
    {
        $user = $this->provider->retrieveByToken(1, 'remember-token');
        $this->assertNull($user);
    }

    // Test updateRememberToken method (should be no-op)
    public function testUpdateRememberTokenDoesNothing(): void
    {
        // Should not throw an exception
        $this->provider->updateRememberToken($this->testUser, 'new-token');
        $this->assertTrue(true); // Assertion to prevent risky test warning
    }

    // Test retrieveByCredentials method
    public function testRetrieveByCredentialsWithJwtToken(): void
    {
        $credentials = ['token' => $this->validToken];
        $user = $this->provider->retrieveByCredentials($credentials);
        
        $this->assertIsArray($user);
        $this->assertEquals(1, $user['id']);
        $this->assertEquals('test@example.com', $user['email']);
    }

    public function testRetrieveByCredentialsWithInvalidToken(): void
    {
        $credentials = ['token' => 'invalid-token'];
        $user = $this->provider->retrieveByCredentials($credentials);
        $this->assertNull($user);
    }

    public function testRetrieveByCredentialsWithEmailReturnsNull(): void
    {
        $credentials = ['email' => 'test@example.com', 'password' => 'password'];
        $user = $this->provider->retrieveByCredentials($credentials);
        $this->assertNull($user);
    }

    public function testRetrieveByCredentialsWithoutTokenReturnsNull(): void
    {
        $credentials = ['username' => 'test'];
        $user = $this->provider->retrieveByCredentials($credentials);
        $this->assertNull($user);
    }

    // Test validateCredentials method
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

    public function testValidateCredentialsWithPasswordReturnsFalse(): void
    {
        $credentials = ['password' => 'password'];
        $user = ['id' => 1, 'password' => 'hashed-password'];

        $isValid = $this->provider->validateCredentials($user, $credentials);
        $this->assertFalse($isValid);
    }

    public function testValidateCredentialsWithNoTokenReturnsFalse(): void
    {
        $credentials = ['email' => 'test@example.com'];
        $isValid = $this->provider->validateCredentials($this->testUser, $credentials);
        $this->assertFalse($isValid);
    }

    // Test rehashPasswordIfRequired method (should be no-op)
    public function testRehashPasswordIfRequiredDoesNothing(): void
    {
        $credentials = ['password' => 'password'];
        
        // Should not throw an exception
        $this->provider->rehashPasswordIfRequired($this->testUser, $credentials);
        $this->assertTrue(true); // Assertion to prevent risky test warning
    }

    // Test createUserFromClaims method
    public function testCreateUserFromClaimsWithEmbeddedData(): void
    {
        $claims = new ClaimsManager([
            'sub' => '123',
            'user' => [
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
            ['required_user_fields' => ['id', 'email']]
        );

        $claims = new ClaimsManager([
            'sub' => '123',
            'user' => [
                'id' => 123,
                // Missing email
                'name' => 'Test User'
            ]
        ]);

        $user = $provider->createUserFromClaims($claims);
        $this->assertNull($user);
    }

    public function testCreateUserFromClaimsWithCustomUserClaim(): void
    {
        $provider = new JwtUserProvider(
            $this->jwtConfig,
            ['user_claim' => 'custom_user']
        );

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
        $this->assertEquals('test@example.com', $user['email']);
    }

    // Test JWT token detection
    public function testIsJwtTokenDetection(): void
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('isJwtToken');
        $method->setAccessible(true);

        // Valid JWT format
        $this->assertTrue($method->invoke($this->provider, 'header.payload.signature'));
        
        // Invalid formats
        $this->assertFalse($method->invoke($this->provider, 'not-a-jwt'));
        $this->assertFalse($method->invoke($this->provider, 'header.payload'));
        $this->assertFalse($method->invoke($this->provider, 'header..signature'));
        $this->assertFalse($method->invoke($this->provider, '.payload.signature'));
        $this->assertFalse($method->invoke($this->provider, 'header.payload.'));
        $this->assertFalse($method->invoke($this->provider, 123));
        $this->assertFalse($method->invoke($this->provider, null));
        $this->assertFalse($method->invoke($this->provider, ''));
    }

    // Test getUserId method
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

    public function testGetUserIdFromNonUserValue(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUserId');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($this->provider, 'string'));
        $this->assertNull($method->invoke($this->provider, 123));
        $this->assertNull($method->invoke($this->provider, null));
    }

    // Test token validation with object users
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

    // Test edge cases
    public function testRetrieveFromJwtWithValidToken(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('retrieveFromJwt');
        $method->setAccessible(true);

        $user = $method->invoke($this->provider, $this->validToken);
        $this->assertIsArray($user);
        $this->assertEquals(1, $user['id']);
    }

    public function testRetrieveFromJwtWithInvalidToken(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('retrieveFromJwt');
        $method->setAccessible(true);

        $user = $method->invoke($this->provider, 'invalid.token.here');
        $this->assertNull($user);
    }

    public function testCreateUserFromDataWithValidData(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('createUserFromData');
        $method->setAccessible(true);

        $userData = [
            'id' => 123,
            'email' => 'test@example.com',
            'name' => 'Test User'
        ];

        $user = $method->invoke($this->provider, $userData);
        $this->assertEquals($userData, $user);
    }

    public function testCreateUserFromDataWithMissingFields(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('createUserFromData');
        $method->setAccessible(true);

        $userData = [
            'id' => 123,
            // Missing email (required field)
            'name' => 'Test User'
        ];

        $user = $method->invoke($this->provider, $userData);
        $this->assertNull($user);
    }

    // Test configuration defaults
    public function testDefaultConfiguration(): void
    {
        $provider = new JwtUserProvider($this->jwtConfig);
        $config = $provider->getConfig();

        $this->assertEquals('user', $config['user_claim']);
        $this->assertTrue($config['embed_user_data']);
        $this->assertEquals(['id', 'email'], $config['required_user_fields']);
    }

    // Test multiple custom claims
    public function testCreateUserFromClaimsWithMultipleCustomClaims(): void
    {
        $claims = new ClaimsManager([
            'sub' => '789',
            'role' => 'moderator',
            'permissions' => ['read', 'write'],
            'department' => 'engineering',
            'level' => 'senior'
        ]);

        $user = $this->provider->createUserFromClaims($claims);
        
        $this->assertIsArray($user);
        $this->assertEquals('789', $user['id']);
        $this->assertEquals('moderator', $user['role']);
        $this->assertEquals(['read', 'write'], $user['permissions']);
        $this->assertEquals('engineering', $user['department']);
        $this->assertEquals('senior', $user['level']);
        $this->assertArrayHasKey('jwt_claims', $user);
    }
}