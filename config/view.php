<?php

return [
    'enabled' => env('VIEW_CACHE_ENABLED', true),
    'paths' => [
        __DIR__ . '/../resources/views',
    ],
    'cache_path' => __DIR__ . '/../storage/views',
    'cache_enabled' => env('VIEW_CACHE_ENABLED', true),
];