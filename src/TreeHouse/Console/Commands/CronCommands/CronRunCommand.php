<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\CronCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Cron\CronScheduler;
use LengthOfRope\TreeHouse\Cron\JobRegistry;
use LengthOfRope\TreeHouse\Cron\JobExecutor;
use LengthOfRope\TreeHouse\Cron\Locking\LockManager;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;

/**
 * Cron Run Command
 * 
 * Main command for executing the cron scheduler. Called every minute
 * from the system crontab to process scheduled jobs.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\CronCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class CronRunCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('cron:run')
            ->setDescription('Execute scheduled cron jobs')
            ->setHelp('This command processes all scheduled cron jobs that are due to run.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force execution even if scheduler is locked')
            ->addOption('timestamp', 't', InputOption::VALUE_OPTIONAL, 'Specific timestamp to run for (for testing)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what jobs would run without executing them')
            ->addOption('jobs', 'j', InputOption::VALUE_OPTIONAL, 'Comma-separated list of specific jobs to run')
            ->addOption('run-all', null, InputOption::VALUE_NONE, 'Run all registered jobs immediately (ignores schedule)')
            ->addOption('quiet', 'q', InputOption::VALUE_NONE, 'Suppress output (except errors)')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Increase output verbosity');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Setup scheduler components
            $scheduler = $this->createScheduler($output);
            
            // Handle options
            $force = (bool) $input->getOption('force');
            $timestamp = $input->getOption('timestamp') ? (int) $input->getOption('timestamp') : null;
            $dryRun = (bool) $input->getOption('dry-run');
            $specificJobs = $input->getOption('jobs') ? explode(',', $input->getOption('jobs')) : null;
            $runAll = (bool) $input->getOption('run-all');
            $quiet = (bool) $input->getOption('quiet');
            $verbose = (bool) $input->getOption('verbose');

            if (!$quiet) {
                $this->info($output, 'TreeHouse Cron Scheduler');
                $this->comment($output, 'Checking for scheduled jobs...');
                
                if ($verbose) {
                    $this->showSystemInfo($output);
                }
            }

            // Handle dry run
            if ($dryRun) {
                return $this->handleDryRun($scheduler, $output, $timestamp, $specificJobs, $runAll);
            }

            // Handle run all
            if ($runAll) {
                return $this->runAllJobsNow($scheduler, $output, $force);
            }

            // Handle specific jobs
            if ($specificJobs) {
                return $this->runSpecificJobs($scheduler, $output, $specificJobs, $force);
            }

            // Regular cron run
            $results = $scheduler->run($timestamp, $force);

            // Output results
            if (!$quiet) {
                $this->displayResults($output, $results, $verbose);
            }

            // Return appropriate exit code
            $hasFailures = !empty(array_filter($results, fn($r) => !$r->isSuccess() && !$r->isSkipped()));
            return $hasFailures ? 1 : 0;

        } catch (\Throwable $e) {
            $this->error($output, "Cron execution failed: {$e->getMessage()}");
            
            if ($input->getOption('verbose')) {
                $this->comment($output, "Stack trace:");
                $output->writeln($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Create and configure the scheduler
     */
    private function createScheduler(OutputInterface $output): CronScheduler
    {
        // Create lock manager
        $lockDirectory = $this->getLockDirectory();
        $lockManager = new LockManager($lockDirectory);

        // Create logger
        $logger = new ErrorLogger();

        // Create job registry and load jobs
        $jobRegistry = new JobRegistry();
        $this->loadJobs($jobRegistry, $output);

        // Create job executor
        $jobExecutor = new JobExecutor($lockManager, $logger);

        // Create scheduler
        return new CronScheduler($jobRegistry, $jobExecutor, $lockManager, $logger);
    }

    /**
     * Load jobs into the registry
     */
    private function loadJobs(JobRegistry $jobRegistry, OutputInterface $output): void
    {
        // Load jobs from configuration
        $config = $this->loadCronConfig();
        
        if (isset($config['jobs']) && is_array($config['jobs'])) {
            try {
                $jobRegistry->registerMany($config['jobs']);
                
                if ($output->isVerbose()) {
                    $this->info($output, "Loaded " . count($config['jobs']) . " jobs from configuration");
                }
            } catch (\Throwable $e) {
                $this->warn($output, "Failed to load some jobs: {$e->getMessage()}");
            }
        }

        // Load built-in jobs
        $this->loadBuiltInJobs($jobRegistry, $output);
    }

    /**
     * Load built-in framework jobs
     */
    private function loadBuiltInJobs(JobRegistry $jobRegistry, OutputInterface $output): void
    {
        $loaded = $jobRegistry->loadBuiltInJobs(true);

        if ($output->isVerbose() && $loaded > 0) {
            $this->info($output, "Loaded {$loaded} built-in jobs");
        }
    }

    /**
     * Handle dry run mode
     */
    private function handleDryRun(CronScheduler $scheduler, OutputInterface $output, ?int $timestamp, ?array $specificJobs, bool $runAll = false): int
    {
        $this->info($output, 'DRY RUN MODE - No jobs will be executed');
        
        if ($runAll) {
            // Show all registered jobs
            $allJobs = $scheduler->getJobRegistry()->getAllJobs();
            $this->comment($output, 'All registered jobs that would be executed:');
            
            if (empty($allJobs)) {
                $this->comment($output, 'No jobs are registered');
                return 0;
            }
            
            foreach ($allJobs as $name => $job) {
                $status = $job->isEnabled() ? 'Enabled' : 'Disabled';
                $this->line($output, "  • {$name} - {$job->getDescription()} [{$status}]");
                $this->line($output, "    Schedule: {$job->getSchedule()}");
                $this->line($output, "    Priority: {$job->getPriority()}");
                $this->line($output, "    Timeout: {$job->getTimeout()}s");
            }
            
            return 0;
        }
        
        $dueJobs = $scheduler->getDueJobs($timestamp);
        
        if ($specificJobs) {
            $dueJobs = array_filter($dueJobs, fn($job, $name) => in_array($name, $specificJobs), ARRAY_FILTER_USE_BOTH);
        }

        if (empty($dueJobs)) {
            $this->comment($output, 'No jobs are due to run');
            return 0;
        }

        $this->comment($output, 'Jobs that would be executed:');
        foreach ($dueJobs as $name => $job) {
            $this->line($output, "  • {$name} - {$job->getDescription()}");
            $this->line($output, "    Schedule: {$job->getSchedule()}");
            $this->line($output, "    Priority: {$job->getPriority()}");
            $this->line($output, "    Timeout: {$job->getTimeout()}s");
        }

        return 0;
    }

    /**
     * Run all registered jobs immediately
     */
    private function runAllJobsNow(CronScheduler $scheduler, OutputInterface $output, bool $force): int
    {
        $this->info($output, 'RUN ALL - Executing all registered jobs immediately');
        
        // Get all registered jobs (enabled and disabled)
        $jobRegistry = $scheduler->getJobRegistry();
        $allJobs = $jobRegistry->getAllJobs();
        
        if (empty($allJobs)) {
            $this->comment($output, 'No jobs are registered');
            return 0;
        }
        
        $this->comment($output, 'Running ' . count($allJobs) . ' jobs immediately (ignoring schedules)...');
        
        $results = [];
        $jobExecutor = $scheduler->getJobExecutor();
        
        // Sort jobs by priority (lower number = higher priority)
        $sortedJobs = $allJobs;
        uasort($sortedJobs, function($a, $b) {
            return $a->getPriority() <=> $b->getPriority();
        });
        
        foreach ($sortedJobs as $name => $job) {
            // Show what we're about to run
            $status = $job->isEnabled() ? 'Enabled' : 'Disabled';
            $this->comment($output, "Executing: {$name} [{$status}]");
            
            // Skip disabled jobs unless force is used
            if (!$force && !$job->isEnabled()) {
                $this->warn($output, "  Skipping disabled job '{$name}' (use --force to run disabled jobs)");
                continue;
            }
            
            // Execute the job with force=true to bypass any additional checks
            $result = $jobExecutor->execute($job, true);
            $results[$name] = $result;
            
            // Show immediate feedback
            if ($result->isSuccess()) {
                $duration = $result->getFormattedDuration();
                $this->line($output, "  ✓ Completed successfully [{$duration}]", 'info');
            } else {
                $this->line($output, "  ✗ Failed: " . $result->getMessage(), 'error');
            }
        }
        
        // Display final results
        $this->displayResults($output, $results, true);
        
        $hasFailures = !empty(array_filter($results, fn($r) => !$r->isSuccess()));
        return $hasFailures ? 1 : 0;
    }

    /**
     * Run specific jobs
     */
    private function runSpecificJobs(CronScheduler $scheduler, OutputInterface $output, array $jobNames, bool $force): int
    {
        $this->info($output, 'Running specific jobs: ' . implode(', ', $jobNames));
        
        // Get job registry to access specific jobs
        $jobRegistry = $scheduler->getJobRegistry();
        $results = [];
        
        foreach ($jobNames as $jobName) {
            $jobName = trim($jobName);
            $job = $jobRegistry->getJob($jobName);
            
            if (!$job) {
                $this->error($output, "Job '{$jobName}' not found");
                continue;
            }
            
            if (!$force && !$job->isEnabled()) {
                $this->warn($output, "Job '{$jobName}' is disabled, use --force to run anyway");
                continue;
            }
            
            $this->comment($output, "Executing job: {$jobName}");
            
            // Execute single job
            $result = $scheduler->getJobExecutor()->execute($job, $force);
            $results[$jobName] = $result;
        }
        
        $this->displayResults($output, $results, true);
        
        $hasFailures = !empty(array_filter($results, fn($r) => !$r->isSuccess()));
        return $hasFailures ? 1 : 0;
    }

    /**
     * Display execution results
     */
    private function displayResults(OutputInterface $output, array $results, bool $verbose): void
    {
        if (empty($results)) {
            $this->comment($output, 'No jobs were executed');
            return;
        }

        $successful = array_filter($results, fn($r) => $r->isSuccess());
        $failed = array_filter($results, fn($r) => !$r->isSuccess() && !$r->isSkipped());
        $skipped = array_filter($results, fn($r) => $r->isSkipped());

        $this->info($output, sprintf(
            'Execution completed: %d successful, %d failed, %d skipped',
            count($successful),
            count($failed),
            count($skipped)
        ));

        if ($verbose || !empty($failed)) {
            foreach ($results as $jobName => $result) {
                $status = $result->isSuccess() ? '✓' : ($result->isSkipped() ? '↷' : '✗');
                $color = $result->isSuccess() ? 'info' : ($result->isSkipped() ? 'comment' : 'error');
                
                $message = sprintf(
                    '%s %s (%s)',
                    $status,
                    $jobName,
                    $result->getMessage()
                );
                
                if ($verbose && $result->getDuration() !== null) {
                    $message .= sprintf(' [%s]', $result->getFormattedDuration());
                }
                
                $this->line($output, $message, $color);
                
                if (!$result->isSuccess() && !$result->isSkipped() && $result->hasException()) {
                    $this->line($output, "    Error: " . $result->getException()->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * Show system information
     */
    private function showSystemInfo(OutputInterface $output): void
    {
        $this->comment($output, 'System Information:');
        $this->line($output, '  PHP Version: ' . PHP_VERSION);
        $this->line($output, '  Memory Usage: ' . round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB');
        $this->line($output, '  Time: ' . date('Y-m-d H:i:s'));
        $this->line($output, '  Timezone: ' . date_default_timezone_get());
        
        if (PHP_OS_FAMILY !== 'Windows') {
            $load = sys_getloadavg();
            if ($load !== false) {
                $this->line($output, '  Load Average: ' . implode(', ', array_map(fn($l) => round($l, 2), $load)));
            }
        }
        
        $output->writeln('');
    }

    /**
     * Get lock directory path
     */
    private function getLockDirectory(): string
    {
        return getcwd() . '/storage/cron/locks';
    }

    /**
     * Load cron configuration
     *
     * @return array<string, mixed>
     */
    private function loadCronConfig(): array
    {
        $configFile = getcwd() . '/config/cron.php';
        
        if (file_exists($configFile)) {
            try {
                return require $configFile;
            } catch (\Throwable $e) {
                // Return empty config if file can't be loaded
                return [];
            }
        }
        
        return [];
    }
}