<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events;

use LengthOfRope\TreeHouse\Database\ActiveRecord;

/**
 * Model Event Base Class
 * 
 * Base class for all model-related events in the TreeHouse framework.
 * Provides access to the model instance and event context.
 * 
 * @package LengthOfRope\TreeHouse\Events
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
abstract class ModelEvent extends Event
{
    /**
     * The model instance that triggered the event
     */
    public readonly ActiveRecord $model;

    /**
     * Create a new model event
     * 
     * @param ActiveRecord $model The model instance
     * @param array $context Additional context data
     */
    public function __construct(ActiveRecord $model, array $context = [])
    {
        parent::__construct($context);
        $this->model = $model;
    }

    /**
     * Get the model instance
     * 
     * @return ActiveRecord
     */
    public function getModel(): ActiveRecord
    {
        return $this->model;
    }

    /**
     * Get the model class name
     * 
     * @return string
     */
    public function getModelClass(): string
    {
        return get_class($this->model);
    }

    /**
     * Get the model's primary key value
     * 
     * @return mixed
     */
    public function getModelKey(): mixed
    {
        return $this->model->getKey();
    }

    /**
     * Get the model's table name
     * 
     * @return string
     */
    public function getModelTable(): string
    {
        return $this->model->getTable();
    }

    /**
     * Check if the model exists in the database
     * 
     * @return bool
     */
    public function modelExists(): bool
    {
        return $this->model->exists();
    }

    /**
     * Get model attributes
     * 
     * @return array
     */
    public function getModelAttributes(): array
    {
        return $this->model->getAttributes();
    }

    /**
     * Get specific model attribute
     * 
     * @param string $key Attribute key
     * @return mixed
     */
    public function getModelAttribute(string $key): mixed
    {
        return $this->model->getAttribute($key);
    }

    /**
     * Convert the event to an array representation
     * 
     * @return array
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['model'] = [
            'class' => $this->getModelClass(),
            'table' => $this->getModelTable(),
            'key' => $this->getModelKey(),
            'exists' => $this->modelExists(),
            'attributes' => $this->getModelAttributes(),
        ];
        
        return $array;
    }

    /**
     * String representation of the model event
     * 
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s [%s] for %s(%s) at %s',
            $this->getEventName(),
            $this->eventId,
            $this->getModelClass(),
            $this->getModelKey() ?? 'new',
            date('Y-m-d H:i:s', (int) $this->timestamp)
        );
    }
}