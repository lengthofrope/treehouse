<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron;

/**
 * Cron Job Interface
 * 
 * Defines the contract for all cron jobs in the TreeHouse framework.
 * Cron jobs implement this interface to provide scheduling and execution capabilities.
 * 
 * @package LengthOfRope\TreeHouse\Cron
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface CronJobInterface
{
    /**
     * Execute the cron job
     * 
     * This method contains the main logic for the cron job.
     * It should return true on successful execution, false on failure.
     *
     * @return bool True on success, false on failure
     */
    public function execute(): bool;

    /**
     * Get the job name
     * 
     * Returns a unique identifier for this job.
     * Used for logging, locking, and job management.
     *
     * @return string The job name
     */
    public function getName(): string;

    /**
     * Get the job description
     * 
     * Returns a human-readable description of what this job does.
     *
     * @return string The job description
     */
    public function getDescription(): string;

    /**
     * Get the cron schedule expression
     * 
     * Returns a cron expression (* * * * *) that defines when this job should run.
     * Format: minute hour day month weekday
     *
     * @return string The cron expression
     */
    public function getSchedule(): string;

    /**
     * Check if the job is enabled
     * 
     * Returns true if the job should be considered for execution.
     * Disabled jobs are skipped by the scheduler.
     *
     * @return bool True if enabled, false if disabled
     */
    public function isEnabled(): bool;

    /**
     * Get the maximum execution timeout
     * 
     * Returns the maximum number of seconds this job is allowed to run.
     * Jobs exceeding this timeout may be terminated and their locks cleaned up.
     *
     * @return int Timeout in seconds
     */
    public function getTimeout(): int;

    /**
     * Get the job priority
     * 
     * Returns the execution priority for this job.
     * Lower numbers indicate higher priority.
     *
     * @return int Priority (0-100, where 0 is highest priority)
     */
    public function getPriority(): int;

    /**
     * Check if the job can run concurrently
     * 
     * Returns true if multiple instances of this job can run simultaneously.
     * Most jobs should return false to prevent conflicts.
     *
     * @return bool True if concurrent execution is allowed
     */
    public function allowsConcurrentExecution(): bool;

    /**
     * Get job metadata
     * 
     * Returns additional metadata about the job for logging and monitoring.
     *
     * @return array<string, mixed> Job metadata
     */
    public function getMetadata(): array;
}