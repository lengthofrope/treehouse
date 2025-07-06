<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Event Dispatcher
    |--------------------------------------------------------------------------
    |
    | This option controls the default event dispatcher that will be used
    | throughout the application. Currently only 'sync' is supported.
    |
    */
    'default_dispatcher' => env('EVENT_DISPATCHER', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Event Dispatchers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the event dispatchers for your application.
    | Currently only synchronous dispatching is supported.
    |
    */
    'dispatchers' => [
        'sync' => \LengthOfRope\TreeHouse\Events\SyncEventDispatcher::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Discovery
    |--------------------------------------------------------------------------
    |
    | Configure automatic discovery of event listeners in your application.
    | When enabled, the framework will scan specified paths for listeners.
    |
    */
    'auto_discovery' => [
        'enabled' => env('EVENT_AUTO_DISCOVERY', true),
        'paths' => [
            'src/App/Listeners',
        ],
        'cache' => env('EVENT_CACHE_DISCOVERY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Events
    |--------------------------------------------------------------------------
    |
    | Configure which model events should be fired automatically.
    | You can disable specific events if they're not needed.
    |
    */
    'model_events' => [
        'enabled' => env('MODEL_EVENTS_ENABLED', true),
        'events' => [
            'creating', 'created', 
            'updating', 'updated', 
            'deleting', 'deleted', 
            'saving', 'saved'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Debugging
    |--------------------------------------------------------------------------
    |
    | Configure debugging options for the event system.
    | Useful during development to track event flow.
    |
    */
    'debugging' => [
        'enabled' => env('EVENT_DEBUG', env('APP_DEBUG', false)),
        'log_events' => env('LOG_EVENTS', false),
        'log_listeners' => env('LOG_LISTENERS', false),
        'performance_monitoring' => env('EVENT_PERFORMANCE_MONITORING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Listeners
    |--------------------------------------------------------------------------
    |
    | Register your event listeners here. Each event can have multiple
    | listeners that will be executed in the order they are registered.
    |
    */
    'listeners' => [
        // Example:
        // \LengthOfRope\TreeHouse\Events\Events\UserCreated::class => [
        //     \App\Listeners\SendWelcomeEmail::class,
        //     \App\Listeners\UpdateUserStatistics::class,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Aliases
    |--------------------------------------------------------------------------
    |
    | You can define aliases for commonly used event classes to make
    | registration and usage more convenient.
    |
    */
    'aliases' => [
        'user.created' => \LengthOfRope\TreeHouse\Events\Events\ModelCreated::class,
        'user.updated' => \LengthOfRope\TreeHouse\Events\Events\ModelUpdated::class,
        'user.deleted' => \LengthOfRope\TreeHouse\Events\Events\ModelDeleted::class,
    ],
];