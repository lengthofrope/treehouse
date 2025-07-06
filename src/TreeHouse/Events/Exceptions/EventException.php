<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events\Exceptions;

use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;

/**
 * Event Exception
 * 
 * Base exception for all event-related errors in the TreeHouse framework.
 * Integrates with the framework's error handling system.
 * 
 * @package LengthOfRope\TreeHouse\Events\Exceptions
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
class EventException extends BaseException
{
    /**
     * The event that caused the exception
     */
    protected ?object $event = null;

    /**
     * The listener that caused the exception
     */
    protected mixed $listener = null;

    /**
     * Create a new event exception
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     * @param object|null $event The event that caused the exception
     * @param mixed $listener The listener that caused the exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?object $event = null,
        mixed $listener = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->event = $event;
        $this->listener = $listener;
    }

    /**
     * Get the event that caused the exception
     * 
     * @return object|null
     */
    public function getEvent(): ?object
    {
        return $this->event;
    }

    /**
     * Get the listener that caused the exception
     * 
     * @return mixed
     */
    public function getListener(): mixed
    {
        return $this->listener;
    }

    /**
     * Set the event that caused the exception
     * 
     * @param object|null $event
     * @return static
     */
    public function setEvent(?object $event): static
    {
        $this->event = $event;
        return $this;
    }

    /**
     * Set the listener that caused the exception
     * 
     * @param mixed $listener
     * @return static
     */
    public function setListener(mixed $listener): static
    {
        $this->listener = $listener;
        return $this;
    }

    /**
     * Get additional context for error reporting
     * 
     * @return array
     */
    public function getContext(): array
    {
        $context = parent::getContext();

        if ($this->event !== null) {
            $context['event'] = [
                'class' => get_class($this->event),
                'data' => method_exists($this->event, 'toArray') ? $this->event->toArray() : 'N/A',
            ];
        }

        if ($this->listener !== null) {
            if (is_object($this->listener)) {
                $context['listener'] = [
                    'class' => get_class($this->listener),
                    'type' => 'object',
                ];
            } elseif (is_string($this->listener)) {
                $context['listener'] = [
                    'class' => $this->listener,
                    'type' => 'string',
                ];
            } else {
                $context['listener'] = [
                    'type' => gettype($this->listener),
                    'value' => is_scalar($this->listener) ? $this->listener : 'N/A',
                ];
            }
        }

        return $context;
    }
}