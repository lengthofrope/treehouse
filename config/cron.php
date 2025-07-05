<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cron Jobs Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for scheduled cron jobs in the
    | TreeHouse framework. Jobs can be registered here or programmatically.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Registered Jobs
    |--------------------------------------------------------------------------
    |
    | List of cron job classes to register. Each class must implement the
    | CronJobInterface. Built-in jobs are automatically loaded.
    |
    */
    'jobs' => [
        // Example custom jobs:
        // \App\Cron\DatabaseCleanupJob::class,
        // \App\Cron\EmailQueueProcessorJob::class,
        // \App\Cron\ReportGenerationJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cron Scheduler Configuration
    |--------------------------------------------------------------------------
    |
    | Global configuration for the cron scheduler behavior.
    |
    */
    'scheduler' => [
        'global_timeout' => 300,        // Maximum time for entire cron run (seconds)
        'max_concurrent_jobs' => 3,     // Maximum number of jobs to run simultaneously
        'cleanup_stale_locks' => true,  // Automatically clean up stale locks
        'skip_on_high_load' => true,    // Skip execution if system load is high
        'max_load_average' => 5.0,      // Maximum 1-minute load average
        'max_memory_usage' => 512,      // Maximum memory usage in MB
        'log_execution' => true,        // Log cron execution details
        'detailed_logging' => false,    // Include detailed debug information
        'timezone' => 'UTC',            // Timezone for cron execution
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Execution Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for individual job execution behavior.
    |
    */
    'execution' => [
        'default_timeout' => 300,       // Default job timeout in seconds
        'memory_limit' => 512,          // Memory limit per job in MB
        'max_concurrent_jobs' => 3,     // Maximum concurrent job execution
        'log_execution' => true,        // Log individual job execution
        'detailed_logging' => false,    // Include detailed job logs
    ],

    /*
    |--------------------------------------------------------------------------
    | Lock Management
    |--------------------------------------------------------------------------
    |
    | Configuration for the cron locking mechanism.
    |
    */
    'locks' => [
        'directory' => storage_path('cron/locks'),  // Lock files directory
        'global_timeout' => 300,        // Global scheduler lock timeout
        'default_job_timeout' => 120,   // Default job lock timeout
        'cleanup_interval' => 300,      // Stale lock cleanup interval
        'max_concurrent_jobs' => 3,     // Maximum concurrent job locks
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in Jobs Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for built-in framework jobs. Set enabled to false
    | to disable specific built-in jobs.
    |
    */
    'built_in_jobs' => [
        'cache_cleanup' => [
            'enabled' => true,
            'schedule' => '0 2 * * *',      // Daily at 2 AM
            'timeout' => 600,               // 10 minutes
            'priority' => 30,
        ],
        'lock_cleanup' => [
            'enabled' => true,
            'schedule' => '*/5 * * * *',    // Every 5 minutes
            'timeout' => 60,                // 1 minute
            'priority' => 10,               // High priority
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cron-specific logging.
    |
    */
    'logging' => [
        'channel' => 'file',            // Log channel to use
        'level' => 'info',              // Minimum log level
        'format' => 'structured',       // Log format (simple, structured, json)
        'include_context' => true,      // Include context data in logs
        'rotation' => [
            'enabled' => true,
            'max_files' => 30,          // Keep 30 days of logs
            'compress' => true,         // Compress old log files
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Alerts
    |--------------------------------------------------------------------------
    |
    | Configuration for cron monitoring and alerting.
    |
    */
    'monitoring' => [
        'enabled' => false,             // Enable monitoring
        'alert_on_failure' => true,     // Send alerts on job failures
        'alert_threshold' => 3,         // Alert after N consecutive failures
        'max_execution_time' => 600,    // Alert if execution exceeds time
        'health_check_interval' => 300, // Health check interval in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Configuration for cron performance optimization.
    |
    */
    'performance' => [
        'cache_job_metadata' => true,   // Cache job metadata for performance
        'lazy_load_jobs' => true,       // Only load jobs when needed
        'batch_size' => 10,             // Maximum jobs to process in one batch
        'memory_cleanup' => true,       // Force garbage collection after jobs
    ],
];

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
|
| Helper functions for configuration values.
|
*/

if (!function_exists('storage_path')) {
    /**
     * Get storage path
     */
    function storage_path(string $path = ''): string
    {
        $basePath = getcwd() . '/storage';
        return $path ? $basePath . '/' . ltrim($path, '/') : $basePath;
    }
}