<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Mailers;

use LengthOfRope\TreeHouse\Mail\Messages\Message;
use Exception;

/**
 * Log Mailer
 * 
 * Log-only mail driver for development and testing.
 * Writes email content to log files instead of sending.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Mailers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class LogMailer implements MailerInterface
{
    /**
     * Log mailer configuration
     */
    protected array $config;

    /**
     * Create a new LogMailer instance
     * 
     * @param array $config Log mailer configuration
     */
    public function __construct(array $config)
    {
        $this->config = array_merge([
            'channel' => 'mail',
            'path' => 'storage/logs/mail.log',
        ], $config);
    }

    /**
     * Send a mail message (log it)
     * 
     * @param Message $message The message to send
     * @return bool True if logged successfully
     * @throws Exception If logging fails
     */
    public function send(Message $message): bool
    {
        $message->validate();

        try {
            $logEntry = $this->formatLogEntry($message);
            $this->writeToLog($logEntry);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to log email: " . $e->getMessage(), 0, $e);
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
        return true; // Log mailer is always configured
    }

    /**
     * Get the mailer's transport name
     * 
     * @return string
     */
    public function getTransport(): string
    {
        return 'log';
    }

    /**
     * Format the log entry
     * 
     * @param Message $message
     * @return string
     */
    protected function formatLogEntry(Message $message): string
    {
        $entry = [];
        
        $entry[] = str_repeat('=', 80);
        $entry[] = "MAIL LOG ENTRY - " . date('Y-m-d H:i:s');
        $entry[] = str_repeat('=', 80);
        
        // Recipients
        $entry[] = "To: " . $message->getTo()->toString();
        
        if (!$message->getCc()->isEmpty()) {
            $entry[] = "Cc: " . $message->getCc()->toString();
        }
        
        if (!$message->getBcc()->isEmpty()) {
            $entry[] = "Bcc: " . $message->getBcc()->toString();
        }
        
        // Sender
        $entry[] = "From: " . $message->getFrom()->toString();
        
        // Subject
        $entry[] = "Subject: " . $message->getSubject();
        
        // Priority
        if ($message->getPriority() !== 5) {
            $entry[] = "Priority: " . $message->getPriority();
        }
        
        // Mailer
        if ($message->getMailer()) {
            $entry[] = "Mailer: " . $message->getMailer();
        }
        
        // Custom headers
        $headers = $message->getHeaders();
        if (!empty($headers)) {
            $entry[] = "";
            $entry[] = "Custom Headers:";
            foreach ($headers as $name => $value) {
                $entry[] = "  {$name}: {$value}";
            }
        }
        
        $entry[] = "";
        $entry[] = str_repeat('-', 40) . " CONTENT " . str_repeat('-', 33);
        
        // Text content
        $textBody = $message->getTextBody();
        if ($textBody) {
            $entry[] = "";
            $entry[] = "TEXT CONTENT:";
            $entry[] = str_repeat('-', 20);
            $entry[] = $textBody;
        }
        
        // HTML content
        $htmlBody = $message->getHtmlBody();
        if ($htmlBody) {
            $entry[] = "";
            $entry[] = "HTML CONTENT:";
            $entry[] = str_repeat('-', 20);
            $entry[] = $htmlBody;
        }
        
        $entry[] = "";
        $entry[] = str_repeat('=', 80);
        $entry[] = "";
        
        return implode("\n", $entry);
    }

    /**
     * Write to log file
     * 
     * @param string $content
     * @throws Exception
     */
    protected function writeToLog(string $content): void
    {
        $logPath = $this->getLogPath();
        
        // Ensure directory exists
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                throw new Exception("Failed to create log directory: {$logDir}");
            }
        }
        
        // Write to log file
        $result = file_put_contents($logPath, $content, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            throw new Exception("Failed to write to log file: {$logPath}");
        }
    }

    /**
     * Get the log file path
     * 
     * @return string
     */
    protected function getLogPath(): string
    {
        $path = $this->config['path'];
        
        // If path is relative, make it relative to project root
        if (!str_starts_with($path, '/')) {
            $path = getcwd() . '/' . $path;
        }
        
        return $path;
    }

    /**
     * Get recent log entries
     * 
     * @param int $lines Number of lines to read
     * @return string
     */
    public function getRecentEntries(int $lines = 100): string
    {
        $logPath = $this->getLogPath();
        
        if (!file_exists($logPath)) {
            return "No mail log entries found.";
        }
        
        // Read last N lines from file
        $file = file($logPath);
        if ($file === false) {
            return "Failed to read log file.";
        }
        
        $totalLines = count($file);
        $startLine = max(0, $totalLines - $lines);
        $recentLines = array_slice($file, $startLine);
        
        return implode('', $recentLines);
    }

    /**
     * Clear the log file
     * 
     * @return bool
     */
    public function clearLog(): bool
    {
        $logPath = $this->getLogPath();
        
        if (file_exists($logPath)) {
            return unlink($logPath);
        }
        
        return true;
    }

    /**
     * Get log file size
     * 
     * @return int Size in bytes
     */
    public function getLogSize(): int
    {
        $logPath = $this->getLogPath();
        
        if (file_exists($logPath)) {
            return filesize($logPath);
        }
        
        return 0;
    }
}