<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events\Events;

use LengthOfRope\TreeHouse\Events\ModelEvent;
use LengthOfRope\TreeHouse\Database\ActiveRecord;

/**
 * Model Saving Event
 * 
 * Fired before a model is saved (either created or updated) in the database.
 * This event can be cancelled by returning false from a listener.
 * 
 * @package LengthOfRope\TreeHouse\Events\Events
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
class ModelSaving extends ModelEvent
{
    /**
     * Create a new model saving event
     * 
     * @param ActiveRecord $model The model being saved
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