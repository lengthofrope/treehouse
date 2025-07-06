<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use LengthOfRope\TreeHouse\Events\ModelEvent;
use LengthOfRope\TreeHouse\Events\Events\ModelCreating;
use LengthOfRope\TreeHouse\Events\Events\ModelCreated;
use LengthOfRope\TreeHouse\Database\ActiveRecord;
use Tests\TestCase;

/**
 * Model Event Tests
 * 
 * @package Tests\Unit\Events
 */
class ModelEventTest extends TestCase
{
    private TestModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new TestModel(['name' => 'Test', 'email' => 'test@example.com']);
    }

    public function testModelEventCreation()
    {
        $event = new ModelCreating($this->model);
        
        $this->assertInstanceOf(ModelEvent::class, $event);
        $this->assertSame($this->model, $event->getModel());
        $this->assertEquals(TestModel::class, $event->getModelClass());
    }

    public function testModelEventWithContext()
    {
        $context = ['action' => 'create', 'source' => 'api'];
        $event = new ModelCreating($this->model, $context);
        
        $this->assertEquals($context, $event->getContext());
        $this->assertEquals('create', $event->getContext('action'));
        $this->assertEquals('api', $event->getContext('source'));
    }

    public function testModelEventMetadata()
    {
        $event = new ModelCreating($this->model);
        
        $this->assertEquals(TestModel::class, $event->getModelClass());
        $this->assertEquals('test_models', $event->getModelTable());
        $this->assertFalse($event->modelExists());
    }

    public function testModelEventWithExistingModel()
    {
        // Mark model as existing
        $this->setPrivateProperty($this->model, 'exists', true);
        $this->setPrivateProperty($this->model, 'attributes', ['id' => 1, 'name' => 'Test', 'email' => 'test@example.com']);
        
        $event = new ModelCreating($this->model);
        
        $this->assertTrue($event->modelExists());
        $this->assertEquals(1, $event->getModelKey());
    }

    public function testModelEventAttributes()
    {
        $event = new ModelCreating($this->model);
        
        $attributes = $event->getModelAttributes();
        $this->assertEquals('Test', $attributes['name']);
        $this->assertEquals('test@example.com', $attributes['email']);
        
        $this->assertEquals('Test', $event->getModelAttribute('name'));
        $this->assertEquals('test@example.com', $event->getModelAttribute('email'));
        $this->assertNull($event->getModelAttribute('non_existent'));
    }

    public function testModelEventToArray()
    {
        $event = new ModelCreating($this->model);
        $array = $event->toArray();
        
        $this->assertArrayHasKey('model', $array);
        $this->assertArrayHasKey('event_id', $array);
        $this->assertArrayHasKey('event_class', $array);
        $this->assertArrayHasKey('timestamp', $array);
        
        $modelData = $array['model'];
        $this->assertEquals(TestModel::class, $modelData['class']);
        $this->assertEquals('test_models', $modelData['table']);
        $this->assertFalse($modelData['exists']);
        $this->assertIsArray($modelData['attributes']);
    }

    public function testModelEventToString()
    {
        $event = new ModelCreating($this->model);
        $string = (string) $event;
        
        $this->assertStringContainsString('ModelCreating', $string);
        $this->assertStringContainsString(TestModel::class, $string);
        $this->assertStringContainsString($event->eventId, $string);
    }

    public function testCancellableEvents()
    {
        $creatingEvent = new ModelCreating($this->model);
        $this->assertTrue($creatingEvent->isCancellable());
        
        $createdEvent = new ModelCreated($this->model);
        $this->assertFalse($createdEvent->isCancellable());
    }

    public function testStopPropagation()
    {
        $event = new ModelCreating($this->model);
        
        $this->assertFalse($event->isPropagationStopped());
        
        $event->stopPropagation();
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testEventInheritance()
    {
        $event = new ModelCreating($this->model);
        
        $this->assertInstanceOf(ModelEvent::class, $event);
        $this->assertInstanceOf(\LengthOfRope\TreeHouse\Events\Event::class, $event);
    }
}

/**
 * Test Model for testing purposes
 */
class TestModel extends ActiveRecord
{
    protected string $table = 'test_models';
    protected array $fillable = ['name', 'email'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
}