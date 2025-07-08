<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Queue;

use LengthOfRope\TreeHouse\Cron\CronJobInterface;
use LengthOfRope\TreeHouse\Foundation\Application;
use Exception;

/**
 * Mail Queue Processor Cron Job
 * 
 * Processes queued emails automatically via cron scheduling.
 * Handles batch processing, retry logic, and cleanup operations.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Queue
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailQueueProcessor implements CronJobInterface
{
    /**
     * Application instance
     */
    protected Application $app;

    /**
     * Create a new MailQueueProcessor instance
     * 
     * @param Application $app Application instance
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Execute the mail queue processing job
     * 
     * @return bool True on success, false on failure
     */
    public function execute(): bool
    {
        try {
            // Get mail queue service
            $mailQueue = $this->app->make('mail.queue');
            
            // Get configuration
            $config = $this->app->config('mail.queue', []);
            $batchSize = $config['batch_size'] ?? 10;
            
            // 1. Release expired reservations
            $mailQueue->releaseExpiredReservations();
            
            // 2. Retry ready emails
            $mailQueue->retryReady();
            
            // 3. Process queue in batches
            $processedBatches = 0;
            $maxBatches = 10; // Prevent infinite loops
            
            while ($processedBatches < $maxBatches) {
                $batchResult = $mailQueue->processQueue($batchSize);
                
                if ($batchResult['processed'] === 0) {
                    // No more emails to process
                    break;
                }
                
                $processedBatches++;
                
                // If we have failures, we still continue but log them
                if ($batchResult['failed'] > 0) {
                    error_log("Mail queue processing: {$batchResult['failed']} emails failed in batch {$processedBatches}");
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Mail queue processing failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the job name
     * 
     * @return string
     */
    public function getName(): string
    {
        return 'mail-queue-processor';
    }

    /**
     * Get the job description
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return 'Processes queued emails and handles retry logic';
    }

    /**
     * Get the cron expression for scheduling
     * 
     * @return string
     */
    public function getSchedule(): string
    {
        // Run every minute
        return '* * * * *';
    }

    /**
     * Check if the job is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        // Check if mail queue is enabled
        $queueConfig = $this->app->config('mail.queue', []);
        return $queueConfig['enabled'] ?? false;
    }

    /**
     * Get the job timeout in seconds
     * 
     * @return int
     */
    public function getTimeout(): int
    {
        return 300; // 5 minutes
    }

    /**
     * Get the job priority
     * 
     * @return int
     */
    public function getPriority(): int
    {
        return 50; // Normal priority
    }

    /**
     * Check if the job allows concurrent execution
     * 
     * @return bool
     */
    public function allowsConcurrentExecution(): bool
    {
        return false; // Prevent multiple instances from running simultaneously
    }

    /**
     * Get job metadata
     * 
     * @return array
     */
    public function getMetadata(): array
    {
        $stats = [];
        
        try {
            $mailQueue = $this->app->make('mail.queue');
            $stats = $mailQueue->getStats();
        } catch (Exception $e) {
            // If we can't get stats, just return empty metadata
        }
        
        return [
            'type' => 'mail-queue-processor',
            'queue_stats' => $stats,
            'config' => $this->app->config('mail.queue', []),
        ];
    }
}