<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use LengthOfRope\TreeHouse\Events\Event;
use LengthOfRope\TreeHouse\Events\SyncEventDispatcher;
use LengthOfRope\TreeHouse\Events\EventListener;
use LengthOfRope\TreeHouse\Events\AbstractEventListener;
use LengthOfRope\TreeHouse\Events\Exceptions\EventException;
use LengthOfRope\TreeHouse\Foundation\Container;
use Tests\TestCase;

/**
 * Synchronous Event Dispatcher Tests
 * 
 * @package Tests\Unit\Events
 */
class SyncEventDispatcherTest extends TestCase
{
    private SyncEventDispatcher $dispatcher;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        $this->dispatcher = new SyncEventDispatcher($this->container);
    }

    public function testDispatcherCreation()
    {
        $dispatcher = new SyncEventDispatcher();
        $this->assertInstanceOf(SyncEventDispatcher::class, $dispatcher);
        
        $dispatcher = new SyncEventDispatcher($this->container);
        $this->assertInstanceOf(SyncEventDispatcher::class, $dispatcher);
    }

    public function testListenAndDispatch()
    {
        $event = new TestEvent();
        $called = false;
        
        $this->dispatcher->listen(TestEvent::class, function ($e) use (&$called) {
            $called = true;
            $this->assertInstanceOf(TestEvent::class, $e);
        });
        
        $this->assertTrue($this->dispatcher->hasListeners(TestEvent::class));
        
        $result = $this->dispatcher->dispatch($event);
        $this->assertTrue($called);
        $this->assertSame($event, $result);
    }

    public function testMultipleListeners()
    {
        $event = new TestEvent();
        $calls = [];
        
        $this->dispatcher->listen(TestEvent::class, function () use (&$calls) {
            $calls[] = 'listener1';
        });
        
        $this->dispatcher->listen(TestEvent::class, function () use (&$calls) {
            $calls[] = 'listener2';
        });
        
        $this->dispatcher->dispatch($event);
        
        $this->assertCount(2, $calls);
        $this->assertContains('listener1', $calls);
        $this->assertContains('listener2', $calls);
    }

    public function testListenerPriority()
    {
        $event = new TestEvent();
        $calls = [];
        
        // Add listeners with different priorities
        $this->dispatcher->listen(TestEvent::class, function () use (&$calls) {
            $calls[] = 'low';
        }, 1);
        
        $this->dispatcher->listen(TestEvent::class, function () use (&$calls) {
            $calls[] = 'high';
        }, 10);
        
        $this->dispatcher->listen(TestEvent::class, function () use (&$calls) {
            $calls[] = 'medium';
        }, 5);
        
        $this->dispatcher->dispatch($event);
        
        // Higher priority should execute first
        $this->assertEquals(['high', 'medium', 'low'], $calls);
    }

    public function testStopPropagation()
    {
        $event = new TestEvent();
        $calls = [];
        
        $this->dispatcher->listen(TestEvent::class, function ($e) use (&$calls) {
            $calls[] = 'first';
            $e->stopPropagation();
        }, 10);
        
        $this->dispatcher->listen(TestEvent::class, function () use (&$calls) {
            $calls[] = 'second';
        }, 5);
        
        $this->dispatcher->dispatch($event);
        
        $this->assertEquals(['first'], $calls);
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testUntilMethod()
    {
        $event = new TestEvent();
        
        $this->dispatcher->listen(TestEvent::class, function () {
            return null; // Continue
        }, 10);
        
        $this->dispatcher->listen(TestEvent::class, function () {
            return 'result'; // Stop here
        }, 5);
        
        $this->dispatcher->listen(TestEvent::class, function () {
            return 'should not reach'; // Should not be called
        }, 1);
        
        $result = $this->dispatcher->until($event);
        $this->assertEquals('result', $result);
    }

    public function testUntilWithoutResult()
    {
        $event = new TestEvent();
        
        $this->dispatcher->listen(TestEvent::class, function () {
            return null;
        });
        
        $result = $this->dispatcher->until($event);
        $this->assertNull($result);
    }

    public function testEventListenerInterface()
    {
        $event = new TestEvent();
        $listener = new TestEventListener();
        
        $this->dispatcher->listen(TestEvent::class, $listener);
        $this->dispatcher->dispatch($event);
        
        $this->assertTrue($listener->wasCalled());
    }

    public function testEventListenerClassString()
    {
        $event = new TestEvent();
        $this->container->bind(TestEventListener::class, fn() => new TestEventListener());
        
        $this->dispatcher->listen(TestEvent::class, TestEventListener::class);
        $this->dispatcher->dispatch($event);
        
        // Since we can't easily access the instance, we'll just ensure no exception is thrown
        $this->assertTrue(true);
    }

    public function testForgetListeners()
    {
        $this->dispatcher->listen(TestEvent::class, function () {});
        $this->assertTrue($this->dispatcher->hasListeners(TestEvent::class));
        
        $this->dispatcher->forget(TestEvent::class);
        $this->assertFalse($this->dispatcher->hasListeners(TestEvent::class));
    }

    public function testRemoveSpecificListener()
    {
        $listener1 = function () { return 'listener1'; };
        $listener2 = function () { return 'listener2'; };
        
        $this->dispatcher->listen(TestEvent::class, $listener1);
        $this->dispatcher->listen(TestEvent::class, $listener2);
        
        $this->assertTrue($this->dispatcher->removeListener(TestEvent::class, $listener1));
        $this->assertFalse($this->dispatcher->removeListener(TestEvent::class, $listener1)); // Already removed
        
        $listeners = $this->dispatcher->getListeners(TestEvent::class);
        $this->assertCount(1, $listeners);
    }

    public function testGetEventClasses()
    {
        $this->dispatcher->listen(TestEvent::class, function () {});
        $this->dispatcher->listen(AnotherTestEvent::class, function () {});
        
        $eventClasses = $this->dispatcher->getEventClasses();
        $this->assertContains(TestEvent::class, $eventClasses);
        $this->assertContains(AnotherTestEvent::class, $eventClasses);
    }

    public function testGetStatistics()
    {
        $this->dispatcher->listen(TestEvent::class, function () {});
        $this->dispatcher->listen(TestEvent::class, function () {});
        $this->dispatcher->listen(AnotherTestEvent::class, function () {});
        
        $stats = $this->dispatcher->getStatistics();
        
        $this->assertEquals(2, $stats['total_events']);
        $this->assertEquals(3, $stats['total_listeners']);
        $this->assertEquals(2, $stats['event_counts'][TestEvent::class]);
        $this->assertEquals(1, $stats['event_counts'][AnotherTestEvent::class]);
    }

    public function testClearCache()
    {
        $this->dispatcher->listen(TestEvent::class, function () {});
        
        // Trigger cache population
        $this->dispatcher->getListeners(TestEvent::class);
        
        $statsBefore = $this->dispatcher->getStatistics();
        $this->assertEquals(1, $statsBefore['cached_events']);
        
        $this->dispatcher->clearCache();
        
        $statsAfter = $this->dispatcher->getStatistics();
        $this->assertEquals(0, $statsAfter['cached_events']);
    }

    public function testInvalidListenerThrowsException()
    {
        $this->expectException(EventException::class);
        
        $event = new TestEvent();
        $this->dispatcher->listen(TestEvent::class, 'NonExistentClass');
        $this->dispatcher->dispatch($event);
    }

    public function testListenerErrorHandling()
    {
        $event = new TestEvent();
        $called = false;
        
        // Add a listener that throws an exception
        $this->dispatcher->listen(TestEvent::class, function () {
            throw new \Exception('Test exception');
        }, 10);
        
        // Add another listener that should still be called
        $this->dispatcher->listen(TestEvent::class, function () use (&$called) {
            $called = true;
        }, 5);
        
        // Dispatch should not throw, but continue with other listeners
        $this->dispatcher->dispatch($event);
        $this->assertTrue($called);
    }
}

/**
 * Test Event for testing purposes
 */
class TestEvent extends Event
{
    public function __construct(array $context = [])
    {
        parent::__construct($context);
    }
}

/**
 * Another Test Event for testing purposes
 */
class AnotherTestEvent extends Event
{
    public function __construct(array $context = [])
    {
        parent::__construct($context);
    }
}

/**
 * Test Event Listener for testing purposes
 */
class TestEventListener implements EventListener
{
    private bool $called = false;

    public function handle(object $event): mixed
    {
        $this->called = true;
        return null;
    }

    public function shouldQueue(): bool
    {
        return false;
    }

    public function getQueue(): ?string
    {
        return null;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function canHandle(object $event): bool
    {
        return $event instanceof TestEvent;
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }
}