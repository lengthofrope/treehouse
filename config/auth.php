<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Authentication Guard
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application.
    |
    */

    'default' => env('AUTH_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Here you may define every authentication guard for your application.
    | A great default configuration has been defined for you here which uses
    | session storage and the database user provider.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],

        'mobile' => [
            'driver' => 'jwt',
            'provider' => 'jwt_users',
        ],

        'token' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'database',
            'table' => env('AUTH_TABLE', 'users'),
        ],

        'jwt_users' => [
            'driver' => 'jwt',
            'user_claim' => 'user',
            'embed_user_data' => true,
            'required_user_fields' => ['id', 'email'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Provider Class
    |--------------------------------------------------------------------------
    |
    | This option specifies the class used to retrieve users for authorization.
    | The class must implement the Authorizable interface and have a find() method.
    |
    */

    'user_provider' => env('AUTH_USER_PROVIDER', \App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | This option controls the default role assigned to new users.
    |
    */

    'default_role' => env('AUTH_DEFAULT_ROLE', 'member'),

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TABLE', 'password_resets'),
            'expire' => env('AUTH_PASSWORD_RESET_EXPIRE', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for JWT authentication including signing algorithms,
    | token lifetime, and security settings.
    |
    */

    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'algorithm' => env('JWT_ALGORITHM', 'HS256'),
        'ttl' => env('JWT_TTL', 900), // 15 minutes
        'refresh_ttl' => env('JWT_REFRESH_TTL', 1209600), // 2 weeks
        'issuer' => env('JWT_ISSUER', env('APP_NAME', 'TreeHouse')),
        'audience' => env('JWT_AUDIENCE', env('APP_URL', 'http://localhost')),
        'leeway' => env('JWT_LEEWAY', 0),
        'required_claims' => [
            'iss', // Issuer
            'aud', // Audience
            'sub', // Subject (user ID)
            'exp', // Expiration time
        ],
        // Phase 4: Refresh token configuration
        'refresh' => [
            'rotation_enabled' => env('JWT_REFRESH_ROTATION', true),
            'family_tracking' => env('JWT_FAMILY_TRACKING', true),
            'max_refresh_count' => env('JWT_MAX_REFRESH_COUNT', 50),
            'grace_period' => env('JWT_REFRESH_GRACE_PERIOD', 300), // 5 minutes
        ],
        
        // Phase 5: Advanced Security Configuration
        'security' => [
            'key_rotation' => [
                'enabled' => env('JWT_KEY_ROTATION_ENABLED', true),
                'interval' => env('JWT_KEY_ROTATION_INTERVAL', 2592000), // 30 days
                'grace_period' => env('JWT_KEY_GRACE_PERIOD', 604800), // 7 days
                'max_keys' => env('JWT_MAX_KEYS', 10),
            ],
            
            'breach_detection' => [
                'enabled' => env('JWT_BREACH_DETECTION_ENABLED', true),
                'failed_auth_threshold' => env('JWT_FAILED_AUTH_THRESHOLD', 5),
                'auto_block_enabled' => env('JWT_AUTO_BLOCK_ENABLED', true),
                'block_duration' => env('JWT_BLOCK_DURATION', 3600), // 1 hour
                'monitoring_window' => env('JWT_MONITORING_WINDOW', 3600), // 1 hour
            ],
            
            'csrf' => [
                'enabled' => env('JWT_CSRF_ENABLED', false),
                'ttl' => env('JWT_CSRF_TTL', 3600), // 1 hour
                'include_fingerprint' => env('JWT_CSRF_FINGERPRINT', true),
            ],
            
            'debugging' => [
                'enabled' => env('JWT_DEBUG_ENABLED', false),
                'trace_validation' => env('JWT_TRACE_VALIDATION', false),
                'performance_profiling' => env('JWT_PERFORMANCE_PROFILING', false),
            ],
        ],
    ],

];