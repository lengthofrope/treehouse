# TreeHouse Framework - Error Handling Layer

The Error Handling Layer provides comprehensive error management, logging, classification, and rendering capabilities for the TreeHouse Framework. This layer implements PSR-3 compliant logging, hierarchical exception handling, and multi-format error rendering.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Components](#components)
- [Usage](#usage)
- [Configuration](#configuration)
- [Testing](#testing)
- [Examples](#examples)

## Overview

The Error Handling Layer is designed to:

- **Catch and classify exceptions** with automatic severity determination
- **Log errors** with structured, secure logging and multiple output formats
- **Collect context** from requests, users, and environment for debugging
- **Render errors** in multiple formats (HTML, JSON, CLI) with template support
- **Provide security** through sensitive data redaction and security pattern detection
- **Enable debugging** with comprehensive error information in development mode

## Architecture

```
src/TreeHouse/Errors/
├── Classification/          # Exception classification and severity determination
│   ├── ClassificationResult.php
│   └── ExceptionClassifier.php
├── Context/                # Context collection from various sources
│   ├── ContextCollectorInterface.php
│   ├── ContextManager.php
│   ├── EnvironmentCollector.php
│   ├── RequestCollector.php
│   └── UserCollector.php
├── Exceptions/             # Hierarchical exception system
│   ├── BaseException.php
│   ├── AuthenticationException.php
│   ├── AuthorizationException.php
│   ├── DatabaseException.php
│   ├── HttpException.php
│   ├── InvalidArgumentException.php
│   ├── SystemException.php
│   └── TypeException.php
├── Logging/                # PSR-3 compliant logging system
│   ├── ErrorLogger.php
│   ├── LogFormatter.php
│   ├── LoggerInterface.php
│   └── LogLevel.php
├── Rendering/              # Multi-format error rendering
│   ├── CliRenderer.php
│   ├── HtmlRenderer.php
│   ├── JsonRenderer.php
│   ├── RenderManager.php
│   └── RendererInterface.php
├── ErrorHandler.php        # Central error handler
└── README.md               # This file
```

## Components

### 1. Exception System

#### BaseException
The foundation exception class that all framework exceptions extend:

```php
use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;

throw new BaseException(
    message: 'Something went wrong',
    userMessage: 'Please try again later',
    errorCode: 'SYS_001',
    statusCode: 500,
    severity: 'high',
    context: ['user_id' => 123]
);
```

#### Specialized Exceptions
- **AuthenticationException**: Authentication failures (401)
- **AuthorizationException**: Authorization failures (403)
- **DatabaseException**: Database-related errors (500)
- **HttpException**: HTTP-specific errors (various)
- **InvalidArgumentException**: Invalid input errors (400)
- **SystemException**: System-level errors (500)
- **TypeException**: Type-related errors (400)

### 2. Classification System

Automatically classifies exceptions by category, severity, and security implications:

```php
use LengthOfRope\TreeHouse\Errors\Classification\ExceptionClassifier;

$classifier = new ExceptionClassifier($config);
$result = $classifier->classify($exception);

echo $result->category;    // 'database', 'authentication', etc.
echo $result->severity;    // 'low', 'medium', 'high', 'critical'
echo $result->isSecurity;  // true/false
echo $result->isCritical;  // true/false
```

### 3. Context Collection

Collects contextual information for debugging:

```php
use LengthOfRope\TreeHouse\Errors\Context\ContextManager;

$contextManager = new ContextManager($config);
$contextManager->addCollector(new RequestCollector());
$contextManager->addCollector(new UserCollector());
$contextManager->addCollector(new EnvironmentCollector());

$context = $contextManager->collect($request);
```

### 4. Logging System

PSR-3 compliant logging with multiple formats:

```php
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;

$logger = new ErrorLogger('file', $channels);
$logger->error('Database connection failed', [
    'exception' => $exception,
    'context' => $context
]);
```

#### Log Formats
- **JSON**: Structured logging for log aggregation systems
- **Structured**: Human-readable structured format
- **Simple**: Basic single-line format

### 5. Rendering System

Multi-format error rendering with template support:

```php
use LengthOfRope\TreeHouse\Errors\Rendering\RenderManager;

$renderManager = new RenderManager($debug, 'html');
$response = $renderManager->render($exception, $classification, $context, $request);
```

#### Renderers
- **HtmlRenderer**: Web browser responses with template support
- **JsonRenderer**: API responses with structured error data
- **CliRenderer**: Command-line error display

### 6. Central Error Handler

Orchestrates the entire error handling process:

```php
use LengthOfRope\TreeHouse\Errors\ErrorHandler;

$errorHandler = new ErrorHandler($classifier, $contextManager, $logger, $config);
$response = $errorHandler->handle($exception, $request);
```

## Usage

### Basic Error Handling

```php
try {
    // Your application code
    throw new DatabaseException('Connection failed');
} catch (Throwable $e) {
    $response = app('error.handler')->handle($e, $request);
    return $response;
}
```

### Custom Exceptions

```php
use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;

class CustomException extends BaseException
{
    public function __construct(string $message = '', array $context = [])
    {
        parent::__construct(
            message: $message,
            userMessage: 'A custom error occurred',
            errorCode: 'CUSTOM_001',
            statusCode: 422,
            severity: 'medium',
            context: $context
        );
    }
}
```

### Manual Logging

```php
$logger = app('error.logger');
$logger->warning('Unusual activity detected', [
    'user_id' => $userId,
    'ip_address' => $request->ip(),
    'action' => 'login_attempt'
]);
```

### Custom Context Collectors

```php
use LengthOfRope\TreeHouse\Errors\Context\ContextCollectorInterface;

class CustomCollector implements ContextCollectorInterface
{
    public function collect(?Request $request = null): array
    {
        return [
            'custom_data' => 'value',
            'timestamp' => time()
        ];
    }
    
    public function getName(): string
    {
        return 'custom';
    }
}

// Register the collector
$contextManager = app('error.context');
$contextManager->addCollector(new CustomCollector());
```

### Custom Renderers

```php
use LengthOfRope\TreeHouse\Errors\Rendering\RendererInterface;

class XmlRenderer implements RendererInterface
{
    public function render(
        Throwable $exception,
        ClassificationResult $classification,
        array $context,
        ?Request $request = null,
        bool $debug = false
    ): string {
        // XML rendering logic
        return $xmlContent;
    }
    
    public function canRender(?Request $request): bool
    {
        return $request && str_contains($request->header('Accept', ''), 'application/xml');
    }
    
    public function getContentType(): string
    {
        return 'application/xml; charset=utf-8';
    }
    
    public function getPriority(): int
    {
        return 60;
    }
    
    public function getName(): string
    {
        return 'xml';
    }
}

// Register the renderer
$renderManager = app('error.renderer');
$renderManager->registerRenderer(new XmlRenderer());
```

## Configuration

Error handling is configured in `config/errors.php`:

```php
return [
    'debug' => env('APP_DEBUG', false),
    
    'logging' => [
        'default_channel' => 'file',
        'channels' => [
            'file' => [
                'driver' => 'file',
                'path' => storage_path('logs/error.log'),
                'format' => 'json',
                'max_files' => 30,
                'level' => 'error'
            ]
        ]
    ],
    
    'classification' => [
        'security_patterns' => [
            'sql_injection' => ['union', 'select', 'drop', 'delete'],
            'xss' => ['<script', 'javascript:', 'onerror='],
            'path_traversal' => ['../', '..\\', '/etc/passwd']
        ],
        'critical_patterns' => [
            'system' => ['out of memory', 'disk full', 'connection refused'],
            'database' => ['deadlock', 'timeout', 'connection lost']
        ]
    ],
    
    'context' => [
        'collectors' => [
            'request' => [
                'collect_headers' => true,
                'collect_body' => false,
                'max_body_size' => 1024
            ],
            'user' => [
                'collect_user_data' => true,
                'collect_session' => false
            ],
            'environment' => [
                'collect_server_info' => true,
                'collect_system_info' => false
            ]
        ]
    ],
    
    'rendering' => [
        'default_renderer' => 'html',
        'template_path' => 'errors',
        'fallback_to_builtin' => true
    ]
];
```

## Testing

The Error Handling Layer includes comprehensive unit tests:

```bash
# Run all error handling tests
./vendor/bin/phpunit tests/Unit/Errors/

# Run specific test suites
./vendor/bin/phpunit tests/Unit/Errors/Classification/
./vendor/bin/phpunit tests/Unit/Errors/Logging/
./vendor/bin/phpunit tests/Unit/Errors/Rendering/
```

### Test Coverage

- **Exception System**: Tests for all exception types and inheritance
- **Classification**: Tests for category, severity, and security detection
- **Context Collection**: Tests for all collectors and data sanitization
- **Logging**: Tests for all log formats and channels
- **Rendering**: Tests for all renderers and template integration
- **Integration**: End-to-end error handling tests

## Examples

### Example 1: Database Error Handling

```php
use LengthOfRope\TreeHouse\Errors\Exceptions\DatabaseException;

try {
    $user = User::find($id);
    if (!$user) {
        throw new DatabaseException(
            message: "User not found with ID: {$id}",
            userMessage: "The requested user could not be found",
            errorCode: "DB_USER_NOT_FOUND",
            context: ['user_id' => $id, 'table' => 'users']
        );
    }
} catch (DatabaseException $e) {
    // Automatically logged and rendered based on request type
    return app('error.handler')->handle($e, $request);
}
```

### Example 2: API Error Response

```php
// For API requests, automatically returns JSON
{
    "error": true,
    "message": "The requested user could not be found",
    "code": "DB_USER_NOT_FOUND",
    "type": "database",
    "severity": "medium",
    "request_id": "req_123456789",
    "timestamp": "2024-01-01T12:00:00Z"
}
```

### Example 3: Web Error Page

For web requests, renders HTML using templates:
- Development: Detailed debug information with stack traces
- Production: User-friendly error pages with suggestions

### Example 4: Security Event Logging

```php
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthorizationException;

// Security events are automatically flagged and logged
throw new AuthorizationException(
    message: "Unauthorized access attempt to admin panel",
    userMessage: "You don't have permission to access this resource",
    errorCode: "AUTH_ADMIN_ACCESS_DENIED",
    context: [
        'user_id' => $userId,
        'requested_resource' => '/admin/users',
        'user_role' => 'user'
    ]
);

// Results in:
// 1. Security flag set in classification
// 2. Enhanced logging with security markers
// 3. Security-themed error page
// 4. Additional security headers in response
```

## Best Practices

1. **Use Specific Exceptions**: Choose the most appropriate exception type
2. **Provide User Messages**: Always include user-friendly messages
3. **Add Context**: Include relevant debugging information
4. **Use Error Codes**: Implement consistent error code schemes
5. **Test Error Paths**: Write tests for error scenarios
6. **Monitor Logs**: Set up log monitoring and alerting
7. **Secure by Default**: Never expose sensitive data in error messages
8. **Document Errors**: Document expected error conditions in your API

## Security Considerations

- **Sensitive Data Redaction**: Automatically redacts passwords, tokens, and keys
- **Security Pattern Detection**: Identifies potential security attacks
- **Production Safety**: Hides technical details in production mode
- **Audit Logging**: Security events are specially marked and logged
- **Rate Limiting**: Supports rate limiting headers for abuse prevention

The Error Handling Layer provides a robust, secure, and developer-friendly foundation for managing errors throughout the TreeHouse Framework.