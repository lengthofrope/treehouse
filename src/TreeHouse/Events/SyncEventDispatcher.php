<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events;

use LengthOfRope\TreeHouse\Foundation\Container;
use LengthOfRope\TreeHouse\Events\Exceptions\EventException;

/**
 * Synchronous Event Dispatcher
 * 
 * Dispatches events synchronously to registered listeners in the TreeHouse framework.
 * Provides immediate execution with priority support and propagation control.
 * 
 * @package LengthOfRope\TreeHouse\Events
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
class SyncEventDispatcher implements EventDispatcher
{
    /**
     * Registered event listeners
     *
     * @var array<string, array<array{listener: callable|string|object, priority: int}>>
     */
    private array $listeners = [];

    /**
     * Sorted listeners cache
     * 
     * @var array<string, array<callable|object>>
     */
    private array $sortedListeners = [];

    /**
     * Create a new synchronous event dispatcher
     * 
     * @param Container|null $container Dependency injection container
     */
    public function __construct(
        private ?Container $container = null
    ) {}

    /**
     * Dispatch an event to all registered listeners
     * 
     * @param object $event The event to dispatch
     * @return object The event (potentially modified by listeners)
     */
    public function dispatch(object $event): object
    {
        $eventClass = get_class($event);
        $listeners = $this->getListeners($eventClass);

        foreach ($listeners as $listener) {
            // Check if event propagation has been stopped
            if ($event instanceof Event && $event->isPropagationStopped()) {
                break;
            }

            try {
                $this->callListener($listener, $event);
            } catch (\Throwable $e) {
                // For dispatch(), we continue with other listeners even if one fails
                // This could be configurable in the future
                error_log("Event listener failed: " . $e->getMessage());
            }
        }

        return $event;
    }

    /**
     * Dispatch an event until a listener returns a non-null value
     * 
     * @param object $event The event to dispatch
     * @return mixed The first non-null value returned by a listener, or null
     */
    public function until(object $event): mixed
    {
        $eventClass = get_class($event);
        $listeners = $this->getListeners($eventClass);

        foreach ($listeners as $listener) {
            // Check if event propagation has been stopped
            if ($event instanceof Event && $event->isPropagationStopped()) {
                break;
            }

            try {
                $result = $this->callListener($listener, $event);
                
                if ($result !== null) {
                    return $result;
                }
            } catch (\Throwable $e) {
                // For until(), we also continue with other listeners
                error_log("Event listener failed: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Register a listener for an event
     *
     * @param string $eventClass The event class name
     * @param callable|string|object $listener The listener callable, class name, or instance
     * @param int $priority Priority for listener execution (higher = earlier)
     * @return void
     */
    public function listen(string $eventClass, callable|string|object $listener, int $priority = 0): void
    {
        if (!isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }

        $this->listeners[$eventClass][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];

        // Clear the sorted cache for this event
        unset($this->sortedListeners[$eventClass]);
    }

    /**
     * Remove all listeners for an event
     * 
     * @param string $eventClass The event class name
     * @return void
     */
    public function forget(string $eventClass): void
    {
        unset($this->listeners[$eventClass], $this->sortedListeners[$eventClass]);
    }

    /**
     * Check if an event has any listeners
     * 
     * @param string $eventClass The event class name
     * @return bool
     */
    public function hasListeners(string $eventClass): bool
    {
        return !empty($this->listeners[$eventClass]);
    }

    /**
     * Get all listeners for an event
     * 
     * @param string $eventClass The event class name
     * @return array Array of listeners sorted by priority
     */
    public function getListeners(string $eventClass): array
    {
        if (!isset($this->listeners[$eventClass])) {
            return [];
        }

        // Return cached sorted listeners if available
        if (isset($this->sortedListeners[$eventClass])) {
            return $this->sortedListeners[$eventClass];
        }

        // Sort listeners by priority (higher priority first)
        $listeners = $this->listeners[$eventClass];
        usort($listeners, fn($a, $b) => $b['priority'] <=> $a['priority']);

        // Resolve listeners and cache the result
        $resolvedListeners = [];
        foreach ($listeners as $listenerData) {
            $resolvedListeners[] = $this->resolveListener($listenerData['listener']);
        }

        $this->sortedListeners[$eventClass] = $resolvedListeners;
        return $resolvedListeners;
    }

    /**
     * Remove a specific listener for an event
     * 
     * @param string $eventClass The event class name
     * @param callable|string $listener The listener to remove
     * @return bool True if listener was found and removed
     */
    public function removeListener(string $eventClass, callable|string $listener): bool
    {
        if (!isset($this->listeners[$eventClass])) {
            return false;
        }

        $found = false;
        $this->listeners[$eventClass] = array_filter(
            $this->listeners[$eventClass],
            function ($listenerData) use ($listener, &$found) {
                $isSame = $this->listenersAreSame($listenerData['listener'], $listener);
                if ($isSame) {
                    $found = true;
                    return false;
                }
                return true;
            }
        );

        if ($found) {
            // Clear the sorted cache
            unset($this->sortedListeners[$eventClass]);
        }

        return $found;
    }

    /**
     * Get all registered event classes
     * 
     * @return array Array of event class names
     */
    public function getEventClasses(): array
    {
        return array_keys($this->listeners);
    }

    /**
     * Resolve a listener to a callable
     * 
     * @param callable|string $listener The listener to resolve
     * @return callable|object The resolved listener
     * @throws EventException If the listener cannot be resolved
     */
    private function resolveListener(callable|string $listener): callable|object
    {
        // If it's already callable, return as-is
        if (is_callable($listener)) {
            return $listener;
        }

        // If it's a class name, try to resolve it through the container
        if (is_string($listener) && class_exists($listener)) {
            if ($this->container) {
                try {
                    return $this->container->make($listener);
                } catch (\Throwable $e) {
                    throw new EventException("Failed to resolve event listener [{$listener}]: " . $e->getMessage(), 0, $e);
                }
            } else {
                // No container, try to instantiate directly
                try {
                    return new $listener();
                } catch (\Throwable $e) {
                    throw new EventException("Failed to instantiate event listener [{$listener}]: " . $e->getMessage(), 0, $e);
                }
            }
        }

        throw new EventException("Invalid event listener: " . gettype($listener));
    }

    /**
     * Call a listener with an event
     * 
     * @param callable|object $listener The listener to call
     * @param object $event The event to pass
     * @return mixed The result from the listener
     * @throws EventException If the listener cannot be called
     */
    private function callListener(callable|object $listener, object $event): mixed
    {
        // If it's a callable, call it directly
        if (is_callable($listener)) {
            return $listener($event);
        }

        // If it's an EventListener instance, call the handle method
        if ($listener instanceof EventListener) {
            // Check if the listener can handle this event
            if (!$listener->canHandle($event)) {
                return null;
            }

            return $listener->handle($event);
        }

        // If it's an object with a handle method, call it
        if (is_object($listener) && method_exists($listener, 'handle')) {
            return $listener->handle($event);
        }

        throw new EventException("Listener does not have a callable handle method");
    }

    /**
     * Check if two listeners are the same
     * 
     * @param callable|string $listener1 First listener
     * @param callable|string $listener2 Second listener
     * @return bool
     */
    private function listenersAreSame(callable|string $listener1, callable|string $listener2): bool
    {
        // Both are strings (class names)
        if (is_string($listener1) && is_string($listener2)) {
            return $listener1 === $listener2;
        }

        // Both are the same callable
        if (is_callable($listener1) && is_callable($listener2)) {
            return $listener1 === $listener2;
        }

        return false;
    }

    /**
     * Get statistics about registered listeners
     * 
     * @return array Statistics array
     */
    public function getStatistics(): array
    {
        $totalListeners = 0;
        $eventCounts = [];

        foreach ($this->listeners as $eventClass => $listeners) {
            $count = count($listeners);
            $totalListeners += $count;
            $eventCounts[$eventClass] = $count;
        }

        return [
            'total_events' => count($this->listeners),
            'total_listeners' => $totalListeners,
            'event_counts' => $eventCounts,
            'cached_events' => count($this->sortedListeners),
        ];
    }

    /**
     * Clear all listener caches
     * 
     * @return void
     */
    public function clearCache(): void
    {
        $this->sortedListeners = [];
    }
}