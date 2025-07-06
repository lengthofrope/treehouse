<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events;

/**
 * Abstract Event Listener
 * 
 * Provides common functionality for event listeners with sensible defaults.
 * Handles error management, queue configuration, and event filtering.
 * 
 * @package LengthOfRope\TreeHouse\Events
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
abstract class AbstractEventListener implements EventListener
{
    /**
     * Whether this listener should be queued
     */
    protected bool $shouldQueue = false;

    /**
     * Queue name for asynchronous processing
     */
    protected ?string $queue = null;

    /**
     * Listener priority (higher = executed first)
     */
    protected int $priority = 0;

    /**
     * Event classes this listener can handle
     */
    protected array $handles = [];

    /**
     * Whether to automatically determine handleable events from handle method
     */
    protected bool $autoDetectEvents = true;

    /**
     * Handle the event - to be implemented by concrete classes
     * 
     * @param object $event The event to handle
     * @return mixed Return value from handling the event
     */
    abstract public function handle(object $event): mixed;

    /**
     * Determine if the listener should be queued for asynchronous processing
     * 
     * @return bool
     */
    public function shouldQueue(): bool
    {
        return $this->shouldQueue;
    }

    /**
     * Get the queue name for asynchronous processing
     * 
     * @return string|null Queue name, or null for default queue
     */
    public function getQueue(): ?string
    {
        return $this->queue;
    }

    /**
     * Get the priority for this listener
     * 
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Determine if this listener can handle the given event
     * 
     * @param object $event The event to check
     * @return bool
     */
    public function canHandle(object $event): bool
    {
        $eventClass = get_class($event);

        // If specific handles are defined, check those
        if (!empty($this->handles)) {
            foreach ($this->handles as $handleClass) {
                if ($eventClass === $handleClass || is_subclass_of($eventClass, $handleClass)) {
                    return true;
                }
            }
            return false;
        }

        // If auto-detection is enabled, try to determine from handle method
        if ($this->autoDetectEvents) {
            return $this->autoDetectCanHandle($event);
        }

        // Default: can handle any event
        return true;
    }

    /**
     * Auto-detect if this listener can handle an event based on method signature
     * 
     * @param object $event The event to check
     * @return bool
     */
    protected function autoDetectCanHandle(object $event): bool
    {
        try {
            $reflection = new \ReflectionMethod($this, 'handle');
            $parameters = $reflection->getParameters();

            if (empty($parameters)) {
                return true; // No type hint, assume it can handle anything
            }

            $parameter = $parameters[0];
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType) {
                return true; // No specific type hint
            }

            $typeName = $type->getName();
            $eventClass = get_class($event);

            return $eventClass === $typeName || is_subclass_of($eventClass, $typeName);
        } catch (\ReflectionException $e) {
            return true; // If we can't reflect, assume it can handle the event
        }
    }

    /**
     * Set whether this listener should be queued
     * 
     * @param bool $shouldQueue
     * @return static
     */
    public function setShouldQueue(bool $shouldQueue): static
    {
        $this->shouldQueue = $shouldQueue;
        return $this;
    }

    /**
     * Set the queue name
     * 
     * @param string|null $queue
     * @return static
     */
    public function setQueue(?string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Set the listener priority
     * 
     * @param int $priority
     * @return static
     */
    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Set the event classes this listener can handle
     * 
     * @param array $handles
     * @return static
     */
    public function setHandles(array $handles): static
    {
        $this->handles = $handles;
        return $this;
    }

    /**
     * Add an event class this listener can handle
     * 
     * @param string $eventClass
     * @return static
     */
    public function addHandle(string $eventClass): static
    {
        if (!in_array($eventClass, $this->handles)) {
            $this->handles[] = $eventClass;
        }
        return $this;
    }

    /**
     * Handle errors that occur during event processing
     * 
     * @param \Throwable $error The error that occurred
     * @param object $event The event being processed
     * @return mixed
     */
    protected function handleError(\Throwable $error, object $event): mixed
    {
        // Default behavior: re-throw the error
        // Subclasses can override to implement custom error handling
        throw $error;
    }

    /**
     * Execute the listener with error handling
     * 
     * @param object $event The event to handle
     * @return mixed
     */
    final public function execute(object $event): mixed
    {
        try {
            return $this->handle($event);
        } catch (\Throwable $error) {
            return $this->handleError($error, $event);
        }
    }
}