<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron\Locking;

/**
 * Lock
 * 
 * Represents a file-based lock for preventing concurrent execution.
 * Handles lock file creation, validation, and cleanup.
 * 
 * @package LengthOfRope\TreeHouse\Cron\Locking
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Lock
{
    /**
     * Lock file path
     */
    private string $lockFile;

    /**
     * Lock metadata
     */
    private ?LockMetadata $metadata = null;

    /**
     * Whether this lock is currently held by this process
     */
    private bool $isHeld = false;

    /**
     * Create new lock instance
     *
     * @param string $lockFile Path to lock file
     */
    public function __construct(string $lockFile)
    {
        $this->lockFile = $lockFile;
    }

    /**
     * Attempt to acquire the lock
     *
     * @param string $jobName Job name
     * @param int $timeout Timeout in seconds
     * @param array<string, mixed> $metadata Additional metadata
     * @return bool True if lock was acquired
     */
    public function acquire(string $jobName, int $timeout = 300, array $metadata = []): bool
    {
        // Check if lock already exists and is valid
        if ($this->exists() && !$this->isStale()) {
            return false;
        }

        // Clean up stale lock if it exists
        if ($this->exists() && $this->isStale()) {
            $this->release();
        }

        // Create lock metadata
        $this->metadata = LockMetadata::forCurrentProcess($jobName, $timeout, $metadata);

        // Attempt atomic lock creation
        if ($this->createLockFile()) {
            $this->isHeld = true;
            return true;
        }

        return false;
    }

    /**
     * Release the lock
     *
     * @return bool True if lock was released
     */
    public function release(): bool
    {
        if (!$this->exists()) {
            $this->isHeld = false;
            return true;
        }

        // Only allow releasing if we own the lock or it's stale
        if ($this->isHeld || $this->isStale()) {
            $success = @unlink($this->lockFile);
            if ($success) {
                $this->isHeld = false;
                $this->metadata = null;
            }
            return $success;
        }

        return false;
    }

    /**
     * Check if lock file exists
     *
     * @return bool True if lock exists
     */
    public function exists(): bool
    {
        return file_exists($this->lockFile);
    }

    /**
     * Check if the lock is stale
     *
     * @return bool True if the lock is stale
     */
    public function isStale(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        $metadata = $this->getMetadata();
        return $metadata ? $metadata->isStale() : true;
    }

    /**
     * Check if this process holds the lock
     *
     * @return bool True if this process holds the lock
     */
    public function isHeldByCurrentProcess(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        $metadata = $this->getMetadata();
        return $metadata && $metadata->pid === getmypid();
    }

    /**
     * Get lock metadata
     *
     * @return LockMetadata|null Lock metadata or null if not available
     */
    public function getMetadata(): ?LockMetadata
    {
        if ($this->metadata !== null) {
            return $this->metadata;
        }

        if (!$this->exists()) {
            return null;
        }

        try {
            $content = file_get_contents($this->lockFile);
            if ($content === false) {
                return null;
            }

            $data = json_decode($content, true);
            if (!is_array($data)) {
                return null;
            }

            $this->metadata = LockMetadata::fromArray($data);
            return $this->metadata;
        } catch (\Throwable $e) {
            // Invalid lock file content
            return null;
        }
    }

    /**
     * Get lock file path
     *
     * @return string Lock file path
     */
    public function getLockFile(): string
    {
        return $this->lockFile;
    }

    /**
     * Get lock age in seconds
     *
     * @return int|null Age in seconds or null if lock doesn't exist
     */
    public function getAge(): ?int
    {
        $metadata = $this->getMetadata();
        return $metadata ? $metadata->getAge() : null;
    }

    /**
     * Get remaining timeout in seconds
     *
     * @return int|null Remaining timeout or null if lock doesn't exist
     */
    public function getRemainingTimeout(): ?int
    {
        $metadata = $this->getMetadata();
        return $metadata ? $metadata->getRemainingTimeout() : null;
    }

    /**
     * Get lock status description
     *
     * @return string Status description
     */
    public function getStatus(): string
    {
        if (!$this->exists()) {
            return 'not locked';
        }

        $metadata = $this->getMetadata();
        if (!$metadata) {
            return 'invalid lock';
        }

        return $metadata->getStatus();
    }

    /**
     * Get lock information
     *
     * @return string Lock information
     */
    public function getInfo(): string
    {
        if (!$this->exists()) {
            return "No lock at {$this->lockFile}";
        }

        $metadata = $this->getMetadata();
        if (!$metadata) {
            return "Invalid lock at {$this->lockFile}";
        }

        return $metadata->getInfo();
    }

    /**
     * Create lock file atomically
     *
     * @return bool True if lock file was created
     */
    private function createLockFile(): bool
    {
        if (!$this->metadata) {
            return false;
        }

        // Ensure lock directory exists
        $lockDir = dirname($this->lockFile);
        if (!is_dir($lockDir) && !mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
            return false;
        }

        // Create temporary file with lock content
        $tempFile = $this->lockFile . '.tmp.' . getmypid();
        $content = $this->metadata->toJson();

        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            return false;
        }

        // Atomic operation: link temp file to lock file
        if (link($tempFile, $this->lockFile)) {
            unlink($tempFile);
            return true;
        }

        // Clean up temp file on failure
        @unlink($tempFile);
        return false;
    }

    /**
     * Force release the lock (emergency use only)
     *
     * @return bool True if lock was force released
     */
    public function forceRelease(): bool
    {
        if ($this->exists()) {
            $success = @unlink($this->lockFile);
            if ($success) {
                $this->isHeld = false;
                $this->metadata = null;
            }
            return $success;
        }

        return true;
    }

    /**
     * Refresh lock metadata (update timeout)
     *
     * @param int $newTimeout New timeout in seconds
     * @return bool True if metadata was refreshed
     */
    public function refresh(int $newTimeout): bool
    {
        if (!$this->isHeldByCurrentProcess()) {
            return false;
        }

        $metadata = $this->getMetadata();
        if (!$metadata) {
            return false;
        }

        // Create new metadata with updated timeout
        $newMetadata = new LockMetadata(
            $metadata->pid,
            $metadata->startedAt,
            $newTimeout,
            $metadata->hostname,
            $metadata->jobName,
            $metadata->metadata
        );

        // Update lock file
        $content = $newMetadata->toJson();
        if (file_put_contents($this->lockFile, $content, LOCK_EX) !== false) {
            $this->metadata = $newMetadata;
            return true;
        }

        return false;
    }

    /**
     * Destructor - ensure lock is released
     */
    public function __destruct()
    {
        if ($this->isHeld) {
            $this->release();
        }
    }
}