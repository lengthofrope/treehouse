<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\MailCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Foundation\Application;
use Exception;

/**
 * Mail Queue Status Command
 * 
 * Shows the current status and statistics of the mail queue.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\MailCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailQueueStatusCommand extends Command
{
    /**
     * The command name
     */
    protected string $name = 'mail:queue:status';

    /**
     * The command description
     */
    protected string $description = 'Show mail queue status and statistics';

    /**
     * Application instance
     */
    protected Application $app;

    /**
     * Create a new command instance
     */
    public function __construct(Application $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName($this->name)
             ->setDescription($this->description)
             ->addOption('metrics', 'm', InputOption::VALUE_NONE, 'Show performance metrics')
             ->addOption('details', 'd', InputOption::VALUE_NONE, 'Show detailed queue information');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $mailQueue = $this->app->make('mail.queue');
            $showMetrics = $input->getOption('metrics', false);
            $showDetails = $input->getOption('details', false);
            
            $output->writeln('<info>Mail Queue Status</info>');
            $output->writeln('==================');
            
            // Get basic queue statistics
            $stats = $mailQueue->getStats();
            
            $output->writeln("Pending emails:    <comment>{$stats['pending']}</comment>");
            $output->writeln("Processing emails: <comment>{$stats['processing']}</comment>");
            $output->writeln("Sent emails:       <comment>{$stats['sent']}</comment>");
            $output->writeln("Failed emails:     <comment>{$stats['failed']}</comment>");
            
            if (isset($stats['retry_queue'])) {
                $output->writeln("Retry queue:       <comment>{$stats['retry_queue']}</comment>");
            }
            
            // Show configuration
            if ($showDetails) {
                $this->showConfiguration($output);
            }
            
            // Show performance metrics
            if ($showMetrics) {
                $this->showPerformanceMetrics($output);
            }
            
            // Show warnings if needed
            $this->showWarnings($output, $stats);
            
            return 0; // SUCCESS
            
        } catch (Exception $e) {
            $output->writeln('<error>Failed to get queue status: ' . $e->getMessage() . '</error>');
            return 1; // FAILURE
        }
    }

    /**
     * Show queue configuration details
     */
    protected function showConfiguration(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Queue Configuration</info>');
        $output->writeln('-------------------');
        
        $config = $this->app->config('mail.queue', []);
        
        $enabled = $config['enabled'] ?? false;
        $output->writeln("Enabled:           <comment>" . ($enabled ? 'Yes' : 'No') . "</comment>");
        $output->writeln("Batch size:        <comment>{$config['batch_size']}</comment>");
        $output->writeln("Max attempts:      <comment>{$config['max_attempts']}</comment>");
        $output->writeln("Retry strategy:    <comment>{$config['retry_strategy']}</comment>");
        $output->writeln("Base retry delay:  <comment>{$config['base_retry_delay']}s</comment>");
        $output->writeln("Max retry delay:   <comment>{$config['max_retry_delay']}s</comment>");
    }

    /**
     * Show performance metrics
     */
    protected function showPerformanceMetrics(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Performance Metrics (24h)</info>');
        $output->writeln('---------------------------');
        
        try {
            $queuedMailClass = \LengthOfRope\TreeHouse\Mail\Queue\QueuedMail::class;
            $metrics = $queuedMailClass::getPerformanceMetrics(24);
            
            if ($metrics['total_processed'] > 0) {
                $output->writeln("Total processed:     <comment>{$metrics['total_processed']}</comment>");
                $output->writeln("Avg queue time:      <comment>{$metrics['avg_queue_time']}s</comment>");
                $output->writeln("Avg processing time: <comment>{$metrics['avg_processing_time']}s</comment>");
                $output->writeln("Avg delivery time:   <comment>{$metrics['avg_delivery_time']}s</comment>");
                $output->writeln("Max queue time:      <comment>{$metrics['max_queue_time']}s</comment>");
                $output->writeln("Max processing time: <comment>{$metrics['max_processing_time']}s</comment>");
                $output->writeln("Max delivery time:   <comment>{$metrics['max_delivery_time']}s</comment>");
            } else {
                $output->writeln('<comment>No emails processed in the last 24 hours</comment>');
            }
            
        } catch (Exception $e) {
            $output->writeln('<error>Failed to get performance metrics: ' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Show warnings based on queue status
     */
    protected function showWarnings(OutputInterface $output, array $stats): void
    {
        $warnings = [];
        
        // Check for high failure rate
        $total = $stats['sent'] + $stats['failed'];
        if ($total > 0) {
            $failureRate = $stats['failed'] / $total;
            if ($failureRate > 0.1) { // 10% failure rate
                $percentage = round($failureRate * 100, 1);
                $warnings[] = "High failure rate: {$percentage}% of emails are failing";
            }
        }
        
        // Check for stuck processing emails
        if ($stats['processing'] > 50) {
            $warnings[] = "Many emails stuck in processing state: {$stats['processing']}";
        }
        
        // Check for large pending queue
        if ($stats['pending'] > 1000) {
            $warnings[] = "Large pending queue: {$stats['pending']} emails waiting";
        }
        
        // Check if queue is enabled
        $config = $this->app->config('mail.queue', []);
        if (!($config['enabled'] ?? false)) {
            $warnings[] = "Mail queue is disabled - emails will be sent immediately";
        }
        
        if (!empty($warnings)) {
            $output->writeln('');
            $output->writeln('<error>Warnings:</error>');
            foreach ($warnings as $warning) {
                $output->writeln("  - <comment>{$warning}</comment>");
            }
        }
    }
}