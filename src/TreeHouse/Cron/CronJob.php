<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron;

/**
 * Abstract Cron Job Base Class
 * 
 * Provides a base implementation for all cron jobs in the TreeHouse framework.
 * Concrete job classes should extend this class and implement the handle() method.
 * 
 * @package LengthOfRope\TreeHouse\Cron
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
abstract class CronJob implements CronJobInterface
{
    /**
     * The job name
     */
    protected string $name = '';

    /**
     * The job description
     */
    protected string $description = '';

    /**
     * The cron schedule expression
     */
    protected string $schedule = '* * * * *';

    /**
     * Whether the job is enabled
     */
    protected bool $enabled = true;

    /**
     * Maximum execution timeout in seconds
     */
    protected int $timeout = 300;

    /**
     * Job execution priority (0-100, lower is higher priority)
     */
    protected int $priority = 50;

    /**
     * Whether concurrent execution is allowed
     */
    protected bool $allowsConcurrentExecution = false;

    /**
     * Additional job metadata
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Handle the job execution
     * 
     * This method contains the main logic for the cron job.
     * Concrete classes must implement this method.
     *
     * @return bool True on success, false on failure
     */
    abstract public function handle(): bool;

    /**
     * Execute the cron job
     *
     * @return bool True on success, false on failure
     */
    public function execute(): bool
    {
        try {
            return $this->handle();
        } catch (\Throwable $e) {
            $this->logError('Job execution failed', [
                'job' => $this->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get the job name
     *
     * @return string The job name
     */
    public function getName(): string
    {
        if (empty($this->name)) {
            // Generate name from class name if not set
            $className = (new \ReflectionClass($this))->getShortName();
            $this->name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $className));
        }
        
        return $this->name;
    }

    /**
     * Set the job name
     *
     * @param string $name The job name
     * @return self
     */
    protected function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the job description
     *
     * @return string The job description
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set the job description
     *
     * @param string $description The job description
     * @return self
     */
    protected function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the cron schedule expression
     *
     * @return string The cron expression
     */
    public function getSchedule(): string
    {
        return $this->schedule;
    }

    /**
     * Set the cron schedule expression
     *
     * @param string $schedule The cron expression
     * @return self
     */
    protected function setSchedule(string $schedule): self
    {
        $this->schedule = $schedule;
        return $this;
    }

    /**
     * Check if the job is enabled
     *
     * @return bool True if enabled, false if disabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set the job enabled status
     *
     * @param bool $enabled Whether the job is enabled
     * @return self
     */
    protected function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Get the maximum execution timeout
     *
     * @return int Timeout in seconds
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set the maximum execution timeout
     *
     * @param int $timeout Timeout in seconds
     * @return self
     */
    protected function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Get the job priority
     *
     * @return int Priority (0-100, where 0 is highest priority)
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Set the job priority
     *
     * @param int $priority Priority (0-100, where 0 is highest priority)
     * @return self
     */
    protected function setPriority(int $priority): self
    {
        $this->priority = max(0, min(100, $priority));
        return $this;
    }

    /**
     * Check if the job can run concurrently
     *
     * @return bool True if concurrent execution is allowed
     */
    public function allowsConcurrentExecution(): bool
    {
        return $this->allowsConcurrentExecution;
    }

    /**
     * Set whether concurrent execution is allowed
     *
     * @param bool $allow Whether to allow concurrent execution
     * @return self
     */
    protected function setAllowsConcurrentExecution(bool $allow): self
    {
        $this->allowsConcurrentExecution = $allow;
        return $this;
    }

    /**
     * Get job metadata
     *
     * @return array<string, mixed> Job metadata
     */
    public function getMetadata(): array
    {
        return array_merge([
            'class' => static::class,
            'created_at' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
        ], $this->metadata);
    }

    /**
     * Set job metadata
     *
     * @param array<string, mixed> $metadata Job metadata
     * @return self
     */
    protected function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Add metadata entry
     *
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return self
     */
    protected function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Log informational message
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log message with level
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        // Enhance context with job-specific information
        $logContext = array_merge([
            'job_name' => $this->getName(),
            'job_class' => static::class,
            'cron_schedule' => $this->getSchedule(),
            'job_priority' => $this->getPriority(),
            'execution_time' => date('Y-m-d H:i:s'),
            'memory_usage' => $this->getMemoryUsage(),
            'pid' => getmypid(),
        ], $context);
        
        // Try to use TreeHouse logger if available, fall back to error_log
        try {
            // Attempt to load and use the ErrorLogger directly
            $logger = new \LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger();
            $logger->log($level, $message, $logContext);
            return;
        } catch (\Throwable $e) {
            // Fall through to error_log if TreeHouse logger is not available
            // This could happen if running outside the full framework context
        }
        
        // Fallback to error_log with structured format
        $logMessage = sprintf(
            '[%s] [CRON:%s] %s %s',
            strtoupper($level),
            $this->getName(),
            $message,
            !empty($logContext) ? json_encode($logContext, JSON_UNESCAPED_SLASHES) : ''
        );
        
        error_log($logMessage);
    }

    /**
     * Get current memory usage in MB
     *
     * @return float Memory usage in megabytes
     */
    protected function getMemoryUsage(): float
    {
        return memory_get_usage(true) / 1024 / 1024;
    }

    /**
     * Get peak memory usage in MB
     *
     * @return float Peak memory usage in megabytes
     */
    protected function getPeakMemoryUsage(): float
    {
        return memory_get_peak_usage(true) / 1024 / 1024;
    }

    /**
     * Get execution time limit
     *
     * @return int Time limit in seconds (0 = unlimited)
     */
    protected function getTimeLimit(): int
    {
        return (int) ini_get('max_execution_time');
    }

    /**
     * Convert job to string representation
     *
     * @return string String representation of the job
     */
    public function __toString(): string
    {
        return sprintf(
            '%s [%s] - %s (Priority: %d, Timeout: %ds, Concurrent: %s)',
            $this->getName(),
            $this->getSchedule(),
            $this->getDescription() ?: 'No description',
            $this->getPriority(),
            $this->getTimeout(),
            $this->allowsConcurrentExecution() ? 'Yes' : 'No'
        );
    }
}