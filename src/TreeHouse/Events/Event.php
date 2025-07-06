<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events;

/**
 * Base Event Class
 * 
 * Provides common functionality for all events in the TreeHouse framework.
 * Includes event metadata, propagation control, and serialization support.
 * 
 * @package LengthOfRope\TreeHouse\Events
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
abstract class Event
{
    /**
     * Timestamp when the event was created
     */
    public readonly float $timestamp;

    /**
     * Unique identifier for this event instance
     */
    public readonly string $eventId;

    /**
     * Whether event propagation has been stopped
     */
    protected bool $propagationStopped = false;

    /**
     * Additional context data for the event
     */
    protected array $context = [];

    /**
     * Create a new event instance
     * 
     * @param array $context Additional context data
     */
    public function __construct(array $context = [])
    {
        $this->timestamp = microtime(true);
        $this->eventId = uniqid('event_', true);
        $this->context = $context;
    }

    /**
     * Stop the propagation of this event to remaining listeners
     * 
     * @return void
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Check if event propagation has been stopped
     * 
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Get event context data
     * 
     * @param string|null $key Specific context key, or null for all context
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function getContext(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->context;
        }

        return $this->context[$key] ?? $default;
    }

    /**
     * Set context data
     * 
     * @param string|array $key Context key or array of key-value pairs
     * @param mixed $value Context value (ignored if key is array)
     * @return static
     */
    public function setContext(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->context = array_merge($this->context, $key);
        } else {
            $this->context[$key] = $value;
        }

        return $this;
    }

    /**
     * Get the event name (class name without namespace)
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return basename(str_replace('\\', '/', static::class));
    }

    /**
     * Get the full event class name
     * 
     * @return string
     */
    public function getEventClass(): string
    {
        return static::class;
    }

    /**
     * Convert the event to an array representation
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_class' => $this->getEventClass(),
            'event_name' => $this->getEventName(),
            'timestamp' => $this->timestamp,
            'context' => $this->context,
            'propagation_stopped' => $this->propagationStopped,
        ];
    }

    /**
     * Convert the event to JSON representation
     * 
     * @param int $options JSON encoding options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * String representation of the event
     * 
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s [%s] at %s',
            $this->getEventName(),
            $this->eventId,
            date('Y-m-d H:i:s', (int) $this->timestamp)
        );
    }
}