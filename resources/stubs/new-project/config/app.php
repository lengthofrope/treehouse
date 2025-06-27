<?php

return [
    'name' => env('APP_NAME', '{{PROJECT_NAME}}'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'key' => env('APP_KEY'),
    'encryption_key' => env('ENCRYPTION_KEY'),
    
    'session' => [
        'driver' => env('SESSION_DRIVER', 'file'),
        'lifetime' => env('SESSION_LIFETIME', 120),
        'path' => __DIR__ . '/../storage/sessions',
    ],
    
    'logging' => [
        'channel' => env('LOG_CHANNEL', 'single'),
        'level' => env('LOG_LEVEL', 'error'),
        'path' => __DIR__ . '/../storage/logs/app.log',
    ],
];
