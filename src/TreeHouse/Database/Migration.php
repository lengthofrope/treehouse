<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Database;

use LengthOfRope\TreeHouse\Support\Carbon;
use LengthOfRope\TreeHouse\Support\Uuid;
use RuntimeException;

/**
 * Database Migration System
 * 
 * Provides database schema migration capabilities with support
 * for creating, modifying, and dropping tables and columns.
 * 
 * @package LengthOfRope\TreeHouse\Database
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
abstract class Migration
{
    /**
     * Database connection
     */
    protected Connection $connection;

    /**
     * Migration name
     */
    protected string $name;

    /**
     * Create a new Migration instance
     * 
     * @param Connection $connection Database connection
     * @param string $name Migration name
     */
    public function __construct(Connection $connection, string $name = '')
    {
        $this->connection = $connection;
        $this->name = $name ?: static::class;
    }

    /**
     * Run the migration
     */
    abstract public function up(): void;

    /**
     * Reverse the migration
     */
    abstract public function down(): void;

    /**
     * Get migration name
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Create a new table
     *
     * @param string $table Table name
     * @param callable $callback Table definition callback
     */
    protected function createTable(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, $this->connection);
        $callback($blueprint);
        
        $sql = $blueprint->toSql();
        $this->connection->statement($sql);
        
        // For SQLite, create indexes separately
        $driver = $this->getDriver();
        if ($driver === 'sqlite') {
            $this->createSqliteIndexes($blueprint);
        }
    }

    /**
     * Modify an existing table
     * 
     * @param string $table Table name
     * @param callable $callback Table modification callback
     */
    protected function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, $this->connection, 'alter');
        $callback($blueprint);
        
        $statements = $blueprint->toSqlStatements();
        foreach ($statements as $sql) {
            $this->connection->statement($sql);
        }
    }

    /**
     * Drop a table
     * 
     * @param string $table Table name
     */
    protected function dropTable(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS `{$table}`";
        $this->connection->statement($sql);
    }

    /**
     * Rename a table
     * 
     * @param string $from Current table name
     * @param string $to New table name
     */
    protected function renameTable(string $from, string $to): void
    {
        // Get database driver for syntax differences
        $reflection = new \ReflectionClass($this->connection);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->connection);
        $driver = $config['driver'] ?? 'mysql';
        
        if ($driver === 'sqlite') {
            $sql = "ALTER TABLE `{$from}` RENAME TO `{$to}`";
        } else {
            $sql = "RENAME TABLE `{$from}` TO `{$to}`";
        }
        
        $this->connection->statement($sql);
    }

    /**
     * Check if table exists
     * 
     * @param string $table Table name
     * @return bool
     */
    protected function hasTable(string $table): bool
    {
        return $this->connection->tableExists($table);
    }

    /**
     * Check if column exists
     * 
     * @param string $table Table name
     * @param string $column Column name
     * @return bool
     */
    protected function hasColumn(string $table, string $column): bool
    {
        $columns = $this->connection->getTableColumns($table);
        return in_array($column, $columns);
    }

    /**
     * Execute raw SQL
     *
     * @param string $sql SQL statement
     * @param array $bindings Parameter bindings
     */
    protected function statement(string $sql, array $bindings = []): void
    {
        $this->connection->statement($sql, $bindings);
    }

    /**
     * Get database driver
     *
     * @return string
     */
    protected function getDriver(): string
    {
        // Access the config property using reflection since it's protected
        $reflection = new \ReflectionClass($this->connection);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->connection);
        
        return $config['driver'] ?? 'mysql';
    }

    /**
     * Create indexes separately for SQLite
     *
     * @param Blueprint $blueprint
     */
    protected function createSqliteIndexes(Blueprint $blueprint): void
    {
        // Access the indexes and table properties using reflection
        $reflection = new \ReflectionClass($blueprint);
        
        $indexesProperty = $reflection->getProperty('indexes');
        $indexesProperty->setAccessible(true);
        $indexes = $indexesProperty->getValue($blueprint);
        
        $tableProperty = $reflection->getProperty('table');
        $tableProperty->setAccessible(true);
        $table = $tableProperty->getValue($blueprint);
        
        foreach ($indexes as $index) {
            if ($index['type'] === 'primary') {
                // Primary key is already handled in column definition for SQLite
                continue;
            }
            
            $sql = $this->buildSqliteIndexSql($table, $index);
            $this->connection->statement($sql);
        }
    }

    /**
     * Build SQLite index SQL
     *
     * @param string $table Table name
     * @param array $index Index definition
     * @return string
     */
    protected function buildSqliteIndexSql(string $table, array $index): string
    {
        $columns = '`' . implode('`, `', $index['columns']) . '`';
        
        if ($index['type'] === 'unique') {
            return "CREATE UNIQUE INDEX `{$index['name']}` ON `{$table}` ({$columns})";
        } else {
            return "CREATE INDEX `{$index['name']}` ON `{$table}` ({$columns})";
        }
    }
}

