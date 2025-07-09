<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Events;

use LengthOfRope\TreeHouse\Mail\Messages\Message;

/**
 * Mail Sending Event
 *
 * Fired before an email is sent. This event can be used to modify
 * the message or cancel the sending process by stopping propagation.
 *
 * @package LengthOfRope\TreeHouse\Mail\Events
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailSending extends MailEvent
{
    /**
     * Whether the sending should be cancelled
     */
    private bool $cancelled = false;

    /**
     * Cancel the email sending
     *
     * This will also stop event propagation to prevent other listeners
     * from processing this event.
     *
     * @return void
     */
    public function cancel(): void
    {
        $this->cancelled = true;
        $this->stopPropagation();
        $this->setContext('cancelled', true);
    }

    /**
     * Check if sending is cancelled
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->cancelled || $this->isPropagationStopped();
    }
}