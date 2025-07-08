<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Mailers;

use LengthOfRope\TreeHouse\Mail\Messages\Message;

/**
 * Mailer Interface
 * 
 * Contract for all mail driver implementations.
 * Defines the standard methods that all mailers must implement.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Mailers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface MailerInterface
{
    /**
     * Send a mail message
     * 
     * @param Message $message The message to send
     * @return bool True if sent successfully, false otherwise
     * @throws \Exception If sending fails
     */
    public function send(Message $message): bool;

    /**
     * Get the mailer configuration
     * 
     * @return array
     */
    public function getConfig(): array;

    /**
     * Check if the mailer is properly configured
     * 
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Get the mailer's transport name
     * 
     * @return string
     */
    public function getTransport(): string;
}