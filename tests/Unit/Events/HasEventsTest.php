<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use LengthOfRope\TreeHouse\Events\SyncEventDispatcher;
use LengthOfRope\TreeHouse\Events\Events\ModelCreating;
use LengthOfRope\TreeHouse\Events\Events\ModelCreated;
use LengthOfRope\TreeHouse\Events\Events\ModelUpdating;
use LengthOfRope\TreeHouse\Events\Events\ModelUpdated;
use LengthOfRope\TreeHouse\Events\Events\ModelDeleting;
use LengthOfRope\TreeHouse\Events\Events\ModelDeleted;
use LengthOfRope\TreeHouse\Events\Events\ModelSaving;
use LengthOfRope\TreeHouse\Events\Events\ModelSaved;
use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Foundation\Container;
use Tests\TestCase;

/**
 * HasEvents Trait Tests
 * 
 * @package Tests\Unit\Events
 */
class HasEventsTest extends TestCase
{
    private SyncEventDispatcher $dispatcher;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        $this->dispatcher = new SyncEventDispatcher($this->container);
        
        // Set the event dispatcher on ActiveRecord
        ActiveRecord::setEventDispatcher($this->dispatcher);
    }

    protected function tearDown(): void
    {
        // Clear the event dispatcher
        ActiveRecord::setEventDispatcher(null);
        parent::tearDown();
    }

    public function testEventDispatcherSetting()
    {
        $dispatcher = new SyncEventDispatcher();
        ActiveRecord::setEventDispatcher($dispatcher);
        
        $this->assertSame($dispatcher, ActiveRecord::getEventDispatcher());
    }

    public function testModelCreationEventsWithoutDatabase()
    {
        $events = [];
        
        $this->dispatcher->listen(ModelSaving::class, function ($event) use (&$events) {
            $events[] = 'saving';
            $this->assertInstanceOf(ModelSaving::class, $event);
        });
        
        $this->dispatcher->listen(ModelCreating::class, function ($event) use (&$events) {
            $events[] = 'creating';
            $this->assertInstanceOf(ModelCreating::class, $event);
        });
        
        $this->dispatcher->listen(ModelCreated::class, function ($event) use (&$events) {
            $events[] = 'created';
            $this->assertInstanceOf(ModelCreated::class, $event);
        });
        
        $this->dispatcher->listen(ModelSaved::class, function ($event) use (&$events) {
            $events[] = 'saved';
            $this->assertInstanceOf(ModelSaved::class, $event);
        });
        
        $model = new TestEventModel(['name' => 'Test']);
        
        // Mock the performInsert method to avoid database operations
        $mockModel = $this->getMockBuilder(TestEventModel::class)
                          ->onlyMethods(['performInsert'])
                          ->setConstructorArgs([['name' => 'Test']])
                          ->getMock();
        
        $mockModel->method('performInsert')->willReturn(true);
        
        // Manually trigger the events as they would be triggered in save()
        $mockModel->fireModelEvent('saving');
        $mockModel->fireModelEvent('creating');
        $mockModel->fireModelEvent('created', false);
        $mockModel->fireModelEvent('saved', false);
        
        $this->assertEquals(['saving', 'creating', 'created', 'saved'], $events);
    }

    public function testEventCancellation()
    {
        $events = [];
        
        // Add a listener that cancels the creating event
        $this->dispatcher->listen(ModelCreating::class, function () use (&$events) {
            $events[] = 'creating-cancelled';
            return false; // Cancel the event
        });
        
        $this->dispatcher->listen(ModelCreated::class, function () use (&$events) {
            $events[] = 'created'; // Should not be called
        });
        
        $model = new TestEventModel(['name' => 'Test']);
        
        // Test the fireModelEvent method directly
        $result = $this->callPrivateMethod($model, 'fireModelEvent', ['creating']);
        
        $this->assertFalse($result);
        $this->assertEquals(['creating-cancelled'], $events);
    }

    public function testEventRegistrationMethods()
    {
        $called = false;
        
        TestEventModel::creating(function () use (&$called) {
            $called = true;
        });
        
        $model = new TestEventModel(['name' => 'Test']);
        
        // Test the fireModelEvent method directly
        $this->callPrivateMethod($model, 'fireModelEvent', ['creating']);
        
        $this->assertTrue($called);
    }

    public function testEventRegistrationWithPriority()
    {
        $events = [];
        
        TestEventModel::creating(function () use (&$events) {
            $events[] = 'low';
        }, 1);
        
        TestEventModel::creating(function () use (&$events) {
            $events[] = 'high';
        }, 10);
        
        $model = new TestEventModel(['name' => 'Test']);
        
        // Test the fireModelEvent method directly
        $this->callPrivateMethod($model, 'fireModelEvent', ['creating']);
        
        $this->assertEquals(['high', 'low'], $events);
    }

    public function testGetModelEvents()
    {
        $events = TestEventModel::getModelEvents();
        
        $this->assertContains('creating', $events);
        $this->assertContains('created', $events);
        $this->assertContains('updating', $events);
        $this->assertContains('updated', $events);
        $this->assertContains('deleting', $events);
        $this->assertContains('deleted', $events);
        $this->assertContains('saving', $events);
        $this->assertContains('saved', $events);
    }

    public function testFlushEventListeners()
    {
        TestEventModel::creating(function () {});
        
        $this->assertTrue($this->dispatcher->hasListeners(ModelCreating::class));
        
        TestEventModel::flushEventListeners();
        
        $this->assertFalse($this->dispatcher->hasListeners(ModelCreating::class));
    }

    public function testWithoutEventDispatcher()
    {
        ActiveRecord::setEventDispatcher(null);
        
        $model = new TestEventModel(['name' => 'Test']);
        
        // Test the fireModelEvent method directly - should return true when no dispatcher
        $result = $this->callPrivateMethod($model, 'fireModelEvent', ['creating']);
        
        $this->assertTrue($result);
    }

    public function testNewModelEvent()
    {
        $model = new TestEventModel(['name' => 'Test']);
        
        $creatingEvent = $this->callPrivateMethod($model, 'newModelEvent', ['creating']);
        $this->assertInstanceOf(ModelCreating::class, $creatingEvent);
        $this->assertSame($model, $creatingEvent->getModel());
        
        $createdEvent = $this->callPrivateMethod($model, 'newModelEvent', ['created']);
        $this->assertInstanceOf(ModelCreated::class, $createdEvent);
        
        $updatingEvent = $this->callPrivateMethod($model, 'newModelEvent', ['updating']);
        $this->assertInstanceOf(ModelUpdating::class, $updatingEvent);
        
        $updatedEvent = $this->callPrivateMethod($model, 'newModelEvent', ['updated']);
        $this->assertInstanceOf(ModelUpdated::class, $updatedEvent);
        
        $deletingEvent = $this->callPrivateMethod($model, 'newModelEvent', ['deleting']);
        $this->assertInstanceOf(ModelDeleting::class, $deletingEvent);
        
        $deletedEvent = $this->callPrivateMethod($model, 'newModelEvent', ['deleted']);
        $this->assertInstanceOf(ModelDeleted::class, $deletedEvent);
        
        $savingEvent = $this->callPrivateMethod($model, 'newModelEvent', ['saving']);
        $this->assertInstanceOf(ModelSaving::class, $savingEvent);
        
        $savedEvent = $this->callPrivateMethod($model, 'newModelEvent', ['saved']);
        $this->assertInstanceOf(ModelSaved::class, $savedEvent);
    }

    public function testInvalidModelEvent()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown model event: invalid');
        
        $model = new TestEventModel(['name' => 'Test']);
        $this->callPrivateMethod($model, 'newModelEvent', ['invalid']);
    }

    public function testEventClassMapping()
    {
        $this->assertEquals(ModelCreating::class, TestEventModel::getEventClassForName('creating'));
        $this->assertEquals(ModelCreated::class, TestEventModel::getEventClassForName('created'));
        $this->assertEquals(ModelUpdating::class, TestEventModel::getEventClassForName('updating'));
        $this->assertEquals(ModelUpdated::class, TestEventModel::getEventClassForName('updated'));
        $this->assertEquals(ModelDeleting::class, TestEventModel::getEventClassForName('deleting'));
        $this->assertEquals(ModelDeleted::class, TestEventModel::getEventClassForName('deleted'));
        $this->assertEquals(ModelSaving::class, TestEventModel::getEventClassForName('saving'));
        $this->assertEquals(ModelSaved::class, TestEventModel::getEventClassForName('saved'));
    }

    public function testInvalidEventClass()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown model event: invalid');
        
        TestEventModel::getEventClassForName('invalid');
    }
}

/**
 * Test Model for event testing
 */
class TestEventModel extends ActiveRecord
{
    protected string $table = 'test_event_models';
    protected array $fillable = ['name', 'email'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
    
    // Make protected methods accessible for testing
    public function fireModelEvent(string $event, bool $halt = true): mixed
    {
        return parent::fireModelEvent($event, $halt);
    }
    
    public static function getEventClassForName(string $event): string
    {
        return parent::getEventClassForName($event);
    }
}