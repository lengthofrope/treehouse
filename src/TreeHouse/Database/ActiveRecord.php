<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Database;

use LengthOfRope\TreeHouse\Support\Arr;
use LengthOfRope\TreeHouse\Support\Carbon;
use LengthOfRope\TreeHouse\Support\Collection;
use LengthOfRope\TreeHouse\Support\Str;
use RuntimeException;

// Import helper functions
require_once __DIR__ . '/../Support/helpers.php';

/**
 * Active Record Base Model
 * 
 * Provides an Active Record implementation with support for
 * relationships, mass assignment protection, and model events.
 * 
 * @package LengthOfRope\TreeHouse\Database
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
abstract class ActiveRecord
{
    /**
     * Database connection
     */
    protected static ?Connection $connection = null;

    /**
     * Application instance for resolving database connection
     */
    protected static $app = null;

    /**
     * Table name
     */
    protected string $table = '';

    /**
     * Primary key column
     */
    protected string $primaryKey = 'id';

    /**
     * Primary key type
     */
    protected string $keyType = 'int';

    /**
     * Auto-incrementing primary key
     */
    protected bool $incrementing = true;

    /**
     * Timestamps columns
     */
    protected bool $timestamps = true;

    /**
     * Created at column
     */
    protected string $createdAt = 'created_at';

    /**
     * Updated at column
     */
    protected string $updatedAt = 'updated_at';

    /**
     * Date format
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

    /**
     * Fillable attributes
     */
    protected array $fillable = [];

    /**
     * Guarded attributes
     */
    protected array $guarded = ['*'];

    /**
     * Hidden attributes
     */
    protected array $hidden = [];

    /**
     * Visible attributes
     */
    protected array $visible = [];

    /**
     * Attribute casts
     */
    protected array $casts = [];

    /**
     * Model attributes
     */
    protected array $attributes = [];

    /**
     * Original attributes
     */
    protected array $original = [];

    /**
     * Changed attributes
     */
    protected array $changes = [];

    /**
     * Model exists in database
     */
    protected bool $exists = false;

    /**
     * Model was recently created
     */
    protected bool $wasRecentlyCreated = false;

    /**
     * Loaded relationships
     */
    protected array $relations = [];

    /**
     * Create a new ActiveRecord instance
     *
     * @param array $attributes Initial attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->syncOriginal();
        $this->fill($attributes);
        
        // Auto-generate UUID for primary key if needed
        if ($this->keyType === 'uuid' && !$this->exists && !isset($attributes[$this->primaryKey])) {
            $this->setAttribute($this->primaryKey, \LengthOfRope\TreeHouse\Support\Uuid::uuid4());
        }
    }

    /**
     * Set database connection
     *
     * @param Connection|null $connection Database connection
     */
    public static function setConnection(?Connection $connection): void
    {
        static::$connection = $connection;
    }

    /**
     * Set application instance for database connection resolution
     *
     * @param mixed $app Application instance
     */
    public static function setApplication($app): void
    {
        static::$app = $app;
    }

    /**
     * Get database connection
     *
     * @return Connection
     * @throws RuntimeException
     */
    public static function getConnection(): Connection
    {
        if (static::$connection === null) {
            static::resolveConnection();
        }

        if (static::$connection === null) {
            throw new RuntimeException('Database connection not set');
        }

        return static::$connection;
    }

    /**
     * Resolve database connection from application container
     *
     * @throws RuntimeException
     */
    protected static function resolveConnection(): void
    {
        // Try to resolve from application container if available
        if (static::$app !== null && method_exists(static::$app, 'make')) {
            try {
                static::$connection = static::$app->make('db');
                return;
            } catch (\Exception $e) {
                // Continue to other resolution methods
            }
        }


        // Try to resolve from config directly if no application available
        if (static::$connection === null) {
            static::resolveFromConfig();
        }
    }

    /**
     * Resolve connection directly from configuration
     */
    protected static function resolveFromConfig(): void
    {
        // Look for database config file
        $configPath = getcwd() . '/config/database.php';
        
        if (file_exists($configPath)) {
            // Load environment variables for config resolution
            static::loadEnvironment();
            
            $config = require $configPath;
            
            if (is_array($config) && isset($config['connections'])) {
                $defaultConnection = $config['default'] ?? 'mysql';
                $connectionConfig = $config['connections'][$defaultConnection] ?? [];
                
                if (!empty($connectionConfig)) {
                    static::$connection = new Connection($connectionConfig);
                }
            }
        }
    }

    /**
     * Load environment variables if .env file exists
     */
    protected static function loadEnvironment(): void
    {
        $envPath = getcwd() . '/.env';
        
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue; // Skip comments
                }
                
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if (($value[0] ?? '') === '"' && ($value[-1] ?? '') === '"') {
                        $value = substr($value, 1, -1);
                    }
                    
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }
    }

    /**
     * Get table name
     * 
     * @return string
     */
    public function getTable(): string
    {
        if (empty($this->table)) {
            $class = static::class;
            $basename = basename(str_replace('\\', '/', $class));
            $this->table = Str::snake(Str::plural($basename));
        }

        return $this->table;
    }

    /**
     * Get primary key name
     * 
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get primary key value
     * 
     * @return mixed
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Create a new query builder
     * 
     * @return QueryBuilder
     */
    public static function query(): QueryBuilder
    {
        $instance = new static();
        return new QueryBuilder(static::getConnection(), $instance->getTable());
    }

    /**
     * Get all records
     *
     * @param array $columns Columns to select
     * @return Collection
     */
    public static function all(array $columns = ['*']): Collection
    {
        $results = static::query()->select($columns)->get();
        return $results->map([static::class, 'newFromBuilder']);
    }

    /**
     * Find a record by primary key
     * 
     * @param mixed $id Primary key value
     * @param array $columns Columns to select
     * @return static|null
     */
    public static function find(mixed $id, array $columns = ['*']): ?static
    {
        $instance = new static();
        $result = static::query()
            ->select($columns)
            ->where($instance->getKeyName(), $id)
            ->first();

        return $result ? static::newFromBuilder($result) : null;
    }

    /**
     * Find a record by primary key or throw exception
     * 
     * @param mixed $id Primary key value
     * @param array $columns Columns to select
     * @return static
     * @throws RuntimeException
     */
    public static function findOrFail(mixed $id, array $columns = ['*']): static
    {
        $result = static::find($id, $columns);

        if ($result === null) {
            throw new RuntimeException('Model not found');
        }

        return $result;
    }

    /**
     * Find records by column value
     *
     * @param string $column Column name
     * @param mixed $value Column value
     * @param array $columns Columns to select
     * @return Collection
     */
    public static function where(string $column, mixed $value, array $columns = ['*']): Collection
    {
        $results = static::query()
            ->select($columns)
            ->where($column, $value)
            ->get();

        return $results->map([static::class, 'newFromBuilder']);
    }

    /**
     * Create a new record
     * 
     * @param array $attributes Attributes
     * @return static
     */
    public static function create(array $attributes = []): static
    {
        $instance = new static($attributes);
        $instance->save();
        return $instance;
    }

    /**
     * Update or create a record
     * 
     * @param array $attributes Search attributes
     * @param array $values Update values
     * @return static
     */
    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $query = static::query();

        foreach ($attributes as $key => $value) {
            $query->where($key, $value);
        }

        $result = $query->first();

        if ($result) {
            $instance = static::newFromBuilder($result);
            $instance->fill($values);
            $instance->save();
            return $instance;
        }

        return static::create(array_merge($attributes, $values));
    }

    /**
     * Save the model
     * 
     * @return bool
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * Update the model
     * 
     * @param array $attributes Attributes to update
     * @return bool
     */
    public function update(array $attributes = []): bool
    {
        if (!empty($attributes)) {
            $this->fill($attributes);
        }

        return $this->save();
    }

    /**
     * Delete the model
     * 
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $deleted = static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * Fill model with attributes
     * 
     * @param array $attributes Attributes
     * @return static
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Get an attribute value
     *
     * @param string $key Attribute key
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        if (!$key) {
            return null;
        }

        // Check if attribute exists using dataGet helper
        if (Arr::has($this->attributes, $key)) {
            return $this->getAttributeValue($key);
        }

        // Check for relationship
        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

    /**
     * Set an attribute value
     * 
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return static
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get all attributes
     * 
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set attributes
     * 
     * @param array $attributes Attributes
     * @return static
     */
    public function setRawAttributes(array $attributes): static
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Get dirty attributes
     * 
     * @return array
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Check if model is dirty
     * 
     * @param array|string|null $attributes Attributes to check
     * @return bool
     */
    public function isDirty(array|string|null $attributes = null): bool
    {
        $dirty = $this->getDirty();

        if ($attributes === null) {
            return !empty($dirty);
        }

        $attributes = is_array($attributes) ? $attributes : func_get_args();

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert model to array
     * 
     * @return array
     */
    public function toArray(): array
    {
        $attributes = $this->attributesToArray();
        $relations = $this->relationsToArray();

        return array_merge($attributes, $relations);
    }

    /**
     * Convert model to JSON
     * 
     * @param int $options JSON options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Create new instance from query builder result
     * 
     * @param array $attributes Attributes from database
     * @return static
     */
    public static function newFromBuilder(array $attributes): static
    {
        $instance = new static();
        $instance->setRawAttributes($attributes);
        $instance->exists = true;
        $instance->syncOriginal();

        return $instance;
    }

    /**
     * Perform model insert
     * 
     * @return bool
     */
    protected function performInsert(): bool
    {
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        $attributes = $this->getAttributesForInsert();

        if (empty($attributes)) {
            return true;
        }

        $id = static::query()->insert($attributes);

        if ($this->incrementing) {
            $this->setAttribute($this->getKeyName(), $id);
        }

        $this->exists = true;
        $this->wasRecentlyCreated = true;
        $this->syncOriginal();

        return true;
    }

    /**
     * Perform model update
     * 
     * @return bool
     */
    protected function performUpdate(): bool
    {
        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        if ($this->timestamps && !isset($dirty[$this->updatedAt])) {
            $this->updateTimestamps();
            $dirty = $this->getDirty();
        }

        $updated = static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->update($dirty);

        if ($updated > 0) {
            $this->syncChanges();
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Update timestamps
     */
    protected function updateTimestamps(): void
    {
        $time = Carbon::now()->format($this->dateFormat);

        if (!$this->exists && !isset($this->attributes[$this->createdAt])) {
            $this->setAttribute($this->createdAt, $time);
        }

        if (!isset($this->attributes[$this->updatedAt])) {
            $this->setAttribute($this->updatedAt, $time);
        }
    }

    /**
     * Get attributes for insert
     * 
     * @return array
     */
    protected function getAttributesForInsert(): array
    {
        return $this->attributes;
    }

    /**
     * Get attribute value with casting
     * 
     * @param string $key Attribute key
     * @return mixed
     */
    protected function getAttributeValue(string $key): mixed
    {
        $value = $this->attributes[$key];

        if (isset($this->casts[$key])) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Cast attribute to specified type
     * 
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return mixed
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $cast = $this->casts[$key];

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => json_decode($value, true),
            'object' => json_decode($value),
            'datetime', 'date', 'timestamp' => Carbon::parse($value),
            default => $value
        };
    }

    /**
     * Get relationship value
     * 
     * @param string $key Relationship key
     * @return mixed
     */
    protected function getRelationValue(string $key): mixed
    {
        if (isset($this->relations[$key])) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $relation = $this->$key();
            $this->relations[$key] = $relation;
            return $relation;
        }

        return null;
    }

    /**
     * Check if attribute is fillable
     * 
     * @param string $key Attribute key
     * @return bool
     */
    protected function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable)) {
            return true;
        }

        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->fillable) && !str_starts_with($key, '_');
    }

    /**
     * Check if attribute is guarded
     * 
     * @param string $key Attribute key
     * @return bool
     */
    protected function isGuarded(string $key): bool
    {
        return in_array($key, $this->guarded) || $this->guarded === ['*'];
    }

    /**
     * Convert attributes to array
     *
     * @return array
     */
    protected function attributesToArray(): array
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            if ($this->isVisible($key)) {
                $attributes[$key] = $this->getAttributeValue($key);
            }
        }

        // Use Arr::only to filter visible attributes if visible is set
        if (!empty($this->visible)) {
            return Arr::only($attributes, $this->visible);
        }

        // Use Arr::except to remove hidden attributes
        return Arr::except($attributes, $this->hidden);
    }

    /**
     * Convert relations to array
     * 
     * @return array
     */
    protected function relationsToArray(): array
    {
        $relations = [];

        foreach ($this->relations as $key => $value) {
            if ($this->isVisible($key)) {
                if (is_array($value)) {
                    $relations[$key] = array_map(function ($item) {
                        return $item instanceof static ? $item->toArray() : $item;
                    }, $value);
                } elseif ($value instanceof static) {
                    $relations[$key] = $value->toArray();
                } else {
                    $relations[$key] = $value;
                }
            }
        }

        return $relations;
    }

    /**
     * Check if attribute is visible
     * 
     * @param string $key Attribute key
     * @return bool
     */
    protected function isVisible(string $key): bool
    {
        if (!empty($this->visible)) {
            return in_array($key, $this->visible);
        }

        return !in_array($key, $this->hidden);
    }

    /**
     * Sync original attributes
     */
    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Sync changes
     */
    protected function syncChanges(): void
    {
        $this->changes = $this->getDirty();
    }

    /**
     * Magic getter
     * 
     * @param string $key Attribute key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter
     * 
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset
     * 
     * @param string $key Attribute key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->getAttribute($key) !== null;
    }

    /**
     * Magic unset
     * 
     * @param string $key Attribute key
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    /**
     * Magic toString
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Set a relationship value
     *
     * @param string $relation Relation name
     * @param mixed $value Relation value
     * @return static
     */
    public function setRelation(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;
        return $this;
    }
}