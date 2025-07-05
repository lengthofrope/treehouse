<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron;

use LengthOfRope\TreeHouse\Cron\Exceptions\CronException;

/**
 * Job Registry
 * 
 * Manages registration and discovery of cron jobs in the TreeHouse framework.
 * Provides job filtering, validation, and metadata management.
 * 
 * @package LengthOfRope\TreeHouse\Cron
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JobRegistry
{
    /**
     * Registered job instances
     *
     * @var array<string, CronJobInterface>
     */
    private array $jobs = [];

    /**
     * Job metadata cache
     *
     * @var array<string, array<string, mixed>>
     */
    private array $jobMetadata = [];

    /**
     * Configuration options
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Create a new job registry
     *
     * @param array<string, mixed> $config Registry configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'validate_jobs' => true,
            'cache_metadata' => true,
            'max_jobs' => 100,
            'timezone' => 'UTC',
        ], $config);
    }

    /**
     * Register a job class
     *
     * @param string $jobClass Job class name
     * @param array<string, mixed> $options Registration options
     * @return self
     * @throws CronException If job registration fails
     */
    public function registerClass(string $jobClass, array $options = []): self
    {
        if (!class_exists($jobClass)) {
            throw CronException::jobRegistrationFailed(
                $jobClass,
                'Class does not exist',
                ['options' => $options]
            );
        }

        if (!is_subclass_of($jobClass, CronJobInterface::class)) {
            throw CronException::jobRegistrationFailed(
                $jobClass,
                'Class must implement CronJobInterface',
                ['implements' => class_implements($jobClass) ?: []]
            );
        }

        try {
            $job = new $jobClass();
            return $this->register($job, $options);
        } catch (\Throwable $e) {
            throw CronException::jobRegistrationFailed(
                $jobClass,
                "Failed to instantiate job: {$e->getMessage()}",
                ['exception' => $e->getMessage(), 'options' => $options]
            );
        }
    }

    /**
     * Register a job instance
     *
     * @param CronJobInterface $job Job instance
     * @param array<string, mixed> $options Registration options
     * @return self
     * @throws CronException If job registration fails
     */
    public function register(CronJobInterface $job, array $options = []): self
    {
        $jobName = $job->getName();

        // Check for duplicate registration
        if (isset($this->jobs[$jobName]) && !($options['allow_override'] ?? false)) {
            throw CronException::jobRegistrationFailed(
                get_class($job),
                "Job with name '$jobName' is already registered",
                ['existing_class' => get_class($this->jobs[$jobName])]
            );
        }

        // Validate job if enabled
        if ($this->config['validate_jobs']) {
            $this->validateJob($job);
        }

        // Check job limit
        if (count($this->jobs) >= $this->config['max_jobs'] && !isset($this->jobs[$jobName])) {
            throw CronException::jobRegistrationFailed(
                get_class($job),
                "Maximum number of jobs ({$this->config['max_jobs']}) exceeded"
            );
        }

        // Register the job
        $this->jobs[$jobName] = $job;

        // Cache metadata if enabled
        if ($this->config['cache_metadata']) {
            $this->jobMetadata[$jobName] = $this->extractJobMetadata($job, $options);
        }

        return $this;
    }

    /**
     * Register multiple jobs
     *
     * @param array<string|CronJobInterface> $jobs Array of job classes or instances
     * @param array<string, mixed> $options Registration options
     * @return self
     */
    public function registerMany(array $jobs, array $options = []): self
    {
        foreach ($jobs as $job) {
            if (is_string($job)) {
                $this->registerClass($job, $options);
            } elseif ($job instanceof CronJobInterface) {
                $this->register($job, $options);
            } else {
                throw CronException::jobRegistrationFailed(
                    is_object($job) ? get_class($job) : gettype($job),
                    'Job must be a class name string or CronJobInterface instance'
                );
            }
        }

        return $this;
    }

    /**
     * Unregister a job
     *
     * @param string $jobName Job name
     * @return bool True if job was unregistered
     */
    public function unregister(string $jobName): bool
    {
        if (isset($this->jobs[$jobName])) {
            unset($this->jobs[$jobName]);
            unset($this->jobMetadata[$jobName]);
            return true;
        }

        return false;
    }

    /**
     * Get a registered job
     *
     * @param string $jobName Job name
     * @return CronJobInterface|null Job instance or null if not found
     */
    public function getJob(string $jobName): ?CronJobInterface
    {
        return $this->jobs[$jobName] ?? null;
    }

    /**
     * Get all registered jobs
     *
     * @return array<string, CronJobInterface> All jobs
     */
    public function getAllJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Get enabled jobs only
     *
     * @return array<string, CronJobInterface> Enabled jobs
     */
    public function getEnabledJobs(): array
    {
        return array_filter($this->jobs, fn($job) => $job->isEnabled());
    }

    /**
     * Get jobs due to run at the given time
     *
     * @param int|null $timestamp Unix timestamp (null for current time)
     * @return array<string, CronJobInterface> Due jobs
     */
    public function getDueJobs(?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? time();
        $dueJobs = [];

        foreach ($this->getEnabledJobs() as $name => $job) {
            try {
                $parser = new CronExpressionParser($job->getSchedule());
                if ($parser->isDue($timestamp)) {
                    $dueJobs[$name] = $job;
                }
            } catch (\Throwable $e) {
                // Skip jobs with invalid cron expressions
                continue;
            }
        }

        return $dueJobs;
    }

    /**
     * Get jobs by priority
     *
     * @param bool $enabledOnly Whether to include only enabled jobs
     * @return array<string, CronJobInterface> Jobs sorted by priority (highest first)
     */
    public function getJobsByPriority(bool $enabledOnly = true): array
    {
        $jobs = $enabledOnly ? $this->getEnabledJobs() : $this->jobs;

        // Sort by priority (lower number = higher priority)
        uasort($jobs, function ($a, $b) {
            return $a->getPriority() <=> $b->getPriority();
        });

        return $jobs;
    }

    /**
     * Check if a job is registered
     *
     * @param string $jobName Job name
     * @return bool True if job is registered
     */
    public function hasJob(string $jobName): bool
    {
        return isset($this->jobs[$jobName]);
    }

    /**
     * Get job count
     *
     * @param bool $enabledOnly Whether to count only enabled jobs
     * @return int Number of jobs
     */
    public function getJobCount(bool $enabledOnly = false): int
    {
        return count($enabledOnly ? $this->getEnabledJobs() : $this->jobs);
    }

    /**
     * Get job names
     *
     * @param bool $enabledOnly Whether to include only enabled jobs
     * @return array<string> Job names
     */
    public function getJobNames(bool $enabledOnly = false): array
    {
        $jobs = $enabledOnly ? $this->getEnabledJobs() : $this->jobs;
        return array_keys($jobs);
    }

    /**
     * Get job metadata
     *
     * @param string $jobName Job name
     * @return array<string, mixed>|null Job metadata or null if not found
     */
    public function getJobMetadata(string $jobName): ?array
    {
        return $this->jobMetadata[$jobName] ?? null;
    }

    /**
     * Get all job metadata
     *
     * @return array<string, array<string, mixed>> All job metadata
     */
    public function getAllJobMetadata(): array
    {
        return $this->jobMetadata;
    }

    /**
     * Get jobs summary
     *
     * @return array<string, mixed> Registry summary
     */
    public function getSummary(): array
    {
        $enabledJobs = $this->getEnabledJobs();
        
        return [
            'total_jobs' => count($this->jobs),
            'enabled_jobs' => count($enabledJobs),
            'disabled_jobs' => count($this->jobs) - count($enabledJobs),
            'due_jobs' => count($this->getDueJobs()),
            'job_names' => array_keys($this->jobs),
            'config' => $this->config,
            'timezone' => $this->config['timezone'],
        ];
    }

    /**
     * Clear all jobs
     *
     * @return self
     */
    public function clear(): self
    {
        $this->jobs = [];
        $this->jobMetadata = [];
        return $this;
    }

    /**
     * Validate a job
     *
     * @param CronJobInterface $job Job to validate
     * @throws CronException If job is invalid
     */
    private function validateJob(CronJobInterface $job): void
    {
        $jobName = $job->getName();
        $jobClass = get_class($job);

        // Validate job name
        if (empty($jobName)) {
            throw CronException::jobRegistrationFailed(
                $jobClass,
                'Job name cannot be empty'
            );
        }

        if (!preg_match('/^[a-zA-Z0-9\-_:]+$/', $jobName)) {
            throw CronException::jobRegistrationFailed(
                $jobClass,
                "Job name '$jobName' contains invalid characters (only a-z, A-Z, 0-9, -, _, : allowed)"
            );
        }

        // Validate cron expression
        try {
            new CronExpressionParser($job->getSchedule());
        } catch (\Throwable $e) {
            throw CronException::jobRegistrationFailed(
                $jobClass,
                "Invalid cron expression '{$job->getSchedule()}': {$e->getMessage()}"
            );
        }

        // Validate timeout
        if ($job->getTimeout() <= 0) {
            throw CronException::jobRegistrationFailed(
                $jobClass,
                "Job timeout must be positive, got {$job->getTimeout()}"
            );
        }

        // Validate priority
        $priority = $job->getPriority();
        if ($priority < 0 || $priority > 100) {
            throw CronException::jobRegistrationFailed(
                $jobClass,
                "Job priority must be between 0-100, got $priority"
            );
        }
    }

    /**
     * Extract job metadata
     *
     * @param CronJobInterface $job Job instance
     * @param array<string, mixed> $options Registration options
     * @return array<string, mixed> Job metadata
     */
    private function extractJobMetadata(CronJobInterface $job, array $options): array
    {
        $metadata = [
            'name' => $job->getName(),
            'description' => $job->getDescription(),
            'schedule' => $job->getSchedule(),
            'enabled' => $job->isEnabled(),
            'timeout' => $job->getTimeout(),
            'priority' => $job->getPriority(),
            'allows_concurrent' => $job->allowsConcurrentExecution(),
            'class' => get_class($job),
            'registered_at' => time(),
            'registration_options' => $options,
        ];

        // Add cron expression info
        try {
            $parser = new CronExpressionParser($job->getSchedule());
            $metadata['schedule_description'] = $parser->getDescription();
            $metadata['next_run'] = $parser->getNextRunTime();
            $metadata['upcoming_runs'] = $parser->getUpcomingRunTimes(3);
        } catch (\Throwable $e) {
            $metadata['schedule_error'] = $e->getMessage();
        }

        // Merge with job's own metadata
        return array_merge($metadata, $job->getMetadata());
    }
}