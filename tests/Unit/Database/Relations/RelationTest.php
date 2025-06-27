<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Relations;

use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Database\Relations\Relation;
use LengthOfRope\TreeHouse\Database\Relations\HasMany;
use LengthOfRope\TreeHouse\Database\Relations\BelongsTo;
use LengthOfRope\TreeHouse\Database\Relations\BelongsToMany;
use LengthOfRope\TreeHouse\Database\QueryBuilder;
use LengthOfRope\TreeHouse\Support\Collection;
use Tests\TestCase;

/**
 * Test Model for relation testing
 */
class TestModel extends ActiveRecord
{
    protected string $table = 'test_models';
    protected array $fillable = ['id', 'name', 'user_id'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setRawAttributes($attributes);
        $this->exists = !empty($attributes);
    }
}

/**
 * Relations Test
 * 
 * Tests for the database relationship classes
 */
class RelationTest extends TestCase
{
    private Connection $connection;

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
        
        $this->createTestTables();
        $this->insertTestData();
    }

    public function testRelationCreation(): void
    {
        $parent = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'posts');
        
        $relation = new class($query, $parent) extends Relation {
            public function addConstraints(): void
            {
                // Test implementation
            }
            
            public function getResults(): array
            {
                return [];
            }
            
            public function addEagerConstraints(array $models): void
            {
                // Test implementation
            }
            
            public function initRelation(array $models, string $relation): array
            {
                return $models;
            }
            
            public function match(array $models, Collection $results, string $relation): array
            {
                return $models;
            }
        };
        
        $this->assertInstanceOf(Relation::class, $relation);
    }

    public function testHasManyRelation(): void
    {
        $user = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'posts');
        
        $relation = new HasMany($query, $user, 'user_id', 'id');
        
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(Relation::class, $relation);
    }

    public function testHasManyConstraints(): void
    {
        $user = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'posts');
        
        $relation = new HasMany($query, $user, 'user_id', 'id');
        $results = $relation->get();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
        $this->assertEquals('First Post', $results[0]['title']);
        $this->assertEquals('Second Post', $results[1]['title']);
    }

    public function testHasManyWhere(): void
    {
        $user = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'posts');
        
        $relation = new HasMany($query, $user, 'user_id', 'id');
        $results = $relation->where('title', 'First Post')->get();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertEquals('First Post', $results[0]['title']);
    }

    public function testBelongsToRelation(): void
    {
        $post = new TestModel(['id' => 1, 'user_id' => 1]);
        $query = new QueryBuilder($this->connection, 'users');
        
        $relation = new BelongsTo($query, $post, 'user_id', 'id');
        
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Relation::class, $relation);
    }

    public function testBelongsToConstraints(): void
    {
        $post = new TestModel(['id' => 1, 'user_id' => 1]);
        $query = new QueryBuilder($this->connection, 'users');
        
        $relation = new BelongsTo($query, $post, 'user_id', 'id');
        $result = $relation->first();
        
        $this->assertNotNull($result);
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    public function testBelongsToManyRelation(): void
    {
        $user = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'roles');
        
        $relation = new BelongsToMany($query, $user, 'user_roles', 'user_id', 'role_id', 'id', 'id');
        
        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertInstanceOf(Relation::class, $relation);
    }

    public function testBelongsToManyConstraints(): void
    {
        $user = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'roles');
        
        $relation = new BelongsToMany($query, $user, 'user_roles', 'user_id', 'role_id', 'id', 'id');
        $results = $relation->get();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
        $this->assertInstanceOf(TestModel::class, $results[0]);
        $this->assertEquals('Admin', $results[0]->getAttribute('name'));
        $this->assertEquals('Editor', $results[1]->getAttribute('name'));
    }

    public function testBelongsToManyPivotData(): void
    {
        $user = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'roles');
        
        $relation = new BelongsToMany($query, $user, 'user_roles', 'user_id', 'role_id', 'id', 'id');
        $results = $relation->get();
        
        // Check that pivot data is included
        $this->assertInstanceOf(TestModel::class, $results[0]);
        $this->assertIsArray($results[0]->pivot);
        $this->assertArrayHasKey('user_id', $results[0]->pivot);
        $this->assertArrayHasKey('role_id', $results[0]->pivot);
        $this->assertEquals(1, $results[0]->pivot['user_id']);
    }

    public function testRelationEagerLoading(): void
    {
        $user = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'posts');
        
        $relation = new HasMany($query, $user, 'user_id', 'id');
        
        // Test that we can add constraints for eager loading
        $relation->where('title', 'like', '%Post%');
        $results = $relation->get();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
    }

    public function testRelationChaining(): void
    {
        $user = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'posts');
        
        $relation = new HasMany($query, $user, 'user_id', 'id');
        
        // Test method chaining
        $result = $relation->where('title', 'First Post')
                          ->orderBy('id', 'desc')
                          ->first();
        
        $this->assertNotNull($result);
        $this->assertEquals('First Post', $result['title']);
    }

    public function testRelationCount(): void
    {
        $user = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'posts');
        
        $relation = new HasMany($query, $user, 'user_id', 'id');
        $count = $relation->count();
        
        $this->assertEquals(2, $count);
    }

    public function testRelationExists(): void
    {
        $user = new TestModel(['id' => 1]);
        $query = new QueryBuilder($this->connection, 'posts');
        
        $relation = new HasMany($query, $user, 'user_id', 'id');
        
        $this->assertTrue($relation->exists());
        
        // Test with non-existing relation
        $user2 = new TestModel(['id' => 999]);
        $query2 = new QueryBuilder($this->connection, 'posts');
        $relation2 = new HasMany($query2, $user2, 'user_id', 'id');
        
        $this->assertFalse($relation2->exists());
    }

    private function createTestTables(): void
    {
        $this->connection->statement("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
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
        
        $this->connection->statement("
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT
            )
        ");
        
        $this->connection->statement("
            CREATE TABLE user_roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                role_id INTEGER,
                created_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (role_id) REFERENCES roles(id)
            )
        ");
    }

    private function insertTestData(): void
    {
        // Insert users
        $this->connection->insert(
            "INSERT INTO users (name, email) VALUES (?, ?)",
            ['John Doe', 'john@example.com']
        );
        
        $this->connection->insert(
            "INSERT INTO users (name, email) VALUES (?, ?)",
            ['Jane Smith', 'jane@example.com']
        );
        
        // Insert posts
        $this->connection->insert(
            "INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [1, 'First Post', 'This is the first post']
        );
        
        $this->connection->insert(
            "INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [1, 'Second Post', 'This is the second post']
        );
        
        $this->connection->insert(
            "INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [2, 'Third Post', 'This is the third post']
        );
        
        // Insert roles
        $this->connection->insert(
            "INSERT INTO roles (name) VALUES (?)",
            ['Admin']
        );
        
        $this->connection->insert(
            "INSERT INTO roles (name) VALUES (?)",
            ['Editor']
        );
        
        $this->connection->insert(
            "INSERT INTO roles (name) VALUES (?)",
            ['Viewer']
        );
        
        // Insert user_roles
        $this->connection->insert(
            "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
            [1, 1]
        );
        
        $this->connection->insert(
            "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
            [1, 2]
        );
        
        $this->connection->insert(
            "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
            [2, 3]
        );
    }
}