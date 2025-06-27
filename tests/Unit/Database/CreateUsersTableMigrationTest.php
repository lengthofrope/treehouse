<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use LengthOfRope\TreeHouse\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Create Users Table Migration Test
 *
 * Tests the users table migration to ensure it creates the correct
 * table structure required for the Auth manager.
 */
class CreateUsersTableMigrationTest extends TestCase
{
    private Connection $connection;
    private $migration;

    protected function setUp(): void
    {
        // Use SQLite in-memory database for testing
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];
        
        $this->connection = new Connection($config);
        
        // Include and instantiate the migration
        require_once __DIR__ . '/../../../database/migrations/001_create_users_table.php';
        $this->migration = new \CreateUsersTable($this->connection);
    }

    public function testMigrationUp(): void
    {
        // Run the migration
        $this->migration->up();
        
        // Verify the table exists
        $this->assertTrue($this->connection->tableExists('users'));
        
        // Verify the table has the expected columns
        $columns = $this->connection->getTableColumns('users');
        
        $expectedColumns = [
            'id',
            'email',
            'password',
            'remember_token',
            'name',
            'email_verified',
            'email_verified_at',
            'created_at',
            'updated_at'
        ];
        
        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '{$column}' should exist in users table");
        }
    }

    public function testMigrationDown(): void
    {
        // First run the migration up
        $this->migration->up();
        $this->assertTrue($this->connection->tableExists('users'));
        
        // Then run the migration down
        $this->migration->down();
        $this->assertFalse($this->connection->tableExists('users'));
    }

    public function testTableStructureForAuth(): void
    {
        // Run the migration
        $this->migration->up();
        
        // Test that we can insert a user record with auth-required fields
        $this->connection->statement("
            INSERT INTO users (email, password, name, remember_token, email_verified, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [
            'test@example.com',
            'hashed_password',
            'Test User',
            'remember_token_123',
            1,
            '2023-01-01 00:00:00',
            '2023-01-01 00:00:00'
        ]);
        
        // Verify the record was inserted
        $result = $this->connection->select("SELECT * FROM users WHERE email = ?", ['test@example.com']);
        $this->assertCount(1, $result);
        
        $user = $result[0];
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertEquals('hashed_password', $user['password']);
        $this->assertEquals('Test User', $user['name']);
        $this->assertEquals('remember_token_123', $user['remember_token']);
        $this->assertEquals(1, $user['email_verified']);
    }

    public function testUniqueEmailConstraint(): void
    {
        // Run the migration
        $this->migration->up();
        
        // Insert first user using query method (which throws exceptions)
        $this->connection->query("
            INSERT INTO users (email, password, name)
            VALUES (?, ?, ?)
        ", ['test@example.com', 'password1', 'User 1']);
        
        // Try to insert second user with same email - should fail
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/UNIQUE constraint failed|Duplicate entry/');
        
        $this->connection->query("
            INSERT INTO users (email, password, name)
            VALUES (?, ?, ?)
        ", ['test@example.com', 'password2', 'User 2']);
    }

    public function testNullableFields(): void
    {
        // Run the migration
        $this->migration->up();
        
        // Insert user with minimal required fields
        $this->connection->statement("
            INSERT INTO users (email, password, name)
            VALUES (?, ?, ?)
        ", ['minimal@example.com', 'password', 'Minimal User']);
        
        // Verify nullable fields are null
        $result = $this->connection->select("SELECT * FROM users WHERE email = ?", ['minimal@example.com']);
        $user = $result[0];
        
        $this->assertNull($user['remember_token']);
        $this->assertNull($user['email_verified_at']);
        $this->assertNull($user['created_at']);
        $this->assertNull($user['updated_at']);
    }
}