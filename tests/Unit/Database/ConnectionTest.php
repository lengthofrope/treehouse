<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use LengthOfRope\TreeHouse\Database\Connection;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * Connection Test
 * 
 * Tests for the database connection manager
 */
class ConnectionTest extends TestCase
{
    private Connection $connection;
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        ];
        
        $this->connection = new Connection($this->config);
    }

    public function testConnectionCreation(): void
    {
        $this->assertInstanceOf(Connection::class, $this->connection);
        $this->assertFalse($this->connection->isConnected());
    }

    public function testConnect(): void
    {
        $this->connection->connect();
        $this->assertTrue($this->connection->isConnected());
        $this->assertInstanceOf(PDO::class, $this->connection->getPdo());
    }

    public function testDisconnect(): void
    {
        $this->connection->connect();
        $this->assertTrue($this->connection->isConnected());
        
        $this->connection->disconnect();
        $this->assertFalse($this->connection->isConnected());
    }

    public function testInvalidConnectionThrowsException(): void
    {
        $invalidConfig = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 1, // Use port 1 which should fail immediately
            'database' => 'invalid-db',
            'username' => 'invalid-user',
            'password' => 'invalid-pass'
        ];
        
        $connection = new Connection($invalidConfig);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');
        $connection->connect();
    }

    public function testCreateTable(): void
    {
        $this->connection->connect();
        
        $sql = "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            created_at TEXT
        )";
        
        $result = $this->connection->statement($sql);
        $this->assertTrue($result);
        $this->assertTrue($this->connection->tableExists('users'));
    }

    public function testInsert(): void
    {
        $this->connection->connect();
        $this->createUsersTable();
        
        $id = $this->connection->insert(
            "INSERT INTO users (name, email, created_at) VALUES (?, ?, ?)",
            ['John Doe', 'john@example.com', '2023-01-01 00:00:00']
        );
        
        $this->assertEquals('1', $id);
    }

    public function testSelect(): void
    {
        $this->connection->connect();
        $this->createUsersTable();
        $this->insertTestUsers();
        
        $users = $this->connection->select("SELECT * FROM users ORDER BY id");
        
        $this->assertCount(2, $users);
        $this->assertEquals('John Doe', $users[0]['name']);
        $this->assertEquals('Jane Smith', $users[1]['name']);
    }

    public function testSelectOne(): void
    {
        $this->connection->connect();
        $this->createUsersTable();
        $this->insertTestUsers();
        
        $user = $this->connection->selectOne("SELECT * FROM users WHERE email = ?", ['john@example.com']);
        
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user['name']);
        $this->assertEquals('john@example.com', $user['email']);
    }

    public function testUpdate(): void
    {
        $this->connection->connect();
        $this->createUsersTable();
        $this->insertTestUsers();
        
        $affected = $this->connection->update(
            "UPDATE users SET name = ? WHERE email = ?",
            ['John Smith', 'john@example.com']
        );
        
        $this->assertEquals(1, $affected);
        
        $user = $this->connection->selectOne("SELECT * FROM users WHERE email = ?", ['john@example.com']);
        $this->assertEquals('John Smith', $user['name']);
    }

    public function testDelete(): void
    {
        $this->connection->connect();
        $this->createUsersTable();
        $this->insertTestUsers();
        
        $deleted = $this->connection->delete("DELETE FROM users WHERE email = ?", ['john@example.com']);
        
        $this->assertEquals(1, $deleted);
        
        $users = $this->connection->select("SELECT * FROM users");
        $this->assertCount(1, $users);
    }

    public function testTransaction(): void
    {
        $this->connection->connect();
        $this->createUsersTable();
        
        $result = $this->connection->transaction(function($conn) {
            $conn->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['User 1', 'user1@example.com']);
            $conn->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['User 2', 'user2@example.com']);
            return 'success';
        });
        
        $this->assertEquals('success', $result);
        
        $users = $this->connection->select("SELECT * FROM users");
        $this->assertCount(2, $users);
    }

    public function testTransactionRollback(): void
    {
        $this->connection->connect();
        $this->createUsersTable();
        
        try {
            $this->connection->transaction(function($conn) {
                $conn->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['User 1', 'user1@example.com']);
                throw new \Exception('Force rollback');
            });
        } catch (\Exception $e) {
            // Expected exception
        }
        
        $users = $this->connection->select("SELECT * FROM users");
        $this->assertCount(0, $users);
    }

    public function testNestedTransactions(): void
    {
        $this->connection->connect();
        $this->createUsersTable();
        
        $this->connection->beginTransaction();
        $this->assertEquals(1, $this->connection->getTransactionLevel());
        
        $this->connection->beginTransaction();
        $this->assertEquals(2, $this->connection->getTransactionLevel());
        
        $this->connection->commit();
        $this->assertEquals(1, $this->connection->getTransactionLevel());
        
        $this->connection->commit();
        $this->assertEquals(0, $this->connection->getTransactionLevel());
    }

    public function testQueryLogging(): void
    {
        $this->connection->connect();
        $this->connection->enableQueryLog(true);
        $this->createUsersTable();
        
        $this->connection->select("SELECT * FROM users");
        
        $log = $this->connection->getQueryLog();
        $this->assertNotEmpty($log);
        $this->assertArrayHasKey('query', $log[0]);
        $this->assertArrayHasKey('bindings', $log[0]);
        $this->assertArrayHasKey('time', $log[0]);
    }

    public function testGetTableNames(): void
    {
        $this->connection->connect();
        $this->createUsersTable();
        
        $tables = $this->connection->getTableNames();
        $this->assertContains('users', $tables);
    }

    public function testGetTableColumns(): void
    {
        $this->connection->connect();
        $this->createUsersTable();
        
        $columns = $this->connection->getTableColumns('users');
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('email', $columns);
    }

    public function testGetDatabaseName(): void
    {
        $this->assertEquals(':memory:', $this->connection->getDatabaseName());
    }

    private function createUsersTable(): void
    {
        $sql = "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            created_at TEXT
        )";
        
        $this->connection->statement($sql);
    }

    private function insertTestUsers(): void
    {
        $this->connection->insert(
            "INSERT INTO users (name, email, created_at) VALUES (?, ?, ?)",
            ['John Doe', 'john@example.com', '2023-01-01 00:00:00']
        );
        
        $this->connection->insert(
            "INSERT INTO users (name, email, created_at) VALUES (?, ?, ?)",
            ['Jane Smith', 'jane@example.com', '2023-01-02 00:00:00']
        );
    }
}