/**
 * Database Schema Blueprint
 * 
 * Provides a fluent interface for defining database table schemas.
 */
class Blueprint
{
    /**
     * Table name
     */
    protected string $table;

    /**
     * Database connection
     */
    protected Connection $connection;

    /**
     * Blueprint type (create/alter)
     */
    protected string $type;

    /**
     * Column definitions
     */
    protected array $columns = [];

    /**
     * Index definitions
     */
    protected array $indexes = [];

    /**
     * Commands to execute
     */
    protected array $commands = [];

    /**
     * Create a new Blueprint instance
     * 
     * @param string $table Table name
     * @param Connection $connection Database connection
     * @param string $type Blueprint type
     */
    public function __construct(string $table, Connection $connection, string $type = 'create')
    {
        $this->table = $table;
        $this->connection = $connection;
        $this->type = $type;
    }

    /**
     * Add auto-incrementing primary key
     * 
     * @param string $column Column name
     * @return Column
     */
    public function id(string $column = 'id'): Column
    {
        return $this->bigIncrements($column);
    }

    /**
     * Add big auto-incrementing primary key
     * 
     * @param string $column Column name
     * @return Column
     */
    public function bigIncrements(string $column): Column
    {
        $col = $this->addColumn('bigint', $column);
        $col->autoIncrement()->primary();
        return $col;
    }

    /**
     * Add auto-incrementing primary key
     * 
     * @param string $column Column name
     * @return Column
     */
    public function increments(string $column): Column
    {
        $col = $this->addColumn('int', $column);
        $col->autoIncrement()->primary();
        return $col;
    }

    /**
     * Add string column
     * 
     * @param string $column Column name
     * @param int $length Column length
     * @return Column
     */
    public function string(string $column, int $length = 255): Column
    {
        return $this->addColumn('varchar', $column, ['length' => $length]);
    }

    /**
     * Add text column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function text(string $column): Column
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Add long text column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function longText(string $column): Column
    {
        return $this->addColumn('longtext', $column);
    }

    /**
     * Add integer column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function integer(string $column): Column
    {
        return $this->addColumn('int', $column);
    }

    /**
     * Add big integer column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function bigInteger(string $column): Column
    {
        return $this->addColumn('bigint', $column);
    }

    /**
     * Add small integer column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function smallInteger(string $column): Column
    {
        return $this->addColumn('smallint', $column);
    }

    /**
     * Add tiny integer column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function tinyInteger(string $column): Column
    {
        return $this->addColumn('tinyint', $column);
    }

    /**
     * Add boolean column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function boolean(string $column): Column
    {
        return $this->addColumn('tinyint', $column, ['length' => 1]);
    }

    /**
     * Add decimal column
     * 
     * @param string $column Column name
     * @param int $precision Total digits
     * @param int $scale Decimal places
     * @return Column
     */
    public function decimal(string $column, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn('decimal', $column, [
            'precision' => $precision,
            'scale' => $scale
        ]);
    }

