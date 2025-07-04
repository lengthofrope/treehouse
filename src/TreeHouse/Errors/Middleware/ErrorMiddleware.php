<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Middleware;

use LengthOfRope\TreeHouse\Errors\ErrorHandler;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareInterface;
use Throwable;

/**
 * Middleware for handling errors in HTTP requests
 */
class ErrorMiddleware implements MiddlewareInterface
{
    private ErrorHandler $errorHandler;
    private bool $debug;
    private array $config;

    public function __construct(ErrorHandler $errorHandler, bool $debug = false, array $config = [])
    {
        $this->errorHandler = $errorHandler;
        $this->debug = $debug;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Handle the request and catch any errors
     */
    public function handle(Request $request, callable $next): Response
    {
        try {
            // Execute the next middleware/controller
            $response = $next($request);
            
            // Check if response is valid
            if (!$response instanceof Response) {
                throw new \RuntimeException('Controller must return a Response instance');
            }
            
            return $response;
            
        } catch (Throwable $exception) {
            return $this->handleException($exception, $request);
        }
    }

    /**
     * Handle an exception and generate appropriate response
     */
    private function handleException(Throwable $exception, Request $request): Response
    {
        try {
            // Use the error handler to process the exception
            return $this->errorHandler->handle($exception, $request);
            
        } catch (Throwable $handlerException) {
            // If the error handler itself fails, create a basic response
            return $this->createFallbackResponse($handlerException, $request);
        }
    }

    /**
     * Create a fallback response when the error handler fails
     */
    private function createFallbackResponse(Throwable $exception, Request $request): Response
    {
        $isJsonRequest = $this->isJsonRequest($request);
        
        if ($isJsonRequest) {
            $content = json_encode([
                'error' => true,
                'message' => $this->debug ? $exception->getMessage() : 'Internal Server Error',
                'type' => 'error_handler_failure'
            ]);
            $contentType = 'application/json; charset=utf-8';
        } else {
            $message = $this->debug ? $exception->getMessage() : 'Internal Server Error';
            $content = $this->createBasicHtmlError($message);
            $contentType = 'text/html; charset=utf-8';
        }

        $response = new Response($content, 500);
        $response->setHeader('Content-Type', $contentType);
        $response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        
        return $response;
    }

    /**
     * Create a basic HTML error page
     */
    private function createBasicHtmlError(string $message): string
    {
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 40px 20px;
            text-align: center;
        }
        .error-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #dc3545;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>🚨 Critical Error</h1>
        <p>The error handling system encountered a problem.</p>
        <div class="error-message">{$escapedMessage}</div>
        <p><small>Please contact the system administrator.</small></p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Check if the request expects JSON response
     */
    private function isJsonRequest(Request $request): bool
    {
        $accept = $request->header('Accept', '');
        $contentType = $request->header('Content-Type', '');
        
        return str_contains($accept, 'application/json') ||
               str_contains($accept, 'application/vnd.api+json') ||
               str_contains($contentType, 'application/json') ||
               $this->isAjaxRequest($request);
    }

    /**
     * Check if the request is an AJAX request
     */
    private function isAjaxRequest(Request $request): bool
    {
        return $request->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'log_errors' => true,
            'display_errors' => false,
            'error_reporting' => E_ALL,
            'max_execution_time' => 30,
            'memory_limit' => '128M',
            'report_all_exceptions' => true,
            'sensitive_headers' => [
                'authorization',
                'cookie',
                'x-api-key',
                'x-auth-token'
            ]
        ];
    }

    /**
     * Set debug mode
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Get debug mode
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set configuration option
     */
    public function setConfig(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Get configuration option
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get all configuration
     */
    public function getAllConfig(): array
    {
        return $this->config;
    }

    /**
     * Register PHP error handlers
     */
    public function registerPhpErrorHandlers(): void
    {
        // Set error reporting level
        error_reporting($this->config['error_reporting']);
        
        // Set display errors
        ini_set('display_errors', $this->config['display_errors'] ? '1' : '0');
        
        // Set memory limit
        if (isset($this->config['memory_limit'])) {
            ini_set('memory_limit', $this->config['memory_limit']);
        }
        
        // Set max execution time
        if (isset($this->config['max_execution_time'])) {
            set_time_limit($this->config['max_execution_time']);
        }

        // Register error handler for PHP errors
        set_error_handler([$this, 'handlePhpError']);
        
        // Register exception handler for uncaught exceptions
        set_exception_handler([$this, 'handleUncaughtException']);
        
        // Register shutdown handler for fatal errors
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle PHP errors (warnings, notices, etc.)
     */
    public function handlePhpError(int $severity, string $message, string $file, int $line): bool
    {
        // Check if error should be reported
        if (!(error_reporting() & $severity)) {
            return false;
        }

        // Convert PHP error to exception
        $exception = new \ErrorException($message, 0, $severity, $file, $line);
        
        // Log the error
        if ($this->config['log_errors']) {
            // Use the logger directly since logException is private
            $logger = $this->errorHandler->getLogger();
            $logger->error($exception->getMessage(), [
                'exception' => [
                    'class' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'severity' => $severity
                ]
            ]);
        }

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleUncaughtException(Throwable $exception): void
    {
        try {
            // For uncaught exceptions, we don't have a proper Request object
            // So we'll handle this directly without going through handleException
            $response = $this->errorHandler->handle($exception, null);
            
            // Send the response
            http_response_code($response->getStatusCode());
            
            foreach ($response->getHeaders() as $name => $value) {
                header("{$name}: {$value}");
            }
            
            echo $response->getContent();
            
        } catch (Throwable $e) {
            // Last resort - basic error output
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo $this->debug ? $e->getMessage() : 'Internal Server Error';
        }
    }

    /**
     * Handle shutdown errors (fatal errors)
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            
            $this->handleUncaughtException($exception);
        }
    }

    /**
     * Create a basic request object for error handling
     */
    private function createBasicRequest()
    {
        // Create a simple object that mimics Request interface for error handling
        return new class {
            public function header(string $key, $default = null)
            {
                return $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] ?? $default;
            }
            
            public function method(): string
            {
                return $_SERVER['REQUEST_METHOD'] ?? 'GET';
            }
            
            public function uri(): string
            {
                return $_SERVER['REQUEST_URI'] ?? '/';
            }
            
            public function url(): string
            {
                $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $uri = $_SERVER['REQUEST_URI'] ?? '/';
                return "{$scheme}://{$host}{$uri}";
            }
            
            public function expectsJson(): bool
            {
                $accept = $this->header('Accept', '');
                return str_contains($accept, 'application/json');
            }
        };
    }

    /**
     * Restore previous error handlers
     */
    public function restoreErrorHandlers(): void
    {
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * Get middleware statistics
     */
    public function getStats(): array
    {
        return [
            'debug_mode' => $this->debug,
            'config' => $this->config,
            'error_handler_class' => get_class($this->errorHandler)
        ];
    }
}