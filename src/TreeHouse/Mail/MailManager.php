<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail;

use LengthOfRope\TreeHouse\Mail\Mailers\MailerInterface;
use LengthOfRope\TreeHouse\Mail\Mailers\SmtpMailer;
use LengthOfRope\TreeHouse\Mail\Mailers\SendmailMailer;
use LengthOfRope\TreeHouse\Mail\Mailers\LogMailer;
use LengthOfRope\TreeHouse\Mail\Messages\Message;
use LengthOfRope\TreeHouse\Mail\Support\Address;
use LengthOfRope\TreeHouse\Mail\Support\AddressList;
use LengthOfRope\TreeHouse\Mail\Events\MailSending;
use LengthOfRope\TreeHouse\Mail\Events\MailSent;
use LengthOfRope\TreeHouse\Mail\Events\MailFailed;
use LengthOfRope\TreeHouse\Mail\Events\MailQueued;
use LengthOfRope\TreeHouse\Foundation\Application;
use RuntimeException;

/**
 * Mail Manager
 * 
 * Central service orchestrator for the TreeHouse Mail system.
 * Manages multiple mail drivers and provides a fluent interface for sending emails.
 * 
 * @package LengthOfRope\TreeHouse\Mail
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailManager
{
    /**
     * Mail configuration
     */
    protected array $config;

    /**
     * Application container
     */
    protected Application $app;

    /**
     * Cached mailer instances
     */
    protected array $mailers = [];

    /**
     * Current message being built
     */
    protected ?Message $pendingMessage = null;

    /**
     * Create a new MailManager instance
     * 
     * @param array $config Mail configuration
     * @param Application $app Application container
     */
    public function __construct(array $config, Application $app)
    {
        $this->config = $config;
        $this->app = $app;
    }

    /**
     * Begin building a new mail message
     * 
     * @return static
     */
    public function compose(): static
    {
        $this->pendingMessage = new Message($this);
        return $this;
    }

    /**
     * Set the recipients of the message
     * 
     * @param string|array|Address|AddressList $recipients
     * @return static
     */
    public function to(string|array|Address|AddressList $recipients): static
    {
        $this->ensurePendingMessage();
        $this->pendingMessage->to($recipients);
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
        $this->ensurePendingMessage();
        $this->pendingMessage->cc($recipients);
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
        $this->ensurePendingMessage();
        $this->pendingMessage->bcc($recipients);
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
        $this->ensurePendingMessage();
        $this->pendingMessage->from($sender);
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
        $this->ensurePendingMessage();
        $this->pendingMessage->subject($subject);
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
        $this->ensurePendingMessage();
        $this->pendingMessage->html($html);
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
        $this->ensurePendingMessage();
        $this->pendingMessage->text($text);
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
        $this->ensurePendingMessage();
        $this->pendingMessage->priority($priority);
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
        $this->ensurePendingMessage();
        $this->pendingMessage->header($name, $value);
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
        $this->ensurePendingMessage();
        $this->pendingMessage->mailer($mailer);
        return $this;
    }

    /**
     * Add an attachment to the message
     *
     * @param string $file Path to file
     * @param array $options Attachment options (as, mime, etc.)
     * @return static
     */
    public function attach(string $file, array $options = []): static
    {
        $this->ensurePendingMessage();
        $this->pendingMessage->attach($file, $options);
        return $this;
    }

    /**
     * Add raw data as an attachment
     *
     * @param string $data Raw file data
     * @param string $name Filename
     * @param array $options Attachment options (mime, etc.)
     * @return static
     */
    public function attachData(string $data, string $name, array $options = []): static
    {
        $this->ensurePendingMessage();
        $this->pendingMessage->attachData($data, $name, $options);
        return $this;
    }

    /**
     * Send the message immediately
     *
     * @return bool True if sent successfully
     */
    public function send(): bool
    {
        $this->ensurePendingMessage();
        
        // Dispatch mail sending event
        $sendingEvent = new MailSending($this->pendingMessage);
        $this->dispatchEvent($sendingEvent);
        
        // Check if sending was cancelled
        if ($sendingEvent->isCancelled()) {
            $this->pendingMessage = null;
            return false;
        }
        
        $mailerName = $this->pendingMessage->getMailer() ?? $this->getDefaultMailer();
        $mailer = $this->getMailer($mailerName);
        
        $startTime = microtime(true);
        
        try {
            $result = $mailer->send($this->pendingMessage);
            $sendTime = microtime(true) - $startTime;
            
            // Dispatch mail sent event
            $sentEvent = new MailSent($this->pendingMessage, $mailerName, $sendTime);
            $this->dispatchEvent($sentEvent);
            
            $this->pendingMessage = null; // Clear after sending
            return $result;
        } catch (\Exception $e) {
            // Dispatch mail failed event
            $failedEvent = new MailFailed($this->pendingMessage, $e, $mailerName);
            $this->dispatchEvent($failedEvent);
            
            $this->pendingMessage = null; // Clear even on failure
            throw $e;
        }
    }

    /**
     * Queue the message for later processing
     *
     * @return bool True if queued successfully
     */
    public function queue(): bool
    {
        $this->ensurePendingMessage();
        
        try {
            // Get the queue service
            $mailQueue = $this->app->make('mail.queue');
            
            // Check if mail queue service is available
            if ($mailQueue === null) {
                // Fall back to immediate sending
                return $this->send();
            }
            
            // Add to queue
            $queuedMail = $mailQueue->add($this->pendingMessage, $this->pendingMessage->getPriority());
            
            // Dispatch mail queued event
            if ($queuedMail !== null) {
                $queuedEvent = new MailQueued($this->pendingMessage, $queuedMail);
                $this->dispatchEvent($queuedEvent);
            }
            
            // Clear the pending message
            $this->pendingMessage = null;
            
            return $queuedMail !== null;
            
        } catch (\Exception $e) {
            // If queue fails, optionally fall back to immediate sending
            $queueConfig = $this->config['queue'] ?? [];
            $fallbackToSend = $queueConfig['fallback_to_send'] ?? true; // Default to true for better reliability
            
            if ($fallbackToSend) {
                return $this->send();
            }
            
            $this->pendingMessage = null; // Clear even on failure
            throw $e;
        }
    }

    /**
     * Get a mailer instance
     * 
     * @param string $name Mailer name
     * @return MailerInterface
     * @throws RuntimeException
     */
    public function getMailer(string $name): MailerInterface
    {
        if (isset($this->mailers[$name])) {
            return $this->mailers[$name];
        }

        $config = $this->config['mailers'][$name] ?? null;
        if ($config === null) {
            throw new RuntimeException("Mailer [{$name}] is not configured.");
        }

        $mailer = $this->createMailer($config);
        $this->mailers[$name] = $mailer;

        return $mailer;
    }

    /**
     * Get the default mailer name
     * 
     * @return string
     */
    public function getDefaultMailer(): string
    {
        return $this->config['default'] ?? 'smtp';
    }

    /**
     * Get the default from address
     * 
     * @return Address
     */
    public function getDefaultFrom(): Address
    {
        $from = $this->config['from'] ?? [];
        return new Address(
            $from['address'] ?? 'noreply@example.com',
            $from['name'] ?? null
        );
    }

    /**
     * Create a mailer instance from configuration
     * 
     * @param array $config Mailer configuration
     * @return MailerInterface
     * @throws RuntimeException
     */
    protected function createMailer(array $config): MailerInterface
    {
        $transport = $config['transport'] ?? 'smtp';

        return match ($transport) {
            'smtp' => new SmtpMailer($config),
            'sendmail' => new SendmailMailer($config),
            'log' => new LogMailer($config),
            default => throw new RuntimeException("Unsupported mail transport [{$transport}].")
        };
    }

    /**
     * Ensure there's a pending message
     */
    protected function ensurePendingMessage(): void
    {
        if ($this->pendingMessage === null) {
            $this->compose();
        }
    }

    /**
     * Dispatch an event through the application's event system
     *
     * @param object $event
     * @return void
     */
    protected function dispatchEvent(object $event): void
    {
        try {
            $eventDispatcher = $this->app->make('events');
            if ($eventDispatcher !== null) {
                $eventDispatcher->dispatch($event);
            }
        } catch (\Exception $e) {
            // Silently fail if event system is not available
            // Mail functionality should not depend on events
        }
    }
}