    /**
     * Add float column
     * 
     * @param string $column Column name
     * @param int $precision Total digits
     * @param int $scale Decimal places
     * @return Column
     */
    public function float(string $column, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn('float', $column, [
            'precision' => $precision,
            'scale' => $scale
        ]);
    }

    /**
     * Add double column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function double(string $column): Column
    {
        return $this->addColumn('double', $column);
    }

    /**
     * Add date column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function date(string $column): Column
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Add datetime column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function dateTime(string $column): Column
    {
        return $this->addColumn('datetime', $column);
    }

    /**
     * Add timestamp column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function timestamp(string $column): Column
    {
        return $this->addColumn('timestamp', $column);
    }

    /**
     * Add timestamps columns (created_at, updated_at)
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Add JSON column
     * 
     * @param string $column Column name
     * @return Column
     */
    public function json(string $column): Column
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Add enum column
     * 
     * @param string $column Column name
     * @param array $values Allowed values
     * @return Column
     */
    public function enum(string $column, array $values): Column
    {
        return $this->addColumn('enum', $column, ['values' => $values]);
    }

    /**
     * Add foreign key column
     *
     * @param string $column Column name
     * @return Column
     */
    public function foreignId(string $column): Column
    {
        return $this->bigInteger($column)->unsigned();
    }

    /**
     * Add UUID column
     *
     * @param string $column Column name
     * @return Column
     */
    public function uuid(string $column): Column
    {
        return $this->addColumn('char', $column, ['length' => 36]);
    }

    /**
     * Add UUID primary key column
     *
     * @param string $column Column name
     * @return Column
     */
    public function uuidPrimary(string $column = 'id'): Column
    {
        return $this->uuid($column)->primary();
    }

    /**
     * Add primary key
     * 
     * @param string|array $columns Column name(s)
     * @param string|null $name Index name
     */
    public function primary(string|array $columns, ?string $name = null): void
    {
        $this->addIndex('primary', $columns, $name);
    }

