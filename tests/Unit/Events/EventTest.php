<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use LengthOfRope\TreeHouse\Events\Event;
use Tests\TestCase;

/**
 * Event Tests
 * 
 * @package Tests\Unit\Events
 */
class EventTest extends TestCase
{
    public function testEventCreation()
    {
        $event = new class extends Event {};
        
        $this->assertInstanceOf(Event::class, $event);
        $this->assertIsFloat($event->timestamp);
        $this->assertIsString($event->eventId);
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testEventWithContext()
    {
        $context = ['user_id' => 123, 'action' => 'test'];
        $event = new class($context) extends Event {};
        
        $this->assertEquals($context, $event->getContext());
        $this->assertEquals(123, $event->getContext('user_id'));
        $this->assertEquals('test', $event->getContext('action'));
        $this->assertNull($event->getContext('non_existent'));
        $this->assertEquals('default', $event->getContext('non_existent', 'default'));
    }

    public function testSetContext()
    {
        $event = new class extends Event {};
        
        // Set individual context
        $event->setContext('key1', 'value1');
        $this->assertEquals('value1', $event->getContext('key1'));
        
        // Set multiple context at once
        $event->setContext(['key2' => 'value2', 'key3' => 'value3']);
        $this->assertEquals('value2', $event->getContext('key2'));
        $this->assertEquals('value3', $event->getContext('key3'));
        
        // Original context should still be there
        $this->assertEquals('value1', $event->getContext('key1'));
    }

    public function testStopPropagation()
    {
        $event = new class extends Event {};
        
        $this->assertFalse($event->isPropagationStopped());
        
        $event->stopPropagation();
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testEventNames()
    {
        $event = new class extends Event {};
        $eventClass = get_class($event);
        
        $this->assertEquals($eventClass, $event->getEventClass());
        
        // Event name should be the class name without namespace
        $expectedName = basename(str_replace('\\', '/', $eventClass));
        $this->assertEquals($expectedName, $event->getEventName());
    }

    public function testToArray()
    {
        $context = ['test' => 'data'];
        $event = new class($context) extends Event {};
        
        $array = $event->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('event_id', $array);
        $this->assertArrayHasKey('event_class', $array);
        $this->assertArrayHasKey('event_name', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('propagation_stopped', $array);
        
        $this->assertEquals($event->eventId, $array['event_id']);
        $this->assertEquals($event->getEventClass(), $array['event_class']);
        $this->assertEquals($event->getEventName(), $array['event_name']);
        $this->assertEquals($event->timestamp, $array['timestamp']);
        $this->assertEquals($context, $array['context']);
        $this->assertFalse($array['propagation_stopped']);
    }

    public function testToJson()
    {
        $event = new class(['key' => 'value']) extends Event {};
        
        $json = $event->toJson();
        $this->assertIsJson($json);
        
        $decoded = json_decode($json, true);
        $this->assertEquals($event->toArray(), $decoded);
    }

    public function testToString()
    {
        $event = new class extends Event {};
        
        $string = (string) $event;
        $this->assertStringContainsString($event->getEventName(), $string);
        $this->assertStringContainsString($event->eventId, $string);
    }

    public function testEventIdUniqueness()
    {
        $event1 = new class extends Event {};
        $event2 = new class extends Event {};
        
        $this->assertNotEquals($event1->eventId, $event2->eventId);
    }

    public function testTimestampAccuracy()
    {
        $before = microtime(true);
        $event = new class extends Event {};
        $after = microtime(true);
        
        $this->assertGreaterThanOrEqual($before, $event->timestamp);
        $this->assertLessThanOrEqual($after, $event->timestamp);
    }
}