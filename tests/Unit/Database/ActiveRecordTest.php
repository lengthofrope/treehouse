<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Support\Collection;
use Tests\TestCase;

/**
 * Test User Model for ActiveRecord testing
 */
class TestUser extends ActiveRecord
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email', 'bio'];
    protected array $casts = [
        'id' => 'int',
        'created_at' => 'datetime'
    ];
}

/**
 * Test Post Model for relationship testing
 */
class TestPost extends ActiveRecord
{
    protected string $table = 'posts';
    protected string $primaryKey = 'id';
    protected array $fillable = ['user_id', 'title', 'content'];
    protected array $casts = [
        'id' => 'int',
        'user_id' => 'int'
    ];

    // Note: belongsTo method would need to be implemented in ActiveRecord
    // This is a placeholder for relationship testing
}

/**
 * ActiveRecord Test
 * 
 * Tests for the Active Record base model
 */
class ActiveRecordTest extends TestCase
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
    }

    public function testModelCreation(): void
    {
        $user = new TestUser();
        
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertEquals('users', $user->getTable());
        $this->assertEquals('id', $user->getKeyName());
    }

    // Note: getFillable() and isFillable() are protected methods in the current implementation
    // These would need to be made public or tested through other means

    public function testAttributeAccess(): void
    {
        $user = new TestUser();
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('John Doe', $user->getAttribute('name'));
    }

    public function testAttributeCasting(): void
    {
        $user = new TestUser();
        $user->setRawAttributes([
            'id' => '123',
            'name' => 'John Doe',
            'created_at' => '2023-01-01 12:00:00'
        ]);
        
        $this->assertIsInt($user->id);
        $this->assertEquals(123, $user->id);
        $this->assertInstanceOf(\DateTime::class, $user->created_at); // DateTime casting is implemented
        $this->assertEquals('2023-01-01 12:00:00', $user->created_at->format('Y-m-d H:i:s'));
    }

    public function testFill(): void
    {
        $user = new TestUser();
        $user->fill([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'bio' => 'Developer',
            'id' => 999 // Should be ignored as not fillable
        ]);
        
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('Developer', $user->bio);
        $this->assertNull($user->id);
    }

    public function testSave(): void
    {
        $user = new TestUser();
        $user->fill([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'bio' => 'Developer'
        ]);
        
        $result = $user->save();
        
        $this->assertTrue($result);
        $this->assertNotNull($user->id);
        $this->assertFalse($user->isDirty());
    }

    public function testUpdate(): void
    {
        $user = new TestUser();
        $user->fill([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'bio' => 'Developer'
        ]);
        $user->save();
        
        $user->name = 'Jane Doe';
        $this->assertTrue($user->isDirty());
        $this->assertTrue($user->isDirty('name'));
        
        $result = $user->save();
        
        $this->assertTrue($result);
        $this->assertFalse($user->isDirty());
        
        // Verify in database
        $updated = TestUser::find($user->id);
        $this->assertEquals('Jane Doe', $updated->name);
    }

    public function testDelete(): void
    {
        $user = new TestUser();
        $user->fill([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        $user->save();
        
        $id = $user->id;
        $result = $user->delete();
        
        $this->assertTrue($result);
        
        $deleted = TestUser::find($id);
        $this->assertNull($deleted);
    }

    public function testFind(): void
    {
        $this->insertTestData();
        
        $user = TestUser::find(1);
        
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function testFindOrFail(): void
    {
        $this->insertTestData();
        
        $user = TestUser::findOrFail(1);
        $this->assertInstanceOf(TestUser::class, $user);
        
        $this->expectException(\RuntimeException::class);
        TestUser::findOrFail(999);
    }

    public function testAll(): void
    {
        $this->insertTestData();
        
        $users = TestUser::all();
        
        $this->assertInstanceOf(Collection::class, $users);
        $this->assertCount(2, $users);
        $this->assertInstanceOf(TestUser::class, $users[0]);
    }

    public function testWhere(): void
    {
        $this->insertTestData();
        
        $users = TestUser::where('name', 'John Doe');
        
        $this->assertInstanceOf(Collection::class, $users);
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]->name);
    }

    public function testCreate(): void
    {
        $user = TestUser::create([
            'name' => 'New User',
            'email' => 'new@example.com',
            'bio' => 'New bio'
        ]);
        
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertNotNull($user->id);
        $this->assertEquals('New User', $user->name);
        $this->assertFalse($user->isDirty());
    }

    // Note: firstOrCreate method is not implemented in the current ActiveRecord

    public function testUpdateOrCreate(): void
    {
        // Test create new
        $user1 = TestUser::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'bio' => 'Test bio']
        );
        
        $this->assertInstanceOf(TestUser::class, $user1);
        $this->assertEquals('Test User', $user1->name);
        
        // Test update existing
        $user2 = TestUser::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Updated User', 'bio' => 'Updated bio']
        );
        
        $this->assertEquals($user1->id, $user2->id);
        $this->assertEquals('Updated User', $user2->name);
    }

    public function testToArray(): void
    {
        $user = new TestUser();
        $user->fill([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'bio' => 'Developer'
        ]);
        
        $array = $user->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('John Doe', $array['name']);
        $this->assertEquals('john@example.com', $array['email']);
        $this->assertEquals('Developer', $array['bio']);
    }

    public function testToJson(): void
    {
        $user = new TestUser();
        $user->fill([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $json = $user->toJson();
        $decoded = json_decode($json, true);
        
        $this->assertIsString($json);
        $this->assertEquals('John Doe', $decoded['name']);
        $this->assertEquals('john@example.com', $decoded['email']);
    }

    // Note: hasMany and belongsTo relationship methods are not implemented in the current ActiveRecord
    // These would need to be added to support ORM relationships

    // Note: getOriginal() method is not public in the current ActiveRecord implementation
    // The original attributes are tracked internally but not exposed via public API

    public function testGetDirty(): void
    {
        $user = new TestUser();
        $user->fill(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->save();
        
        $user->name = 'Jane Doe';
        $user->bio = 'New bio';
        
        $dirty = $user->getDirty();
        
        $this->assertArrayHasKey('name', $dirty);
        $this->assertArrayHasKey('bio', $dirty);
        $this->assertArrayNotHasKey('email', $dirty);
        $this->assertEquals('Jane Doe', $dirty['name']);
    }

    // Note: exists() method is not public in the current ActiveRecord implementation
    // The exists property is tracked internally but not exposed via public API

    private function createTestTables(): void
    {
        $this->connection->statement("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                bio TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ");
        
        $this->connection->statement("
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                title TEXT NOT NULL,
                content TEXT,
                created_at TEXT,
                updated_at TEXT,
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
            "INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)",
            [1, 'First Post', 'This is the first post']
        );
    }
}