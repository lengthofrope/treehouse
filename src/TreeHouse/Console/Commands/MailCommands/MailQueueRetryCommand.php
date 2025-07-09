<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\MailCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\InputArgument;
use Exception;

// Import helper functions
require_once __DIR__ . '/../../../Support/helpers.php';

/**
 * Mail Queue Retry Command
 * 
 * Retries failed emails in the mail queue with options to filter
 * by specific criteria and control batch processing.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\MailCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailQueueRetryCommand extends Command
{
    /**
     * The command name
     */
    protected string $name = 'mail:queue:retry';

    /**
     * The command description
     */
    protected string $description = 'Retry failed emails in the mail queue';

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName($this->name)
             ->setDescription($this->description)
             ->addArgument('ids', InputArgument::OPTIONAL, 'Specific email IDs to retry (comma-separated)')
             ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of emails to retry', 10)
             ->addOption('max-attempts', 'm', InputOption::VALUE_OPTIONAL, 'Only retry emails with fewer than N attempts')
             ->addOption('older-than', 'o', InputOption::VALUE_OPTIONAL, 'Only retry emails failed longer than N minutes ago', 30)
             ->addOption('mailer', null, InputOption::VALUE_OPTIONAL, 'Only retry emails for specific mailer')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force retry even if max attempts exceeded')
             ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Show what would be retried without actually retrying');
    }
    
    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $mailQueue = $this->getMailQueue();
            
            // Get options
            $ids = $input->getArgument('ids');
            $limit = (int) $input->getOption('limit', 10);
            $maxAttempts = $input->getOption('max-attempts') ? (int) $input->getOption('max-attempts') : null;
            $olderThan = (int) $input->getOption('older-than', 30);
            $mailer = $input->getOption('mailer');
            $force = (bool) $input->getOption('force', false);
            $dryRun = (bool) $input->getOption('dry-run', false);
            
            $output->writeln('<info>Mail Queue Retry</info>');
            $output->writeln('<info>================</info>');
            $output->writeln('');
            
            if ($ids) {
                // Retry specific emails by ID
                $emailIds = array_map('trim', explode(',', $ids));
                $retried = $this->retrySpecificEmails($mailQueue, $emailIds, $force, $dryRun, $output);
            } else {
                // Retry failed emails with filters
                $retried = $this->retryFailedEmails($mailQueue, $limit, $maxAttempts, $olderThan, $mailer, $force, $dryRun, $output);
            }
            
            if ($dryRun) {
                $output->writeln(sprintf('<info>Dry run completed. Would retry %d emails.</info>', $retried));
            } else {
                $output->writeln(sprintf('<info>Successfully retried %d emails.</info>', $retried));
            }
            
            return 0;
            
        } catch (Exception $e) {
            $output->writeln(sprintf('<error>Error retrying emails: %s</error>', $e->getMessage()));
            return 1;
        }
    }
    
    /**
     * Retry specific emails by ID
     */
    protected function retrySpecificEmails($mailQueue, array $emailIds, bool $force, bool $dryRun, OutputInterface $output): int
    {
        $retried = 0;
        
        foreach ($emailIds as $id) {
            $email = \LengthOfRope\TreeHouse\Mail\Queue\QueuedMail::find((int) $id);
            
            if (!$email) {
                $output->writeln("<warning>Email ID {$id} not found</warning>");
                continue;
            }
            
            if ($email->sent_at || !$email->failed_at) {
                $output->writeln("<warning>Email ID {$id} is not in failed state</warning>");
                continue;
            }
            
            if (!$force && !$email->canRetry()) {
                $output->writeln("<warning>Email ID {$id} has exceeded max retry attempts</warning>");
                continue;
            }
            
            if ($dryRun) {
                $output->writeln("<info>Would retry email ID {$id} (Subject: {$email->subject})</info>");
            } else {
                $email->failed_at = null;
                $email->error_message = null;
                $email->next_retry_at = null;
                $email->available_at = new \DateTime();
                $email->save();
                
                $output->writeln("<info>Retried email ID {$id} (Subject: {$email->subject})</info>");
            }
            
            $retried++;
        }
        
        return $retried;
    }
    
    /**
     * Retry failed emails with filters
     */
    protected function retryFailedEmails($mailQueue, int $limit, ?int $maxAttempts, int $olderThan, ?string $mailer, bool $force, bool $dryRun, OutputInterface $output): int
    {
        // Build query for failed emails using TreeHouse QueryBuilder patterns
        $query = \LengthOfRope\TreeHouse\Mail\Queue\QueuedMail::query()
            ->whereNotNull('failed_at')
            ->whereNull('sent_at');
        
        // Apply filters
        if ($maxAttempts !== null) {
            $query->where('attempts', '<', $maxAttempts);
        }
        
        if ($olderThan > 0) {
            $cutoffTime = new \DateTime();
            $cutoffTime->modify("-{$olderThan} minutes");
            $query->where('failed_at', '<=', $cutoffTime->format('Y-m-d H:i:s'));
        }
        
        if ($mailer) {
            $query->where('mailer', '=', $mailer);
        }
        
        $failedEmails = $query->limit($limit)->get();
        
        // Filter out emails that exceed max attempts if not forced
        if (!$force) {
            $failedEmails = $failedEmails->filter(function ($email) {
                return $email->attempts < $email->max_attempts;
            });
        }
        
        if ($failedEmails->isEmpty()) {
            $output->writeln('<info>No failed emails found matching criteria.</info>');
            return 0;
        }
        
        $output->writeln(sprintf('<info>Found %d failed emails to retry</info>', $failedEmails->count()));
        $output->writeln('');
        
        $retried = 0;
        foreach ($failedEmails as $email) {
            if ($dryRun) {
                $output->writeln(sprintf(
                    '<info>Would retry: ID %d, Subject: %s, Attempts: %d/%d, Failed: %s</info>',
                    $email->id,
                    substr($email->subject, 0, 50) . (strlen($email->subject) > 50 ? '...' : ''),
                    $email->attempts,
                    $email->max_attempts,
                    $email->failed_at
                ));
            } else {
                $email->failed_at = null;
                $email->error_message = null;
                $email->next_retry_at = null;
                $email->available_at = new \DateTime();
                $email->save();
                
                $output->writeln(sprintf(
                    '<info>Retried: ID %d, Subject: %s</info>',
                    $email->id,
                    substr($email->subject, 0, 50) . (strlen($email->subject) > 50 ? '...' : '')
                ));
            }
            
            $retried++;
        }
        
        return $retried;
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