    /**
     * Add unique index
     * 
     * @param string|array $columns Column name(s)
     * @param string|null $name Index name
     */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $this->addIndex('unique', $columns, $name);
    }

    /**
     * Add regular index
     * 
     * @param string|array $columns Column name(s)
     * @param string|null $name Index name
     */
    public function index(string|array $columns, ?string $name = null): void
    {
        $this->addIndex('index', $columns, $name);
    }

    /**
     * Add foreign key constraint
     * 
     * @param string|array $columns Local column(s)
     * @param string $table Referenced table
     * @param string|array $references Referenced column(s)
     * @param string $onDelete On delete action
     * @param string $onUpdate On update action
     */
    public function foreign(
        string|array $columns,
        string $table,
        string|array $references = ['id'],
        string $onDelete = 'restrict',
        string $onUpdate = 'restrict'
    ): void {
        $this->commands[] = [
            'type' => 'foreign',
            'columns' => is_array($columns) ? $columns : [$columns],
            'table' => $table,
            'references' => is_array($references) ? $references : [$references],
            'onDelete' => $onDelete,
            'onUpdate' => $onUpdate
        ];
    }

    /**
     * Drop column
     * 
     * @param string|array $columns Column name(s)
     */
    public function dropColumn(string|array $columns): void
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        
        foreach ($columns as $column) {
            $this->commands[] = [
                'type' => 'dropColumn',
                'column' => $column
            ];
        }
    }

    /**
     * Drop index
     * 
     * @param string|array $index Index name or columns
     */
    public function dropIndex(string|array $index): void
    {
        $this->commands[] = [
            'type' => 'dropIndex',
            'index' => $index
        ];
    }

    /**
     * Drop primary key
     */
    public function dropPrimary(): void
    {
        $this->commands[] = ['type' => 'dropPrimary'];
    }

    /**
     * Drop foreign key
     * 
     * @param string $foreign Foreign key name
     */
    public function dropForeign(string $foreign): void
    {
        $this->commands[] = [
            'type' => 'dropForeign',
            'foreign' => $foreign
        ];
    }

    /**
     * Convert blueprint to SQL
     * 
     * @return string
     */
    public function toSql(): string
    {
        if ($this->type === 'create') {
            return $this->buildCreateTableSql();
        }

        throw new RuntimeException('Use toSqlStatements() for alter operations');
    }

    /**
     * Convert blueprint to SQL statements array
     * 
     * @return array
     */
    public function toSqlStatements(): array
    {
        if ($this->type === 'alter') {
            return $this->buildAlterTableSql();
        }

        return [$this->toSql()];
    }

    /**
     * Add a column
     * 
     * @param string $type Column type
     * @param string $name Column name
     * @param array $parameters Column parameters
     * @return Column
     */
    protected function addColumn(string $type, string $name, array $parameters = []): Column
    {
        $column = new Column($type, $name, $parameters, $this->connection);
        $this->columns[] = $column;
        return $column;
    }

    /**
     * Add an index
     * 
     * @param string $type Index type
     * @param string|array $columns Column name(s)
     * @param string|null $name Index name
     */
    protected function addIndex(string $type, string|array $columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        
        if ($name === null) {
            $name = $this->table . '_' . implode('_', $columns) . '_' . $type;
        }

        $this->indexes[] = [
            'type' => $type,
            'name' => $name,
            'columns' => $columns
        ];
    }

    /**
     * Build CREATE TABLE SQL
     *
     * @return string
     */
    protected function buildCreateTableSql(): string
    {
        $sql = "CREATE TABLE `{$this->table}` (\n";
        
        $definitions = [];
        
        // Add columns
        foreach ($this->columns as $column) {
            $definitions[] = '  ' . $column->toSql();
        }
        
        // Add indexes (only for non-SQLite databases)
        $driver = $this->getDriver();
        if ($driver !== 'sqlite') {
            foreach ($this->indexes as $index) {
                $definitions[] = '  ' . $this->buildIndexSql($index);
            }
        }
        
        $sql .= implode(",\n", $definitions);
        
        // Add database-specific table options
        if ($driver === 'mysql') {
            $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        } else {
            $sql .= "\n)";
        }
        
        return $sql;
    }

    /**
     * Build ALTER TABLE SQL statements
     * 
     * @return array
     */
    protected function buildAlterTableSql(): array
    {
        $statements = [];
        
        // Add columns
        foreach ($this->columns as $column) {
            $statements[] = "ALTER TABLE `{$this->table}` ADD COLUMN " . $column->toSql();
        }
        
        // Add indexes
        foreach ($this->indexes as $index) {
            $statements[] = "ALTER TABLE `{$this->table}` ADD " . $this->buildIndexSql($index);
        }
        
        // Execute commands
        foreach ($this->commands as $command) {
            $statements[] = $this->buildCommandSql($command);
        }
        
        return $statements;
    }

    /**
     * Build index SQL
     * 
     * @param array $index Index definition
     * @return string
     */
    protected function buildIndexSql(array $index): string
    {
        $columns = '`' . implode('`, `', $index['columns']) . '`';
        
        return match ($index['type']) {
            'primary' => "PRIMARY KEY ({$columns})",
            'unique' => "UNIQUE KEY `{$index['name']}` ({$columns})",
            'index' => "KEY `{$index['name']}` ({$columns})",
            default => throw new RuntimeException("Unknown index type: {$index['type']}")
        };
    }

    /**
     * Build command SQL
     * 
     * @param array $command Command definition
     * @return string
     */
    protected function buildCommandSql(array $command): string
    {
        return match ($command['type']) {
            'dropColumn' => "ALTER TABLE `{$this->table}` DROP COLUMN `{$command['column']}`",
            'dropIndex' => "ALTER TABLE `{$this->table}` DROP INDEX `{$command['index']}`",
            'dropPrimary' => "ALTER TABLE `{$this->table}` DROP PRIMARY KEY",
            'dropForeign' => "ALTER TABLE `{$this->table}` DROP FOREIGN KEY `{$command['foreign']}`",
            'foreign' => $this->buildForeignKeySql($command),
            default => throw new RuntimeException("Unknown command type: {$command['type']}")
        };
    }

    /**
     * Build foreign key SQL
     * 
     * @param array $foreign Foreign key definition
     * @return string
     */
    protected function buildForeignKeySql(array $foreign): string
    {
        $localColumns = '`' . implode('`, `', $foreign['columns']) . '`';
        $foreignColumns = '`' . implode('`, `', $foreign['references']) . '`';
        
        $sql = "ALTER TABLE `{$this->table}` ADD CONSTRAINT ";
        $sql .= "`fk_{$this->table}_" . implode('_', $foreign['columns']) . "` ";
        $sql .= "FOREIGN KEY ({$localColumns}) ";
        $sql .= "REFERENCES `{$foreign['table']}` ({$foreignColumns})";
        
        if ($foreign['onDelete'] !== 'restrict') {
            $sql .= " ON DELETE " . strtoupper($foreign['onDelete']);
        }
        
        if ($foreign['onUpdate'] !== 'restrict') {
            $sql .= " ON UPDATE " . strtoupper($foreign['onUpdate']);
        }
        
        return $sql;
    }

    /**
     * Get database driver
     *
     * @return string
     */
    protected function getDriver(): string
    {
        // Access the config property using reflection since it's protected
        $reflection = new \ReflectionClass($this->connection);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->connection);
        
        return $config['driver'] ?? 'mysql';
    }
}

