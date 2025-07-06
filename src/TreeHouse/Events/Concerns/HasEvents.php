<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events\Concerns;

use LengthOfRope\TreeHouse\Events\EventDispatcher;
use LengthOfRope\TreeHouse\Events\Events\ModelCreating;
use LengthOfRope\TreeHouse\Events\Events\ModelCreated;
use LengthOfRope\TreeHouse\Events\Events\ModelUpdating;
use LengthOfRope\TreeHouse\Events\Events\ModelUpdated;
use LengthOfRope\TreeHouse\Events\Events\ModelDeleting;
use LengthOfRope\TreeHouse\Events\Events\ModelDeleted;
use LengthOfRope\TreeHouse\Events\Events\ModelSaving;
use LengthOfRope\TreeHouse\Events\Events\ModelSaved;

/**
 * HasEvents Trait
 * 
 * Provides event firing capabilities for ActiveRecord models.
 * Automatically fires lifecycle events during model operations.
 * 
 * @package LengthOfRope\TreeHouse\Events\Concerns
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
trait HasEvents
{
    /**
     * The event dispatcher instance
     */
    protected static ?EventDispatcher $dispatcher = null;

    /**
     * Model events that should be fired
     */
    protected static array $modelEvents = [
        'creating', 'created', 'updating', 'updated', 
        'deleting', 'deleted', 'saving', 'saved'
    ];

    /**
     * User-defined event observers
     */
    protected static array $observersBooted = [];

    /**
     * Set the event dispatcher instance
     * 
     * @param EventDispatcher|null $dispatcher
     * @return void
     */
    public static function setEventDispatcher(?EventDispatcher $dispatcher): void
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Get the event dispatcher instance
     * 
     * @return EventDispatcher|null
     */
    public static function getEventDispatcher(): ?EventDispatcher
    {
        return static::$dispatcher;
    }

    /**
     * Fire a model event
     * 
     * @param string $event Event name
     * @param bool $halt Whether to halt on first false result
     * @return mixed
     */
    protected function fireModelEvent(string $event, bool $halt = true): mixed
    {
        if (!isset(static::$dispatcher)) {
            return true;
        }

        $method = $halt ? 'until' : 'dispatch';
        $eventObject = $this->newModelEvent($event);
        
        $result = static::$dispatcher->{$method}($eventObject);

        return $halt ? $result !== false : $result;
    }

    /**
     * Create a new model event instance
     * 
     * @param string $event Event name
     * @return object
     * @throws \InvalidArgumentException If the event is unknown
     */
    protected function newModelEvent(string $event): object
    {
        return match ($event) {
            'creating' => new ModelCreating($this),
            'created' => new ModelCreated($this),
            'updating' => new ModelUpdating($this),
            'updated' => new ModelUpdated($this),
            'deleting' => new ModelDeleting($this),
            'deleted' => new ModelDeleted($this),
            'saving' => new ModelSaving($this),
            'saved' => new ModelSaved($this),
            default => throw new \InvalidArgumentException("Unknown model event: {$event}")
        };
    }

    /**
     * Register a model event listener
     * 
     * @param string $event Event name
     * @param callable|string|object $callback Event listener
     * @param int $priority Event priority
     * @return void
     */
    public static function registerModelEvent(string $event, callable|string|object $callback, int $priority = 0): void
    {
        if (!isset(static::$dispatcher)) {
            return;
        }

        $eventClass = static::getEventClassForName($event);
        static::$dispatcher->listen($eventClass, $callback, $priority);
    }

    /**
     * Register a creating event listener
     * 
     * @param callable|string|object $callback Event listener
     * @param int $priority Event priority
     * @return void
     */
    public static function creating(callable|string|object $callback, int $priority = 0): void
    {
        static::registerModelEvent('creating', $callback, $priority);
    }

    /**
     * Register a created event listener
     * 
     * @param callable|string|object $callback Event listener
     * @param int $priority Event priority
     * @return void
     */
    public static function created(callable|string|object $callback, int $priority = 0): void
    {
        static::registerModelEvent('created', $callback, $priority);
    }

    /**
     * Register an updating event listener
     * 
     * @param callable|string|object $callback Event listener
     * @param int $priority Event priority
     * @return void
     */
    public static function updating(callable|string|object $callback, int $priority = 0): void
    {
        static::registerModelEvent('updating', $callback, $priority);
    }

    /**
     * Register an updated event listener
     * 
     * @param callable|string|object $callback Event listener
     * @param int $priority Event priority
     * @return void
     */
    public static function updated(callable|string|object $callback, int $priority = 0): void
    {
        static::registerModelEvent('updated', $callback, $priority);
    }

    /**
     * Register a deleting event listener
     * 
     * @param callable|string|object $callback Event listener
     * @param int $priority Event priority
     * @return void
     */
    public static function deleting(callable|string|object $callback, int $priority = 0): void
    {
        static::registerModelEvent('deleting', $callback, $priority);
    }

    /**
     * Register a deleted event listener
     * 
     * @param callable|string|object $callback Event listener
     * @param int $priority Event priority
     * @return void
     */
    public static function deleted(callable|string|object $callback, int $priority = 0): void
    {
        static::registerModelEvent('deleted', $callback, $priority);
    }

    /**
     * Register a saving event listener
     * 
     * @param callable|string|object $callback Event listener
     * @param int $priority Event priority
     * @return void
     */
    public static function saving(callable|string|object $callback, int $priority = 0): void
    {
        static::registerModelEvent('saving', $callback, $priority);
    }

    /**
     * Register a saved event listener
     * 
     * @param callable|string|object $callback Event listener
     * @param int $priority Event priority
     * @return void
     */
    public static function saved(callable|string|object $callback, int $priority = 0): void
    {
        static::registerModelEvent('saved', $callback, $priority);
    }

    /**
     * Get the event class name for an event name
     * 
     * @param string $event Event name
     * @return string Event class name
     * @throws \InvalidArgumentException If the event is unknown
     */
    protected static function getEventClassForName(string $event): string
    {
        return match ($event) {
            'creating' => ModelCreating::class,
            'created' => ModelCreated::class,
            'updating' => ModelUpdating::class,
            'updated' => ModelUpdated::class,
            'deleting' => ModelDeleting::class,
            'deleted' => ModelDeleted::class,
            'saving' => ModelSaving::class,
            'saved' => ModelSaved::class,
            default => throw new \InvalidArgumentException("Unknown model event: {$event}")
        };
    }

    /**
     * Get all model events
     * 
     * @return array
     */
    public static function getModelEvents(): array
    {
        return static::$modelEvents;
    }

    /**
     * Remove all event listeners for this model
     * 
     * @return void
     */
    public static function flushEventListeners(): void
    {
        if (!isset(static::$dispatcher)) {
            return;
        }

        foreach (static::$modelEvents as $event) {
            $eventClass = static::getEventClassForName($event);
            static::$dispatcher->forget($eventClass);
        }
    }

    /**
     * Boot the trait by registering model observers
     * 
     * @return void
     */
    protected static function bootHasEvents(): void
    {
        $class = static::class;
        
        if (isset(static::$observersBooted[$class])) {
            return;
        }

        static::$observersBooted[$class] = true;

        // Register observers if defined
        if (method_exists(static::class, 'observe')) {
            static::observe();
        }
    }
}