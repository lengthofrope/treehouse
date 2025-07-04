<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Exceptions;

use Throwable;

/**
 * System Exception
 * 
 * Handles system-level errors such as file system operations,
 * memory issues, network problems, and other infrastructure-related
 * failures that are typically outside of application logic.
 * 
 * @package LengthOfRope\TreeHouse\Errors\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class SystemException extends BaseException
{
    /**
     * Default error severity for system errors
     */
    protected string $severity = 'critical';

    /**
     * Default HTTP status code for system errors
     */
    protected int $statusCode = 500;

    /**
     * System resource that failed
     */
    protected ?string $resource = null;

    /**
     * Operation that was being performed
     */
    protected ?string $operation = null;

    /**
     * System error code (if available)
     */
    protected ?int $systemErrorCode = null;

    /**
     * Create a new system exception
     *
     * @param string $message Exception message
     * @param string|null $resource System resource (file, directory, network, etc.)
     * @param string|null $operation Operation being performed
     * @param int|null $systemErrorCode System error code
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        string $message = 'System error occurred',
        ?string $resource = null,
        ?string $operation = null,
        ?int $systemErrorCode = null,
        ?Throwable $previous = null,
        array $context = []
    ) {
        $this->resource = $resource;
        $this->operation = $operation;
        $this->systemErrorCode = $systemErrorCode;

        // Add system information to context
        $context = array_merge($context, [
            'resource' => $resource,
            'operation' => $operation,
            'system_error_code' => $systemErrorCode,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'disk_free_space' => $this->getDiskFreeSpace(),
        ]);

        parent::__construct($message, 0, $previous, $context);
        
        $this->userMessage = 'A system error occurred. Please try again later or contact support.';
    }

    /**
     * Get disk free space for the current directory
     */
    private function getDiskFreeSpace(): ?int
    {
        try {
            $space = disk_free_space('.');
            return $space !== false ? (int) $space : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get the system resource
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }

    /**
     * Get the operation
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Get the system error code
     */
    public function getSystemErrorCode(): ?int
    {
        return $this->systemErrorCode;
    }

    /**
     * Create exception for file system errors
     *
     * @param string $operation
     * @param string $filePath
     * @param string|null $message
     * @param Throwable|null $previous
     * @return static
     */
    public static function fileSystemError(
        string $operation,
        string $filePath,
        ?string $message = null,
        ?Throwable $previous = null
    ): static {
        $message = $message ?? "File system operation '{$operation}' failed for: {$filePath}";
        
        $context = [
            'file_path' => $filePath,
            'file_exists' => file_exists($filePath),
            'is_readable' => is_readable($filePath),
            'is_writable' => is_writable($filePath),
            'file_permissions' => is_file($filePath) ? substr(sprintf('%o', fileperms($filePath)), -4) : null,
        ];

        return new static($message, $filePath, $operation, null, $previous, $context);
    }

    /**
     * Create exception for memory errors
     *
     * @param string $operation
     * @param int|null $requiredMemory
     * @param Throwable|null $previous
     * @return static
     */
    public static function memoryError(
        string $operation,
        ?int $requiredMemory = null,
        ?Throwable $previous = null
    ): static {
        $message = "Memory error during operation: {$operation}";
        
        if ($requiredMemory) {
            $message .= " (required: " . number_format($requiredMemory / 1024 / 1024, 2) . " MB)";
        }

        $context = [
            'required_memory' => $requiredMemory,
            'memory_limit' => ini_get('memory_limit'),
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
        ];

        return new static($message, 'memory', $operation, null, $previous, $context);
    }

    /**
     * Create exception for permission errors
     *
     * @param string $resource
     * @param string $operation
     * @param string|null $requiredPermission
     * @param Throwable|null $previous
     * @return static
     */
    public static function permissionError(
        string $resource,
        string $operation,
        ?string $requiredPermission = null,
        ?Throwable $previous = null
    ): static {
        $message = "Permission denied for operation '{$operation}' on resource: {$resource}";
        
        if ($requiredPermission) {
            $message .= " (required: {$requiredPermission})";
        }

        $context = [
            'required_permission' => $requiredPermission,
            'current_user' => get_current_user(),
            'process_user' => posix_getpwuid(posix_geteuid())['name'] ?? 'unknown',
        ];

        return new static($message, $resource, $operation, null, $previous, $context);
    }

    /**
     * Create exception for network errors
     *
     * @param string $operation
     * @param string $endpoint
     * @param int|null $errorCode
     * @param string|null $errorMessage
     * @param Throwable|null $previous
     * @return static
     */
    public static function networkError(
        string $operation,
        string $endpoint,
        ?int $errorCode = null,
        ?string $errorMessage = null,
        ?Throwable $previous = null
    ): static {
        $message = "Network error during '{$operation}' to endpoint: {$endpoint}";
        
        if ($errorMessage) {
            $message .= " - {$errorMessage}";
        }

        $context = [
            'endpoint' => $endpoint,
            'network_error_message' => $errorMessage,
        ];

        return new static($message, $endpoint, $operation, $errorCode, $previous, $context);
    }

    /**
     * Create exception for timeout errors
     *
     * @param string $operation
     * @param int $timeoutSeconds
     * @param string|null $resource
     * @param Throwable|null $previous
     * @return static
     */
    public static function timeoutError(
        string $operation,
        int $timeoutSeconds,
        ?string $resource = null,
        ?Throwable $previous = null
    ): static {
        $message = "Operation '{$operation}' timed out after {$timeoutSeconds} seconds";
        
        if ($resource) {
            $message .= " for resource: {$resource}";
        }

        $context = [
            'timeout_seconds' => $timeoutSeconds,
            'max_execution_time' => ini_get('max_execution_time'),
        ];

        return new static($message, $resource, $operation, null, $previous, $context);
    }

    /**
     * Create exception for configuration errors
     *
     * @param string $configKey
     * @param string $issue
     * @param mixed $currentValue
     * @param Throwable|null $previous
     * @return static
     */
    public static function configurationError(
        string $configKey,
        string $issue,
        mixed $currentValue = null,
        ?Throwable $previous = null
    ): static {
        $message = "Configuration error for '{$configKey}': {$issue}";

        $context = [
            'config_key' => $configKey,
            'current_value' => $currentValue,
            'issue' => $issue,
        ];

        return new static($message, $configKey, 'configuration', null, $previous, $context);
    }

    /**
     * Create exception for resource exhaustion
     *
     * @param string $resourceType
     * @param string $operation
     * @param array<string, mixed> $limits
     * @param Throwable|null $previous
     * @return static
     */
    public static function resourceExhausted(
        string $resourceType,
        string $operation,
        array $limits = [],
        ?Throwable $previous = null
    ): static {
        $message = "Resource '{$resourceType}' exhausted during operation: {$operation}";

        $context = array_merge($limits, [
            'resource_type' => $resourceType,
        ]);

        return new static($message, $resourceType, $operation, null, $previous, $context);
    }

    /**
     * Get system diagnostics information
     *
     * @return array<string, mixed>
     */
    public function getSystemDiagnostics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'max_execution_time' => ini_get('max_execution_time'),
            'disk_free_space' => $this->getDiskFreeSpace(),
            'loaded_extensions' => get_loaded_extensions(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        ];
    }

    /**
     * Convert to array with system-specific information
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['system'] = [
            'resource' => $this->resource,
            'operation' => $this->operation,
            'system_error_code' => $this->systemErrorCode,
            'diagnostics' => $this->getSystemDiagnostics(),
        ];
        
        return $array;
    }
}