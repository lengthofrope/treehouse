<?php

declare(strict_types=1);

namespace App\Mail;

use LengthOfRope\TreeHouse\Mail\Mailable;

/**
 * Welcome Email
 * 
 * Example mailable class for sending welcome emails to new users.
 * 
 * @package App\Mail
 */
class WelcomeEmail extends Mailable
{
    /**
     * User instance
     */
    protected mixed $user;

    /**
     * Create a new WelcomeEmail instance
     * 
     * @param mixed $user User object or array
     */
    public function __construct(mixed $user)
    {
        $this->user = $user;
    }

    /**
     * Build the mailable
     * 
     * @return self
     */
    public function build(): self
    {
        return $this
            ->subject('Welcome to ' . (app()->config('app.name') ?? 'TreeHouse App'))
            ->emailTemplate('emails.welcome', [
                'user' => $this->user,
                'dashboard_url' => (app()->config('app.url') ?? 'http://localhost') . '/dashboard',
            ]);
    }
}