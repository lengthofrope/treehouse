# TreeHouse Cron System

The TreeHouse framework includes a comprehensive cron system that provides scheduled job execution with advanced locking mechanisms, error handling, and monitoring capabilities.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Quick Start](#quick-start)
- [Creating Jobs](#creating-jobs)
- [Configuration](#configuration)
- [CLI Commands](#cli-commands)
- [Locking System](#locking-system)
- [Monitoring and Logging](#monitoring-and-logging)
- [Built-in Jobs](#built-in-jobs)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Overview

The TreeHouse cron system provides:

- **Class-based job definitions** with a clean interface
- **Cron expression scheduling** (standard `* * * * *` syntax)
- **Multi-level locking** to prevent concurrent executions
- **Comprehensive error handling** and logging
- **Job priority management**
- **Resource monitoring** and load balancing
- **CLI management tools**

## Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   CronScheduler │────│   JobRegistry   │────│   CronJob       │
│                 │    │                 │    │   Interface     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   JobExecutor   │    │   LockManager   │    │  Built-in Jobs  │
│                 │    │                 │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Core Components

- **CronScheduler**: Main orchestrator that manages job execution
- **JobRegistry**: Manages job registration and discovery
- **JobExecutor**: Handles individual job execution with timeout management
- **LockManager**: Provides file-based locking for concurrency control
- **CronExpressionParser**: Parses and evaluates cron expressions

## Quick Start

### 1. System Setup

Add the cron command to your system's crontab to run every minute:

```bash
* * * * * cd /path/to/your/project && php bin/treehouse cron:run >> /dev/null 2>&1
```

### 2. Create a Custom Job

```php
<?php

namespace App\Cron\Jobs;

use LengthOfRope\TreeHouse\Cron\CronJob;

class DatabaseCleanupJob extends CronJob
{
    public function __construct()
    {
        $this->setName('database:cleanup')
            ->setDescription('Clean up old database records')
            ->setSchedule('0 2 * * *') // Daily at 2 AM
            ->setPriority(20)
            ->setTimeout(1800); // 30 minutes
    }

    public function handle(): bool
    {
        $this->logInfo('Starting database cleanup');
        
        // Your cleanup logic here
        $deletedRecords = $this->cleanupOldRecords();
        
        $this->logInfo("Database cleanup completed", [
            'deleted_records' => $deletedRecords
        ]);
        
        return true;
    }
    
    private function cleanupOldRecords(): int
    {
        // Implementation here
        return 0;
    }
}
```

### 3. Register the Job

Add your job to `config/cron.php`:

```php
return [
    'jobs' => [
        \App\Cron\Jobs\DatabaseCleanupJob::class,
    ],
];
```

### 4. Test the Job

```bash
# List all jobs
php bin/treehouse cron:list

# Run specific job
php bin/treehouse cron:run --jobs=database:cleanup

# Dry run to see what would execute
php bin/treehouse cron:run --dry-run
```

## Creating Jobs

### Job Interface

All jobs must implement `CronJobInterface` or extend the `CronJob` base class:

```php
interface CronJobInterface
{
    public function execute(): bool;
    public function getName(): string;
    public function getDescription(): string;
    public function getSchedule(): string;
    public function isEnabled(): bool;
    public function getTimeout(): int;
    public function getPriority(): int;
    public function allowsConcurrentExecution(): bool;
    public function getMetadata(): array;
}
```

### Using the Base Class

The `CronJob` base class provides helpful defaults and utilities:

```php
class MyJob extends CronJob
{
    public function __construct()
    {
        $this->setName('my-job')
            ->setDescription('My custom job')
            ->setSchedule('*/15 * * * *') // Every 15 minutes
            ->setPriority(50)
            ->setTimeout(300)
            ->setEnabled(true)
            ->addMetadata('category', 'maintenance');
    }

    public function handle(): bool
    {
        // Use built-in logging
        $this->logInfo('Job started');
        $this->logWarning('Something might be wrong');
        $this->logError('An error occurred');
        
        // Access utility methods
        $memory = $this->getMemoryUsage();
        $peak = $this->getPeakMemoryUsage();
        
        return true;
    }
}
```

### Cron Expression Syntax

The system supports standard cron syntax:

```
 * * * * *
 │ │ │ │ │
 │ │ │ │ └─── Day of week (0-7, 0 and 7 = Sunday)
 │ │ │ └───── Month (1-12)
 │ │ └─────── Day of month (1-31)
 │ └───────── Hour (0-23)
 └─────────── Minute (0-59)
```

**Common Examples:**
- `* * * * *` - Every minute
- `0 * * * *` - Every hour
- `0 2 * * *` - Daily at 2 AM
- `0 2 * * 0` - Weekly on Sunday at 2 AM
- `0 2 1 * *` - Monthly on the 1st at 2 AM
- `*/15 * * * *` - Every 15 minutes
- `0 9-17 * * 1-5` - Every hour from 9 AM to 5 PM, Monday to Friday

## Configuration

The cron system is configured in `config/cron.php`:

```php
return [
    'jobs' => [
        // Your job classes here
    ],
    
    'scheduler' => [
        'global_timeout' => 300,
        'max_concurrent_jobs' => 3,
        'cleanup_stale_locks' => true,
        'skip_on_high_load' => true,
        'max_load_average' => 5.0,
        'max_memory_usage' => 512,
        'log_execution' => true,
        'detailed_logging' => false,
        'timezone' => 'UTC',
    ],
    
    'execution' => [
        'default_timeout' => 300,
        'memory_limit' => 512,
        'max_concurrent_jobs' => 3,
        'log_execution' => true,
    ],
    
    'locks' => [
        'directory' => storage_path('cron/locks'),
        'global_timeout' => 300,
        'default_job_timeout' => 120,
        'cleanup_interval' => 300,
    ],
];
```

## CLI Commands

### cron:run

Executes the cron scheduler (called by system crontab):

```bash
# Normal execution
php bin/treehouse cron:run

# Force execution even if locked
php bin/treehouse cron:run --force

# Run specific jobs only
php bin/treehouse cron:run --jobs=cache:cleanup,database:cleanup

# Run ALL jobs immediately (ignores schedules)
php bin/treehouse cron:run --run-all

# Dry run (show what would execute)
php bin/treehouse cron:run --dry-run

# Quiet mode (suppress output except errors)
php bin/treehouse cron:run --quiet

# Verbose mode (detailed output)
php bin/treehouse cron:run --verbose
```

### cron:list

Lists all registered cron jobs:

```bash
# List all jobs
php bin/treehouse cron:list

# Show only enabled jobs
php bin/treehouse cron:list --enabled-only

# Show next run times
php bin/treehouse cron:list --show-next-run

# Show job metadata
php bin/treehouse cron:list --show-metadata

# Filter jobs by pattern
php bin/treehouse cron:list --filter="cache:*"

# JSON output
php bin/treehouse cron:list --format=json
```

## Locking System

The cron system implements a comprehensive locking mechanism to prevent dangerous concurrent executions:

### Global Scheduler Lock

- Prevents multiple `cron:run` executions
- Automatically cleaned up on completion
- Respects timeout settings
- Can be force-released in emergencies

### Individual Job Locks

- Prevents overlapping executions of the same job
- Configurable per job
- Automatic stale lock cleanup
- Optional for jobs that allow concurrency

### Lock Files

Lock files are stored in `storage/cron/locks/`:

```
storage/cron/locks/
├── global.lock              # Global scheduler lock
└── jobs/
    ├── cache-cleanup.lock   # Individual job locks
    └── database-cleanup.lock
```

Each lock file contains metadata:

```json
{
    "pid": 12345,
    "started_at": 1704649200,
    "timeout": 300,
    "hostname": "server01",
    "job_name": "cache:cleanup"
}
```

## Monitoring and Logging

### Logging

The system provides comprehensive logging through the TreeHouse error logging system:

```php
// In your job
$this->logInfo('Job started');
$this->logWarning('Non-critical issue occurred');
$this->logError('Critical error', ['context' => 'data']);
```

Logs include:
- Job execution details
- Memory usage
- Execution time
- Error traces
- Lock information

### Job Results

Each job execution returns a `JobResult` object containing:

```php
$result = $executor->execute($job);

$result->isSuccess();           // bool
$result->getMessage();          // string
$result->getDuration();         // float (seconds)
$result->getMemoryUsed();       // int (bytes)
$result->getException();        // Throwable|null
$result->getFormattedDuration(); // "1.5s" or "2m 30s"
```

## Built-in Jobs

### CacheCleanupJob

Cleans expired cache entries and view cache files.

- **Schedule**: Daily at 2 AM (`0 2 * * *`)
- **Priority**: 30
- **Timeout**: 10 minutes

### LockCleanupJob

Removes stale lock files from the cron system.

- **Schedule**: Every 5 minutes (`*/5 * * * *`)
- **Priority**: 10 (high)
- **Timeout**: 1 minute
- **Concurrent**: Allowed

## Best Practices

### Job Design

1. **Keep jobs idempotent** - they should be safe to run multiple times
2. **Use appropriate timeouts** - prevent runaway processes
3. **Log meaningful information** - aid in debugging and monitoring
4. **Handle exceptions gracefully** - return false on failure
5. **Use priority wisely** - critical jobs get lower numbers (higher priority)

### Performance

1. **Avoid memory leaks** - clean up resources properly
2. **Use efficient queries** - database operations should be optimized
3. **Batch operations** - process data in chunks for large datasets
4. **Monitor execution time** - optimize slow jobs

### Security

1. **Validate input data** - never trust external data sources
2. **Use proper permissions** - lock files and logs should be secure
3. **Sanitize file operations** - prevent directory traversal attacks
4. **Log security events** - track suspicious activity

### Reliability

1. **Test jobs thoroughly** - use dry-run mode for testing
2. **Monitor job failures** - set up alerting for critical jobs
3. **Plan for failures** - jobs should degrade gracefully
4. **Use transactions** - database operations should be atomic

## Troubleshooting

### Common Issues

#### Jobs Not Running

1. Check if cron is properly configured in system crontab
2. Verify job is enabled: `php bin/treehouse cron:list --enabled-only`
3. Check cron expression syntax: `php bin/treehouse cron:list --show-next-run`
4. Look for global lock issues: check `storage/cron/locks/global.lock`

#### High Memory Usage

1. Check job timeout settings in configuration
2. Monitor job execution: `php bin/treehouse cron:run --verbose`
3. Optimize job logic to use less memory
4. Increase memory limits if necessary

#### Lock Issues

1. Clean stale locks: built-in `LockCleanupJob` runs every 5 minutes
2. Force unlock all: `php bin/treehouse cron:run --force`
3. Check lock directory permissions: `storage/cron/locks/`
4. Verify process detection is working correctly

#### Failed Jobs

1. Check error logs: `storage/logs/`
2. Run specific job for debugging: `php bin/treehouse cron:run --jobs=job-name --verbose`
3. Use dry-run mode: `php bin/treehouse cron:run --dry-run`
4. Verify job class exists and is properly registered

### Debug Commands

```bash
# Show system info and job status
php bin/treehouse cron:list --show-metadata --verbose

# Test specific job execution
php bin/treehouse cron:run --jobs=my-job --verbose

# Check what jobs would run now
php bin/treehouse cron:run --dry-run

# Run ALL jobs immediately (ignores schedules)
php bin/treehouse cron:run --run-all

# Test what ALL jobs would do without running them
php bin/treehouse cron:run --run-all --dry-run

# Run ALL jobs including disabled ones
php bin/treehouse cron:run --run-all --force

# Force clear all locks (emergency only)
php bin/treehouse cron:run --force
```

### Log Analysis

Check logs in `storage/logs/` for:
- Job execution results
- Lock acquisition/release events
- Error traces and stack dumps
- Memory and performance metrics

Example log entry:
```
[INFO] [CRON_SCHEDULER] Cron scheduler completed {"executed_jobs":2,"successful_jobs":2,"failed_jobs":0,"skipped_jobs":0,"duration":1.234}
```

## Advanced Usage

### Custom Job Registry

```php
use LengthOfRope\TreeHouse\Cron\JobRegistry;

$registry = new JobRegistry();
$registry->registerClass(MyCustomJob::class);
$registry->registerMany([Job1::class, Job2::class]);

// Get jobs due now
$dueJobs = $registry->getDueJobs();

// Get jobs by priority
$prioritizedJobs = $registry->getJobsByPriority();
```

### Custom Lock Manager

```php
use LengthOfRope\TreeHouse\Cron\Locking\LockManager;

$lockManager = new LockManager('/custom/lock/path');

// Acquire custom lock
if ($lockManager->acquireJobLock('my-operation', 600)) {
    // Do work
    $lockManager->releaseJobLock('my-operation');
}
```

### Programmatic Execution

```php
use LengthOfRope\TreeHouse\Cron\CronScheduler;
use LengthOfRope\TreeHouse\Cron\JobRegistry;
use LengthOfRope\TreeHouse\Cron\JobExecutor;
use LengthOfRope\TreeHouse\Cron\Locking\LockManager;

$lockManager = new LockManager('/path/to/locks');
$jobRegistry = new JobRegistry();
$jobExecutor = new JobExecutor($lockManager);
$scheduler = new CronScheduler($jobRegistry, $jobExecutor, $lockManager);

// Register jobs
$jobRegistry->registerClass(MyJob::class);

// Run scheduler
$results = $scheduler->run();

foreach ($results as $jobName => $result) {
    if ($result->isSuccess()) {
        echo "Job $jobName completed successfully\n";
    } else {
        echo "Job $jobName failed: " . $result->getMessage() . "\n";
    }
}
```

This comprehensive cron system provides enterprise-grade scheduled job execution with safety, monitoring, and management capabilities built into the TreeHouse framework.