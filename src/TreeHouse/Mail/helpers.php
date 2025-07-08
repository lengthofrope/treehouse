<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Mail\MailManager;

if (!function_exists('mailer')) {
    /**
     * Get the mail manager instance
     *
     * @return MailManager
     */
    function mailer(): MailManager
    {
        /** @var \LengthOfRope\TreeHouse\Foundation\Application $app */
        $app = $GLOBALS['app'] ?? null;
        
        if (!$app) {
            throw new RuntimeException('Application instance not available');
        }
        
        return $app->make('mail');
    }
}

if (!function_exists('send_mail')) {
    /**
     * Send a simple email immediately
     *
     * @param string|array $to Recipients
     * @param string $subject Email subject
     * @param string $message Email content (HTML or text)
     * @param array $headers Additional headers
     * @param string|null $from Sender address
     * @return bool True if sent successfully
     */
    function send_mail(
        string|array $to,
        string $subject,
        string $message,
        array $headers = [],
        ?string $from = null
    ): bool {
        $mail = mailer()
            ->to($to)
            ->subject($subject);
        
        if ($from) {
            $mail->from($from);
        }
        
        foreach ($headers as $name => $value) {
            $mail->header($name, $value);
        }
        
        // Detect if message is HTML
        if (preg_match('/<[^<]+>/', $message)) {
            $mail->html($message);
        } else {
            $mail->text($message);
        }
        
        return $mail->send();
    }
}

if (!function_exists('queue_mail')) {
    /**
     * Queue an email for later processing
     *
     * @param string|array $to Recipients
     * @param string $subject Email subject
     * @param string $message Email content (HTML or text)
     * @param array $headers Additional headers
     * @param string|null $from Sender address
     * @param int $priority Priority level (1 = highest, 10 = lowest)
     * @return bool True if queued successfully
     */
    function queue_mail(
        string|array $to,
        string $subject,
        string $message,
        array $headers = [],
        ?string $from = null,
        int $priority = 5
    ): bool {
        $mail = mailer()
            ->to($to)
            ->subject($subject)
            ->priority($priority);
        
        if ($from) {
            $mail->from($from);
        }
        
        foreach ($headers as $name => $value) {
            $mail->header($name, $value);
        }
        
        // Detect if message is HTML
        if (preg_match('/<[^<]+>/', $message)) {
            $mail->html($message);
        } else {
            $mail->text($message);
        }
        
        return $mail->queue();
    }
}