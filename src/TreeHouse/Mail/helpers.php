<?php

declare(strict_types=1);

if (!function_exists('renderEmail')) {
    /**
     * Render an email template
     * 
     * @param string $template Template path (e.g., 'emails.welcome')
     * @param array $data Template data
     * @return string
     */
    function renderEmail(string $template, array $data = []): string
    {
        return app('mail.renderer')->render($template, $data);
    }
}

if (!function_exists('mailTemplate')) {
    /**
     * Create a new email template instance
     * 
     * @param string $template Template path
     * @param array $data Template data
     * @return string
     */
    function mailTemplate(string $template, array $data = []): string
    {
        return renderEmail($template, $data);
    }
}

if (!function_exists('previewEmail')) {
    /**
     * Preview an email template (for development)
     *
     * @param string $template Template path
     * @param array $data Template data
     * @return string
     */
    function previewEmail(string $template, array $data = []): string
    {
        return renderEmail($template, $data);
    }
}

if (!function_exists('sendMail')) {
    /**
     * Send an email immediately
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML or text)
     * @param array $headers Optional headers
     * @return bool
     */
    function sendMail(string $to, string $subject, string $body, array $headers = []): bool
    {
        $message = app('mail')->compose()
            ->to($to)
            ->subject($subject);
            
        // Determine if body is HTML or text
        if (strpos($body, '<') !== false && strpos($body, '>') !== false) {
            $message->html($body);
        } else {
            $message->text($body);
        }
        
        // Add custom headers
        foreach ($headers as $name => $value) {
            $message->header($name, $value);
        }
        
        return $message->send();
    }
}

if (!function_exists('queueMail')) {
    /**
     * Queue an email for later processing
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML or text)
     * @param int $priority Queue priority (1-5, 1 = highest)
     * @param array $headers Optional headers
     * @return \LengthOfRope\TreeHouse\Mail\Queue\QueuedMail|bool
     */
    function queueMail(string $to, string $subject, string $body, int $priority = 5, array $headers = []): \LengthOfRope\TreeHouse\Mail\Queue\QueuedMail|bool
    {
        $message = app('mail')->compose()
            ->to($to)
            ->subject($subject)
            ->priority($priority);
            
        // Determine if body is HTML or text
        if (strpos($body, '<') !== false && strpos($body, '>') !== false) {
            $message->html($body);
        } else {
            $message->text($body);
        }
        
        // Add custom headers
        foreach ($headers as $name => $value) {
            $message->header($name, $value);
        }
        
        return $message->queue($priority);
    }
}

if (!function_exists('mailer')) {
    /**
     * Get the mail manager instance
     *
     * @param string|null $mailer Specific mailer to use
     * @return \LengthOfRope\TreeHouse\Mail\MailManager
     */
    function mailer(?string $mailer = null): \LengthOfRope\TreeHouse\Mail\MailManager
    {
        $mailManager = app('mail');
        
        if ($mailer) {
            return $mailManager->mailer($mailer);
        }
        
        return $mailManager;
    }
}