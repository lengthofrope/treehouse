<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Events;

use LengthOfRope\TreeHouse\Mail\Messages\Message;

/**
 * Mail Sent Event
 * 
 * Fired after an email has been successfully sent.
 * This event can be used for logging, analytics, or follow-up actions.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Events
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailSent extends MailEvent
{
    /**
     * Create a new mail sent event
     *
     * @param Message $message
     * @param string $mailerUsed
     * @param float $sendTime Time taken to send in seconds
     */
    public function __construct(
        Message $message,
        public readonly string $mailerUsed,
        public readonly float $sendTime
    ) {
        parent::__construct($message, [
            'mailer_used' => $mailerUsed,
            'send_time' => $sendTime,
            'send_time_formatted' => $this->formatSendTime($sendTime),
        ]);
    }

    /**
     * Format send time for display
     *
     * @param float $sendTime
     * @return string
     */
    private function formatSendTime(float $sendTime): string
    {
        if ($sendTime < 1) {
            return round($sendTime * 1000) . 'ms';
        }
        return round($sendTime, 2) . 's';
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
     * Get the time taken to send the email
     * 
     * @return float
     */
    public function getSendTime(): float
    {
        return $this->sendTime;
    }

    /**
     * Get human-readable send time
     * 
     * @return string
     */
    public function getSendTimeFormatted(): string
    {
        if ($this->sendTime < 1) {
            return round($this->sendTime * 1000) . 'ms';
        }
        return round($this->sendTime, 2) . 's';
    }
}