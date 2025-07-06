<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events\Events;

use LengthOfRope\TreeHouse\Events\ModelEvent;
use LengthOfRope\TreeHouse\Database\ActiveRecord;

/**
 * Model Creating Event
 * 
 * Fired before a model is created (inserted) in the database.
 * This event can be cancelled by returning false from a listener.
 * 
 * @package LengthOfRope\TreeHouse\Events\Events
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
class ModelCreating extends ModelEvent
{
    /**
     * Create a new model creating event
     * 
     * @param ActiveRecord $model The model being created
     * @param array $context Additional context data
     */
    public function __construct(ActiveRecord $model, array $context = [])
    {
        parent::__construct($model, $context);
    }

    /**
     * Check if this is a cancellable event
     * 
     * @return bool
     */
    public function isCancellable(): bool
    {
        return true;
    }
}