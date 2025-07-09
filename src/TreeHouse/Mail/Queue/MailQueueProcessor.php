<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Queue;

use LengthOfRope\TreeHouse\Cron\CronJob;
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
class MailQueueProcessor extends CronJob
{
    /**
     * Configure the job
     */
    public function __construct()
    {
        $this->setName('mail:queue:process')
            ->setDescription('Processes queued emails and handles retry logic')
            ->setSchedule('* * * * *') // Every minute
            ->setPriority(50) // Normal priority
            ->setTimeout(300) // 5 minutes
            ->setAllowsConcurrentExecution(false) // Prevent multiple instances
            ->addMetadata('category', 'mail')
            ->addMetadata('type', 'queue-processor');
    }

    /**
     * Handle the job execution
     * 
     * @return bool True on success, false on failure
     */
    public function handle(): bool
    {
        try {
            $this->logInfo('Starting mail queue processing');

            // Get the TreeHouse application instance
            $app = $this->getTreeHouseApp();
            
            // Check if mail queue is enabled
            $queueConfig = $app->config('mail.queue', []);
            if (!($queueConfig['enabled'] ?? false)) {
                $this->logInfo('Mail queue is disabled, skipping processing');
                return true;
            }
            
            // Get mail queue service
            $mailQueue = $app->make('mail.queue');
            
            // Get configuration
            $batchSize = $queueConfig['batch_size'] ?? 10;
            
            // 1. Release expired reservations
            $released = $mailQueue->releaseExpiredReservations();
            if ($released > 0) {
                $this->logInfo("Released {$released} expired reservations");
            }
            
            // 2. Retry ready emails
            $retried = $mailQueue->retryReady();
            if ($retried > 0) {
                $this->logInfo("Retried {$retried} emails");
            }
            
            // 3. Process queue in batches
            $processedBatches = 0;
            $maxBatches = 10; // Prevent infinite loops
            $totalProcessed = 0;
            $totalSent = 0;
            $totalFailed = 0;
            
            while ($processedBatches < $maxBatches) {
                $batchResult = $mailQueue->processQueue($batchSize);
                
                if ($batchResult['processed'] === 0) {
                    // No more emails to process
                    break;
                }
                
                $processedBatches++;
                $totalProcessed += $batchResult['processed'];
                $totalSent += $batchResult['sent'];
                $totalFailed += $batchResult['failed'];
                
                // If we have failures, log them but continue
                if ($batchResult['failed'] > 0) {
                    $this->logWarning("Batch {$processedBatches}: {$batchResult['failed']} emails failed");
                    
                    // Log specific errors if available
                    if (!empty($batchResult['errors'])) {
                        foreach ($batchResult['errors'] as $error) {
                            $this->logError("Email ID {$error['email_id']}: {$error['error']}");
                        }
                    }
                }
            }
            
            if ($totalProcessed > 0) {
                $this->logInfo("Mail queue processing completed successfully", [
                    'batches_processed' => $processedBatches,
                    'total_processed' => $totalProcessed,
                    'total_sent' => $totalSent,
                    'total_failed' => $totalFailed,
                    'memory_after' => $this->getMemoryUsage()
                ]);
            } else {
                $this->logInfo('No emails to process');
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logError("Mail queue processing failed: {$e->getMessage()}", [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Check if the job is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        try {
            // Get the TreeHouse application instance
            $app = $this->getTreeHouseApp();
            
            // Check if mail queue is enabled
            $queueConfig = $app->config('mail.queue', []);
            return $queueConfig['enabled'] ?? false;
        } catch (Exception $e) {
            // If we can't check config, assume disabled
            return false;
        }
    }

    /**
     * Get job metadata
     * 
     * @return array
     */
    public function getMetadata(): array
    {
        $baseMetadata = parent::getMetadata();
        
        try {
            $app = $this->getTreeHouseApp();
            $mailQueue = $app->make('mail.queue');
            $stats = $mailQueue->getStats();
            $config = $app->config('mail.queue', []);
            
            $baseMetadata['queue_stats'] = $stats;
            $baseMetadata['mail_config'] = [
                'enabled' => $config['enabled'] ?? false,
                'batch_size' => $config['batch_size'] ?? 10,
                'max_attempts' => $config['max_attempts'] ?? 3,
            ];
        } catch (Exception $e) {
            // If we can't get stats, just return base metadata
            $baseMetadata['error'] = 'Could not fetch queue stats: ' . $e->getMessage();
        }
        
        return $baseMetadata;
    }

    /**
     * Get the TreeHouse Application instance
     * 
     * @return \LengthOfRope\TreeHouse\Foundation\Application
     * @throws Exception
     */
    private function getTreeHouseApp(): \LengthOfRope\TreeHouse\Foundation\Application
    {
        $projectRoot = getcwd();
        return new \LengthOfRope\TreeHouse\Foundation\Application($projectRoot);
    }
}