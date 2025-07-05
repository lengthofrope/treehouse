<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron\Results;

/**
 * Job Result
 * 
 * Represents the result of a cron job execution, including timing,
 * memory usage, success status, and any exceptions that occurred.
 * 
 * @package LengthOfRope\TreeHouse\Cron\Results
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JobResult
{
    /**
     * Job name
     */
    private string $jobName;

    /**
     * Whether the job executed successfully
     */
    private bool $success;

    /**
     * Result message
     */
    private string $message;

    /**
     * Start time (microtime)
     */
    private ?float $startTime = null;

    /**
     * End time (microtime)
     */
    private ?float $endTime = null;

    /**
     * Execution duration in seconds
     */
    private ?float $duration = null;

    /**
     * Start memory usage in bytes
     */
    private ?int $startMemory = null;

    /**
     * End memory usage in bytes
     */
    private ?int $endMemory = null;

    /**
     * Memory used during execution in bytes
     */
    private ?int $memoryUsed = null;

    /**
     * Exception that occurred during execution
     */
    private ?\Throwable $exception = null;

    /**
     * Whether the job was skipped
     */
    private bool $skipped = false;

    /**
     * Additional metadata
     *
     * @var array<string, mixed>
     */
    private array $metadata = [];

    /**
     * Exit code (for command-line jobs)
     */
    private ?int $exitCode = null;

    /**
     * Output captured during execution
     */
    private ?string $output = null;

    /**
     * Create a new job result
     *
     * @param string $jobName Job name
     * @param bool $success Success status
     * @param string $message Result message
     */
    public function __construct(string $jobName, bool $success = false, string $message = '')
    {
        $this->jobName = $jobName;
        $this->success = $success;
        $this->message = $message;
    }

    /**
     * Get job name
     */
    public function getJobName(): string
    {
        return $this->jobName;
    }

    /**
     * Check if job was successful
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Set success status
     */
    public function setSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    /**
     * Get result message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Set result message
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Get start time
     */
    public function getStartTime(): ?float
    {
        return $this->startTime;
    }

    /**
     * Set start time
     */
    public function setStartTime(float $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    /**
     * Get end time
     */
    public function getEndTime(): ?float
    {
        return $this->endTime;
    }

    /**
     * Set end time
     */
    public function setEndTime(float $endTime): self
    {
        $this->endTime = $endTime;
        
        // Auto-calculate duration if start time is set and duration is not manually set
        if ($this->startTime !== null && $this->duration === null) {
            $this->duration = $endTime - $this->startTime;
        }
        
        return $this;
    }

    /**
     * Get execution duration in seconds
     */
    public function getDuration(): ?float
    {
        return $this->duration;
    }

    /**
     * Set execution duration
     */
    public function setDuration(float $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    /**
     * Get start memory usage in bytes
     */
    public function getStartMemory(): ?int
    {
        return $this->startMemory;
    }

    /**
     * Set start memory usage
     */
    public function setStartMemory(int $startMemory): self
    {
        $this->startMemory = $startMemory;
        return $this;
    }

    /**
     * Get end memory usage in bytes
     */
    public function getEndMemory(): ?int
    {
        return $this->endMemory;
    }

    /**
     * Set end memory usage
     */
    public function setEndMemory(int $endMemory): self
    {
        $this->endMemory = $endMemory;
        
        // Auto-calculate memory used if start memory is set and memory usage is not manually set
        if ($this->startMemory !== null && $this->memoryUsed === null) {
            $this->memoryUsed = $endMemory - $this->startMemory;
        }
        
        return $this;
    }

    /**
     * Get memory used during execution in bytes
     */
    public function getMemoryUsed(): ?int
    {
        return $this->memoryUsed;
    }

    /**
     * Set memory used
     */
    public function setMemoryUsed(int $memoryUsed): self
    {
        $this->memoryUsed = $memoryUsed;
        return $this;
    }

    /**
     * Get memory used in megabytes
     */
    public function getMemoryUsedMB(): ?float
    {
        return $this->memoryUsed !== null ? round($this->memoryUsed / 1024 / 1024, 2) : null;
    }

    /**
     * Get exception that occurred
     */
    public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    /**
     * Set exception
     */
    public function setException(\Throwable $exception): self
    {
        $this->exception = $exception;
        return $this;
    }

    /**
     * Check if an exception occurred
     */
    public function hasException(): bool
    {
        return $this->exception !== null;
    }

    /**
     * Check if job was skipped
     */
    public function isSkipped(): bool
    {
        return $this->skipped;
    }

    /**
     * Set skipped status
     */
    public function setSkipped(bool $skipped): self
    {
        $this->skipped = $skipped;
        return $this;
    }

    /**
     * Get metadata
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set metadata
     *
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Add metadata entry
     */
    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get metadata value
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get exit code
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    /**
     * Set exit code
     */
    public function setExitCode(int $exitCode): self
    {
        $this->exitCode = $exitCode;
        return $this;
    }

    /**
     * Get output
     */
    public function getOutput(): ?string
    {
        return $this->output;
    }

    /**
     * Set output
     */
    public function setOutput(string $output): self
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Get formatted duration string
     */
    public function getFormattedDuration(): string
    {
        if ($this->duration === null) {
            return 'N/A';
        }
        
        if ($this->duration < 1) {
            return (int) round($this->duration * 1000) . 'ms';
        }
        
        if ($this->duration < 60) {
            return round($this->duration, 2) . 's';
        }
        
        $minutes = (int) floor($this->duration / 60);
        $remainingSeconds = $this->duration - ($minutes * 60);
        $seconds = (int) round($remainingSeconds);
        return "{$minutes}m {$seconds}s";
    }

    /**
     * Get formatted memory usage string
     */
    public function getFormattedMemoryUsed(): string
    {
        if ($this->memoryUsed === null) {
            return 'N/A';
        }
        
        $mb = $this->getMemoryUsedMB();
        
        if ($mb < 1) {
            return (int) round($this->memoryUsed / 1024) . 'KB';
        }
        
        return $mb . 'MB';
    }

    /**
     * Get result summary
     */
    public function getSummary(): string
    {
        $status = $this->success ? 'SUCCESS' : 'FAILED';
        
        if ($this->skipped) {
            $status = 'SKIPPED';
        }
        
        $duration = $this->getFormattedDuration();
        $memory = $this->getFormattedMemoryUsed();
        
        return "[$status] {$this->jobName} - {$this->message} (Duration: $duration, Memory: $memory)";
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [
            'job_name' => $this->jobName,
            'success' => $this->success,
            'message' => $this->message,
            'skipped' => $this->skipped,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration' => $this->duration,
            'formatted_duration' => $this->getFormattedDuration(),
            'start_memory' => $this->startMemory,
            'end_memory' => $this->endMemory,
            'memory_used' => $this->memoryUsed,
            'memory_used_mb' => $this->getMemoryUsedMB(),
            'formatted_memory' => $this->getFormattedMemoryUsed(),
            'exit_code' => $this->exitCode,
            'output' => $this->output,
            'metadata' => $this->metadata,
        ];
        
        if ($this->exception) {
            $array['exception'] = [
                'class' => get_class($this->exception),
                'message' => $this->exception->getMessage(),
                'file' => $this->exception->getFile(),
                'line' => $this->exception->getLine(),
                'code' => $this->exception->getCode(),
            ];
        }
        
        return $array;
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Create a successful result
     */
    public static function success(string $jobName, string $message = 'Job completed successfully'): self
    {
        return new self($jobName, true, $message);
    }

    /**
     * Create a failed result
     */
    public static function failure(string $jobName, string $message = 'Job failed', ?\Throwable $exception = null): self
    {
        $result = new self($jobName, false, $message);
        
        if ($exception) {
            $result->setException($exception);
        }
        
        return $result;
    }

    /**
     * Create a skipped result
     */
    public static function skipped(string $jobName, string $reason = 'Job was skipped'): self
    {
        return (new self($jobName, false, $reason))->setSkipped(true);
    }
}