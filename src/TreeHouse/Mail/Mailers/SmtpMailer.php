<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Mailers;

use LengthOfRope\TreeHouse\Mail\Messages\Message;
use LengthOfRope\TreeHouse\Mail\Support\Address;
use LengthOfRope\TreeHouse\Mail\Support\AddressList;
use Exception;
use RuntimeException;

/**
 * SMTP Mailer
 * 
 * SMTP mail driver implementation using native PHP socket connections.
 * Supports SSL/TLS encryption and authentication.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Mailers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class SmtpMailer implements MailerInterface
{
    /**
     * SMTP configuration
     */
    protected array $config;

    /**
     * SMTP connection resource
     */
    protected $connection = null;

    /**
     * Line ending
     */
    protected string $lineEnding = "\r\n";

    /**
     * Create a new SmtpMailer instance
     * 
     * @param array $config SMTP configuration
     */
    public function __construct(array $config)
    {
        $this->config = array_merge([
            'host' => 'localhost',
            'port' => 587,
            'encryption' => 'tls',
            'username' => null,
            'password' => null,
            'timeout' => 60,
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
            $this->connect();
            $this->authenticate();
            $this->sendMessage($message);
            $this->disconnect();
            
            return true;
        } catch (Exception $e) {
            $this->disconnect();
            throw $e;
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
        return !empty($this->config['host']) && !empty($this->config['port']);
    }

    /**
     * Get the mailer's transport name
     * 
     * @return string
     */
    public function getTransport(): string
    {
        return 'smtp';
    }

    /**
     * Connect to SMTP server
     * 
     * @throws RuntimeException
     */
    protected function connect(): void
    {
        $host = $this->config['host'];
        $port = $this->config['port'];
        $timeout = $this->config['timeout'];
        $encryption = $this->config['encryption'];

        // Handle SSL/TLS
        if ($encryption === 'ssl') {
            $host = "ssl://{$host}";
        }

        $this->connection = fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$this->connection) {
            throw new RuntimeException("Failed to connect to SMTP server: {$errstr} ({$errno})");
        }

        // Read welcome message
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response)) {
            throw new RuntimeException("SMTP server error: {$response}");
        }

        // Send EHLO
        $this->sendCommand("EHLO " . gethostname());

        // Start TLS if required
        if ($encryption === 'tls') {
            $this->sendCommand('STARTTLS');
            
            if (!stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Failed to enable TLS encryption');
            }

            // Send EHLO again after TLS
            $this->sendCommand("EHLO " . gethostname());
        }
    }

    /**
     * Authenticate with SMTP server
     * 
     * @throws RuntimeException
     */
    protected function authenticate(): void
    {
        $username = $this->config['username'];
        $password = $this->config['password'];

        if (empty($username) || empty($password)) {
            return; // No authentication required
        }

        // AUTH LOGIN
        $this->sendCommand('AUTH LOGIN');
        $this->sendCommand(base64_encode($username));
        $this->sendCommand(base64_encode($password));
    }

    /**
     * Send the email message
     * 
     * @param Message $message
     * @throws RuntimeException
     */
    protected function sendMessage(Message $message): void
    {
        // MAIL FROM
        $from = $message->getFrom();
        $this->sendCommand("MAIL FROM:<{$from->getEmail()}>");

        // RCPT TO
        foreach ($message->getAllRecipients() as $recipient) {
            $this->sendCommand("RCPT TO:<{$recipient->getEmail()}>");
        }

        // DATA
        $this->sendCommand('DATA');

        // Send headers and body
        $emailData = $this->buildEmailData($message);
        $this->sendData($emailData);

        // End DATA
        $this->sendCommand('.');
    }

    /**
     * Build email data (headers + body)
     * 
     * @param Message $message
     * @return string
     */
    protected function buildEmailData(Message $message): string
    {
        $data = [];

        // Standard headers
        $data[] = "From: " . $message->getFrom()->toString();
        
        if (!$message->getTo()->isEmpty()) {
            $data[] = "To: " . $message->getTo()->toString();
        }
        
        if (!$message->getCc()->isEmpty()) {
            $data[] = "Cc: " . $message->getCc()->toString();
        }

        $data[] = "Subject: " . $this->encodeHeader($message->getSubject());
        $data[] = "Date: " . date('r');
        $data[] = "Message-ID: <" . uniqid() . "@" . gethostname() . ">";

        // Priority header
        if ($message->getPriority() !== 5) {
            $data[] = "X-Priority: " . $message->getPriority();
        }

        // Custom headers
        foreach ($message->getHeaders() as $name => $value) {
            $data[] = "{$name}: {$value}";
        }

        // Content type and body
        $htmlBody = $message->getHtmlBody();
        $textBody = $message->getTextBody();

        if ($htmlBody && $textBody) {
            // Multipart message
            $boundary = uniqid('boundary');
            $data[] = "MIME-Version: 1.0";
            $data[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $data[] = "";
            
            // Text part
            $data[] = "--{$boundary}";
            $data[] = "Content-Type: text/plain; charset=UTF-8";
            $data[] = "Content-Transfer-Encoding: 8bit";
            $data[] = "";
            $data[] = $textBody;
            $data[] = "";
            
            // HTML part
            $data[] = "--{$boundary}";
            $data[] = "Content-Type: text/html; charset=UTF-8";
            $data[] = "Content-Transfer-Encoding: 8bit";
            $data[] = "";
            $data[] = $htmlBody;
            $data[] = "";
            $data[] = "--{$boundary}--";
        } elseif ($htmlBody) {
            // HTML only
            $data[] = "MIME-Version: 1.0";
            $data[] = "Content-Type: text/html; charset=UTF-8";
            $data[] = "Content-Transfer-Encoding: 8bit";
            $data[] = "";
            $data[] = $htmlBody;
        } else {
            // Text only
            $data[] = "MIME-Version: 1.0";
            $data[] = "Content-Type: text/plain; charset=UTF-8";
            $data[] = "Content-Transfer-Encoding: 8bit";
            $data[] = "";
            $data[] = $textBody ?? '';
        }

        return implode($this->lineEnding, $data);
    }

    /**
     * Send SMTP command
     * 
     * @param string $command
     * @throws RuntimeException
     */
    protected function sendCommand(string $command): void
    {
        fwrite($this->connection, $command . $this->lineEnding);
        
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response)) {
            throw new RuntimeException("SMTP command failed: {$command}. Response: {$response}");
        }
    }

    /**
     * Send data (used for email content)
     * 
     * @param string $data
     */
    protected function sendData(string $data): void
    {
        fwrite($this->connection, $data . $this->lineEnding);
    }

    /**
     * Read SMTP response
     * 
     * @return string
     */
    protected function readResponse(): string
    {
        $response = '';
        
        while ($line = fgets($this->connection, 515)) {
            $response .= $line;
            
            // Check if this is the last line of a multi-line response
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        
        return trim($response);
    }

    /**
     * Check if response indicates success
     * 
     * @param string $response
     * @return bool
     */
    protected function isSuccessResponse(string $response): bool
    {
        $code = (int) substr($response, 0, 3);
        return $code >= 200 && $code < 400;
    }

    /**
     * Encode email header for UTF-8 support
     * 
     * @param string $header
     * @return string
     */
    protected function encodeHeader(string $header): string
    {
        if (mb_check_encoding($header, 'ASCII')) {
            return $header;
        }
        
        return '=?UTF-8?B?' . base64_encode($header) . '?=';
    }

    /**
     * Disconnect from SMTP server
     */
    protected function disconnect(): void
    {
        if ($this->connection) {
            fwrite($this->connection, "QUIT" . $this->lineEnding);
            fclose($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}