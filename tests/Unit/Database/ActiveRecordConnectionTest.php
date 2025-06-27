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
        
        // Force initialization of database service
        $dbConnection = $this->app->make('db');
        
        // Create a test model class
        $model = new class extends ActiveRecord {
            protected string $table = 'test_table';
        };

        // The connection should be automatically resolved
        $connection = $model::getConnection();
        
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertEquals($dbConnection, $connection);
    }

    public function testActiveRecordCanResolveConnectionFromConfig(): void
    {
        // Reset static connection to null
        ActiveRecord::setConnection(null);
        ActiveRecord::setApplication(null);
        
        // Create a temporary working directory with config subdirectory
        $tempDir = sys_get_temp_dir() . '/treehouse_test_' . uniqid();
        $configDir = $tempDir . '/config';
        
        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }
        
        $configContent = '<?php return [
            "default" => "sqlite",
            "connections" => [
                "sqlite" => [
                    "driver" => "sqlite",
                    "database" => ":memory:",
                ],
            ],
        ];';
        
        file_put_contents($configDir . '/database.php', $configContent);
        
        // Change working directory temporarily
        $originalCwd = getcwd();
        chdir($tempDir);
        
        try {
            // Create a test model class
            $model = new class extends ActiveRecord {
                protected string $table = 'test_table';
            };

            // The connection should be automatically resolved from config
            $connection = $model::getConnection();
            
            $this->assertInstanceOf(Connection::class, $connection);
        } finally {
            // Restore original working directory
            chdir($originalCwd);
            
            // Clean up
            unlink($configDir . '/database.php');
            rmdir($configDir);
            rmdir($tempDir);
        }
    }

    protected function tearDown(): void
    {
        // Reset static state
        ActiveRecord::setConnection(null);
        ActiveRecord::setApplication(null);
        
        parent::tearDown();
    }
}