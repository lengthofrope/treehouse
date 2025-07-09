<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail;

use LengthOfRope\TreeHouse\Mail\Queue\QueuedMail;
use LengthOfRope\TreeHouse\Mail\Messages\Message;

/**
 * Mailable Base Class
 * 
 * Abstract base class for creating email templates with TreeHouse's view system.
 * Provides Laravel-style mailable functionality with email-specific optimizations.
 * 
 * @package LengthOfRope\TreeHouse\Mail
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
abstract class Mailable
{
    /**
     * Email subject
     */
    protected string $subject = '';

    /**
     * Email template path
     */
    protected string $template = '';

    /**
     * Template data
     */
    protected array $data = [];

    /**
     * Email attachments
     */
    protected array $attachments = [];

    /**
     * Specific mailer to use
     */
    protected ?string $mailer = null;

    /**
     * Email priority (1-5, 1 = highest)
     */
    protected int $priority = 5;

    /**
     * Build the mailable
     * 
     * @return self
     */
    abstract public function build(): self;

    /**
     * Set the email subject
     * 
     * @param string $subject
     * @return self
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set the email template and data
     * 
     * @param string $template Template path (e.g., 'emails.welcome')
     * @param array $data Template data
     * @return self
     */
    public function emailTemplate(string $template, array $data = []): self
    {
        $this->template = $template;
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Add data to the template
     * 
     * @param string|array $key
     * @param mixed $value
     * @return self
     */
    public function with(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
        return $this;
    }

    /**
     * Add an attachment
     * 
     * @param string $file Path to file
     * @param array $options Attachment options (as, mime, etc.)
     * @return self
     */
    public function attach(string $file, array $options = []): self
    {
        $this->attachments[] = [
            'path' => $file,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Set the mailer to use
     * 
     * @param string $mailer Mailer name
     * @return self
     */
    public function mailer(string $mailer): self
    {
        $this->mailer = $mailer;
        return $this;
    }

    /**
     * Set email priority
     * 
     * @param int $priority Priority level (1-5, 1 = highest)
     * @return self
     */
    public function priority(int $priority): self
    {
        $this->priority = max(1, min(5, $priority));
        return $this;
    }

    /**
     * Send the email immediately
     *
     * @param string|null $to Recipient email address
     * @return bool
     */
    public function send(?string $to = null): bool
    {
        $message = $this->buildMessage();
        if ($to) {
            $message->to($to);
        }
        return $message->send();
    }

    /**
     * Queue the email for later processing
     *
     * @param string|null $to Recipient email address
     * @param int|null $priority Queue priority (overrides mailable priority)
     * @return QueuedMail
     */
    public function queue(?string $to = null, ?int $priority = null): QueuedMail
    {
        $message = $this->buildMessage();
        if ($to) {
            $message->to($to);
        }
        return $message->queue($priority ?? $this->priority);
    }

    /**
     * Build the message instance
     * 
     * @return Message
     */
    protected function buildMessage(): Message
    {
        // Call the build method to configure the mailable
        $this->build();

        // Get mail manager
        $mailManager = app('mail');
        if ($this->mailer) {
            $mailManager = $mailManager->mailer($this->mailer);
        }

        // Create message
        $message = $mailManager->compose();
        $message->subject($this->subject);
        $message->priority($this->priority);

        // Render template if specified
        if ($this->template) {
            $html = $this->renderEmailTemplate();
            $message->html($html);

            // Auto-generate text version
            $text = $this->generateTextVersion($html);
            $message->text($text);
        }

        // Add attachments
        foreach ($this->attachments as $attachment) {
            $message->attach($attachment['path'], $attachment['options']);
        }

        return $message;
    }

    /**
     * Render the email template
     * 
     * @return string
     */
    protected function renderEmailTemplate(): string
    {
        return renderEmail($this->template, $this->data);
    }

    /**
     * Generate plain text version from HTML
     * 
     * @param string $html
     * @return string
     */
    protected function generateTextVersion(string $html): string
    {
        // Remove script and style tags completely
        $html = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', '', $html);
        
        // Convert common HTML elements to text equivalents
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $html = preg_replace('/<\/div>/i', "\n", $html);
        $html = preg_replace('/<\/h[1-6]>/i', "\n\n", $html);
        $html = preg_replace('/<hr[^>]*>/i', "\n" . str_repeat('-', 50) . "\n", $html);
        
        // Convert links to text with URL
        $html = preg_replace('/<a[^>]+href="([^"]*)"[^>]*>(.*?)<\/a>/i', '$2 ($1)', $html);
        
        // Strip remaining HTML tags
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text); // Multiple spaces/tabs to single space
        $text = preg_replace('/\n[ \t]+/', "\n", $text); // Remove leading spaces on lines
        $text = preg_replace('/[ \t]+\n/', "\n", $text); // Remove trailing spaces on lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text); // Multiple newlines to double newline
        
        return trim($text);
    }

    /**
     * Get the subject line
     * 
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Get the template path
     * 
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Get the template data
     * 
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get attachments
     * 
     * @return array
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }
}