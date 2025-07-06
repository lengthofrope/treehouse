<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events\Events;

use LengthOfRope\TreeHouse\Events\ModelEvent;
use LengthOfRope\TreeHouse\Database\ActiveRecord;

/**
 * Model Deleted Event
 * 
 * Fired after a model has been deleted from the database.
 * This event is informational and cannot be cancelled.
 * 
 * @package LengthOfRope\TreeHouse\Events\Events
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
class ModelDeleted extends ModelEvent
{
    /**
     * Create a new model deleted event
     * 
     * @param ActiveRecord $model The model that was deleted
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
        return false;
    }
}