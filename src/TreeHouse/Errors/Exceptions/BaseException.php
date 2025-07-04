<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Exceptions;

use Exception;
use Throwable;

/**
 * Base Exception for TreeHouse Framework
 * 
 * All TreeHouse exceptions should extend this base class to provide
 * consistent error handling, context collection, and reporting capabilities.
 * 
 * Features:
 * - Unique error codes for tracking
 * - Severity levels (low, medium, high, critical)
 * - Rich context data collection
 * - User-friendly messages
 * - Reporting and logging flags
 * - HTTP status code mapping
 * 
 * @package LengthOfRope\TreeHouse\Errors\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
abstract class BaseException extends Exception
{
    /**
     * Unique error code for tracking and identification
     */
    protected string $errorCode = '';

    /**
     * Error severity level
     */
    protected string $severity = 'medium';

    /**
     * Additional context data
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * User-friendly error message
     */
    protected ?string $userMessage = null;

    /**
     * Whether this exception should be reported to external services
     */
    protected bool $reportable = true;

    /**
     * Whether this exception should be logged
     */
    protected bool $loggable = true;

    /**
     * HTTP status code for this exception
     */
    protected int $statusCode = 500;

    /**
     * Create a new base exception
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->context = $context;
        $this->generateErrorCode();
    }

    /**
     * Generate a unique error code for this exception
     */
    protected function generateErrorCode(): void
    {
        if (empty($this->errorCode)) {
            $className = (new \ReflectionClass($this))->getShortName();
            $this->errorCode = 'TH_' . strtoupper(preg_replace('/Exception$/', '', $className)) . '_' . 
                              str_pad((string)random_int(1, 999), 3, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Get the unique error code
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Set the error code
     *
     * @param string $errorCode
     * @return static
     */
    public function setErrorCode(string $errorCode): static
    {
        $this->errorCode = $errorCode;
        return $this;
    }

    /**
     * Get the error severity level
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Set the error severity level
     *
     * @param string $severity One of: low, medium, high, critical
     * @return static
     */
    public function setSeverity(string $severity): static
    {
        $validSeverities = ['low', 'medium', 'high', 'critical'];
        if (!in_array($severity, $validSeverities, true)) {
            throw new InvalidArgumentException("Invalid severity level: {$severity}");
        }
        
        $this->severity = $severity;
        return $this;
    }

    /**
     * Get the context data
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set context data
     *
     * @param array<string, mixed> $context
     * @return static
     */
    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context data
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function addContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get the user-friendly message
     */
    public function getUserMessage(): ?string
    {
        return $this->userMessage;
    }

    /**
     * Set the user-friendly message
     *
     * @param string|null $userMessage
     * @return static
     */
    public function setUserMessage(?string $userMessage): static
    {
        $this->userMessage = $userMessage;
        return $this;
    }

    /**
     * Check if this exception should be reported
     */
    public function isReportable(): bool
    {
        return $this->reportable;
    }

    /**
     * Set whether this exception should be reported
     *
     * @param bool $reportable
     * @return static
     */
    public function setReportable(bool $reportable): static
    {
        $this->reportable = $reportable;
        return $this;
    }

    /**
     * Check if this exception should be logged
     */
    public function isLoggable(): bool
    {
        return $this->loggable;
    }

    /**
     * Set whether this exception should be logged
     *
     * @param bool $loggable
     * @return static
     */
    public function setLoggable(bool $loggable): static
    {
        $this->loggable = $loggable;
        return $this;
    }

    /**
     * Get the HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Check if this exception should be reported (alias for isReportable)
     */
    public function shouldReport(): bool
    {
        return $this->isReportable();
    }

    /**
     * Set the HTTP status code
     *
     * @param int $statusCode
     * @return static
     */
    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Convert exception to array format
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'user_message' => $this->userMessage,
            'severity' => $this->severity,
            'status_code' => $this->statusCode,
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTrace(),
        ];
    }

    /**
     * Convert exception to JSON format
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Get a summary of the exception for logging
     */
    public function getSummary(): string
    {
        return sprintf(
            '[%s] %s (Code: %s, Severity: %s, File: %s:%d)',
            $this->errorCode,
            $this->getMessage(),
            $this->getCode(),
            $this->severity,
            basename($this->getFile()),
            $this->getLine()
        );
    }
}