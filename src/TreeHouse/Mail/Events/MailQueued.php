<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Events;

use LengthOfRope\TreeHouse\Mail\Messages\Message;
use LengthOfRope\TreeHouse\Mail\Queue\QueuedMail;

/**
 * Mail Queued Event
 * 
 * Fired when an email is successfully queued for later processing.
 * This event can be used for logging or tracking queued emails.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Events
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailQueued extends MailEvent
{
    /**
     * Create a new mail queued event
     *
     * @param Message $message
     * @param QueuedMail $queuedMail
     */
    public function __construct(
        Message $message,
        public readonly QueuedMail $queuedMail
    ) {
        parent::__construct($message, [
            'queue_id' => $queuedMail->id,
            'priority' => $queuedMail->priority,
            'scheduled_at' => $queuedMail->scheduled_at?->format('Y-m-d H:i:s'),
            'is_scheduled' => $queuedMail->scheduled_at !== null && $queuedMail->scheduled_at > new \DateTime(),
            'status' => $queuedMail->status,
        ]);
    }

    /**
     * Get the queued mail instance
     * 
     * @return QueuedMail
     */
    public function getQueuedMail(): QueuedMail
    {
        return $this->queuedMail;
    }

    /**
     * Get the queue ID
     * 
     * @return int
     */
    public function getQueueId(): int
    {
        return $this->queuedMail->id;
    }

    /**
     * Get the queue priority
     * 
     * @return int
     */
    public function getPriority(): int
    {
        return $this->queuedMail->priority;
    }

    /**
     * Get the scheduled send time
     * 
     * @return \DateTimeInterface|null
     */
    public function getScheduledAt(): ?\DateTimeInterface
    {
        return $this->queuedMail->scheduled_at;
    }

    /**
     * Check if this is a scheduled email
     * 
     * @return bool
     */
    public function isScheduled(): bool
    {
        return $this->queuedMail->scheduled_at !== null && 
               $this->queuedMail->scheduled_at > new \DateTime();
    }
}