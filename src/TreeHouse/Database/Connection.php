<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Database;

use LengthOfRope\TreeHouse\Support\Arr;
use LengthOfRope\TreeHouse\Errors\Exceptions\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Database Connection Manager
 * 
 * Manages PDO database connections with connection pooling,
 * transaction support, and query execution utilities.
 * 
 * @package LengthOfRope\TreeHouse\Database
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class Connection
{
    /**
     * PDO instance
     */
    protected ?PDO $pdo = null;

    /**
     * Database configuration
     */
    protected array $config = [];

    /**
     * Transaction nesting level
     */
    protected int $transactionLevel = 0;

    /**
     * Query log
     */
    protected array $queryLog = [];

    /**
     * Enable query logging
     */
    protected bool $enableQueryLog = false;

    /**
     * Create a new Connection instance
     * 
     * @param array $config Database configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        ], $config);
    }

    /**
     * Get PDO instance
     * 
     * @return PDO
     * @throws DatabaseException
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Connect to database
     * 
     * @throws DatabaseException
     */
    public function connect(): void
    {
        try {
            $dsn = $this->buildDsn();
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
        } catch (PDOException $e) {
            throw DatabaseException::connectionFailed(
                $this->config['host'] ?? 'unknown',
                $this->config['database'] ?? 'unknown',
                $e
            );
        }
    }

    /**
     * Disconnect from database
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->transactionLevel = 0;
    }

    /**
     * Check if connected
     * 
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * Execute a query and return statement
     * 
     * @param string $query SQL query
     * @param array $bindings Parameter bindings
     * @return PDOStatement
     * @throws DatabaseException
     */
    public function query(string $query, array $bindings = []): PDOStatement
    {
        $start = microtime(true);
        
        try {
            $statement = $this->getPdo()->prepare($query);
            $statement->execute($bindings);
            
            if ($this->enableQueryLog) {
                $this->logQuery($query, $bindings, microtime(true) - $start);
            }
            
            return $statement;
        } catch (PDOException $e) {
            throw DatabaseException::queryFailed($query, $bindings, $e);
        }
    }

    /**
     * Execute a select query and return all results
     * 
     * @param string $query SQL query
     * @param array $bindings Parameter bindings
     * @return array
     */
    public function select(string $query, array $bindings = []): array
    {
        return $this->query($query, $bindings)->fetchAll();
    }

    /**
     * Execute a select query and return first result
     * 
     * @param string $query SQL query
     * @param array $bindings Parameter bindings
     * @return array|null
     */
    public function selectOne(string $query, array $bindings = []): ?array
    {
        $result = $this->query($query, $bindings)->fetch();
        return $result ?: null;
    }

    /**
     * Execute an insert query
     * 
     * @param string $query SQL query
     * @param array $bindings Parameter bindings
     * @return string Last insert ID
     */
    public function insert(string $query, array $bindings = []): string
    {
        $this->query($query, $bindings);
        return $this->getPdo()->lastInsertId();
    }

    /**
     * Execute an update query
     * 
     * @param string $query SQL query
     * @param array $bindings Parameter bindings
     * @return int Number of affected rows
     */
    public function update(string $query, array $bindings = []): int
    {
        return $this->query($query, $bindings)->rowCount();
    }

    /**
     * Execute a delete query
     * 
     * @param string $query SQL query
     * @param array $bindings Parameter bindings
     * @return int Number of affected rows
     */
    public function delete(string $query, array $bindings = []): int
    {
        return $this->query($query, $bindings)->rowCount();
    }

    /**
     * Execute a statement (insert, update, delete)
     *
     * @param string $query SQL query
     * @param array $bindings Parameter bindings
     * @return bool Success status
     * @throws DatabaseException
     */
    public function statement(string $query, array $bindings = []): bool
    {
        $this->query($query, $bindings);
        return true;
    }

    /**
     * Begin a transaction
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            $result = $this->getPdo()->beginTransaction();
        } else {
            $this->query("SAVEPOINT trans{$this->transactionLevel}");
            $result = true;
        }

        $this->transactionLevel++;
        return $result;
    }

    /**
     * Commit a transaction
     * 
     * @return bool
     */
    public function commit(): bool
    {
        if ($this->transactionLevel === 0) {
            return false;
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            return $this->getPdo()->commit();
        } else {
            $this->query("RELEASE SAVEPOINT trans{$this->transactionLevel}");
            return true;
        }
    }

    /**
     * Rollback a transaction
     * 
     * @return bool
     */
    public function rollback(): bool
    {
        if ($this->transactionLevel === 0) {
            return false;
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            return $this->getPdo()->rollback();
        } else {
            $this->query("ROLLBACK TO SAVEPOINT trans{$this->transactionLevel}");
            return true;
        }
    }

    /**
     * Execute a callback within a transaction
     * 
     * @param callable $callback Callback to execute
     * @return mixed Callback result
     * @throws DatabaseException
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Check if in transaction
     * 
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    /**
     * Get transaction level
     * 
     * @return int
     */
    public function getTransactionLevel(): int
    {
        return $this->transactionLevel;
    }

    /**
     * Enable query logging
     * 
     * @param bool $enable Enable logging
     */
    public function enableQueryLog(bool $enable = true): void
    {
        $this->enableQueryLog = $enable;
    }

    /**
     * Get query log
     * 
     * @return array
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Clear query log
     */
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Get database name
     * 
     * @return string
     */
    public function getDatabaseName(): string
    {
        return $this->config['database'];
    }

    /**
     * Get table names
     *
     * @return array
     */
    public function getTableNames(): array
    {
        $driver = $this->config['driver'];
        
        switch ($driver) {
            case 'mysql':
                $query = 'SHOW TABLES';
                $results = $this->select($query);
                return Arr::pluck($results, '0');
            case 'sqlite':
                $query = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
                $results = $this->select($query);
                return Arr::pluck($results, 'name');
            case 'pgsql':
                $query = "SELECT tablename FROM pg_tables WHERE schemaname = 'public'";
                $results = $this->select($query);
                return Arr::pluck($results, 'tablename');
            default:
                throw new DatabaseException("Unsupported database driver: {$driver}", 'DB_UNSUPPORTED_DRIVER');
        }
    }

    /**
     * Check if table exists
     * 
     * @param string $table Table name
     * @return bool
     */
    public function tableExists(string $table): bool
    {
        return in_array($table, $this->getTableNames());
    }

    /**
     * Get table columns
     *
     * @param string $table Table name
     * @return array
     */
    public function getTableColumns(string $table): array
    {
        $driver = $this->config['driver'];
        
        switch ($driver) {
            case 'mysql':
                $query = "SHOW COLUMNS FROM `{$table}`";
                return Arr::pluck($this->select($query), 'Field');
            case 'sqlite':
                $query = "PRAGMA table_info(`{$table}`)";
                return Arr::pluck($this->select($query), 'name');
            case 'pgsql':
                $query = "SELECT column_name FROM information_schema.columns WHERE table_name = ?";
                return Arr::pluck($this->select($query, [$table]), 'column_name');
            default:
                throw new DatabaseException("Unsupported database driver: {$driver}", 'DB_UNSUPPORTED_DRIVER');
        }
    }

    /**
     * Build DSN string
     * 
     * @return string
     */
    protected function buildDsn(): string
    {
        $driver = $this->config['driver'];
        
        switch ($driver) {
            case 'mysql':
                return sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['database'],
                    $this->config['charset']
                );
            case 'sqlite':
                return 'sqlite:' . $this->config['database'];
            case 'pgsql':
                return sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['database']
                );
            default:
                throw new DatabaseException("Unsupported database driver: {$driver}", 'DB_UNSUPPORTED_DRIVER');
        }
    }

    /**
     * Log a query
     * 
     * @param string $query SQL query
     * @param array $bindings Parameter bindings
     * @param float $time Execution time
     */
    protected function logQuery(string $query, array $bindings, float $time): void
    {
        $this->queryLog[] = [
            'query' => $query,
            'bindings' => $bindings,
            'time' => $time,
            'timestamp' => microtime(true)
        ];
    }
}