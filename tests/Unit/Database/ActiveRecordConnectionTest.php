<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Foundation\Application;

class ActiveRecordConnectionTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create application instance with test database config
        $this->app = new Application(__DIR__ . '/../../..');
        
        // Set test database configuration
        $this->app->setConfig('database', [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
        ]);
    }

    public function testActiveRecordGetsConnectionFromApplication(): void
    {
        // Load configuration into the application
        $this->app->loadConfiguration(__DIR__ . '/../../..');
        
        // Create a test model class
        $model = new class extends ActiveRecord {
            protected string $table = 'test_table';
        };

        // The connection should be automatically set by the application
        $connection = $model::getConnection();
        
        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testActiveRecordConnectionCanBeSetManually(): void
    {
        // Reset static connection to null
        ActiveRecord::setConnection(null);
        
        // Create a manual connection
        $connection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        // Set the connection manually
        ActiveRecord::setConnection($connection);
        
        // Create a test model class
        $model = new class extends ActiveRecord {
            protected string $table = 'test_table';
        };

        // The connection should be available
        $retrievedConnection = $model::getConnection();
        
        $this->assertInstanceOf(Connection::class, $retrievedConnection);
        $this->assertSame($connection, $retrievedConnection);
    }

    protected function tearDown(): void
    {
        // Reset static state
        ActiveRecord::setConnection(null);
        
        parent::tearDown();
    }
}