<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Database\Relations;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Database\QueryBuilder;
use LengthOfRope\TreeHouse\Support\Collection;
use LengthOfRope\TreeHouse\Support\Arr;

/**
 * Belongs To Many Relationship
 * 
 * Represents a many-to-many relationship using a pivot table
 * to connect two models.
 * 
 * @package LengthOfRope\TreeHouse\Database\Relations
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class BelongsToMany extends Relation
{
    /**
     * Pivot table name
     */
    protected string $table;

    /**
     * Foreign key on the pivot table for the parent model
     */
    protected string $foreignPivotKey;

    /**
     * Foreign key on the pivot table for the related model
     */
    protected string $relatedPivotKey;

    /**
     * Parent key on the parent model
     */
    protected string $parentKey;

    /**
     * Related key on the related model
     */
    protected string $relatedKey;

    /**
     * Pivot columns to retrieve
     */
    protected array $pivotColumns = [];

    /**
     * Create a new BelongsToMany relationship
     * 
     * @param QueryBuilder $query Query builder for related model
     * @param ActiveRecord $parent Parent model instance
     * @param string $table Pivot table name
     * @param string $foreignPivotKey Foreign key for parent model
     * @param string $relatedPivotKey Foreign key for related model
     * @param string $parentKey Parent model key
     * @param string $relatedKey Related model key
     */
    public function __construct(
        QueryBuilder $query,
        ActiveRecord $parent,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey
    ) {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->performJoin();
            $this->query->where($this->getQualifiedForeignPivotKeyName(), '=', $this->getParentKey());
        }
    }

    /**
     * Set the constraints for an eager load of the relation
     * 
     * @param array $models Parent models
     */
    public function addEagerConstraints(array $models): void
    {
        $this->performJoin();
        $keys = $this->getKeys($models, $this->parentKey);
        $this->query->whereIn($this->getQualifiedForeignPivotKeyName(), $keys);
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
            $model->setRelation($relation, new Collection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents
     *
     * @param array $models Parent models
     * @param Collection $results Related models with pivot data
     * @param string $relation Relation name
     * @return array
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results->all());

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            
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
        $results = $this->query->get();
        $relatedClass = $this->getRelatedClass();
        $models = [];

        foreach ($results as $result) {
            $model = $relatedClass::createFromData($result);
            $model->pivot = $this->cleanPivotAttributes($result);
            $models[] = $model;
        }

        return new Collection($models);
    }

    /**
     * Attach models to the parent
     * 
     * @param mixed $id Model ID or array of IDs
     * @param array $attributes Additional pivot attributes
     * @return void
     */
    public function attach(mixed $id, array $attributes = []): void
    {
        $ids = is_array($id) ? $id : [$id];
        
        foreach ($ids as $relatedId) {
            $pivotData = array_merge([
                $this->foreignPivotKey => $this->getParentKey(),
                $this->relatedPivotKey => $relatedId
            ], $attributes);
            
            $this->insertPivot($pivotData);
        }
    }

    /**
     * Detach models from the parent
     * 
     * @param mixed $ids Model ID, array of IDs, or null for all
     * @return int Number of detached records
     */
    public function detach(mixed $ids = null): int
    {
        $query = $this->newPivotQuery();
        $query->where($this->foreignPivotKey, $this->getParentKey());
        
        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedPivotKey, $ids);
        }
        
        return $query->delete();
    }

    /**
     * Sync the intermediate table with a list of IDs
     * 
     * @param array $ids Array of IDs to sync
     * @param bool $detaching Whether to detach missing records
     * @return array Changes made (attached, detached, updated)
     */
    public function sync(array $ids, bool $detaching = true): array
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => []
        ];

        // Get current IDs
        $current = $this->getCurrentIds();
        
        // Determine what to attach and detach
        $detach = array_diff($current, array_keys($ids));
        $attach = array_diff(array_keys($ids), $current);
        
        // Detach records
        if ($detaching && !empty($detach)) {
            $this->detach($detach);
            $changes['detached'] = $detach;
        }
        
        // Attach new records
        foreach ($attach as $id) {
            $attributes = is_array($ids[$id]) ? $ids[$id] : [];
            $this->attach($id, $attributes);
            $changes['attached'][] = $id;
        }
        
        // Update existing records with new attributes
        foreach ($ids as $id => $attributes) {
            if (in_array($id, $current) && is_array($attributes)) {
                $this->updateExistingPivot($id, $attributes);
                $changes['updated'][] = $id;
            }
        }
        
        return $changes;
    }

    /**
     * Toggle the attachment of models
     * 
     * @param mixed $ids Model ID or array of IDs
     * @param bool $touch Whether to touch parent timestamps
     * @return array Changes made (attached, detached)
     */
    public function toggle(mixed $ids, bool $touch = true): array
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $changes = ['attached' => [], 'detached' => []];
        
        $current = $this->getCurrentIds();
        
        foreach ($ids as $id) {
            if (in_array($id, $current)) {
                $this->detach($id);
                $changes['detached'][] = $id;
            } else {
                $this->attach($id);
                $changes['attached'][] = $id;
            }
        }
        
        return $changes;
    }

    /**
     * Specify which pivot columns to retrieve
     * 
     * @param array $columns Pivot columns
     * @return static
     */
    public function withPivot(array $columns): static
    {
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);
        return $this;
    }

    /**
     * Get the pivot table name
     * 
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Perform the join for the relationship
     */
    protected function performJoin(): void
    {
        $relatedTable = $this->query->table;
        
        $this->query->join(
            $this->table,
            $this->getQualifiedRelatedKeyName(),
            '=',
            $this->getQualifiedRelatedPivotKeyName()
        );
        
        // Select related model columns and pivot columns
        $columns = [$relatedTable . '.*'];
        
        // Add pivot columns
        foreach ($this->getPivotColumns() as $column) {
            $columns[] = $this->table . '.' . $column . ' as pivot_' . $column;
        }
        
        $this->query->select($columns);
    }

    /**
     * Get the parent key value
     * 
     * @return mixed
     */
    protected function getParentKey(): mixed
    {
        return $this->parent->getAttribute($this->parentKey);
    }

    /**
     * Get current related IDs
     * 
     * @return array
     */
    protected function getCurrentIds(): array
    {
        $query = $this->newPivotQuery();
        $query->where($this->foreignPivotKey, $this->getParentKey());
        
        $results = $query->get();
        return Arr::pluck($results, $this->relatedPivotKey);
    }

    /**
     * Create a new pivot query builder
     * 
     * @return QueryBuilder
     */
    protected function newPivotQuery(): QueryBuilder
    {
        return new QueryBuilder($this->parent::getConnection(), $this->table);
    }

    /**
     * Insert a pivot record
     * 
     * @param array $attributes Pivot attributes
     */
    protected function insertPivot(array $attributes): void
    {
        $this->newPivotQuery()->insert($attributes);
    }

    /**
     * Update an existing pivot record
     * 
     * @param mixed $id Related model ID
     * @param array $attributes Attributes to update
     * @return int Number of updated records
     */
    protected function updateExistingPivot(mixed $id, array $attributes): int
    {
        return $this->newPivotQuery()
            ->where($this->foreignPivotKey, $this->getParentKey())
            ->where($this->relatedPivotKey, $id)
            ->update($attributes);
    }

    /**
     * Build model dictionary keyed by the foreign pivot key
     * 
     * @param array $results Related models with pivot data
     * @return array
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];
        $relatedClass = $this->getRelatedClass();

        foreach ($results as $result) {
            $model = $relatedClass::createFromData($result);
            $model->pivot = $this->cleanPivotAttributes($result);
            
            $key = $result['pivot_' . $this->foreignPivotKey] ?? null;
            
            if ($key !== null) {
                if (!isset($dictionary[$key])) {
                    $dictionary[$key] = new Collection();
                }
                $dictionary[$key]->push($model);
            }
        }

        return $dictionary;
    }

    /**
     * Clean pivot attributes from result
     * 
     * @param array $result Database result
     * @return array Pivot attributes
     */
    protected function cleanPivotAttributes(array $result): array
    {
        $pivot = [];
        
        foreach ($result as $key => $value) {
            if (str_starts_with($key, 'pivot_')) {
                $pivot[substr($key, 6)] = $value;
            }
        }
        
        return $pivot;
    }

    /**
     * Get pivot columns to select
     * 
     * @return array
     */
    protected function getPivotColumns(): array
    {
        return array_merge(
            [$this->foreignPivotKey, $this->relatedPivotKey],
            $this->pivotColumns
        );
    }

    /**
     * Get qualified foreign pivot key name
     * 
     * @return string
     */
    protected function getQualifiedForeignPivotKeyName(): string
    {
        return $this->table . '.' . $this->foreignPivotKey;
    }

    /**
     * Get qualified related pivot key name
     * 
     * @return string
     */
    protected function getQualifiedRelatedPivotKeyName(): string
    {
        return $this->table . '.' . $this->relatedPivotKey;
    }

    /**
     * Get qualified related key name
     * 
     * @return string
     */
    protected function getQualifiedRelatedKeyName(): string
    {
        $relatedTable = $this->query->table;
        return $relatedTable . '.' . $this->relatedKey;
    }
}