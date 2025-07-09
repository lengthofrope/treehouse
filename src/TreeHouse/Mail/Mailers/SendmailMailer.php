<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Mailers;

use LengthOfRope\TreeHouse\Mail\Messages\Message;
use Exception;
use RuntimeException;

/**
 * Sendmail Mailer
 * 
 * Sendmail mail driver implementation using the system's sendmail binary.
 * Uses PHP's mail() function or direct sendmail execution.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Mailers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class SendmailMailer implements MailerInterface
{
    /**
     * Sendmail configuration
     */
    protected array $config;

    /**
     * Create a new SendmailMailer instance
     * 
     * @param array $config Sendmail configuration
     */
    public function __construct(array $config)
    {
        $this->config = array_merge([
            'path' => '/usr/sbin/sendmail -bs',
        ], $config);
    }

    /**
     * Send a mail message
     * 
     * @param Message $message The message to send
     * @return bool True if sent successfully
     * @throws Exception If sending fails
     */
    public function send(Message $message): bool
    {
        $message->validate();

        try {
            return $this->sendViaMail($message);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to send email via sendmail: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the mailer configuration
     * 
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if the mailer is properly configured
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['path']);
    }

    /**
     * Get the mailer's transport name
     * 
     * @return string
     */
    public function getTransport(): string
    {
        return 'sendmail';
    }

    /**
     * Send email using PHP's mail() function
     * 
     * @param Message $message
     * @return bool
     */
    protected function sendViaMail(Message $message): bool
    {
        // Set sendmail path
        if (!empty($this->config['path'])) {
            ini_set('sendmail_path', $this->config['path']);
        }

        $to = $message->getTo()->toString();
        $subject = $message->getSubject();
        $headers = $this->buildHeaders($message);
        $body = $this->buildBody($message);

        $result = mail($to, $subject, $body, $headers);

        if (!$result) {
            throw new RuntimeException('PHP mail() function failed');
        }

        return true;
    }

    /**
     * Build email headers
     * 
     * @param Message $message
     * @return string
     */
    protected function buildHeaders(Message $message): string
    {
        $headers = [];

        // From header
        $from = $message->getFrom();
        $headers[] = "From: " . $from->toString();

        // CC headers
        if (!$message->getCc()->isEmpty()) {
            $headers[] = "Cc: " . $message->getCc()->toString();
        }

        // BCC headers
        if (!$message->getBcc()->isEmpty()) {
            $headers[] = "Bcc: " . $message->getBcc()->toString();
        }

        // Standard headers
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . uniqid() . "@" . gethostname() . ">";
        $headers[] = "X-Mailer: TreeHouse Mail System";

        // Priority header
        if ($message->getPriority() !== 5) {
            $headers[] = "X-Priority: " . $message->getPriority();
        }

        // Content type
        $htmlBody = $message->getHtmlBody();
        $textBody = $message->getTextBody();
        $attachments = $message->getAttachments();

        $headers[] = "MIME-Version: 1.0";

        if (!empty($attachments)) {
            // Mixed multipart with attachments
            $mixedBoundary = uniqid('mixed_boundary');
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$mixedBoundary}\"";
        } elseif ($htmlBody && $textBody) {
            // Multipart message without attachments
            $boundary = uniqid('boundary');
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        } elseif ($htmlBody) {
            // HTML only
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";
        } else {
            // Text only
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";
        }

        // Custom headers
        foreach ($message->getHeaders() as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        return implode("\r\n", $headers);
    }

    /**
     * Build email body
     * 
     * @param Message $message
     * @return string
     */
    protected function buildBody(Message $message): string
    {
        $htmlBody = $message->getHtmlBody();
        $textBody = $message->getTextBody();
        $attachments = $message->getAttachments();

        if (!empty($attachments)) {
            // Mixed multipart with attachments
            $mixedBoundary = uniqid('mixed_boundary');
            $body = [];
            
            $body[] = "This is a multi-part message in MIME format.";
            $body[] = "";
            
            // Content part
            $body[] = "--{$mixedBoundary}";
            
            if ($htmlBody && $textBody) {
                // Alternative multipart for content
                $altBoundary = uniqid('alt_boundary');
                $body[] = "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"";
                $body[] = "";
                $body[] = "";
                
                // Text part
                $body[] = "--{$altBoundary}";
                $body[] = "Content-Type: text/plain; charset=UTF-8";
                $body[] = "Content-Transfer-Encoding: 8bit";
                $body[] = "";
                $body[] = $textBody;
                $body[] = "";
                
                // HTML part
                $body[] = "--{$altBoundary}";
                $body[] = "Content-Type: text/html; charset=UTF-8";
                $body[] = "Content-Transfer-Encoding: 8bit";
                $body[] = "";
                $body[] = $htmlBody;
                $body[] = "";
                $body[] = "--{$altBoundary}--";
            } elseif ($htmlBody) {
                // HTML only
                $body[] = "Content-Type: text/html; charset=UTF-8";
                $body[] = "Content-Transfer-Encoding: 8bit";
                $body[] = "";
                $body[] = $htmlBody;
            } else {
                // Text only
                $body[] = "Content-Type: text/plain; charset=UTF-8";
                $body[] = "Content-Transfer-Encoding: 8bit";
                $body[] = "";
                $body[] = $textBody ?? '';
            }
            
            // Add attachments
            foreach ($attachments as $attachment) {
                $body[] = "";
                $body[] = "--{$mixedBoundary}";
                $body[] = "Content-Type: {$attachment['mime']}; name=\"{$attachment['name']}\"";
                $body[] = "Content-Transfer-Encoding: base64";
                $body[] = "Content-Disposition: attachment; filename=\"{$attachment['name']}\"";
                $body[] = "";
                
                // Encode attachment data
                if (isset($attachment['data'])) {
                    // Data attachment
                    $encodedData = base64_encode($attachment['data']);
                } else {
                    // File attachment
                    $fileData = file_get_contents($attachment['path']);
                    $encodedData = base64_encode($fileData);
                }
                
                // Split into 76-character lines as per RFC
                $body[] = chunk_split($encodedData, 76, "\r\n");
            }
            
            $body[] = "--{$mixedBoundary}--";
            
            return implode("\r\n", $body);
            
        } elseif ($htmlBody && $textBody) {
            // Multipart message without attachments
            $boundary = uniqid('boundary');
            $body = [];
            
            $body[] = "This is a multi-part message in MIME format.";
            $body[] = "";
            
            // Text part
            $body[] = "--{$boundary}";
            $body[] = "Content-Type: text/plain; charset=UTF-8";
            $body[] = "Content-Transfer-Encoding: 8bit";
            $body[] = "";
            $body[] = $textBody;
            $body[] = "";
            
            // HTML part
            $body[] = "--{$boundary}";
            $body[] = "Content-Type: text/html; charset=UTF-8";
            $body[] = "Content-Transfer-Encoding: 8bit";
            $body[] = "";
            $body[] = $htmlBody;
            $body[] = "";
            $body[] = "--{$boundary}--";
            
            return implode("\r\n", $body);
        } elseif ($htmlBody) {
            return $htmlBody;
        } else {
            return $textBody ?? '';
        }
    }
}