<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use LengthOfRope\TreeHouse\Foundation\Application;
use LengthOfRope\TreeHouse\Events\EventDispatcher;
use LengthOfRope\TreeHouse\Events\SyncEventDispatcher;
use LengthOfRope\TreeHouse\Events\Events\ModelCreating;
use LengthOfRope\TreeHouse\Events\Events\ModelCreated;
use LengthOfRope\TreeHouse\Database\ActiveRecord;
use Tests\TestCase;

/**
 * Event System Integration Tests
 * 
 * Tests the complete integration of the event system with the TreeHouse framework.
 * 
 * @package Tests\Unit\Events
 */
class EventIntegrationTest extends TestCase
{
    private Application $app;
    private string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testBasePath = sys_get_temp_dir() . '/treehouse_integration_test_' . uniqid();
        if (!is_dir($this->testBasePath)) {
            mkdir($this->testBasePath, 0755, true);
        }
        
        // Create config directory
        $configDir = $this->testBasePath . '/config';
        mkdir($configDir, 0755, true);
        
        // Create events config
        file_put_contents($configDir . '/events.php', '<?php return ["default_dispatcher" => "sync"];');
        
        $this->app = new Application($this->testBasePath);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testBasePath)) {
            $this->removeDirectory($this->testBasePath);
        }
        
        parent::tearDown();
    }

    public function testEventSystemRegistration()
    {
        $this->assertTrue($this->app->getContainer()->bound('events'));
        
        $dispatcher = $this->app->make('events');
        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
        $this->assertInstanceOf(SyncEventDispatcher::class, $dispatcher);
    }

    public function testActiveRecordEventDispatcherIntegration()
    {
        $dispatcher = ActiveRecord::getEventDispatcher();
        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
        
        // Should be the same instance as the one in the container
        $containerDispatcher = $this->app->make('events');
        $this->assertSame($dispatcher, $containerDispatcher);
    }

    public function testEventHelperFunctions()
    {
        $events = [];
        
        // Test listen helper function
        listen(ModelCreating::class, function ($event) use (&$events) {
            $events[] = 'creating';
            $this->assertInstanceOf(ModelCreating::class, $event);
        });
        
        listen(ModelCreated::class, function ($event) use (&$events) {
            $events[] = 'created';
            $this->assertInstanceOf(ModelCreated::class, $event);
        });
        
        // Test event helper function
        $model = new IntegrationTestModel(['name' => 'Test']);
        
        $creatingEvent = new ModelCreating($model);
        $dispatchedEvent = event($creatingEvent);
        $this->assertSame($creatingEvent, $dispatchedEvent);
        
        $createdEvent = new ModelCreated($model);
        event($createdEvent);
        
        $this->assertEquals(['creating', 'created'], $events);
    }

    public function testUntilHelperFunction()
    {
        listen(ModelCreating::class, function () {
            return null; // Continue
        });
        
        listen(ModelCreating::class, function () {
            return 'stopped'; // Stop here
        });
        
        listen(ModelCreating::class, function () {
            return 'should not reach'; // Should not be called
        });
        
        $model = new IntegrationTestModel(['name' => 'Test']);
        $event = new ModelCreating($model);
        
        $result = until($event);
        $this->assertEquals('stopped', $result);
    }

    public function testAppHelperFunction()
    {
        $app = app();
        $this->assertSame($this->app, $app);
        
        $dispatcher = app('events');
        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
    }

    public function testCacheHelperFunction()
    {
        $cache = cache();
        $this->assertInstanceOf(\LengthOfRope\TreeHouse\Cache\CacheManager::class, $cache);
    }

    public function testViewHelperFunction()
    {
        $view = view();
        $this->assertInstanceOf(\LengthOfRope\TreeHouse\View\ViewFactory::class, $view);
    }

    public function testEventSystemWithoutConfiguration()
    {
        // Remove events config
        unlink($this->testBasePath . '/config/events.php');
        
        // Create new app without events config
        $app = new Application($this->testBasePath);
        
        // Should still work with defaults
        $this->assertTrue($app->getContainer()->bound('events'));
        $dispatcher = $app->make('events');
        $this->assertInstanceOf(SyncEventDispatcher::class, $dispatcher);
    }

    public function testEventStatistics()
    {
        $dispatcher = $this->app->make('events');
        
        listen(ModelCreating::class, function () {});
        listen(ModelCreating::class, function () {});
        listen(ModelCreated::class, function () {});
        
        $stats = $dispatcher->getStatistics();
        
        $this->assertEquals(2, $stats['total_events']);
        $this->assertEquals(3, $stats['total_listeners']);
        $this->assertEquals(2, $stats['event_counts'][ModelCreating::class]);
        $this->assertEquals(1, $stats['event_counts'][ModelCreated::class]);
    }

    public function testEventCaching()
    {
        $dispatcher = $this->app->make('events');
        
        listen(ModelCreating::class, function () {});
        
        // First call should populate cache
        $listeners1 = $dispatcher->getListeners(ModelCreating::class);
        $this->assertCount(1, $listeners1);
        
        // Second call should use cache
        $listeners2 = $dispatcher->getListeners(ModelCreating::class);
        $this->assertSame($listeners1, $listeners2);
        
        // Clear cache
        $dispatcher->clearCache();
        $stats = $dispatcher->getStatistics();
        $this->assertEquals(0, $stats['cached_events']);
    }

    /**
     * Recursively remove directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

/**
 * Test Model for integration testing
 */
class IntegrationTestModel extends ActiveRecord
{
    protected string $table = 'integration_test_models';
    protected array $fillable = ['name', 'email'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
}