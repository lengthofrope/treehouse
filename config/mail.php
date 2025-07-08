<?php

/**
 * Mail Configuration
 * 
 * Configuration for the TreeHouse Mail system.
 * Defines mail drivers, default settings, and queue configuration.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as needed.
    |
    */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'localhost'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 60,
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => 'mail',
            'path' => 'storage/logs/mail.log',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the mail queue system. Controls how emails are
    | queued, processed, and retried when failures occur.
    |
    */

    'queue' => [
        'enabled' => env('MAIL_QUEUE_ENABLED', true),
        'batch_size' => env('MAIL_QUEUE_BATCH_SIZE', 10),
        'max_attempts' => env('MAIL_QUEUE_MAX_ATTEMPTS', 3),
        
        // Enhanced Retry Configuration
        'retry_strategy' => env('MAIL_QUEUE_RETRY_STRATEGY', 'exponential'), // linear, exponential
        'base_retry_delay' => env('MAIL_QUEUE_BASE_RETRY_DELAY', 300), // 5 minutes
        'max_retry_delay' => env('MAIL_QUEUE_MAX_RETRY_DELAY', 3600), // 1 hour
        'retry_multiplier' => env('MAIL_QUEUE_RETRY_MULTIPLIER', 2),
        
        // Performance Monitoring
        'performance_tracking' => env('MAIL_QUEUE_PERFORMANCE_TRACKING', true),
        'slow_query_threshold' => env('MAIL_QUEUE_SLOW_THRESHOLD', 5.0), // seconds
        'enable_metrics_logging' => env('MAIL_QUEUE_METRICS_LOGGING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Templates
    |--------------------------------------------------------------------------
    |
    | Configuration for email templates and rendering.
    |
    */

    'templates' => [
        'path' => 'emails',
        'cache_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail System Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring mail system health and performance.
    |
    */

    'monitoring' => [
        'queue_health_check' => true,
        'alert_on_slow_processing' => true,
        'alert_on_high_failure_rate' => true,
        'failure_rate_threshold' => 0.1, // 10%
    ],
];