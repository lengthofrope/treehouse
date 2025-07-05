<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\CronCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Cron\JobRegistry;
use LengthOfRope\TreeHouse\Cron\CronExpressionParser;

/**
 * Cron List Command
 * 
 * Lists all registered cron jobs with their schedules, status, and metadata.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\CronCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class CronListCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('cron:list')
            ->setDescription('List all registered cron jobs')
            ->setHelp('This command displays information about all registered cron jobs.')
            ->addOption('enabled-only', 'e', InputOption::VALUE_NONE, 'Show only enabled jobs')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (table, json)', 'table')
            ->addOption('show-next-run', 'n', InputOption::VALUE_NONE, 'Show next run times')
            ->addOption('show-metadata', 'm', InputOption::VALUE_NONE, 'Show job metadata')
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'Filter jobs by name pattern');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Create job registry and load jobs
            $jobRegistry = $this->createJobRegistry();

            // Get jobs based on options
            $enabledOnly = (bool) $input->getOption('enabled-only');
            $jobs = $enabledOnly ? $jobRegistry->getEnabledJobs() : $jobRegistry->getAllJobs();

            // Apply filter if specified
            $filter = $input->getOption('filter');
            if ($filter) {
                $jobs = $this->filterJobs($jobs, $filter);
            }

            // Get output format
            $format = $input->getOption('format') ?? 'table';
            $showNextRun = (bool) $input->getOption('show-next-run');
            $showMetadata = (bool) $input->getOption('show-metadata');

            if (empty($jobs)) {
                $this->comment($output, 'No cron jobs found.');
                return 0;
            }

            // Display jobs in requested format
            switch ($format) {
                case 'json':
                    $this->displayJsonFormat($output, $jobs, $showNextRun, $showMetadata);
                    break;
                case 'table':
                default:
                    $this->displayTableFormat($output, $jobs, $showNextRun, $showMetadata);
                    break;
            }

            // Display summary
            $this->displaySummary($output, $jobRegistry, $enabledOnly);

            return 0;

        } catch (\Throwable $e) {
            $this->error($output, "Failed to list cron jobs: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Create and configure job registry
     */
    private function createJobRegistry(): JobRegistry
    {
        $jobRegistry = new JobRegistry();

        // Load jobs from configuration
        $config = $this->loadCronConfig();
        if (isset($config['jobs']) && is_array($config['jobs'])) {
            try {
                $jobRegistry->registerMany($config['jobs']);
            } catch (\Throwable $e) {
                // Continue with built-in jobs even if config jobs fail
            }
        }

        // Load built-in jobs
        $builtInJobs = [
            \LengthOfRope\TreeHouse\Cron\Jobs\CacheCleanupJob::class,
            \LengthOfRope\TreeHouse\Cron\Jobs\LockCleanupJob::class,
        ];

        foreach ($builtInJobs as $jobClass) {
            if (class_exists($jobClass)) {
                try {
                    $jobRegistry->registerClass($jobClass);
                } catch (\Throwable $e) {
                    // Continue with other jobs
                }
            }
        }

        return $jobRegistry;
    }

    /**
     * Filter jobs by name pattern
     */
    private function filterJobs(array $jobs, string $pattern): array
    {
        return array_filter($jobs, function($job, $name) use ($pattern) {
            return fnmatch($pattern, $name) || fnmatch($pattern, get_class($job));
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Display jobs in table format
     */
    private function displayTableFormat(OutputInterface $output, array $jobs, bool $showNextRun, bool $showMetadata): void
    {
        $this->info($output, 'Registered Cron Jobs:');
        $output->writeln('');

        // Table headers
        $headers = ['Job Name', 'Schedule', 'Description', 'Status', 'Priority', 'Timeout'];
        if ($showNextRun) {
            $headers[] = 'Next Run';
        }

        // Calculate column widths
        $widths = [
            'name' => max(8, max(array_map('strlen', array_keys($jobs)))),
            'schedule' => 15,
            'description' => 40,
            'status' => 8,
            'priority' => 8,
            'timeout' => 8,
        ];

        if ($showNextRun) {
            $widths['next_run'] = 19;
        }

        // Display header
        $this->displayTableRow($output, $headers, $widths, 'comment');
        $this->displayTableSeparator($output, $widths);

        // Display jobs
        foreach ($jobs as $name => $job) {
            $row = [
                substr($name, 0, $widths['name']),
                substr($job->getSchedule(), 0, $widths['schedule']),
                substr($job->getDescription(), 0, $widths['description']),
                $job->isEnabled() ? 'Enabled' : 'Disabled',
                (string) $job->getPriority(),
                $job->getTimeout() . 's',
            ];

            if ($showNextRun) {
                try {
                    $parser = new CronExpressionParser($job->getSchedule());
                    $nextRun = $parser->getNextRunTime();
                    $row[] = $nextRun ? date('Y-m-d H:i:s', $nextRun) : 'Never';
                } catch (\Throwable $e) {
                    $row[] = 'Invalid';
                }
            }

            $color = $job->isEnabled() ? 'info' : 'comment';
            $this->displayTableRow($output, $row, $widths, $color);

            // Show metadata if requested
            if ($showMetadata) {
                $metadata = $job->getMetadata();
                if (!empty($metadata)) {
                    $this->line($output, '    Metadata: ' . json_encode($metadata), 'comment');
                }
            }
        }
    }

    /**
     * Display table row
     */
    private function displayTableRow(OutputInterface $output, array $row, array $widths, string $color = null): void
    {
        $line = '';
        $i = 0;
        foreach (array_values($widths) as $width) {
            $value = $row[$i] ?? '';
            $line .= str_pad($value, $width + 2);
            $i++;
        }

        $this->line($output, $line, $color);
    }

    /**
     * Display table separator
     */
    private function displayTableSeparator(OutputInterface $output, array $widths): void
    {
        $line = '';
        foreach (array_values($widths) as $width) {
            $line .= str_repeat('-', $width + 2);
        }
        $output->writeln($line);
    }

    /**
     * Display jobs in JSON format
     */
    private function displayJsonFormat(OutputInterface $output, array $jobs, bool $showNextRun, bool $showMetadata): void
    {
        $jobsData = [];

        foreach ($jobs as $name => $job) {
            $jobData = [
                'name' => $name,
                'class' => get_class($job),
                'schedule' => $job->getSchedule(),
                'description' => $job->getDescription(),
                'enabled' => $job->isEnabled(),
                'priority' => $job->getPriority(),
                'timeout' => $job->getTimeout(),
                'allows_concurrent' => $job->allowsConcurrentExecution(),
            ];

            if ($showNextRun) {
                try {
                    $parser = new CronExpressionParser($job->getSchedule());
                    $nextRun = $parser->getNextRunTime();
                    $jobData['next_run'] = $nextRun ? date('c', $nextRun) : null;
                    $jobData['schedule_description'] = $parser->getDescription();
                } catch (\Throwable $e) {
                    $jobData['next_run'] = null;
                    $jobData['schedule_error'] = $e->getMessage();
                }
            }

            if ($showMetadata) {
                $jobData['metadata'] = $job->getMetadata();
            }

            $jobsData[] = $jobData;
        }

        $output->writeln(json_encode($jobsData, JSON_PRETTY_PRINT));
    }

    /**
     * Display summary information
     */
    private function displaySummary(OutputInterface $output, JobRegistry $jobRegistry, bool $enabledOnly): void
    {
        $output->writeln('');
        
        $summary = $jobRegistry->getSummary();
        
        if ($enabledOnly) {
            $this->info($output, "Total enabled jobs: {$summary['enabled_jobs']}");
        } else {
            $this->info($output, "Total jobs: {$summary['total_jobs']} ({$summary['enabled_jobs']} enabled, {$summary['disabled_jobs']} disabled)");
        }
        
        $this->comment($output, "Jobs due now: {$summary['due_jobs']}");
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
                return [];
            }
        }
        
        return [];
    }
}