<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Messages;

use LengthOfRope\TreeHouse\Mail\MailManager;
use LengthOfRope\TreeHouse\Mail\Support\Address;
use LengthOfRope\TreeHouse\Mail\Support\AddressList;
use InvalidArgumentException;

/**
 * Mail Message
 * 
 * Represents an email message with all its properties.
 * Provides a fluent interface for building email messages.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Messages
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Message
{
    /**
     * Mail manager instance
     */
    protected MailManager $mailManager;

    /**
     * Message recipients (to)
     */
    protected AddressList $to;

    /**
     * Message CC recipients
     */
    protected AddressList $cc;

    /**
     * Message BCC recipients
     */
    protected AddressList $bcc;

    /**
     * Message sender
     */
    protected ?Address $from = null;

    /**
     * Message subject
     */
    protected string $subject = '';

    /**
     * HTML body
     */
    protected ?string $htmlBody = null;

    /**
     * Plain text body
     */
    protected ?string $textBody = null;

    /**
     * Message priority (1 = highest, 10 = lowest)
     */
    protected int $priority = 5;

    /**
     * Custom headers
     */
    protected array $headers = [];

    /**
     * Mailer to use for this message
     */
    protected ?string $mailer = null;

    /**
     * Attachments
     */
    protected array $attachments = [];

    /**
     * Create a new Message instance
     * 
     * @param MailManager $mailManager
     */
    public function __construct(MailManager $mailManager)
    {
        $this->mailManager = $mailManager;
        $this->to = new AddressList();
        $this->cc = new AddressList();
        $this->bcc = new AddressList();
    }

    /**
     * Set the recipients of the message
     * 
     * @param string|array|Address|AddressList $recipients
     * @return static
     */
    public function to(string|array|Address|AddressList $recipients): static
    {
        $this->to = AddressList::parse($recipients);
        return $this;
    }

    /**
     * Add recipients to the message
     * 
     * @param string|array|Address|AddressList $recipients
     * @return static
     */
    public function addTo(string|array|Address|AddressList $recipients): static
    {
        $addresses = AddressList::parse($recipients);
        foreach ($addresses as $address) {
            $this->to->add($address);
        }
        return $this;
    }

    /**
     * Set the CC recipients of the message
     * 
     * @param string|array|Address|AddressList $recipients
     * @return static
     */
    public function cc(string|array|Address|AddressList $recipients): static
    {
        $this->cc = AddressList::parse($recipients);
        return $this;
    }

    /**
     * Add CC recipients to the message
     * 
     * @param string|array|Address|AddressList $recipients
     * @return static
     */
    public function addCc(string|array|Address|AddressList $recipients): static
    {
        $addresses = AddressList::parse($recipients);
        foreach ($addresses as $address) {
            $this->cc->add($address);
        }
        return $this;
    }

    /**
     * Set the BCC recipients of the message
     * 
     * @param string|array|Address|AddressList $recipients
     * @return static
     */
    public function bcc(string|array|Address|AddressList $recipients): static
    {
        $this->bcc = AddressList::parse($recipients);
        return $this;
    }

    /**
     * Add BCC recipients to the message
     * 
     * @param string|array|Address|AddressList $recipients
     * @return static
     */
    public function addBcc(string|array|Address|AddressList $recipients): static
    {
        $addresses = AddressList::parse($recipients);
        foreach ($addresses as $address) {
            $this->bcc->add($address);
        }
        return $this;
    }

    /**
     * Set the sender of the message
     * 
     * @param string|Address $sender
     * @return static
     */
    public function from(string|Address $sender): static
    {
        if (is_string($sender)) {
            $sender = Address::parse($sender);
        }
        
        $this->from = $sender;
        return $this;
    }

    /**
     * Set the subject of the message
     * 
     * @param string $subject
     * @return static
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set the HTML body of the message
     * 
     * @param string $html
     * @return static
     */
    public function html(string $html): static
    {
        $this->htmlBody = $html;
        return $this;
    }

    /**
     * Set the plain text body of the message
     * 
     * @param string $text
     * @return static
     */
    public function text(string $text): static
    {
        $this->textBody = $text;
        return $this;
    }

    /**
     * Set the priority of the message
     * 
     * @param int $priority Priority level (1 = highest, 10 = lowest)
     * @return static
     */
    public function priority(int $priority): static
    {
        if ($priority < 1 || $priority > 10) {
            throw new InvalidArgumentException('Priority must be between 1 and 10');
        }
        
        $this->priority = $priority;
        return $this;
    }

    /**
     * Add a header to the message
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return static
     */
    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set multiple headers
     * 
     * @param array $headers
     * @return static
     */
    public function headers(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
        return $this;
    }

    /**
     * Set the mailer to use for this message
     * 
     * @param string $mailer Mailer name
     * @return static
     */
    public function mailer(string $mailer): static
    {
        $this->mailer = $mailer;
        return $this;
    }

    // Getters

    /**
     * Get the recipients
     * 
     * @return AddressList
     */
    public function getTo(): AddressList
    {
        return $this->to;
    }

    /**
     * Get the CC recipients
     * 
     * @return AddressList
     */
    public function getCc(): AddressList
    {
        return $this->cc;
    }

    /**
     * Get the BCC recipients
     * 
     * @return AddressList
     */
    public function getBcc(): AddressList
    {
        return $this->bcc;
    }

    /**
     * Get the sender
     * 
     * @return Address
     */
    public function getFrom(): Address
    {
        return $this->from ?? $this->mailManager->getDefaultFrom();
    }

    /**
     * Get the subject
     * 
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Get the HTML body
     * 
     * @return string|null
     */
    public function getHtmlBody(): ?string
    {
        return $this->htmlBody;
    }

    /**
     * Get the plain text body
     * 
     * @return string|null
     */
    public function getTextBody(): ?string
    {
        return $this->textBody;
    }

    /**
     * Get the priority
     * 
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get all headers
     * 
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header
     * 
     * @param string $name
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get the mailer name
     * 
     * @return string|null
     */
    public function getMailer(): ?string
    {
        return $this->mailer;
    }

    /**
     * Get all recipients as a single list
     * 
     * @return AddressList
     */
    public function getAllRecipients(): AddressList
    {
        $all = new AddressList();
        
        foreach ($this->to as $address) {
            $all->add($address);
        }
        
        foreach ($this->cc as $address) {
            $all->add($address);
        }
        
        foreach ($this->bcc as $address) {
            $all->add($address);
        }
        
        return $all;
    }

    /**
     * Check if the message has any content
     * 
     * @return bool
     */
    public function hasContent(): bool
    {
        return !empty($this->htmlBody) || !empty($this->textBody);
    }

    /**
     * Check if the message is valid for sending
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->to->isEmpty() && 
               !empty($this->subject) && 
               $this->hasContent();
    }

    /**
     * Validate the message and throw exception if invalid
     * 
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if ($this->to->isEmpty()) {
            throw new InvalidArgumentException('Message must have at least one recipient');
        }
        
        if (empty($this->subject)) {
            throw new InvalidArgumentException('Message must have a subject');
        }
        
        if (!$this->hasContent()) {
            throw new InvalidArgumentException('Message must have content (HTML or text body)');
        }
    }

    /**
     * Send the message
     * 
     * @return bool
     */
    public function send(): bool
    {
        return $this->mailManager->send();
    }

    /**
     * Queue the message
     *
     * @param int|null $priority Queue priority (1-5, 1 = highest)
     * @return \LengthOfRope\TreeHouse\Mail\Queue\QueuedMail|bool
     */
    public function queue(int $priority = null): \LengthOfRope\TreeHouse\Mail\Queue\QueuedMail|bool
    {
        if ($priority !== null) {
            $this->priority($priority);
        }
        
        try {
            // Use global app() helper to get the queue service
            $mailQueue = app('mail.queue');
            
            if ($mailQueue === null) {
                // Fall back to basic queue method
                return $this->mailManager->queue();
            }
            
            // Add to queue and return the QueuedMail instance
            return $mailQueue->add($this, $this->priority);
            
        } catch (\Exception $e) {
            // Fall back to basic queue method
            return $this->mailManager->queue();
        }
    }

    /**
     * Queue the message (legacy method)
     *
     * @return bool
     */
    public function queueBool(): bool
    {
        return $this->mailManager->queue();
    }
}