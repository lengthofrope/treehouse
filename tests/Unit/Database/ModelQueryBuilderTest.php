<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Database\ModelQueryBuilder;
use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Support\Collection;
use Tests\TestCase;

/**
 * Test Model for ModelQueryBuilder testing
 */
class TestModelQueryUser extends ActiveRecord
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email'];
}

/**
 * ModelQueryBuilder Test
 * 
 * Tests for the ModelQueryBuilder class
 */
class ModelQueryBuilderTest extends TestCase
{
    private Connection $connection;
    private ModelQueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];
        
        $this->connection = new Connection($config);
        $this->connection->connect();
        
        // Set the connection for ActiveRecord
        ActiveRecord::setConnection($this->connection);
        
        $this->createTestTable();
        $this->insertTestData();
        
        $this->queryBuilder = new ModelQueryBuilder(
            $this->connection, 
            'users', 
            TestModelQueryUser::class
        );
    }

    public function testGetReturnsCollectionOfModelInstances(): void
    {
        $results = $this->queryBuilder->get();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
        
        foreach ($results as $result) {
            $this->assertInstanceOf(TestModelQueryUser::class, $result);
        }
        
        $this->assertEquals('John Doe', $results->first()->name);
        $this->assertEquals('john@example.com', $results->first()->email);
    }

    public function testFirstReturnsModelInstance(): void
    {
        $result = $this->queryBuilder->where('name', 'John Doe')->first();
        
        $this->assertInstanceOf(TestModelQueryUser::class, $result);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
    }

    public function testFirstReturnsNullWhenNoResults(): void
    {
        $result = $this->queryBuilder->where('name', 'Nonexistent User')->first();
        
        $this->assertNull($result);
    }

    public function testFindReturnsModelInstance(): void
    {
        $result = $this->queryBuilder->find(1);
        
        $this->assertInstanceOf(TestModelQueryUser::class, $result);
        $this->assertEquals('John Doe', $result->name);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $result = $this->queryBuilder->find(999);
        
        $this->assertNull($result);
    }

    public function testQueryBuilderMethodsReturnModelInstances(): void
    {
        $results = $this->queryBuilder
            ->where('name', 'like', '%Doe%')
            ->orderBy('id', 'desc')
            ->limit(1)
            ->get();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(TestModelQueryUser::class, $results->first());
        $this->assertEquals('Jane Doe', $results->first()->name);
    }

    public function testEmptyResultsReturnEmptyCollection(): void
    {
        $results = $this->queryBuilder
            ->where('name', 'Nonexistent User')
            ->get();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertTrue($results->isEmpty());
        $this->assertEquals(0, $results->count());
    }

    public function testModelInstancesHaveCorrectAttributes(): void
    {
        $user = $this->queryBuilder->find(1);
        
        $this->assertInstanceOf(TestModelQueryUser::class, $user);
        $this->assertEquals(1, $user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        
        // Test that it's properly hydrated as existing model
        $this->assertTrue($user->exists());
    }

    public function testModelInstancesCanBeSaved(): void
    {
        $user = $this->queryBuilder->find(1);
        $user->name = 'Updated Name';
        
        $this->assertTrue($user->save());
        
        // Verify the update
        $updated = $this->queryBuilder->find(1);
        $this->assertEquals('Updated Name', $updated->name);
    }

    private function createTestTable(): void
    {
        $this->connection->statement("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                created_at TEXT,
                updated_at TEXT
            )
        ");
    }

    private function insertTestData(): void
    {
        $this->connection->insert(
            "INSERT INTO users (name, email) VALUES (?, ?)",
            ['John Doe', 'john@example.com']
        );
        
        $this->connection->insert(
            "INSERT INTO users (name, email) VALUES (?, ?)",
            ['Jane Doe', 'jane@example.com']
        );
    }
}