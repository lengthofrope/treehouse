<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron\Exceptions;

use Throwable;

/**
 * Lock Exception
 *
 * Exception thrown when lock operations fail in the cron system.
 * Specialized exception for file-based locking mechanism failures.
 *
 * @package LengthOfRope\TreeHouse\Cron\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class LockException extends CronException
{
    /**
     * Lock name associated with this exception
     */
    protected ?string $lockName = null;

    /**
     * Lock file path
     */
    protected ?string $lockFile = null;

    /**
     * Process ID that owns the lock
     */
    protected ?int $ownerPid = null;

    /**
     * Create a new lock exception
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $context Additional context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        // Extract lock-specific information from context
        if (isset($context['lock_name'])) {
            $this->lockName = $context['lock_name'];
        }
        if (isset($context['lock_file'])) {
            $this->lockFile = $context['lock_file'];
        }
        if (isset($context['owner_pid'])) {
            $this->ownerPid = $context['owner_pid'];
        }

        // Set default severity and user message for lock exceptions
        $this->setSeverity('medium');
        $this->setUserMessage('A system lock error occurred. Please try again.');

        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * Get the lock name
     */
    public function getLockName(): ?string
    {
        return $this->lockName;
    }

    /**
     * Get the lock file path
     */
    public function getLockFile(): ?string
    {
        return $this->lockFile;
    }

    /**
     * Get the owner process ID
     */
    public function getOwnerPid(): ?int
    {
        return $this->ownerPid;
    }

    /**
     * Create exception for lock acquisition failure
     *
     * @param string $lockName Lock name
     * @param string $reason Failure reason
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function acquisitionFailed(
        string $lockName,
        string $reason,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'lock_name' => $lockName,
            'operation' => 'acquire',
            'reason' => $reason,
            'timestamp' => time(),
        ], $additionalContext);

        $exception = new static(
            "Failed to acquire lock '$lockName': $reason",
            409,
            null,
            $context
        );

        $exception->setSeverity('medium')
                  ->setUserMessage('Unable to start the requested operation due to system lock.')
                  ->setStatusCode(409);

        return $exception;
    }

    /**
     * Create exception for lock release failure
     *
     * @param string $lockName Lock name
     * @param string $reason Failure reason
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function releaseFailed(
        string $lockName,
        string $reason,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'lock_name' => $lockName,
            'operation' => 'release',
            'reason' => $reason,
        ], $additionalContext);

        $exception = new static(
            "Failed to release lock '$lockName': $reason",
            500,
            null,
            $context
        );

        $exception->setSeverity('high')
                  ->setUserMessage('System lock could not be properly released.')
                  ->setStatusCode(500);

        return $exception;
    }

    /**
     * Create exception for stale lock detection
     *
     * @param string $lockName Lock name
     * @param int $age Lock age in seconds
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function staleLock(
        string $lockName,
        int $age,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'lock_name' => $lockName,
            'operation' => 'validate',
            'age' => $age,
            'reason' => 'stale_lock',
        ], $additionalContext);

        $exception = new static(
            "Stale lock detected '$lockName' (age: {$age}s)",
            410,
            null,
            $context
        );

        $exception->setSeverity('low')
                  ->setUserMessage('Detected and cleaned up stale system lock.')
                  ->setStatusCode(410)
                  ->setReportable(false); // Stale locks are expected and handled

        return $exception;
    }

    /**
     * Create exception for lock directory creation failure
     *
     * @param string $directory Directory path
     * @param string $reason Failure reason
     * @param Throwable|null $previous Previous exception
     * @return static
     */
    public static function directoryCreationFailed(
        string $directory,
        string $reason,
        ?Throwable $previous = null
    ): static {
        $context = [
            'directory' => $directory,
            'operation' => 'create_directory',
            'reason' => $reason,
            'permissions' => '0755',
        ];

        $exception = new static(
            "Failed to create lock directory '$directory': $reason",
            500,
            $previous,
            $context
        );

        $exception->setSeverity('critical')
                  ->setUserMessage('System configuration error - lock directory could not be created.')
                  ->setStatusCode(500);

        return $exception;
    }

    /**
     * Create exception for invalid lock metadata
     *
     * @param string $lockFile Lock file path
     * @param string $reason Validation failure reason
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function invalidMetadata(
        string $lockFile,
        string $reason,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'lock_file' => $lockFile,
            'operation' => 'read_metadata',
            'reason' => $reason,
        ], $additionalContext);

        $exception = new static(
            "Invalid lock metadata in '$lockFile': $reason",
            400,
            null,
            $context
        );

        $exception->setSeverity('medium')
                  ->setUserMessage('Lock file contains invalid data.')
                  ->setStatusCode(400);

        return $exception;
    }

    /**
     * Create exception for lock timeout
     *
     * @param string $lockName Lock name
     * @param int $timeout Timeout value in seconds
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function timeout(
        string $lockName,
        int $timeout,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'lock_name' => $lockName,
            'operation' => 'timeout',
            'timeout' => $timeout,
            'reason' => 'timeout',
        ], $additionalContext);

        $exception = new static(
            "Lock '$lockName' timed out after {$timeout} seconds",
            408,
            null,
            $context
        );

        $exception->setSeverity('high')
                  ->setUserMessage("Operation timed out after {$timeout} seconds.")
                  ->setStatusCode(408);

        return $exception;
    }

    /**
     * Create exception for concurrent access attempt
     *
     * @param string $lockName Lock name
     * @param int $ownerPid Process ID of lock owner
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function concurrentAccess(
        string $lockName,
        int $ownerPid,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'lock_name' => $lockName,
            'operation' => 'acquire',
            'owner_pid' => $ownerPid,
            'current_pid' => getmypid(),
            'reason' => 'concurrent_access',
        ], $additionalContext);

        $exception = new static(
            "Lock '$lockName' is already held by process $ownerPid",
            409,
            null,
            $context
        );

        $exception->setSeverity('low')
                  ->setUserMessage('Another process is currently handling this operation.')
                  ->setStatusCode(409)
                  ->setReportable(false); // Concurrent access is expected behavior

        return $exception;
    }

    /**
     * Convert exception to array with lock-specific fields
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['lock_name'] = $this->lockName;
        $array['lock_file'] = $this->lockFile;
        $array['owner_pid'] = $this->ownerPid;
        $array['subcategory'] = 'lock';
        
        return $array;
    }
}