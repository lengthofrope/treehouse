<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Events;

use LengthOfRope\TreeHouse\Mail\Messages\Message;

/**
 * Mail Failed Event
 * 
 * Fired when an email fails to send.
 * This event can be used for error handling, logging, or retry logic.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Events
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailFailed extends MailEvent
{
    /**
     * Create a new mail failed event
     *
     * @param Message $message
     * @param \Throwable $exception
     * @param string $mailerUsed
     */
    public function __construct(
        Message $message,
        public readonly \Throwable $exception,
        public readonly string $mailerUsed
    ) {
        parent::__construct($message, [
            'mailer_used' => $mailerUsed,
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'error_class' => get_class($exception),
            'is_retryable' => $this->determineRetryable($exception),
        ]);
    }

    /**
     * Determine if this failure is retryable
     *
     * @param \Throwable $exception
     * @return bool
     */
    private function determineRetryable(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        
        $retryablePatterns = [
            'timeout', 'connection', 'network', 'temporary',
            'rate limit', 'throttle', '4.', // SMTP 4xx codes
        ];
        
        foreach ($retryablePatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the exception that caused the failure
     * 
     * @return \Throwable
     */
    public function getException(): \Throwable
    {
        return $this->exception;
    }

    /**
     * Get the error message
     * 
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->exception->getMessage();
    }

    /**
     * Get the mailer that was used
     * 
     * @return string
     */
    public function getMailerUsed(): string
    {
        return $this->mailerUsed;
    }

    /**
     * Check if this is a temporary failure (retryable)
     * 
     * @return bool
     */
    public function isRetryable(): bool
    {
        $message = strtolower($this->exception->getMessage());
        
        // Common temporary failure patterns
        $retryablePatterns = [
            'timeout',
            'connection',
            'network',
            'temporary',
            'rate limit',
            'throttle',
            '4.', // SMTP 4xx codes are temporary
        ];
        
        foreach ($retryablePatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
}