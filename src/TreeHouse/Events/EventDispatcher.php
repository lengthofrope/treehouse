<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events;

/**
 * Event Dispatcher Interface
 * 
 * Defines the contract for event dispatching systems in TreeHouse framework.
 * Supports both synchronous and asynchronous event dispatching patterns.
 * 
 * @package LengthOfRope\TreeHouse\Events
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
interface EventDispatcher
{
    /**
     * Dispatch an event to all registered listeners
     * 
     * @param object $event The event to dispatch
     * @return object The event (potentially modified by listeners)
     */
    public function dispatch(object $event): object;

    /**
     * Dispatch an event until a listener returns a non-null value
     * 
     * @param object $event The event to dispatch
     * @return mixed The first non-null value returned by a listener, or null
     */
    public function until(object $event): mixed;

    /**
     * Register a listener for an event
     *
     * @param string $eventClass The event class name
     * @param callable|string|object $listener The listener callable, class name, or instance
     * @param int $priority Priority for listener execution (higher = earlier)
     * @return void
     */
    public function listen(string $eventClass, callable|string|object $listener, int $priority = 0): void;

    /**
     * Remove all listeners for an event
     * 
     * @param string $eventClass The event class name
     * @return void
     */
    public function forget(string $eventClass): void;

    /**
     * Check if an event has any listeners
     * 
     * @param string $eventClass The event class name
     * @return bool
     */
    public function hasListeners(string $eventClass): bool;

    /**
     * Get all listeners for an event
     * 
     * @param string $eventClass The event class name
     * @return array Array of listeners sorted by priority
     */
    public function getListeners(string $eventClass): array;

    /**
     * Remove a specific listener for an event
     *
     * @param string $eventClass The event class name
     * @param callable|string|object $listener The listener to remove
     * @return bool True if listener was found and removed
     */
    public function removeListener(string $eventClass, callable|string|object $listener): bool;

    /**
     * Get all registered event classes
     * 
     * @return array Array of event class names
     */
    public function getEventClasses(): array;
}