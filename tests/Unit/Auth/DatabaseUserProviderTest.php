<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Auth\DatabaseUserProvider;
use LengthOfRope\TreeHouse\Auth\GenericUser;
use LengthOfRope\TreeHouse\Security\Hash;
use LengthOfRope\TreeHouse\Database\Connection;
use InvalidArgumentException;

/**
 * Database User Provider Tests
 *
 * Tests for the database-backed user authentication provider.
 */
class DatabaseUserProviderTest extends TestCase
{
    protected DatabaseUserProvider $provider;
    protected Connection $connection;
    protected Hash $hash;
    protected array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hash = new Hash();
        
        $this->config = [
            'table' => 'test_users',
            'model' => null,
            'connection' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ];

        $this->provider = new DatabaseUserProvider($this->hash, $this->config);
        $this->connection = $this->provider->getConnection();
        
        $this->createTestTable();
        $this->seedTestData();
    }

    protected function createTestTable(): void
    {
        $this->connection->statement("
            CREATE TABLE test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                name TEXT NOT NULL,
                remember_token TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ");
    }

    protected function seedTestData(): void
    {
        $hashedPassword = $this->hash->make('password123');
        
        $this->connection->insert(
            "INSERT INTO test_users (email, password, name, remember_token) VALUES (?, ?, ?, ?)",
            ['john@example.com', $hashedPassword, 'John Doe', 'old_token_123']
        );
        
        $this->connection->insert(
            "INSERT INTO test_users (email, password, name, remember_token) VALUES (?, ?, ?, ?)",
            ['jane@example.com', $hashedPassword, 'Jane Smith', null]
        );
    }

    public function testRetrieveById(): void
    {
        $user = $this->provider->retrieveById(1);
        
        $this->assertInstanceOf(GenericUser::class, $user);
        $this->assertEquals(1, $user->getAuthIdentifier());
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('John Doe', $user->name);
    }

    public function testRetrieveByIdWithNonexistentUser(): void
    {
        $user = $this->provider->retrieveById(999);
        
        $this->assertNull($user);
    }

    public function testRetrieveByToken(): void
    {
        $user = $this->provider->retrieveByToken(1, 'old_token_123');
        
        $this->assertInstanceOf(GenericUser::class, $user);
        $this->assertEquals(1, $user->getAuthIdentifier());
        $this->assertEquals('john@example.com', $user->email);
    }

    public function testRetrieveByTokenWithWrongToken(): void
    {
        $user = $this->provider->retrieveByToken(1, 'wrong_token');
        
        $this->assertNull($user);
    }

    public function testRetrieveByTokenWithNullToken(): void
    {
        $user = $this->provider->retrieveByToken(2, 'any_token');
        
        $this->assertNull($user);
    }

    public function testUpdateRememberToken(): void
    {
        $user = $this->provider->retrieveById(1);
        $newToken = 'new_token_456';
        
        $this->provider->updateRememberToken($user, $newToken);
        
        // Verify the token was updated
        $updatedUser = $this->provider->retrieveByToken(1, $newToken);
        $this->assertNotNull($updatedUser);
        $this->assertEquals(1, $updatedUser->getAuthIdentifier());
        
        // Verify old token no longer works
        $oldTokenUser = $this->provider->retrieveByToken(1, 'old_token_123');
        $this->assertNull($oldTokenUser);
    }

    public function testRetrieveByCredentials(): void
    {
        $credentials = ['email' => 'john@example.com'];
        
        $user = $this->provider->retrieveByCredentials($credentials);
        
        $this->assertInstanceOf(GenericUser::class, $user);
        $this->assertEquals(1, $user->getAuthIdentifier());
        $this->assertEquals('john@example.com', $user->email);
    }

    public function testRetrieveByCredentialsWithPassword(): void
    {
        $credentials = [
            'email' => 'john@example.com',
            'password' => 'password123' // Should be ignored in query
        ];
        
        $user = $this->provider->retrieveByCredentials($credentials);
        
        $this->assertInstanceOf(GenericUser::class, $user);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function testRetrieveByCredentialsWithNonexistentUser(): void
    {
        $credentials = ['email' => 'nonexistent@example.com'];
        
        $user = $this->provider->retrieveByCredentials($credentials);
        
        $this->assertNull($user);
    }

    public function testValidateCredentialsWithCorrectPassword(): void
    {
        $user = $this->provider->retrieveById(1);
        $credentials = ['password' => 'password123'];
        
        $isValid = $this->provider->validateCredentials($user, $credentials);
        
        $this->assertTrue($isValid);
    }

    public function testValidateCredentialsWithIncorrectPassword(): void
    {
        $user = $this->provider->retrieveById(1);
        $credentials = ['password' => 'wrong_password'];
        
        $isValid = $this->provider->validateCredentials($user, $credentials);
        
        $this->assertFalse($isValid);
    }

    public function testValidateCredentialsWithoutPassword(): void
    {
        $user = $this->provider->retrieveById(1);
        $credentials = ['email' => 'john@example.com'];
        
        $isValid = $this->provider->validateCredentials($user, $credentials);
        
        $this->assertFalse($isValid);
    }

    public function testRehashPasswordIfRequired(): void
    {
        $user = $this->provider->retrieveById(1);
        $credentials = ['password' => 'password123'];
        
        // Force rehashing
        $this->provider->rehashPasswordIfRequired($user, $credentials, true);
        
        // Verify the password still works
        $updatedUser = $this->provider->retrieveById(1);
        $isValid = $this->provider->validateCredentials($updatedUser, $credentials);
        $this->assertTrue($isValid);
    }

    public function testRehashPasswordWithoutPassword(): void
    {
        $user = $this->provider->retrieveById(1);
        $credentials = ['email' => 'john@example.com'];
        
        // Should not throw an exception
        $this->provider->rehashPasswordIfRequired($user, $credentials);
        
        // Verify nothing changed
        $this->assertTrue(true);
    }

    public function testGetUserIdFromObjectWithGetAuthIdentifier(): void
    {
        $user = $this->provider->retrieveById(1);
        
        // GenericUser implements getAuthIdentifier
        $this->assertEquals(1, $user->getAuthIdentifier());
    }

    public function testGetUserIdFromArrayWithId(): void
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUserId');
        $method->setAccessible(true);
        
        $userData = ['id' => 123, 'name' => 'Test User'];
        $userId = $method->invoke($this->provider, $userData);
        
        $this->assertEquals(123, $userId);
    }

    public function testGetUserIdThrowsExceptionForInvalidUser(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User must have an ID or implement getAuthIdentifier method');
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUserId');
        $method->setAccessible(true);
        
        $method->invoke($this->provider, ['name' => 'No ID']);
    }

    public function testGetUserPasswordFromGenericUser(): void
    {
        $user = $this->provider->retrieveById(1);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUserPassword');
        $method->setAccessible(true);
        
        $password = $method->invoke($this->provider, $user);
        
        $this->assertIsString($password);
        $this->assertTrue($this->hash->check('password123', $password));
    }

    public function testGetUserPasswordFromArray(): void
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUserPassword');
        $method->setAccessible(true);
        
        $userData = ['password' => 'test_password'];
        $password = $method->invoke($this->provider, $userData);
        
        $this->assertEquals('test_password', $password);
    }

    public function testGetUserPasswordThrowsExceptionForInvalidUser(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User must have a password or implement getAuthPassword method');
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUserPassword');
        $method->setAccessible(true);
        
        $method->invoke($this->provider, ['name' => 'No Password']);
    }

    public function testGetConnection(): void
    {
        $connection = $this->provider->getConnection();
        
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertSame($this->connection, $connection);
    }

    public function testConfigurationDefaults(): void
    {
        $minimalConfig = [
            'connection' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ]
        ];
        
        $provider = new DatabaseUserProvider($this->hash, $minimalConfig);
        
        // Should use default table name 'users'
        $this->assertInstanceOf(DatabaseUserProvider::class, $provider);
    }

    public function testWithCustomModel(): void
    {
        $configWithModel = [
            'table' => 'test_users',
            'model' => TestAuthUser::class,
            'connection' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ];
        
        $provider = new DatabaseUserProvider($this->hash, $configWithModel);
        $provider->getConnection()->statement("
            CREATE TABLE test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT,
                password TEXT,
                name TEXT
            )
        ");
        
        $provider->getConnection()->insert(
            "INSERT INTO test_users (email, password, name) VALUES (?, ?, ?)",
            ['test@example.com', 'password', 'Test User']
        );
        
        $user = $provider->retrieveById(1);
        
        $this->assertInstanceOf(TestAuthUser::class, $user);
        $this->assertEquals('test@example.com', $user->email);
    }

    protected function tearDown(): void
    {
        // No need to set connection to null - it's handled automatically
        parent::tearDown();
    }
}

/**
 * Test user class for custom model testing
 */
class TestAuthUser extends \LengthOfRope\TreeHouse\Auth\GenericUser
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
}