/**
 * Database Column Definition
 * 
 * Represents a database column with its properties and constraints.
 */
class Column
{
    /**
     * Column type
     */
    protected string $type;

    /**
     * Column name
     */
    protected string $name;

    /**
     * Column parameters
     */
    protected array $parameters;

    /**
     * Column attributes
     */
    protected array $attributes = [];

    /**
     * Database connection
     */
    protected ?Connection $connection = null;

    /**
     * Create a new Column instance
     *
     * @param string $type Column type
     * @param string $name Column name
     * @param array $parameters Column parameters
     * @param Connection|null $connection Database connection
     */
    public function __construct(string $type, string $name, array $parameters = [], ?Connection $connection = null)
    {
        $this->type = $type;
        $this->name = $name;
        $this->parameters = $parameters;
        $this->connection = $connection;
    }

    /**
     * Make column nullable
     * 
     * @return static
     */
    public function nullable(): static
    {
        $this->attributes['nullable'] = true;
        return $this;
    }

    /**
     * Set default value
     * 
     * @param mixed $value Default value
     * @return static
     */
    public function default(mixed $value): static
    {
        $this->attributes['default'] = $value;
        return $this;
    }

    /**
     * Make column unsigned
     * 
     * @return static
     */
    public function unsigned(): static
    {
        $this->attributes['unsigned'] = true;
        return $this;
    }

    /**
     * Make column auto-increment
     * 
     * @return static
     */
    public function autoIncrement(): static
    {
        $this->attributes['autoIncrement'] = true;
        return $this;
    }

    /**
     * Make column primary key
     * 
     * @return static
     */
    public function primary(): static
    {
        $this->attributes['primary'] = true;
        return $this;
    }

    /**
     * Make column unique
     * 
     * @return static
     */
    public function unique(): static
    {
        $this->attributes['unique'] = true;
        return $this;
    }

    /**
     * Add column comment
     * 
     * @param string $comment Comment text
     * @return static
     */
    public function comment(string $comment): static
    {
        $this->attributes['comment'] = $comment;
        return $this;
    }

