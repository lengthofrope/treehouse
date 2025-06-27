<?php

return [
    'default' => env('CACHE_DRIVER', 'file'),
    
    'file' => [
        'driver' => 'file',
        'path' => __DIR__ . '/../storage/cache',
        'default_ttl' => 3600,
    ],
];