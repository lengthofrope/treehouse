<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Database\Relations;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Database\QueryBuilder;
use LengthOfRope\TreeHouse\Support\Collection;

/**
 * Has Many Relationship
 * 
 * Represents a one-to-many relationship where the parent model
 * has many related child models.
 * 
 * @package LengthOfRope\TreeHouse\Database\Relations
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class HasMany extends Relation
{
    /**
     * Foreign key on the related model
     */
    protected string $foreignKey;

    /**
     * Local key on the parent model
     */
    protected string $localKey;

    /**
     * Create a new HasMany relationship
     * 
     * @param QueryBuilder $query Query builder for related model
     * @param ActiveRecord $parent Parent model instance
     * @param string $foreignKey Foreign key column
     * @param string $localKey Local key column
     */
    public function __construct(
        QueryBuilder $query,
        ActiveRecord $parent,
        string $foreignKey,
        string $localKey
    ) {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->getParentKey());
        }
    }

    /**
     * Set the constraints for an eager load of the relation
     * 
     * @param array $models Parent models
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->localKey);
        $this->query->whereIn($this->foreignKey, $keys);
    }

    /**
     * Initialize the relation on a set of models
     * 
     * @param array $models Parent models
     * @param string $relation Relation name
     * @return array
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, []);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents
     *
     * @param array $models Parent models
     * @param Collection $results Related models
     * @param string $relation Relation name
     * @return array
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship
     *
     * @return Collection
     */
    public function getResults(): Collection
    {
        return $this->query->get();
    }

    /**
     * Create a new instance of the related model
     * 
     * @param array $attributes Model attributes
     * @return ActiveRecord
     */
    public function create(array $attributes = []): ActiveRecord
    {
        $attributes[$this->foreignKey] = $this->getParentKey();
        
        $relatedClass = $this->getRelatedClass();
        return $relatedClass::create($attributes);
    }

    /**
     * Save a model and set the foreign key
     * 
     * @param ActiveRecord $model Model to save
     * @return ActiveRecord
     */
    public function save(ActiveRecord $model): ActiveRecord
    {
        $model->setAttribute($this->foreignKey, $this->getParentKey());
        $model->save();
        
        return $model;
    }

    /**
     * Save multiple models and set foreign keys
     * 
     * @param array $models Models to save
     * @return array
     */
    public function saveMany(array $models): array
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    /**
     * Find a related model by its primary key
     * 
     * @param mixed $id Primary key value
     * @param array $columns Columns to select
     * @return ActiveRecord|null
     */
    public function find(mixed $id, array $columns = ['*']): ?ActiveRecord
    {
        $relatedClass = $this->getRelatedClass();
        $instance = new $relatedClass();
        
        $result = $this->query
            ->select($columns)
            ->where($instance->getKeyName(), $id)
            ->first();

        return $result ? $relatedClass::createFromData($result) : null;
    }

    /**
     * Get the first related model matching the attributes
     * 
     * @param array $attributes Attributes to match
     * @param array $columns Columns to select
     * @return ActiveRecord|null
     */
    public function firstWhere(array $attributes, array $columns = ['*']): ?ActiveRecord
    {
        $query = $this->query->select($columns);
        
        foreach ($attributes as $key => $value) {
            $query->where($key, $value);
        }

        $result = $query->first();
        
        if ($result) {
            $relatedClass = $this->getRelatedClass();
            return $relatedClass::createFromData($result);
        }

        return null;
    }

    /**
     * Update all related models
     * 
     * @param array $attributes Attributes to update
     * @return int Number of updated records
     */
    public function update(array $attributes): int
    {
        return $this->query->update($attributes);
    }

    /**
     * Delete all related models
     * 
     * @return int Number of deleted records
     */
    public function delete(): int
    {
        return $this->query->delete();
    }

    /**
     * Get the parent key value
     * 
     * @return mixed
     */
    protected function getParentKey(): mixed
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Build model dictionary keyed by the relation's foreign key
     *
     * @param Collection $results Related models
     * @return array
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];
        $relatedClass = $this->getRelatedClass();

        foreach ($results as $result) {
            $model = $relatedClass::createFromData($result);
            $key = $model->getAttribute($this->foreignKey);
            
            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            
            $dictionary[$key][] = $model;
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
     * Get the local key for the relationship
     * 
     * @return string
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }
}