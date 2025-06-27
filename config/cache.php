<?php

return [
    'enabled' => env('CACHE_ENABLED', true),
    'default' => env('CACHE_DRIVER', 'file'),
    
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/cache',
            'default_ttl' => env('CACHE_TTL', 3600),
        ],
    ],
];