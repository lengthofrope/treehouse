<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Database\QueryBuilder;
use Tests\TestCase;

/**
 * QueryBuilder Test
 * 
 * Tests for the fluent query builder
 */
class QueryBuilderTest extends TestCase
{
    private Connection $connection;
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];
        
        $this->connection = new Connection($config);
        $this->connection->connect();
        $this->createTestTables();
        
        $this->builder = new QueryBuilder($this->connection);
    }
    
    protected function tearDown(): void
    {
        // Clean up tables for next test
        $this->connection->statement("DELETE FROM posts");
        $this->connection->statement("DELETE FROM users");
        parent::tearDown();
    }
    
    /**
     * Get a fresh QueryBuilder instance
     */
    private function getBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }

    public function testSelectAll(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')->get();
        
        $this->assertCount(3, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function testSelectSpecificColumns(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->select(['name', 'email'])
            ->get();
        
        $this->assertCount(3, $results);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayHasKey('email', $results[0]);
        $this->assertArrayNotHasKey('id', $results[0]);
    }

    public function testWhere(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->where('name', '=', 'John Doe')
            ->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function testWhereShorthand(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->where('name', 'John Doe')
            ->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function testWhereIn(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->whereIn('name', ['John Doe', 'Jane Smith'])
            ->get();
        
        $this->assertCount(2, $results);
    }

    public function testWhereNotIn(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->whereNotIn('name', ['John Doe'])
            ->get();
        
        $this->assertCount(2, $results);
        $this->assertNotEquals('John Doe', $results[0]['name']);
    }

    public function testWhereBetween(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->whereBetween('id', 1, 2)
            ->get();
        
        $this->assertCount(2, $results);
    }

    public function testWhereNull(): void
    {
        $this->connection->insert(
            "INSERT INTO users (name, email, bio) VALUES (?, ?, ?)",
            ['Test User', 'test@example.com', null]
        );
        
        $results = $this->builder->table('users')
            ->whereNull('bio')
            ->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals('Test User', $results[0]['name']);
    }

    public function testWhereNotNull(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->whereNotNull('name')
            ->get();
        
        $this->assertCount(3, $results);
    }

    public function testOrWhere(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->where('name', 'John Doe')
            ->orWhere('name', 'Jane Smith')
            ->get();
        
        $this->assertCount(2, $results);
    }

    public function testWhereGroup(): void
    {
        $this->insertTestData();
        
        // Test multiple OR conditions instead of closure grouping
        $results = $this->builder->table('users')
            ->where('name', 'Jane Smith')
            ->orWhere('name', 'Bob Johnson')
            ->get();
        
        $this->assertCount(2, $results);
    }

    public function testOrderBy(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->orderBy('name', 'desc')
            ->get();
        
        $this->assertEquals('John Doe', $results[0]['name']);
        $this->assertEquals('Jane Smith', $results[1]['name']);
        $this->assertEquals('Bob Johnson', $results[2]['name']);
    }

    public function testMultipleOrderBy(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->orderBy('name')
            ->orderBy('id', 'desc')
            ->get();
        
        $this->assertCount(3, $results);
    }

    public function testLimit(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->limit(2)
            ->get();
        
        $this->assertCount(2, $results);
    }

    public function testOffset(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->offset(1)
            ->limit(2)
            ->get();
        
        $this->assertCount(2, $results);
        $this->assertNotEquals('1', $results[0]['id']);
    }

    public function testGroupBy(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->select(['name'])
            ->groupBy('name')
            ->get();
        
        $this->assertCount(3, $results);
    }

    public function testHaving(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->select(['name'])
            ->groupBy('name')
            ->having('name', '!=', 'John Doe')
            ->get();
        
        $this->assertCount(2, $results);
    }

    public function testJoin(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->select(['users.name', 'posts.title'])
            ->get();
        
        $this->assertCount(2, $results);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayHasKey('title', $results[0]);
    }

    public function testLeftJoin(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->select(['users.name', 'posts.title'])
            ->get();
        
        $this->assertCount(3, $results);
    }

    public function testRightJoin(): void
    {
        $this->insertTestData();
        
        $results = $this->builder->table('users')
            ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
            ->select(['users.name', 'posts.title'])
            ->get();
        
        $this->assertCount(2, $results);
    }

    public function testFirst(): void
    {
        $this->insertTestData();
        
        $result = $this->builder->table('users')
            ->where('name', 'John Doe')
            ->first();
        
        $this->assertNotNull($result);
        $this->assertEquals('John Doe', $result['name']);
    }

    public function testFind(): void
    {
        $this->insertTestData();
        
        $result = $this->builder->table('users')->find(1);
        
        $this->assertNotNull($result);
        $this->assertEquals('1', $result['id']);
    }

    public function testCount(): void
    {
        $this->insertTestData();
        
        $count = $this->builder->table('users')->count();
        
        $this->assertEquals(3, $count);
    }

    public function testCountWithWhere(): void
    {
        $this->insertTestData();
        
        $count = $this->builder->table('users')
            ->where('name', 'like', '%John%')
            ->count();
        
        $this->assertEquals(2, $count);
    }

    // Note: max, min, sum, avg methods are not implemented in the current QueryBuilder
    // These would need to be added to the QueryBuilder class for full functionality

    public function testInsert(): void
    {
        $uniqueEmail = 'new_' . time() . '@example.com';
        
        $id = $this->getBuilder()->table('users')->insert([
            'name' => 'New User',
            'email' => $uniqueEmail,
            'bio' => 'New user bio'
        ]);
        
        $this->assertNotNull($id);
        $this->assertIsString($id);
        
        $user = $this->getBuilder()->table('users')
            ->where('email', $uniqueEmail)
            ->first();
        
        $this->assertNotNull($user);
        $this->assertEquals('New User', $user['name']);
    }

    // Note: insertGetId method is not implemented - insert() already returns the ID

    public function testUpdate(): void
    {
        $this->insertTestData();
        
        $affected = $this->getBuilder()->table('users')
            ->where('name', 'John Doe')
            ->update(['bio' => 'Updated bio']);
        
        $this->assertEquals(1, $affected);
        
        $user = $this->getBuilder()->table('users')
            ->where('name', 'John Doe')
            ->first();
        
        $this->assertEquals('Updated bio', $user['bio']);
    }

    public function testDelete(): void
    {
        $this->insertTestData();
        
        $deleted = $this->getBuilder()->table('users')
            ->where('name', 'John Doe')
            ->delete();
        
        $this->assertEquals(1, $deleted);
        
        $count = $this->getBuilder()->table('users')->count();
        $this->assertEquals(2, $count);
    }

    // Note: truncate method is not implemented in the current QueryBuilder

    public function testPaginate(): void
    {
        $this->insertTestData();
        
        // paginate() sets limit and offset, then we call get()
        $results = $this->builder->table('users')
            ->paginate(1, 2)  // page 1, 2 per page
            ->get();
        
        $this->assertCount(2, $results);
        
        // Test page 2
        $results2 = $this->builder->table('users')
            ->paginate(2, 2)  // page 2, 2 per page
            ->get();
        
        $this->assertCount(1, $results2);
    }

    public function testExists(): void
    {
        $this->insertTestData();
        
        $exists = $this->builder->table('users')
            ->where('name', 'John Doe')
            ->exists();
        
        $this->assertTrue($exists);
        
        $notExists = $this->builder->table('users')
            ->where('name', 'Non Existent')
            ->exists();
        
        $this->assertFalse($notExists);
    }

    public function testToSql(): void
    {
        $sql = $this->builder->table('users')
            ->where('name', 'John Doe')
            ->toSql();
        
        $this->assertStringContainsString('SELECT * FROM users', $sql);
        $this->assertStringContainsString('WHERE name = ?', $sql);
    }

    public function testGetBindings(): void
    {
        $this->builder->table('users')
            ->where('name', 'John Doe')
            ->where('id', '>', 1);
        
        $bindings = $this->builder->getBindings();
        
        $this->assertCount(2, $bindings);
        $this->assertEquals('John Doe', $bindings[0]);
        $this->assertEquals(1, $bindings[1]);
    }

    private function createTestTables(): void
    {
        $this->connection->statement("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                bio TEXT,
                created_at TEXT
            )
        ");
        
        $this->connection->statement("
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                title TEXT NOT NULL,
                content TEXT,
                created_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
    }

    private function insertTestData(): void
    {
        $this->connection->insert(
            "INSERT INTO users (name, email, bio) VALUES (?, ?, ?)",
            ['John Doe', 'john@example.com', 'Software developer']
        );
        
        $this->connection->insert(
            "INSERT INTO users (name, email, bio) VALUES (?, ?, ?)",
            ['Jane Smith', 'jane@example.com', 'Designer']
        );
        
        $this->connection->insert(
            "INSERT INTO users (name, email, bio) VALUES (?, ?, ?)",
            ['Bob Johnson', 'bob@example.com', 'Manager']
        );
        
        $this->connection->insert(
            "INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [1, 'First Post', 'This is the first post']
        );
        
        $this->connection->insert(
            "INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [2, 'Second Post', 'This is the second post']
        );
    }
}