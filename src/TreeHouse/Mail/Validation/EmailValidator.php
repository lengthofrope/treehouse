<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Validation;

use LengthOfRope\TreeHouse\Mail\Messages\Message;
use LengthOfRope\TreeHouse\Mail\Support\Address;
use InvalidArgumentException;

/**
 * Email Validator
 * 
 * Comprehensive validation for email messages including content,
 * attachments, size limits, and security checks.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Validation
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class EmailValidator
{
    /**
     * Default validation rules
     */
    private array $defaultRules = [
        'max_subject_length' => 200,
        'max_body_length' => 1024 * 1024, // 1MB
        'max_attachment_size' => 10 * 1024 * 1024, // 10MB
        'max_total_size' => 25 * 1024 * 1024, // 25MB
        'max_attachments' => 10,
        'max_recipients' => 100,
        'require_subject' => true,
        'require_content' => true,
        'require_recipient' => true,
        'validate_email_format' => true,
        'check_disposable_emails' => false,
        'blocked_domains' => [],
        'allowed_attachment_types' => [
            'pdf', 'doc', 'docx', 'txt', 'csv', 'xls', 'xlsx',
            'jpg', 'jpeg', 'png', 'gif', 'zip', 'mp4', 'mov'
        ],
        'blocked_attachment_types' => ['exe', 'bat', 'cmd', 'scr', 'vbs', 'js'],
    ];

    /**
     * Validation rules
     */
    private array $rules;

    /**
     * Validation errors
     */
    private array $errors = [];

    /**
     * Create a new email validator
     * 
     * @param array $rules Custom validation rules
     */
    public function __construct(array $rules = [])
    {
        $this->rules = array_merge($this->defaultRules, $rules);
    }

    /**
     * Validate a message
     * 
     * @param Message $message
     * @return bool
     */
    public function validate(Message $message): bool
    {
        $this->errors = [];

        $this->validateRecipients($message);
        $this->validateSubject($message);
        $this->validateContent($message);
        $this->validateAttachments($message);
        $this->validateSize($message);

        return empty($this->errors);
    }

    /**
     * Get validation errors
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message
     * 
     * @return string|null
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Validate recipients
     * 
     * @param Message $message
     * @return void
     */
    private function validateRecipients(Message $message): void
    {
        $allRecipients = $message->getAllRecipients();

        // Check if recipients are required
        if ($this->rules['require_recipient'] && $allRecipients->isEmpty()) {
            $this->errors[] = 'Email must have at least one recipient';
            return;
        }

        // Check recipient count
        if ($allRecipients->count() > $this->rules['max_recipients']) {
            $this->errors[] = "Too many recipients. Maximum allowed: {$this->rules['max_recipients']}";
        }

        // Validate email formats and domains
        if ($this->rules['validate_email_format']) {
            foreach ($allRecipients as $address) {
                $this->validateEmailAddress($address);
            }
        }
    }

    /**
     * Validate email address
     * 
     * @param Address $address
     * @return void
     */
    private function validateEmailAddress(Address $address): void
    {
        $email = $address->getEmail();

        // Basic format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "Invalid email format: {$email}";
            return;
        }

        // Domain validation
        $domain = substr(strrchr($email, '@'), 1);
        
        // Check blocked domains
        if (in_array($domain, $this->rules['blocked_domains'])) {
            $this->errors[] = "Email domain is blocked: {$domain}";
        }

        // Check disposable email providers
        if ($this->rules['check_disposable_emails'] && $this->isDisposableEmail($domain)) {
            $this->errors[] = "Disposable email addresses are not allowed: {$email}";
        }
    }

    /**
     * Check if domain is a disposable email provider
     * 
     * @param string $domain
     * @return bool
     */
    private function isDisposableEmail(string $domain): bool
    {
        $disposableDomains = [
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
            'tempmail.org', 'yopmail.com', 'throwaway.email'
        ];

        return in_array(strtolower($domain), $disposableDomains);
    }

    /**
     * Validate subject
     * 
     * @param Message $message
     * @return void
     */
    private function validateSubject(Message $message): void
    {
        $subject = $message->getSubject();

        // Check if subject is required
        if ($this->rules['require_subject'] && empty($subject)) {
            $this->errors[] = 'Email subject is required';
            return;
        }

        // Check subject length
        if (strlen($subject) > $this->rules['max_subject_length']) {
            $this->errors[] = "Subject is too long. Maximum length: {$this->rules['max_subject_length']} characters";
        }

        // Check for suspicious patterns
        if ($this->containsSuspiciousContent($subject)) {
            $this->errors[] = 'Subject contains suspicious content';
        }
    }

    /**
     * Validate message content
     * 
     * @param Message $message
     * @return void
     */
    private function validateContent(Message $message): void
    {
        // Check if content is required
        if ($this->rules['require_content'] && !$message->hasContent()) {
            $this->errors[] = 'Email must have content (HTML or text body)';
            return;
        }

        // Check content length
        $htmlBody = $message->getHtmlBody() ?? '';
        $textBody = $message->getTextBody() ?? '';
        $totalLength = strlen($htmlBody) + strlen($textBody);

        if ($totalLength > $this->rules['max_body_length']) {
            $maxMB = round($this->rules['max_body_length'] / (1024 * 1024), 2);
            $this->errors[] = "Email content is too large. Maximum size: {$maxMB}MB";
        }

        // Check for suspicious content
        if ($this->containsSuspiciousContent($htmlBody . $textBody)) {
            $this->errors[] = 'Email content contains suspicious patterns';
        }
    }

    /**
     * Validate attachments
     * 
     * @param Message $message
     * @return void
     */
    private function validateAttachments(Message $message): void
    {
        $attachments = $message->getAttachments();

        // Check attachment count
        if (count($attachments) > $this->rules['max_attachments']) {
            $this->errors[] = "Too many attachments. Maximum allowed: {$this->rules['max_attachments']}";
        }

        foreach ($attachments as $attachment) {
            $this->validateAttachment($attachment);
        }
    }

    /**
     * Validate individual attachment
     * 
     * @param array $attachment
     * @return void
     */
    private function validateAttachment(array $attachment): void
    {
        $name = $attachment['name'];
        $size = $attachment['size'];

        // Check file size
        if ($size > $this->rules['max_attachment_size']) {
            $sizeMB = round($size / (1024 * 1024), 2);
            $maxMB = round($this->rules['max_attachment_size'] / (1024 * 1024), 2);
            $this->errors[] = "Attachment '{$name}' is too large ({$sizeMB}MB). Maximum size: {$maxMB}MB";
        }

        // Check file extension
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (in_array($extension, $this->rules['blocked_attachment_types'])) {
            $this->errors[] = "Attachment type not allowed: {$extension}";
        }

        if (!empty($this->rules['allowed_attachment_types']) && 
            !in_array($extension, $this->rules['allowed_attachment_types'])) {
            $this->errors[] = "Attachment type not in allowed list: {$extension}";
        }
    }

    /**
     * Validate total message size
     * 
     * @param Message $message
     * @return void
     */
    private function validateSize(Message $message): void
    {
        $totalSize = $message->getAttachmentsSize();
        
        // Add content size
        $htmlBody = $message->getHtmlBody() ?? '';
        $textBody = $message->getTextBody() ?? '';
        $totalSize += strlen($htmlBody) + strlen($textBody);

        if ($totalSize > $this->rules['max_total_size']) {
            $totalMB = round($totalSize / (1024 * 1024), 2);
            $maxMB = round($this->rules['max_total_size'] / (1024 * 1024), 2);
            $this->errors[] = "Total email size ({$totalMB}MB) exceeds maximum ({$maxMB}MB)";
        }
    }

    /**
     * Check for suspicious content patterns
     * 
     * @param string $content
     * @return bool
     */
    private function containsSuspiciousContent(string $content): bool
    {
        $suspiciousPatterns = [
            '/urgent.*action.*required/i',
            '/click.*here.*immediately/i',
            '/congratulations.*won/i',
            '/nigerian.*prince/i',
            '/wire.*transfer/i',
            '/social.*security.*suspended/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}