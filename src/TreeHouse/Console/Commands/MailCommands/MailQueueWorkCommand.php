<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\MailCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use Exception;

// Import helper functions
require_once __DIR__ . '/../../../Support/helpers.php';

/**
 * Mail Queue Work Command
 * 
 * Processes emails in the mail queue manually or continuously.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\MailCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailQueueWorkCommand extends Command
{
    /**
     * The command name
     */
    protected string $name = 'mail:queue:work';

    /**
     * The command description
     */
    protected string $description = 'Process emails in the mail queue';


    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName($this->name)
             ->setDescription($this->description)
             ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of emails to process (default: 10)', 10)
             ->addOption('continuous', 'c', InputOption::VALUE_NONE, 'Run continuously (for development only)')
             ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Timeout in seconds for continuous mode (default: 60)', 60);
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $mailQueue = $this->getMailQueue();
            
            $limit = (int) $input->getOption('limit', 10);
            $continuous = $input->getOption('continuous', false);
            $timeout = (int) $input->getOption('timeout', 60);
            
            $output->writeln('<info>Starting mail queue processing...</info>');
            
            if ($continuous) {
                $output->writeln('<comment>Running in continuous mode (Ctrl+C to stop)</comment>');
                return $this->runContinuous($mailQueue, $output, $limit, $timeout);
            } else {
                return $this->runOnce($mailQueue, $output, $limit);
            }
            
        } catch (Exception $e) {
            $output->writeln('<error>Failed to process mail queue: ' . $e->getMessage() . '</error>');
            return 1; // FAILURE
        }
    }

    /**
     * Run queue processing once
     */
    protected function runOnce($mailQueue, OutputInterface $output, int $limit): int
    {
        $output->writeln("Processing up to {$limit} emails...");
        
        // Get queue stats before processing
        $statsBefore = $mailQueue->getStats();
        $output->writeln("Queue status: {$statsBefore['pending']} pending, {$statsBefore['failed']} failed");
        
        // Release expired reservations
        $released = $mailQueue->releaseExpiredReservations();
        if ($released > 0) {
            $output->writeln("<info>Released {$released} expired reservations</info>");
        }
        
        // Retry ready emails
        $retried = $mailQueue->retryReady();
        if ($retried > 0) {
            $output->writeln("<info>Retried {$retried} emails</info>");
        }
        
        // Process queue
        $result = $mailQueue->processQueue($limit);
        
        if ($result['processed'] === 0) {
            $output->writeln('<comment>No emails to process</comment>');
            return 0; // SUCCESS
        }
        
        $output->writeln("<info>Processed {$result['processed']} emails:</info>");
        $output->writeln("  - Sent: {$result['sent']}");
        $output->writeln("  - Failed: {$result['failed']}");
        
        if (!empty($result['errors'])) {
            $output->writeln('<error>Errors encountered:</error>');
            foreach ($result['errors'] as $error) {
                $output->writeln("  - Email ID {$error['email_id']}: {$error['error']}");
            }
        }
        
        // Get queue stats after processing
        $statsAfter = $mailQueue->getStats();
        $output->writeln("Queue status after processing: {$statsAfter['pending']} pending, {$statsAfter['failed']} failed");
        
        return 0; // SUCCESS
    }

    /**
     * Run queue processing continuously
     */
    protected function runContinuous($mailQueue, OutputInterface $output, int $limit, int $timeout): int
    {
        $startTime = time();
        $totalProcessed = 0;
        $totalSent = 0;
        $totalFailed = 0;
        
        while (true) {
            // Check timeout
            if (time() - $startTime >= $timeout) {
                $output->writeln('<comment>Timeout reached, stopping...</comment>');
                break;
            }
            
            // Process a batch
            $result = $mailQueue->processQueue($limit);
            
            if ($result['processed'] > 0) {
                $totalProcessed += $result['processed'];
                $totalSent += $result['sent'];
                $totalFailed += $result['failed'];
                
                $output->writeln("[" . date('H:i:s') . "] Processed {$result['processed']} emails (sent: {$result['sent']}, failed: {$result['failed']})");
                
                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $output->writeln("  <error>Email ID {$error['email_id']}: {$error['error']}</error>");
                    }
                }
            } else {
                $output->write('.');
            }
            
            // Short sleep to prevent excessive CPU usage
            sleep(1);
        }
        
        $output->writeln("\n<info>Continuous processing completed:</info>");
        $output->writeln("  - Total processed: {$totalProcessed}");
        $output->writeln("  - Total sent: {$totalSent}");
        $output->writeln("  - Total failed: {$totalFailed}");
        
        return 0; // SUCCESS
    }

    /**
     * Get the mail queue service
     */
    protected function getMailQueue(): \LengthOfRope\TreeHouse\Mail\Queue\MailQueue
    {
        // Create TreeHouse Application instance
        $projectRoot = getcwd();
        $app = new \LengthOfRope\TreeHouse\Foundation\Application($projectRoot);
        
        return $app->make('mail.queue');
    }
}