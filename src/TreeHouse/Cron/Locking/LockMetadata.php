<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron\Locking;

/**
 * Lock Metadata
 * 
 * Represents metadata stored with a lock file for tracking lock state,
 * process information, and timeout management.
 * 
 * @package LengthOfRope\TreeHouse\Cron\Locking
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class LockMetadata
{
    /**
     * Process ID that owns the lock
     */
    public readonly int $pid;

    /**
     * Timestamp when the lock was created
     */
    public readonly int $startedAt;

    /**
     * Maximum execution timeout in seconds
     */
    public readonly int $timeout;

    /**
     * Hostname where the lock was created
     */
    public readonly string $hostname;

    /**
     * Job name (for job locks) or 'global' (for global lock)
     */
    public readonly string $jobName;

    /**
     * Additional metadata
     *
     * @var array<string, mixed>
     */
    public readonly array $metadata;

    /**
     * Create new lock metadata
     *
     * @param int $pid Process ID
     * @param int $startedAt Start timestamp
     * @param int $timeout Timeout in seconds
     * @param string $hostname Hostname
     * @param string $jobName Job name
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        int $pid,
        int $startedAt,
        int $timeout,
        string $hostname,
        string $jobName,
        array $metadata = []
    ) {
        $this->pid = $pid;
        $this->startedAt = $startedAt;
        $this->timeout = $timeout;
        $this->hostname = $hostname;
        $this->jobName = $jobName;
        $this->metadata = $metadata;
    }

    /**
     * Create lock metadata for current process
     *
     * @param string $jobName Job name
     * @param int $timeout Timeout in seconds
     * @param array<string, mixed> $metadata Additional metadata
     * @return self
     */
    public static function forCurrentProcess(string $jobName, int $timeout = 300, array $metadata = []): self
    {
        return new self(
            getmypid(),
            time(),
            $timeout,
            gethostname() ?: 'unknown',
            $jobName,
            $metadata
        );
    }

    /**
     * Create from array data
     *
     * @param array<string, mixed> $data Array data
     * @return self
     * @throws \InvalidArgumentException If required fields are missing
     */
    public static function fromArray(array $data): self
    {
        $required = ['pid', 'started_at', 'timeout', 'hostname', 'job_name'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: $field");
            }
        }

        return new self(
            (int) $data['pid'],
            (int) $data['started_at'],
            (int) $data['timeout'],
            (string) $data['hostname'],
            (string) $data['job_name'],
            $data['metadata'] ?? []
        );
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pid' => $this->pid,
            'started_at' => $this->startedAt,
            'timeout' => $this->timeout,
            'hostname' => $this->hostname,
            'job_name' => $this->jobName,
            'metadata' => $this->metadata,
            'created_at' => date('Y-m-d H:i:s', $this->startedAt),
            'expires_at' => date('Y-m-d H:i:s', $this->startedAt + $this->timeout),
        ];
    }

    /**
     * Convert to JSON string
     *
     * @return string JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * Check if the lock is stale
     *
     * A lock is considered stale if:
     * 1. The process is no longer running, OR
     * 2. The lock has exceeded its timeout
     *
     * @return bool True if the lock is stale
     */
    public function isStale(): bool
    {
        // Check if timeout has been exceeded
        if (time() - $this->startedAt > $this->timeout) {
            return true;
        }

        // Check if process is still running
        if (!$this->isProcessRunning()) {
            return true;
        }

        return false;
    }

    /**
     * Check if the process is still running
     *
     * @return bool True if the process is running
     */
    public function isProcessRunning(): bool
    {
        // Cross-platform process check
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec("tasklist /FI \"PID eq {$this->pid}\" 2>NUL");
            return $output !== null && strpos($output, (string) $this->pid) !== false;
        } else {
            return file_exists("/proc/{$this->pid}");
        }
    }

    /**
     * Get the age of the lock in seconds
     *
     * @return int Age in seconds
     */
    public function getAge(): int
    {
        return time() - $this->startedAt;
    }

    /**
     * Get the remaining timeout in seconds
     *
     * @return int Remaining timeout (negative if expired)
     */
    public function getRemainingTimeout(): int
    {
        return $this->timeout - $this->getAge();
    }

    /**
     * Check if the lock has expired
     *
     * @return bool True if expired
     */
    public function hasExpired(): bool
    {
        return $this->getRemainingTimeout() <= 0;
    }

    /**
     * Get lock status description
     *
     * @return string Status description
     */
    public function getStatus(): string
    {
        if ($this->isStale()) {
            if (!$this->isProcessRunning()) {
                return 'stale (process not running)';
            } else {
                return 'stale (timeout exceeded)';
            }
        }

        if ($this->hasExpired()) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Get human-readable lock information
     *
     * @return string Lock information
     */
    public function getInfo(): string
    {
        return sprintf(
            'Lock for %s (PID: %d, Host: %s, Started: %s, Status: %s)',
            $this->jobName,
            $this->pid,
            $this->hostname,
            date('Y-m-d H:i:s', $this->startedAt),
            $this->getStatus()
        );
    }
}