    /**
     * Convert column to SQL
     * 
     * @return string
     */
    public function toSql(): string
    {
        // Get database driver for syntax differences
        $driver = 'mysql';
        if ($this->connection) {
            $reflection = new \ReflectionClass($this->connection);
            $configProperty = $reflection->getProperty('config');
            $configProperty->setAccessible(true);
            $config = $configProperty->getValue($this->connection);
            $driver = $config['driver'] ?? 'mysql';
        }
        
        // For SQLite auto-increment, we need INTEGER PRIMARY KEY AUTOINCREMENT
        if ($driver === 'sqlite' && isset($this->attributes['autoIncrement']) && $this->attributes['autoIncrement']) {
            return "`{$this->name}` INTEGER PRIMARY KEY AUTOINCREMENT";
        }
        
        $sql = "`{$this->name}` " . $this->buildTypeSql();
        
        if (isset($this->attributes['unsigned']) && $this->attributes['unsigned']) {
            $sql .= ' UNSIGNED';
        }
        
        if (!isset($this->attributes['nullable']) || !$this->attributes['nullable']) {
            $sql .= ' NOT NULL';
        }
        
        if (isset($this->attributes['autoIncrement']) && $this->attributes['autoIncrement']) {
            $sql .= ' AUTO_INCREMENT';
        }
        
        if (isset($this->attributes['primary']) && $this->attributes['primary']) {
            $sql .= ' PRIMARY KEY';
        }
        
        if (isset($this->attributes['unique']) && $this->attributes['unique']) {
            $sql .= ' UNIQUE';
        }
        
        if (isset($this->attributes['default'])) {
            $default = $this->attributes['default'];
            if (is_string($default)) {
                $sql .= " DEFAULT '{$default}'";
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? '1' : '0');
            } elseif ($default === null) {
                $sql .= ' DEFAULT NULL';
            } else {
                $sql .= " DEFAULT {$default}";
            }
        }
        
        if (isset($this->attributes['comment'])) {
            $sql .= " COMMENT '{$this->attributes['comment']}'";
        }
        
        return $sql;
    }

    /**
     * Build type-specific SQL
     *
     * @return string
     */
    protected function buildTypeSql(): string
    {
        // Get database driver for type mapping
        $driver = 'mysql';
        if ($this->connection) {
            $reflection = new \ReflectionClass($this->connection);
            $configProperty = $reflection->getProperty('config');
            $configProperty->setAccessible(true);
            $config = $configProperty->getValue($this->connection);
            $driver = $config['driver'] ?? 'mysql';
        }
        
        // For SQLite, map types appropriately
        if ($driver === 'sqlite') {
            return match ($this->type) {
                'varchar' => "TEXT",
                'char' => 'TEXT',
                'int' => 'INTEGER',
                'bigint' => 'INTEGER', // SQLite uses INTEGER for all integer types
                'smallint' => 'INTEGER',
                'tinyint' => 'INTEGER',
                'decimal' => 'REAL',
                'float' => 'REAL',
                'double' => 'REAL',
                'text' => 'TEXT',
                'longtext' => 'TEXT',
                'date' => 'TEXT',
                'datetime' => 'TEXT',
                'timestamp' => 'TEXT',
                'json' => 'TEXT',
                'enum' => 'TEXT',
                default => 'TEXT'
            };
        }
        
        // MySQL/other databases
        return match ($this->type) {
            'varchar' => "VARCHAR({$this->parameters['length']})",
            'char' => "CHAR({$this->parameters['length']})",
            'int' => 'INT',
            'bigint' => 'BIGINT',
            'smallint' => 'SMALLINT',
            'tinyint' => isset($this->parameters['length']) ? "TINYINT({$this->parameters['length']})" : 'TINYINT',
            'decimal' => "DECIMAL({$this->parameters['precision']},{$this->parameters['scale']})",
            'float' => "FLOAT({$this->parameters['precision']},{$this->parameters['scale']})",
            'double' => 'DOUBLE',
            'text' => 'TEXT',
            'longtext' => 'LONGTEXT',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'json' => 'JSON',
            'enum' => "ENUM('" . implode("','", $this->parameters['values']) . "')",
            default => strtoupper($this->type)
        };
    }
}