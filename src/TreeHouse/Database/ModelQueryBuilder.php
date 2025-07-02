<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Database;

use LengthOfRope\TreeHouse\Support\Collection;

/**
 * Model Query Builder
 * 
 * Extends QueryBuilder to return model instances instead of arrays
 * 
 * @package LengthOfRope\TreeHouse\Database
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class ModelQueryBuilder extends QueryBuilder
{
    /**
     * Model class for creating model instances
     */
    protected string $modelClass;

    /**
     * Create a new ModelQueryBuilder instance
     * 
     * @param Connection $connection Database connection
     * @param string $table Table name
     * @param string $modelClass Model class for creating instances
     */
    public function __construct(Connection $connection, string $table, string $modelClass)
    {
        parent::__construct($connection, $table);
        $this->modelClass = $modelClass;
    }

    /**
     * Execute the query and get all results as model instances
     *
     * @return Collection
     */
    public function get(): Collection
    {
        $sql = $this->toSql();
        $results = $this->connection->select($sql, $this->getBindings());
        
        // Convert results to model instances
        $models = array_map([$this->modelClass, 'createFromData'], $results);
        return new Collection($models);
    }

    /**
     * Execute the query and get first result as model instance
     *
     * @return mixed
     */
    public function first(): mixed
    {
        $sql = $this->limit(1)->toSql();
        $result = $this->connection->selectOne($sql, $this->getBindings());
        
        if ($result) {
            return $this->modelClass::createFromData($result);
        }
        
        return null;
    }

    /**
     * Find a record by ID and return as model instance
     *
     * @param mixed $id Record ID
     * @param string $column ID column name
     * @return mixed
     */
    public function find(mixed $id, string $column = 'id'): mixed
    {
        return $this->where($column, $id)->first();
    }
}