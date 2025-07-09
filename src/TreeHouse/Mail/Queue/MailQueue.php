<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Queue;

use LengthOfRope\TreeHouse\Mail\Queue\QueuedMail;
use LengthOfRope\TreeHouse\Mail\MailManager;
use LengthOfRope\TreeHouse\Mail\Messages\Message;
use LengthOfRope\TreeHouse\Foundation\Application;
use LengthOfRope\TreeHouse\Support\Carbon;
use DateTime;
use Exception;

/**
 * Mail Queue Service
 * 
 * Manages the mail queue system including adding emails to queue,
 * processing queued emails, and handling retry logic.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Queue
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailQueue
{
    /**
     * Queue configuration
     */
    protected array $config;

    /**
     * Application instance
     */
    protected Application $app;

    /**
     * Create a new MailQueue instance
     * 
     * @param array $config Queue configuration
     * @param Application $app Application instance
     */
    public function __construct(array $config, Application $app)
    {
        $this->config = array_merge([
            'batch_size' => 10,
            'max_attempts' => 3,
            'retry_strategy' => 'exponential',
            'base_retry_delay' => 300, // 5 minutes
            'max_retry_delay' => 3600, // 1 hour
            'retry_multiplier' => 2,
            'reservation_timeout' => 300, // 5 minutes
        ], $config);
        
        $this->app = $app;
    }

    /**
     * Add a message to the queue
     * 
     * @param Message $message The message to queue
     * @param int|null $priority Priority level (1 = highest, 10 = lowest)
     * @param DateTime|null $availableAt When the message should be available for processing
     * @return QueuedMail The queued mail record
     */
    public function add(Message $message, ?int $priority = null, ?DateTime $availableAt = null): QueuedMail
    {
        $message->validate();

        $queuedMail = new QueuedMail();
        
        // Set email content
        $queuedMail->to_addresses = $message->getTo()->toArray();
        $queuedMail->from_address = $message->getFrom()->toArray();
        $queuedMail->cc_addresses = $message->getCc()->isEmpty() ? null : $message->getCc()->toArray();
        $queuedMail->bcc_addresses = $message->getBcc()->isEmpty() ? null : $message->getBcc()->toArray();
        $queuedMail->subject = $message->getSubject();
        $queuedMail->body_html = $message->getHtmlBody();
        $queuedMail->body_text = $message->getTextBody();
        $queuedMail->headers = empty($message->getHeaders()) ? null : $message->getHeaders();
        
        // Set configuration
        $queuedMail->mailer = $message->getMailer() ?? 'default';
        $queuedMail->priority = $priority ?? $message->getPriority();
        $queuedMail->max_attempts = $this->config['max_attempts'];
        
        // Set availability
        $queuedMail->available_at = $availableAt ? Carbon::parse($availableAt->format('Y-m-d H:i:s')) : Carbon::now();
        
        // Mark as queued and save
        $queuedMail->markAsQueued();
        $queuedMail->save();
        
        return $queuedMail;
    }

    /**
     * Get available emails for processing
     * 
     * @param int|null $limit Maximum number of emails to retrieve
     * @return array Array of QueuedMail instances
     */
    public function getAvailable(?int $limit = null): array
    {
        $limit = $limit ?: $this->config['batch_size'];
        
        return QueuedMail::getAvailableForProcessing(null, $limit)->all();
    }

    /**
     * Reserve emails for processing
     * 
     * @param array $emails Array of QueuedMail instances
     * @param int $timeoutSeconds Reservation timeout in seconds
     * @return array Array of successfully reserved emails
     */
    public function reserve(array $emails, ?int $timeoutSeconds = null): array
    {
        $timeoutSeconds = $timeoutSeconds ?: $this->config['reservation_timeout'];
        $reservedUntil = Carbon::now()->addSeconds($timeoutSeconds);
        
        $reserved = [];
        
        foreach ($emails as $email) {
            try {
                // Use the existing startProcessing method which handles reservation
                $email->startProcessing();
                $reserved[] = $email;
            } catch (Exception $e) {
                // Skip this email if reservation fails
                continue;
            }
        }
        
        return $reserved;
    }

    /**
     * Process a single queued email
     * 
     * @param QueuedMail $queuedMail The email to process
     * @return bool True if processed successfully
     */
    public function process(QueuedMail $queuedMail): bool
    {
        $deliveryStart = microtime(true);
        
        try {
            // Get mail manager
            $mailManager = $this->app->make('mail');
            
            // Recreate the message
            $message = $this->recreateMessage($queuedMail, $mailManager);
            
            // Get the appropriate mailer
            $mailer = $mailManager->getMailer($queuedMail->mailer);
            
            // Send the email
            $result = $mailer->send($message);
            
            $deliveryTime = microtime(true) - $deliveryStart;
            
            if ($result) {
                // Mark as sent
                $queuedMail->markAsProcessed($deliveryTime);
            } else {
                // Mark as failed
                $queuedMail->markAsFailed('Mailer returned false');
            }
            
            return $result;
            
        } catch (Exception $e) {
            $deliveryTime = microtime(true) - $deliveryStart;
            
            // Handle failure using existing method
            $queuedMail->markAsFailed($e->getMessage());
            
            return false;
        }
    }

    /**
     * Process available emails in the queue
     * 
     * @param int|null $limit Maximum number of emails to process
     * @return array Processing results
     */
    public function processQueue(?int $limit = null): array
    {
        $results = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        
        // Get available emails
        $emails = $this->getAvailable($limit);
        
        if (empty($emails)) {
            return $results;
        }
        
        // Reserve emails for processing
        $reserved = $this->reserve($emails);
        
        foreach ($reserved as $email) {
            $results['processed']++;
            
            try {
                $success = $this->process($email);
                
                if ($success) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'email_id' => $email->id,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * Recreate a Message object from a QueuedMail record
     *
     * @param QueuedMail $queuedMail The queued mail record
     * @param MailManager $mailManager The mail manager instance
     * @return Message The recreated message
     */
    protected function recreateMessage(QueuedMail $queuedMail, MailManager $mailManager): Message
    {
        $message = new Message($mailManager);
        
        // Set recipients - convert array data back to address strings
        $toAddresses = $this->extractEmailAddresses($queuedMail->to_addresses);
        $message->to($toAddresses);
        
        if ($queuedMail->cc_addresses) {
            $ccAddresses = $this->extractEmailAddresses($queuedMail->cc_addresses);
            $message->cc($ccAddresses);
        }
        
        if ($queuedMail->bcc_addresses) {
            $bccAddresses = $this->extractEmailAddresses($queuedMail->bcc_addresses);
            $message->bcc($bccAddresses);
        }
        
        // Set sender
        $fromAddress = $this->extractEmailAddresses($queuedMail->from_address);
        $message->from($fromAddress[0] ?? 'noreply@example.com');
        
        // Set content
        $message->subject($queuedMail->subject);
        
        if ($queuedMail->body_html) {
            $message->html($queuedMail->body_html);
        }
        
        if ($queuedMail->body_text) {
            $message->text($queuedMail->body_text);
        }
        
        // Set priority
        $message->priority($queuedMail->priority);
        
        // Set headers
        if ($queuedMail->headers) {
            foreach ($queuedMail->headers as $name => $value) {
                $message->header($name, $value);
            }
        }
        
        return $message;
    }

    /**
     * Extract email addresses from stored address data
     *
     * @param array $addressData Stored address data
     * @return array Array of email addresses
     */
    protected function extractEmailAddresses(array $addressData): array
    {
        $addresses = [];
        
        foreach ($addressData as $data) {
            if (is_string($data)) {
                $addresses[] = $data;
            } elseif (is_array($data) && isset($data['email'])) {
                if (isset($data['name']) && !empty($data['name'])) {
                    $addresses[] = "\"{$data['name']}\" <{$data['email']}>";
                } else {
                    $addresses[] = $data['email'];
                }
            }
        }
        
        return $addresses;
    }

    /**
     * Calculate the next retry time based on the retry strategy
     * 
     * @param int $attempts Number of previous attempts
     * @return DateTime Next retry time
     */
    protected function calculateNextRetryTime(int $attempts): DateTime
    {
        $baseDelay = $this->config['base_retry_delay'];
        $maxDelay = $this->config['max_retry_delay'];
        $multiplier = $this->config['retry_multiplier'];
        $strategy = $this->config['retry_strategy'];
        
        switch ($strategy) {
            case 'linear':
                $delay = $baseDelay * ($attempts + 1);
                break;
                
            case 'exponential':
                $delay = $baseDelay * pow($multiplier, $attempts);
                break;
                
            default:
                $delay = $baseDelay;
                break;
        }
        
        // Cap at maximum delay
        $delay = min($delay, $maxDelay);
        
        $nextRetry = new DateTime();
        $nextRetry->modify("+{$delay} seconds");
        
        return $nextRetry;
    }

    /**
     * Release expired reservations
     * 
     * @return int Number of reservations released
     */
    public function releaseExpiredReservations(): int
    {
        $expiredEmails = QueuedMail::query()
            ->where('reserved_until', '<', Carbon::now())
            ->whereNotNull('reserved_at')
            ->get();
            
        $count = 0;
        foreach ($expiredEmails as $email) {
            $email->releaseReservation();
            $count++;
        }
        
        return $count;
    }

    /**
     * Retry failed emails that are ready for retry
     * 
     * @return int Number of emails retried
     */
    public function retryReady(): int
    {
        $retryEmails = QueuedMail::getFailedForRetry(100);
        
        $count = 0;
        foreach ($retryEmails as $email) {
            $email->available_at = Carbon::now();
            $email->next_retry_at = null;
            $email->reserved_at = null;
            $email->reserved_until = null;
            $email->save();
            $count++;
        }
        
        return $count;
    }

    /**
     * Get queue statistics
     * 
     * @return array Queue statistics
     */
    public function getStats(): array
    {
        return QueuedMail::getQueueStats();
    }

    /**
     * Clear all failed emails
     * 
     * @return int Number of emails cleared
     */
    public function clearFailed(): int
    {
        $failedEmails = QueuedMail::query()->whereNotNull('failed_at')->get();
        $count = $failedEmails->count();
        
        foreach ($failedEmails as $email) {
            $email->delete();
        }
        
        return $count;
    }

    /**
     * Clear all sent emails
     * 
     * @return int Number of emails cleared
     */
    public function clearSent(): int
    {
        $sentEmails = QueuedMail::query()->whereNotNull('sent_at')->get();
        $count = $sentEmails->count();
        
        foreach ($sentEmails as $email) {
            $email->delete();
        }
        
        return $count;
    }
}