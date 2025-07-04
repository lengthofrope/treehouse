<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Error Handling Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the TreeHouse error handling system.
    | You can customize logging, rendering, classification, and context collection.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When debug mode is enabled, detailed error information will be shown
    | including stack traces, context data, and internal error details.
    | This should be disabled in production for security.
    |
    */
    'debug' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Error Reporting
    |--------------------------------------------------------------------------
    |
    | Configure which errors should be reported and logged.
    |
    */
    'reporting' => [
        'log_exceptions' => true,
        'log_context' => true,
        'report_all_exceptions' => true,
        'max_context_collection_time' => 2.0,
        'continue_on_collector_failure' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how errors are logged including channels, formatters,
    | and log rotation settings.
    |
    */
    'logging' => [
        'default_channel' => env('ERROR_LOG_CHANNEL', 'file'),
        
        'channels' => [
            'file' => [
                'driver' => 'file',
                'path' => __DIR__ . '/../storage/logs/errors.log',
                'level' => 'debug',
                'max_files' => 30,
                'max_size' => '10MB',
            ],
            
            'syslog' => [
                'driver' => 'syslog',
                'ident' => 'treehouse',
                'facility' => LOG_USER,
                'level' => 'warning',
            ],
            
            'error_log' => [
                'driver' => 'error_log',
                'level' => 'error',
            ],
        ],
        
        'formatters' => [
            'default' => 'json',
            'available' => ['json', 'structured', 'simple'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Classification
    |--------------------------------------------------------------------------
    |
    | Configure how exceptions are classified and their severity levels.
    |
    */
    'classification' => [
        'severity_mapping' => [
            // Critical system errors
            'critical' => [
                'OutOfMemoryError',
                'ParseError',
                'TypeError',
                'FatalError',
                'SystemException',
            ],
            
            // High severity errors
            'high' => [
                'DatabaseException',
                'ConnectionException',
                'SecurityException',
                'AuthenticationException',
                'AuthorizationException',
            ],
            
            // Medium severity errors
            'medium' => [
                'ValidationException',
                'HttpException',
                'InvalidArgumentException',
                'RuntimeException',
            ],
            
            // Low severity errors
            'low' => [
                'LogicException',
                'DomainException',
                'UnexpectedValueException',
            ],
        ],
        
        'security_patterns' => [
            'sql injection',
            'xss',
            'csrf',
            'path traversal',
            'file inclusion',
            'code injection',
            'authentication bypass',
            'privilege escalation',
            'unauthorized access',
            'session hijacking',
        ],
        
        'critical_patterns' => [
            'out of memory',
            'disk full',
            'database connection failed',
            'cache connection failed',
            'file system error',
            'permission denied',
            'service unavailable',
            'timeout',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Collection
    |--------------------------------------------------------------------------
    |
    | Configure what context information is collected when errors occur.
    |
    */
    'context' => [
        'collectors' => [
            'request' => [
                'enabled' => true,
                'priority' => 100,
                'timeout' => 1.0,
                'sensitive_headers' => [
                    'authorization',
                    'cookie',
                    'x-api-key',
                    'x-auth-token',
                    'x-csrf-token',
                ],
                'sensitive_params' => [
                    'password',
                    'password_confirmation',
                    'token',
                    'secret',
                    'key',
                    'api_key',
                ],
            ],
            
            'user' => [
                'enabled' => true,
                'priority' => 90,
                'timeout' => 0.5,
                'include_permissions' => true,
                'include_roles' => true,
            ],
            
            'environment' => [
                'enabled' => true,
                'priority' => 80,
                'timeout' => 1.0,
                'include_system_info' => true,
                'include_php_info' => true,
                'include_memory_info' => true,
                'include_disk_info' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Rendering
    |--------------------------------------------------------------------------
    |
    | Configure how errors are rendered for different output formats.
    |
    */
    'rendering' => [
        'default_renderer' => 'html',
        
        'renderers' => [
            'json' => [
                'class' => \LengthOfRope\TreeHouse\Errors\Rendering\JsonRenderer::class,
                'priority' => 80,
                'include_debug_info' => env('APP_DEBUG', false),
                'include_trace' => env('APP_DEBUG', false),
                'pretty_print' => env('APP_DEBUG', false),
            ],
            
            'html' => [
                'class' => \LengthOfRope\TreeHouse\Errors\Rendering\HtmlRenderer::class,
                'priority' => 70,
                'template_path' => __DIR__ . '/../resources/views/errors',
                'fallback_templates' => true,
                'include_debug_info' => env('APP_DEBUG', false),
            ],
            
            'cli' => [
                'class' => \LengthOfRope\TreeHouse\Errors\Rendering\CliRenderer::class,
                'priority' => 90,
                'color_support' => null, // Auto-detect
                'terminal_width' => 80,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Status Code Mapping
    |--------------------------------------------------------------------------
    |
    | Map exception types to HTTP status codes.
    |
    */
    'http_status_codes' => [
        'InvalidArgumentException' => 400,
        'ValidationException' => 422,
        'AuthenticationException' => 401,
        'AuthorizationException' => 403,
        'NotFoundException' => 404,
        'MethodNotAllowedException' => 405,
        'ConflictException' => 409,
        'TooManyRequestsException' => 429,
        'DatabaseException' => 500,
        'SystemException' => 500,
        'ServiceUnavailableException' => 503,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Page Templates
    |--------------------------------------------------------------------------
    |
    | Configure error page templates for different error types.
    |
    */
    'templates' => [
        'path' => __DIR__ . '/../resources/views/errors',
        
        'pages' => [
            '400' => 'errors.400',
            '401' => 'errors.401',
            '403' => 'errors.403',
            '404' => 'errors.404',
            '405' => 'errors.405',
            '419' => 'errors.419', // CSRF token mismatch
            '422' => 'errors.422',
            '429' => 'errors.429',
            '500' => 'errors.500',
            '503' => 'errors.503',
        ],
        
        'fallback' => 'errors.generic',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security-related error handling configuration.
    |
    */
    'security' => [
        'hide_sensitive_data' => !env('APP_DEBUG', false),
        'log_security_events' => true,
        'rate_limit_error_responses' => true,
        'add_security_headers' => true,
        
        'sensitive_data_patterns' => [
            '/password/i',
            '/secret/i',
            '/token/i',
            '/key/i',
            '/auth/i',
            '/credential/i',
        ],
        
        'security_headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Performance-related settings for error handling.
    |
    */
    'performance' => [
        'cache_compiled_templates' => true,
        'cache_classification_results' => false,
        'max_context_collection_time' => 2.0,
        'max_log_entry_size' => '1MB',
        'async_logging' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    |
    | Settings specific to development environment.
    |
    */
    'development' => [
        'show_exception_editor_links' => env('APP_DEBUG', false),
        'editor' => env('ERROR_EDITOR', 'vscode'), // vscode, phpstorm, sublime
        'editor_url_template' => env('ERROR_EDITOR_URL', null),
        'include_source_code' => env('APP_DEBUG', false),
        'source_code_lines' => 10, // Lines of context around error
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure error notifications (future feature).
    |
    */
    'notifications' => [
        'enabled' => false,
        'channels' => [
            'email' => [
                'enabled' => false,
                'to' => env('ERROR_EMAIL_TO'),
                'severity_threshold' => 'high',
            ],
            'slack' => [
                'enabled' => false,
                'webhook_url' => env('ERROR_SLACK_WEBHOOK'),
                'severity_threshold' => 'critical',
            ],
        ],
    ],
];