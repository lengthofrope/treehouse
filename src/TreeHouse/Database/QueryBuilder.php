<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Database;

use LengthOfRope\TreeHouse\Support\Arr;
use LengthOfRope\TreeHouse\Support\Collection;
use RuntimeException;

/**
 * Fluent Query Builder
 * 
 * Provides a fluent interface for building SQL queries with support
 * for complex conditions, joins, aggregations, and subqueries.
 * 
 * @package LengthOfRope\TreeHouse\Database
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class QueryBuilder
{
    /**
     * Database connection
     */
    protected Connection $connection;

    /**
     * Query type
     */
    protected string $type = 'select';

    /**
     * Table name
     */
    public string $table = '';

    /**
     * Select columns
     */
    protected array $columns = ['*'];

    /**
     * Where conditions
     */
    protected array $wheres = [];

    /**
     * Join clauses
     */
    protected array $joins = [];

    /**
     * Order by clauses
     */
    protected array $orders = [];

    /**
     * Group by columns
     */
    protected array $groups = [];

    /**
     * Having conditions
     */
    protected array $havings = [];

    /**
     * Limit value
     */
    protected ?int $limit = null;

    /**
     * Offset value
     */
    protected ?int $offset = null;

    /**
     * Insert data
     */
    protected array $insertData = [];

    /**
     * Update data
     */
    protected array $updateData = [];

    /**
     * Parameter bindings
     */
    protected array $bindings = [];

    /**
     * Create a new QueryBuilder instance
     *
     * @param Connection $connection Database connection
     * @param string $table Table name
     */
    public function __construct(Connection $connection, string $table = '')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Set the table
     * 
     * @param string $table Table name
     * @return static
     */
    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set select columns
     * 
     * @param array|string $columns Columns to select
     * @return static
     */
    public function select(array|string $columns = ['*']): static
    {
        $this->type = 'select';
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Add a where condition
     * 
     * @param string|array $column Column name or conditions array
     * @param mixed $operator Operator or value
     * @param mixed $value Value
     * @param string $boolean Boolean operator (AND/OR)
     * @return static
     */
    public function where(string|array $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val, $boolean);
            }
            return $this;
        }

        // If only two arguments, assume equals
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ];

        $this->addBinding($value);
        return $this;
    }

    /**
     * Add an OR where condition
     * 
     * @param string|array $column Column name or conditions array
     * @param mixed $operator Operator or value
     * @param mixed $value Value
     * @return static
     */
    public function orWhere(string|array $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Add a where IN condition
     * 
     * @param string $column Column name
     * @param array $values Values array
     * @param string $boolean Boolean operator
     * @param bool $not Whether to negate
     * @return static
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
            'not' => $not
        ];

        foreach ($values as $value) {
            $this->addBinding($value);
        }

        return $this;
    }

    /**
     * Add a where NOT IN condition
     * 
     * @param string $column Column name
     * @param array $values Values array
     * @param string $boolean Boolean operator
     * @return static
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add a where NULL condition
     * 
     * @param string $column Column name
     * @param string $boolean Boolean operator
     * @param bool $not Whether to negate
     * @return static
     */
    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
            'not' => $not
        ];

        return $this;
    }

    /**
     * Add a where NOT NULL condition
     * 
     * @param string $column Column name
     * @param string $boolean Boolean operator
     * @return static
     */
    public function whereNotNull(string $column, string $boolean = 'AND'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add a where BETWEEN condition
     * 
     * @param string $column Column name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @param string $boolean Boolean operator
     * @param bool $not Whether to negate
     * @return static
     */
    public function whereBetween(string $column, mixed $min, mixed $max, string $boolean = 'AND', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => $boolean,
            'not' => $not
        ];

        $this->addBinding($min);
        $this->addBinding($max);
        return $this;
    }

    /**
     * Add a where LIKE condition
     * 
     * @param string $column Column name
     * @param string $value Value to match
     * @param string $boolean Boolean operator
     * @return static
     */
    public function whereLike(string $column, string $value, string $boolean = 'AND'): static
    {
        return $this->where($column, 'LIKE', $value, $boolean);
    }

    /**
     * Add a join clause
     * 
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Join operator
     * @param string $second Second column
     * @param string $type Join type
     * @return static
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * Add a left join clause
     * 
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Join operator
     * @param string $second Second column
     * @return static
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add a right join clause
     * 
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Join operator
     * @param string $second Second column
     * @return static
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Add an order by clause
     * 
     * @param string $column Column name
     * @param string $direction Sort direction
     * @return static
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];

        return $this;
    }

    /**
     * Add a group by clause
     * 
     * @param string|array $columns Column(s) to group by
     * @return static
     */
    public function groupBy(string|array $columns): static
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    /**
     * Add a having condition
     * 
     * @param string $column Column name
     * @param string $operator Operator
     * @param mixed $value Value
     * @param string $boolean Boolean operator
     * @return static
     */
    public function having(string $column, string $operator, mixed $value, string $boolean = 'AND'): static
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ];

        $this->addBinding($value);
        return $this;
    }

    /**
     * Set limit
     * 
     * @param int $limit Limit value
     * @return static
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set offset
     * 
     * @param int $offset Offset value
     * @return static
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Set pagination
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return static
     */
    public function paginate(int $page, int $perPage = 15): static
    {
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;
        return $this;
    }

    /**
     * Execute the query and get all results
     *
     * @return Collection
     */
    public function get(): Collection
    {
        $sql = $this->toSql();
        $results = $this->connection->select($sql, $this->getBindings());
        return new Collection($results);
    }

    /**
     * Execute the query and get first result
     *
     * @return mixed
     */
    public function first(): mixed
    {
        $sql = $this->limit(1)->toSql();
        return $this->connection->selectOne($sql, $this->getBindings());
    }

    /**
     * Find a record by ID
     *
     * @param mixed $id Record ID
     * @param string $column ID column name
     * @return mixed
     */
    public function find(mixed $id, string $column = 'id'): mixed
    {
        return $this->where($column, $id)->first();
    }

    /**
     * Get count of records
     * 
     * @param string $column Column to count
     * @return int
     */
    public function count(string $column = '*'): int
    {
        $sql = $this->select(["COUNT({$column}) as count"])->toSql();
        $result = $this->connection->selectOne($sql, $this->getBindings());
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Check if records exist
     * 
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Insert data
     * 
     * @param array $data Data to insert
     * @return string Last insert ID
     */
    public function insert(array $data): string
    {
        $this->type = 'insert';
        $this->insertData = $data;

        $sql = $this->toSql();
        return $this->connection->insert($sql, $this->getBindings());
    }

    /**
     * Update data
     * 
     * @param array $data Data to update
     * @return int Number of affected rows
     */
    public function update(array $data): int
    {
        $this->type = 'update';
        $this->updateData = $data;

        $sql = $this->toSql();
        return $this->connection->update($sql, $this->getBindings());
    }

    /**
     * Delete records
     * 
     * @return int Number of affected rows
     */
    public function delete(): int
    {
        $this->type = 'delete';

        $sql = $this->toSql();
        return $this->connection->delete($sql, $this->getBindings());
    }

    /**
     * Convert query to SQL string
     * 
     * @return string
     */
    public function toSql(): string
    {
        return match ($this->type) {
            'select' => $this->buildSelectSql(),
            'insert' => $this->buildInsertSql(),
            'update' => $this->buildUpdateSql(),
            'delete' => $this->buildDeleteSql(),
            default => throw new RuntimeException("Unknown query type: {$this->type}")
        };
    }

    /**
     * Get parameter bindings
     * 
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Build SELECT SQL
     * 
     * @return string
     */
    protected function buildSelectSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns);
        $sql .= ' FROM ' . $this->table;

        if (!empty($this->joins)) {
            $sql .= $this->buildJoins();
        }

        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }

        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if (!empty($this->havings)) {
            $sql .= $this->buildHavings();
        }

        if (!empty($this->orders)) {
            $sql .= $this->buildOrders();
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * Build INSERT SQL
     * 
     * @return string
     */
    protected function buildInsertSql(): string
    {
        $columns = array_keys($this->insertData);
        $placeholders = array_fill(0, count($columns), '?');

        $this->bindings = array_values($this->insertData);

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
    }

    /**
     * Build UPDATE SQL
     *
     * @return string
     */
    protected function buildUpdateSql(): string
    {
        $sets = [];
        $updateBindings = [];
        
        foreach ($this->updateData as $column => $value) {
            $sets[] = "{$column} = ?";
            $updateBindings[] = $value;
        }

        // Combine update bindings with existing WHERE bindings
        $this->bindings = array_merge($updateBindings, $this->bindings);

        $sql = sprintf('UPDATE %s SET %s', $this->table, implode(', ', $sets));

        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }

        return $sql;
    }

    /**
     * Build DELETE SQL
     * 
     * @return string
     */
    protected function buildDeleteSql(): string
    {
        $sql = 'DELETE FROM ' . $this->table;

        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }

        return $sql;
    }

    /**
     * Build WHERE clauses
     * 
     * @return string
     */
    protected function buildWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        $conditions = [];

        foreach ($this->wheres as $i => $where) {
            $condition = '';

            if ($i > 0) {
                $condition .= ' ' . $where['boolean'] . ' ';
            }

            $condition .= match ($where['type']) {
                'basic' => $this->buildBasicWhere($where),
                'in' => $this->buildInWhere($where),
                'null' => $this->buildNullWhere($where),
                'between' => $this->buildBetweenWhere($where),
                default => throw new RuntimeException("Unknown where type: {$where['type']}")
            };

            $conditions[] = $condition;
        }

        return $sql . implode('', $conditions);
    }

    /**
     * Build basic WHERE condition
     * 
     * @param array $where Where condition
     * @return string
     */
    protected function buildBasicWhere(array $where): string
    {
        return "{$where['column']} {$where['operator']} ?";
    }

    /**
     * Build IN WHERE condition
     * 
     * @param array $where Where condition
     * @return string
     */
    protected function buildInWhere(array $where): string
    {
        $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
        $operator = $where['not'] ? 'NOT IN' : 'IN';
        return "{$where['column']} {$operator} ({$placeholders})";
    }

    /**
     * Build NULL WHERE condition
     * 
     * @param array $where Where condition
     * @return string
     */
    protected function buildNullWhere(array $where): string
    {
        $operator = $where['not'] ? 'IS NOT NULL' : 'IS NULL';
        return "{$where['column']} {$operator}";
    }

    /**
     * Build BETWEEN WHERE condition
     * 
     * @param array $where Where condition
     * @return string
     */
    protected function buildBetweenWhere(array $where): string
    {
        $operator = $where['not'] ? 'NOT BETWEEN' : 'BETWEEN';
        return "{$where['column']} {$operator} ? AND ?";
    }

    /**
     * Build JOIN clauses
     * 
     * @return string
     */
    protected function buildJoins(): string
    {
        $sql = '';

        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        return $sql;
    }

    /**
     * Build ORDER BY clauses
     * 
     * @return string
     */
    protected function buildOrders(): string
    {
        $orders = [];

        foreach ($this->orders as $order) {
            $orders[] = "{$order['column']} {$order['direction']}";
        }

        return ' ORDER BY ' . implode(', ', $orders);
    }

    /**
     * Build HAVING clauses
     * 
     * @return string
     */
    protected function buildHavings(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        $sql = ' HAVING ';
        $conditions = [];

        foreach ($this->havings as $i => $having) {
            $condition = '';

            if ($i > 0) {
                $condition .= ' ' . $having['boolean'] . ' ';
            }

            $condition .= "{$having['column']} {$having['operator']} ?";
            $conditions[] = $condition;
        }

        return $sql . implode('', $conditions);
    }

    /**
     * Add parameter binding
     * 
     * @param mixed $value Value to bind
     */
    protected function addBinding(mixed $value): void
    {
        $this->bindings[] = $value;
    }
}