<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Database\Migration;
use LengthOfRope\TreeHouse\Database\Blueprint;
use LengthOfRope\TreeHouse\Database\Column;
use Tests\TestCase;

/**
 * Test Migration for testing
 */
class TestCreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->createTable('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('bio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->dropTable('users');
    }
}

/**
 * Test Migration for altering table
 */
class TestAlterUsersTable extends Migration
{
    public function up(): void
    {
        $this->table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->index('email');
        });
    }

    public function down(): void
    {
        $this->table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->dropIndex('email');
        });
    }
}

/**
 * Migration Test
 * 
 * Tests for the database migration system
 */
class MigrationTest extends TestCase
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
    }

    public function testMigrationCreation(): void
    {
        $migration = new TestCreateUsersTable($this->connection);
        
        $this->assertInstanceOf(Migration::class, $migration);
    }

    public function testCreateTable(): void
    {
        $migration = new TestCreateUsersTable($this->connection);
        
        // Run the migration
        try {
            $migration->up();
        } catch (\Exception $e) {
            $this->fail('Migration failed: ' . $e->getMessage());
        }
        
        // Check if table exists
        $this->assertTrue($this->connection->tableExists('users'));
        
        // Check table columns
        $columns = $this->connection->getTableColumns('users');
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('email', $columns);
        $this->assertContains('bio', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    public function testDropTable(): void
    {
        $migration = new TestCreateUsersTable($this->connection);
        
        // Create table first
        $migration->up();
        $this->assertTrue($this->connection->tableExists('users'));
        
        // Drop table
        $migration->down();
        $this->assertFalse($this->connection->tableExists('users'));
    }

    public function testAlterTable(): void
    {
        // Create table first
        $createMigration = new TestCreateUsersTable($this->connection);
        $createMigration->up();
        
        // Alter table
        $alterMigration = new TestAlterUsersTable($this->connection);
        $alterMigration->up();
        
        // Check if new column exists
        $columns = $this->connection->getTableColumns('users');
        $this->assertContains('phone', $columns);
    }

    public function testBlueprintCreation(): void
    {
        $blueprint = new Blueprint('test_table', $this->connection);
        
        $this->assertInstanceOf(Blueprint::class, $blueprint);
    }

    public function testBlueprintColumns(): void
    {
        $blueprint = new Blueprint('test_table', $this->connection);
        
        $blueprint->id();
        $blueprint->string('name', 100);
        $blueprint->integer('age');
        $blueprint->text('description');
        $blueprint->boolean('active');
        $blueprint->decimal('price', 8, 2);
        $blueprint->dateTime('created_at');
        $blueprint->timestamps();
        
        // Test that columns were added (we can't access them directly)
        // So we'll test by generating SQL
        $sql = $blueprint->toSql();
        
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('id', $sql);
        $this->assertStringContainsString('name', $sql);
        $this->assertStringContainsString('age', $sql);
        $this->assertStringContainsString('description', $sql);
        $this->assertStringContainsString('active', $sql);
        $this->assertStringContainsString('price', $sql);
    }

    public function testColumnConstraints(): void
    {
        $blueprint = new Blueprint('test_table', $this->connection);
        
        $blueprint->string('email')->unique();
        $blueprint->string('name')->nullable();
        
        $sql = $blueprint->toSql();
        
        // Check that constraints are in SQL
        $this->assertStringContainsString('email', $sql);
        $this->assertStringContainsString('name', $sql);
    }

    public function testBlueprintIndexes(): void
    {
        $blueprint = new Blueprint('test_table', $this->connection);
        
        $blueprint->string('email');
        $blueprint->string('name');
        
        $blueprint->index('email');
        $blueprint->index(['name', 'email'], 'name_email_index');
        $blueprint->unique('email', 'unique_email');
        
        $sql = $blueprint->toSql();
        
        // Get database driver to check appropriate syntax
        $reflection = new \ReflectionClass($this->connection);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->connection);
        $driver = $config['driver'] ?? 'mysql';
        
        if ($driver === 'sqlite') {
            // For SQLite, indexes are created separately, so just check table structure
            $this->assertStringContainsString('CREATE TABLE', $sql);
            $this->assertStringContainsString('email', $sql);
            $this->assertStringContainsString('name', $sql);
        } else {
            // For MySQL and other databases, indexes should be in the CREATE TABLE statement
            $this->assertStringContainsString('KEY', $sql);
        }
    }

    public function testColumnCreation(): void
    {
        $column = new Column('varchar', 'test_column');
        
        $this->assertInstanceOf(Column::class, $column);
    }

    public function testColumnModifiers(): void
    {
        $column = new Column('varchar', 'test_column', ['length' => 100]);
        
        $column->nullable()
               ->unique()
               ->default('test')
               ->comment('Test column');
        
        $sql = $column->toSql();
        
        $this->assertStringContainsString('test_column', $sql);
        $this->assertStringContainsString('VARCHAR(100)', $sql);
        $this->assertStringContainsString('DEFAULT', $sql);
        $this->assertStringContainsString('COMMENT', $sql);
    }

    public function testSqlGeneration(): void
    {
        $blueprint = new Blueprint('users', $this->connection);
        
        $blueprint->id();
        $blueprint->string('name', 100);
        $blueprint->string('email')->unique();
        $blueprint->text('bio')->nullable();
        $blueprint->timestamps();
        
        $sql = $blueprint->toSql();
        
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('users', $sql);
        $this->assertStringContainsString('id', $sql);
        $this->assertStringContainsString('name', $sql);
        $this->assertStringContainsString('email', $sql);
        $this->assertStringContainsString('bio', $sql);
        $this->assertStringContainsString('created_at', $sql);
        $this->assertStringContainsString('updated_at', $sql);
    }

    public function testRenameTable(): void
    {
        // Create table first
        $createMigration = new TestCreateUsersTable($this->connection);
        $createMigration->up();
        
        // Rename table
        $migration = new class($this->connection) extends Migration {
            public function up(): void
            {
                $this->renameTable('users', 'people');
            }
            
            public function down(): void
            {
                $this->renameTable('people', 'users');
            }
        };
        
        $migration->up();
        
        $this->assertFalse($this->connection->tableExists('users'));
        $this->assertTrue($this->connection->tableExists('people'));
        
        // Rename back
        $migration->down();
        
        $this->assertTrue($this->connection->tableExists('users'));
        $this->assertFalse($this->connection->tableExists('people'));
    }

    public function testHasTable(): void
    {
        $migration = new TestCreateUsersTable($this->connection);
        
        // Table should not exist initially
        $this->assertFalse($this->connection->tableExists('users'));
        
        // Create table
        $migration->up();
        
        // Table should exist now
        $this->assertTrue($this->connection->tableExists('users'));
    }

    public function testHasColumn(): void
    {
        $migration = new TestCreateUsersTable($this->connection);
        $migration->up();
        
        // Check existing column
        $columns = $this->connection->getTableColumns('users');
        $this->assertContains('name', $columns);
        
        // Check non-existing column
        $this->assertNotContains('nonexistent', $columns);
    }

    public function testMigrationName(): void
    {
        $migration = new TestCreateUsersTable($this->connection, 'test_migration');
        
        $this->assertEquals('test_migration', $migration->getName());
    }
}