<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Database\Relations;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Database\QueryBuilder;
use LengthOfRope\TreeHouse\Support\Collection;

/**
 * Belongs To Relationship
 * 
 * Represents a many-to-one relationship where the child model
 * belongs to a single parent model.
 * 
 * @package LengthOfRope\TreeHouse\Database\Relations
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class BelongsTo extends Relation
{
    /**
     * Foreign key on the child model
     */
    protected string $foreignKey;

    /**
     * Owner key on the parent model
     */
    protected string $ownerKey;

    /**
     * Create a new BelongsTo relationship
     * 
     * @param QueryBuilder $query Query builder for related model
     * @param ActiveRecord $child Child model instance
     * @param string $foreignKey Foreign key column
     * @param string $ownerKey Owner key column
     */
    public function __construct(
        QueryBuilder $query,
        ActiveRecord $child,
        string $foreignKey,
        string $ownerKey
    ) {
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;

        parent::__construct($query, $child);
    }

    /**
     * Set the base constraints on the relation query
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->ownerKey, '=', $this->getChildKey());
        }
    }

    /**
     * Set the constraints for an eager load of the relation
     * 
     * @param array $models Child models
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->foreignKey);
        $this->query->whereIn($this->ownerKey, $keys);
    }

    /**
     * Initialize the relation on a set of models
     * 
     * @param array $models Child models
     * @param string $relation Relation name
     * @return array
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their children
     *
     * @param array $models Child models
     * @param Collection $results Parent models
     * @param string $relation Relation name
     * @return array
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results->all());

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship
     * 
     * @return ActiveRecord|null
     */
    public function getResults(): ?ActiveRecord
    {
        $result = $this->query->first();
        
        if ($result) {
            $relatedClass = $this->getRelatedClass();
            return $relatedClass::newFromBuilder($result);
        }

        return null;
    }

    /**
     * Associate the model with the given parent
     * 
     * @param ActiveRecord $model Parent model
     * @return ActiveRecord
     */
    public function associate(ActiveRecord $model): ActiveRecord
    {
        $this->parent->setAttribute($this->foreignKey, $model->getAttribute($this->ownerKey));
        $this->parent->setRelation($this->getRelationName(), $model);
        
        return $this->parent;
    }

    /**
     * Dissociate the model from its parent
     * 
     * @return ActiveRecord
     */
    public function dissociate(): ActiveRecord
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setRelation($this->getRelationName(), null);
        
        return $this->parent;
    }

    /**
     * Update the parent model
     * 
     * @param array $attributes Attributes to update
     * @return int Number of updated records
     */
    public function update(array $attributes): int
    {
        return $this->query->update($attributes);
    }

    /**
     * Get the child key value
     * 
     * @return mixed
     */
    protected function getChildKey(): mixed
    {
        return $this->parent->getAttribute($this->foreignKey);
    }

    /**
     * Build model dictionary keyed by the relation's owner key
     * 
     * @param array $results Parent models
     * @return array
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];
        $relatedClass = $this->getRelatedClass();

        foreach ($results as $result) {
            $model = $relatedClass::newFromBuilder($result);
            $key = $model->getAttribute($this->ownerKey);
            $dictionary[$key] = $model;
        }

        return $dictionary;
    }

    /**
     * Get the foreign key for the relationship
     * 
     * @return string
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the owner key for the relationship
     * 
     * @return string
     */
    public function getOwnerKeyName(): string
    {
        return $this->ownerKey;
    }

    /**
     * Get the relation name (for internal use)
     * 
     * @return string
     */
    protected function getRelationName(): string
    {
        // This would typically be determined from the calling context
        // For now, return a generic name
        return 'parent';
    }
}