<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Exceptions;

use PDOException;
use Throwable;

/**
 * Database Exception
 * 
 * Thrown when database operations fail. Provides detailed information
 * about database errors while ensuring sensitive information is not
 * exposed in production environments.
 * 
 * @package LengthOfRope\TreeHouse\Errors\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class DatabaseException extends BaseException
{
    /**
     * Default error severity for database errors
     */
    protected string $severity = 'critical';

    /**
     * Default HTTP status code for database errors
     */
    protected int $statusCode = 500;

    /**
     * SQL query that caused the error
     */
    protected ?string $query = null;

    /**
     * Query parameters/bindings
     *
     * @var array<string, mixed>
     */
    protected array $bindings = [];

    /**
     * Database connection information
     *
     * @var array<string, mixed>
     */
    protected array $connectionInfo = [];

    /**
     * Create a new database exception
     *
     * @param string $message Exception message
     * @param string|null $errorCode Custom error code (optional)
     * @param array<string, mixed> $context Additional context data
     * @param array<string, mixed> $options Additional options (user_message, query, bindings, etc.)
     * @param Throwable|null $previous Previous exception (usually PDOException)
     */
    public function __construct(
        string $message = 'Database operation failed',
        ?string $errorCode = null,
        array $context = [],
        array $options = [],
        ?Throwable $previous = null
    ) {
        // Extract database-specific options
        $this->query = $options['query'] ?? null;
        $this->bindings = $options['bindings'] ?? [];
        $this->connectionInfo = $this->sanitizeConnectionInfo($options['connection_info'] ?? []);

        // Merge database context only if values are provided
        $databaseContext = [];
        
        if ($this->query !== null) {
            $databaseContext['query'] = $this->query;
        }
        
        if (!empty($this->bindings)) {
            $databaseContext['bindings_count'] = count($this->bindings);
        }
        
        if (!empty($this->connectionInfo)) {
            $databaseContext['connection'] = $this->connectionInfo;
        }
        
        $context = array_merge($context, $databaseContext);

        // Add PDO-specific information if available
        if ($previous instanceof PDOException) {
            $context['pdo_code'] = $previous->getCode();
            $context['sql_state'] = $previous->errorInfo[0] ?? null;
            $context['driver_code'] = $previous->errorInfo[1] ?? null;
            $context['driver_message'] = $previous->errorInfo[2] ?? null;
        }

        parent::__construct($message, 0, $previous, $context);
        
        // Set custom error code if provided
        if ($errorCode !== null) {
            $this->errorCode = $errorCode;
        }
        
        // Set user message from options or default
        $this->userMessage = $options['user_message'] ?? 'A database error occurred. Please try again later.';
    }

    /**
     * Generate a unique error code for database exceptions
     */
    protected function generateErrorCode(): void
    {
        if (empty($this->errorCode)) {
            $this->errorCode = 'DB_' . str_pad((string)random_int(1, 999), 3, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Sanitize connection info to remove sensitive data
     *
     * @param array<string, mixed> $connectionInfo
     * @return array<string, mixed>
     */
    private function sanitizeConnectionInfo(array $connectionInfo): array
    {
        $sanitized = $connectionInfo;
        
        // Remove sensitive information
        unset(
            $sanitized['password'],
            $sanitized['pass'],
            $sanitized['pwd'],
            $sanitized['secret'],
            $sanitized['key']
        );

        return $sanitized;
    }

    /**
     * Get the SQL query that caused the error
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * Get the query bindings (sanitized)
     *
     * @return array<string, mixed>
     */
    public function getBindings(): array
    {
        return $this->sanitizeBindings($this->bindings);
    }

    /**
     * Get the raw query bindings (for internal use only)
     *
     * @return array<string, mixed>
     */
    public function getRawBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Sanitize bindings to remove sensitive data
     *
     * @param array<string, mixed> $bindings
     * @return array<string, mixed>
     */
    private function sanitizeBindings(array $bindings): array
    {
        $sanitized = [];
        
        foreach ($bindings as $key => $value) {
            // Hide potentially sensitive fields
            if (is_string($key) && preg_match('/password|pass|pwd|secret|token|key|hash/i', $key)) {
                $sanitized[$key] = '[HIDDEN]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Get connection information
     *
     * @return array<string, mixed>
     */
    public function getConnectionInfo(): array
    {
        return $this->connectionInfo;
    }

    /**
     * Create exception for connection failure
     *
     * @param string $host
     * @param string $database
     * @param Throwable|null $previous
     * @return static
     */
    public static function connectionFailed(string $host, string $database, ?Throwable $previous = null): static
    {
        $message = "Failed to connect to database '{$database}' on host '{$host}'";
        
        return new static(
            $message,
            null,
            [],
            ['connection_info' => ['host' => $host, 'database' => $database]],
            $previous
        );
    }

    /**
     * Create exception for query execution failure
     *
     * @param string $query
     * @param array<string, mixed> $bindings
     * @param Throwable|null $previous
     * @return static
     */
    public static function queryFailed(string $query, array $bindings = [], ?Throwable $previous = null): static
    {
        $message = 'Database query execution failed';
        
        return new static(
            $message,
            null,
            [],
            ['query' => $query, 'bindings' => $bindings],
            $previous
        );
    }

    /**
     * Create exception for transaction failure
     *
     * @param string $operation
     * @param Throwable|null $previous
     * @return static
     */
    public static function transactionFailed(string $operation, ?Throwable $previous = null): static
    {
        $message = "Database transaction {$operation} failed";
        
        return new static($message, null, ['operation' => $operation], [], $previous);
    }

    /**
     * Create exception for migration failure
     *
     * @param string $migration
     * @param string $direction
     * @param Throwable|null $previous
     * @return static
     */
    public static function migrationFailed(string $migration, string $direction, ?Throwable $previous = null): static
    {
        $message = "Migration '{$migration}' failed during {$direction}";
        
        $context = [
            'migration' => $migration,
            'direction' => $direction,
        ];
        
        return new static($message, null, $context, [], $previous);
    }

    /**
     * Convert to array with database-specific information
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['database'] = [
            'query' => $this->query,
            'bindings' => $this->getBindings(), // Use sanitized bindings
            'connection' => $this->connectionInfo,
        ];
        
        return $array;
    }
}