<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Database\Relations;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Database\QueryBuilder;
use LengthOfRope\TreeHouse\Support\Collection;

/**
 * Base Relationship Class
 * 
 * Provides the foundation for all database relationships
 * with common functionality and constraints handling.
 * 
 * @package LengthOfRope\TreeHouse\Database\Relations
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
abstract class Relation
{
    /**
     * Query builder instance
     */
    protected QueryBuilder $query;

    /**
     * Parent model instance
     */
    protected ActiveRecord $parent;

    /**
     * Related model class
     */
    protected string $related;

    /**
     * Indicates if the relation is adding constraints
     */
    protected static bool $constraints = true;

    /**
     * Create a new relation instance
     * 
     * @param QueryBuilder $query Query builder for related model
     * @param ActiveRecord $parent Parent model instance
     */
    public function __construct(QueryBuilder $query, ActiveRecord $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $this->guessRelatedClass();

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query
     */
    abstract public function addConstraints(): void;

    /**
     * Set the constraints for an eager load of the relation
     * 
     * @param array $models Parent models
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Initialize the relation on a set of models
     * 
     * @param array $models Parent models
     * @param string $relation Relation name
     * @return array
     */
    abstract public function initRelation(array $models, string $relation): array;

    /**
     * Match the eagerly loaded results to their parents
     *
     * @param array $models Parent models
     * @param Collection $results Related models
     * @param string $relation Relation name
     * @return array
     */
    abstract public function match(array $models, Collection $results, string $relation): array;

    /**
     * Get the results of the relationship
     * 
     * @return mixed
     */
    abstract public function getResults(): mixed;

    /**
     * Get the relationship for eager loading
     * 
     * @return mixed
     */
    public function getEager(): mixed
    {
        return $this->get();
    }

    /**
     * Execute the query and get the results
     * 
     * @return mixed
     */
    public function get(): mixed
    {
        return $this->getResults();
    }

    /**
     * Get the underlying query builder
     * 
     * @return QueryBuilder
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Get the parent model
     * 
     * @return ActiveRecord
     */
    public function getParent(): ActiveRecord
    {
        return $this->parent;
    }

    /**
     * Get the related model class
     * 
     * @return string
     */
    public function getRelatedClass(): string
    {
        return $this->related;
    }

    /**
     * Set the related model class
     * 
     * @param string $related Related model class
     * @return static
     */
    public function setRelated(string $related): static
    {
        $this->related = $related;
        return $this;
    }

    /**
     * Run a callback with constraints disabled
     * 
     * @param callable $callback Callback to run
     * @return mixed
     */
    public static function noConstraints(callable $callback): mixed
    {
        $previous = static::$constraints;
        static::$constraints = false;

        try {
            return $callback();
        } finally {
            static::$constraints = $previous;
        }
    }

    /**
     * Get the keys from an array of models
     * 
     * @param array $models Models array
     * @param string $key Key to extract
     * @return array
     */
    protected function getKeys(array $models, string $key): array
    {
        $keys = [];

        foreach ($models as $model) {
            if ($model instanceof ActiveRecord) {
                $value = $model->getAttribute($key);
                if ($value !== null) {
                    $keys[] = $value;
                }
            } elseif (is_array($model) && isset($model[$key])) {
                $keys[] = $model[$key];
            }
        }

        return array_unique($keys);
    }

    /**
     * Guess the related model class name
     * 
     * @return string
     */
    protected function guessRelatedClass(): string
    {
        // This is a simplified implementation
        // In a real application, you might want more sophisticated logic
        $parentClass = get_class($this->parent);
        $namespace = substr($parentClass, 0, strrpos($parentClass, '\\'));
        
        // For now, return the parent class as a fallback
        // This should be overridden in specific relation implementations
        return $parentClass;
    }

    /**
     * Add a basic where clause to the query
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value
     * @param string $boolean Boolean operator
     * @return static
     */
    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static
    {
        $this->query->where($column, $operator, $value, $boolean);
        return $this;
    }

    /**
     * Add an "or where" clause to the query
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value
     * @return static
     */
    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query->orWhere($column, $operator, $value);
        return $this;
    }

    /**
     * Add a "where in" clause to the query
     * 
     * @param string $column Column name
     * @param array $values Values array
     * @return static
     */
    public function whereIn(string $column, array $values): static
    {
        $this->query->whereIn($column, $values);
        return $this;
    }

    /**
     * Add a "where not in" clause to the query
     * 
     * @param string $column Column name
     * @param array $values Values array
     * @return static
     */
    public function whereNotIn(string $column, array $values): static
    {
        $this->query->whereNotIn($column, $values);
        return $this;
    }

    /**
     * Add a "where null" clause to the query
     * 
     * @param string $column Column name
     * @return static
     */
    public function whereNull(string $column): static
    {
        $this->query->whereNull($column);
        return $this;
    }

    /**
     * Add a "where not null" clause to the query
     * 
     * @param string $column Column name
     * @return static
     */
    public function whereNotNull(string $column): static
    {
        $this->query->whereNotNull($column);
        return $this;
    }

    /**
     * Add an "order by" clause to the query
     * 
     * @param string $column Column name
     * @param string $direction Sort direction
     * @return static
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    /**
     * Set the "limit" value of the query
     * 
     * @param int $value Limit value
     * @return static
     */
    public function limit(int $value): static
    {
        $this->query->limit($value);
        return $this;
    }

    /**
     * Set the "offset" value of the query
     * 
     * @param int $value Offset value
     * @return static
     */
    public function offset(int $value): static
    {
        $this->query->offset($value);
        return $this;
    }

    /**
     * Get the count of the related models
     * 
     * @return int
     */
    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * Determine if any related models exist
     * 
     * @return bool
     */
    public function exists(): bool
    {
        return $this->query->exists();
    }

    /**
     * Handle dynamic method calls
     * 
     * @param string $method Method name
     * @param array $parameters Method parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->query, $method)) {
            $result = $this->query->$method(...$parameters);
            
            if ($result === $this->query) {
                return $this;
            }
            
            return $result;
        }

        throw new \BadMethodCallException("Method {$method} does not exist on " . static::class);
    }
}