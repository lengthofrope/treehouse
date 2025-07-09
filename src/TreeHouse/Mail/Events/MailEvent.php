<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Events;

use LengthOfRope\TreeHouse\Events\Event;
use LengthOfRope\TreeHouse\Mail\Messages\Message;

/**
 * Base Mail Event
 *
 * Base class for all mail-related events in the TreeHouse mail system.
 * Extends TreeHouse's Event class for full event system integration.
 *
 * @package LengthOfRope\TreeHouse\Mail\Events
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
abstract class MailEvent extends Event
{
    /**
     * Create a new mail event
     *
     * @param Message $message
     * @param array $context Additional context data
     */
    public function __construct(
        public readonly Message $message,
        array $context = []
    ) {
        // Add mail-specific context
        $mailContext = array_merge([
            'subject' => $message->getSubject(),
            'recipients' => count($message->getAllRecipients()),
            'has_attachments' => $message->hasAttachments(),
            'attachment_count' => count($message->getAttachments()),
            'total_size' => strlen($message->getHtmlBody() ?? '') + strlen($message->getTextBody() ?? '') + $message->getAttachmentsSize(),
        ], $context);

        parent::__construct($mailContext);
    }

    /**
     * Get the email subject
     * 
     * @return string
     */
    public function getSubject(): string
    {
        return $this->message->getSubject();
    }

    /**
     * Get the recipients
     * 
     * @return array
     */
    public function getRecipients(): array
    {
        return $this->message->getAllRecipients()->toArray();
    }

    /**
     * Get the sender
     * 
     * @return string
     */
    public function getSender(): string
    {
        return $this->message->getFrom()->getEmail();
    }

    /**
     * Check if message has attachments
     * 
     * @return bool
     */
    public function hasAttachments(): bool
    {
        return $this->message->hasAttachments();
    }

    /**
     * Get attachment count
     * 
     * @return int
     */
    public function getAttachmentCount(): int
    {
        return count($this->message->getAttachments());
    }

    /**
     * Get total attachment size
     * 
     * @return int
     */
    public function getAttachmentSize(): int
    {
        return $this->message->getAttachmentsSize();
    }
}