<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors;

use LengthOfRope\TreeHouse\Errors\Classification\ExceptionClassifier;
use LengthOfRope\TreeHouse\Errors\Context\ContextManager;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;
use LengthOfRope\TreeHouse\Errors\Logging\LogLevel;
use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use Throwable;

/**
 * Central error handler that coordinates classification, logging, and response generation
 */
class ErrorHandler
{
    private ExceptionClassifier $classifier;
    private ContextManager $contextManager;
    private ErrorLogger $logger;
    private array $config;
    private bool $debug;

    public function __construct(
        ExceptionClassifier $classifier,
        ContextManager $contextManager,
        ErrorLogger $logger,
        array $config = []
    ) {
        $this->classifier = $classifier;
        $this->contextManager = $contextManager;
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->debug = $this->config['debug'] ?? false;
    }

    /**
     * Handle an exception
     */
    public function handle(Throwable $exception, ?Request $request = null): Response
    {
        try {
            // Classify the exception
            $classification = $this->classifier->classify($exception);

            // Collect context
            $context = $this->contextManager->collect($exception);

            // Log the exception if it should be reported
            if ($classification->shouldReport) {
                $this->logException($exception, $classification, $context);
            }

            // Generate appropriate response
            return $this->generateResponse($exception, $classification, $context, $request);

        } catch (Throwable $handlerException) {
            // If the error handler itself fails, fall back to basic handling
            return $this->handleHandlerFailure($exception, $handlerException, $request);
        }
    }

    /**
     * Log an exception with classification and context
     */
    private function logException(Throwable $exception, $classification, array $context): void
    {
        $logContext = [
            'exception' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->debug ? $exception->getTraceAsString() : null
            ],
            'classification' => $classification->toArray(),
            'context' => $context
        ];

        // Add BaseException specific data
        if ($exception instanceof BaseException) {
            $logContext['exception']['error_code'] = $exception->getErrorCode();
            $logContext['exception']['severity'] = $exception->getSeverity();
            $logContext['exception']['user_message'] = $exception->getUserMessage();
            $logContext['exception']['exception_context'] = $exception->getContext();
        }

        // Log with appropriate level
        $this->logger->log($classification->logLevel, $exception->getMessage(), $logContext);

        // Log critical issues with higher priority
        if ($classification->isCritical) {
            $this->logger->critical('Critical system error detected', $logContext);
        }

        // Log security issues with special attention
        if ($classification->isSecurity) {
            $this->logger->warning('Security-related exception detected', $logContext);
        }
    }

    /**
     * Generate appropriate response based on exception and request context
     */
    private function generateResponse(
        Throwable $exception,
        $classification,
        array $context,
        ?Request $request
    ): Response {
        // Determine response format
        $format = $this->determineResponseFormat($request);
        
        // Get status code
        $statusCode = $this->getStatusCode($exception);
        
        // Generate response content
        $content = $this->generateResponseContent($exception, $classification, $context, $format);
        
        // Create response
        $response = new Response($content, $statusCode);
        
        // Set appropriate headers
        $this->setResponseHeaders($response, $format, $classification);
        
        return $response;
    }

    /**
     * Determine response format (HTML, JSON, etc.)
     */
    private function determineResponseFormat(?Request $request): string
    {
        if (!$request) {
            return 'text'; // CLI or no request context
        }

        // Check if client expects JSON
        if ($request->expectsJson()) {
            return 'json';
        }

        // Check Accept header
        $accept = $request->header('accept', '');
        if (str_contains($accept, 'application/json')) {
            return 'json';
        }

        if (str_contains($accept, 'text/html')) {
            return 'html';
        }

        // Default to HTML for web requests
        return 'html';
    }

    /**
     * Get HTTP status code from exception
     */
    private function getStatusCode(Throwable $exception): int
    {
        if ($exception instanceof BaseException) {
            return $exception->getStatusCode();
        }

        // Default status codes for common exceptions
        return match (get_class($exception)) {
            'InvalidArgumentException' => 400,
            'UnauthorizedException' => 401,
            'ForbiddenException' => 403,
            'NotFoundException' => 404,
            'MethodNotAllowedException' => 405,
            'ValidationException' => 422,
            'TooManyRequestsException' => 429,
            default => 500
        };
    }

    /**
     * Generate response content based on format
     */
    private function generateResponseContent(
        Throwable $exception,
        $classification,
        array $context,
        string $format
    ): string {
        $data = $this->prepareResponseData($exception, $classification, $context);

        return match ($format) {
            'json' => $this->generateJsonResponse($data),
            'html' => $this->generateHtmlResponse($data),
            'text' => $this->generateTextResponse($data),
            default => $this->generateTextResponse($data)
        };
    }

    /**
     * Prepare response data
     */
    private function prepareResponseData(Throwable $exception, $classification, array $context): array
    {
        $data = [
            'error' => true,
            'message' => $this->getUserMessage($exception),
            'type' => $classification->category,
            'timestamp' => time()
        ];

        // Add debug information if in debug mode
        if ($this->debug) {
            $data['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
                'classification' => $classification->toArray(),
                'context' => $context
            ];
        }

        // Add error code if available
        if ($exception instanceof BaseException && $exception->getErrorCode()) {
            $data['code'] = $exception->getErrorCode();
        }

        return $data;
    }

    /**
     * Get user-friendly message
     */
    private function getUserMessage(Throwable $exception): string
    {
        // Use user message from BaseException if available
        if ($exception instanceof BaseException && $exception->getUserMessage()) {
            return $exception->getUserMessage();
        }

        // In production, use generic messages for security
        if (!$this->debug) {
            return match (true) {
                $exception instanceof \InvalidArgumentException => 'Invalid request parameters.',
                str_contains(get_class($exception), 'Unauthorized') => 'Authentication required.',
                str_contains(get_class($exception), 'Forbidden') => 'Access denied.',
                str_contains(get_class($exception), 'NotFound') => 'The requested resource was not found.',
                default => 'An error occurred while processing your request.'
            };
        }

        // In debug mode, show actual exception message
        return $exception->getMessage();
    }

    /**
     * Generate JSON response
     */
    private function generateJsonResponse(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * Generate HTML response
     */
    private function generateHtmlResponse(array $data): string
    {
        // For now, return a simple HTML error page
        // In Phase 2, this will use proper templates
        $title = 'Error ' . ($data['code'] ?? '500');
        $message = htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8');
        
        $html = "<!DOCTYPE html>
<html>
<head>
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .error { background: #f8f8f8; border: 1px solid #ddd; padding: 20px; border-radius: 4px; }
        .debug { margin-top: 20px; background: #fff; border: 1px solid #ccc; padding: 15px; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <div class='error'>
        <h1>{$title}</h1>
        <p>{$message}</p>
    </div>";

        if (isset($data['debug'])) {
            $debug = htmlspecialchars(json_encode($data['debug'], JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
            $html .= "<div class='debug'><h2>Debug Information</h2><pre>{$debug}</pre></div>";
        }

        $html .= "</body></html>";
        
        return $html;
    }

    /**
     * Generate text response
     */
    private function generateTextResponse(array $data): string
    {
        $output = "ERROR: " . $data['message'] . "\n";
        
        if (isset($data['debug'])) {
            $output .= "\nDEBUG INFORMATION:\n";
            $output .= json_encode($data['debug'], JSON_PRETTY_PRINT) . "\n";
        }
        
        return $output;
    }

    /**
     * Set response headers
     */
    private function setResponseHeaders(Response $response, string $format, $classification): void
    {
        // Set content type
        $contentType = match ($format) {
            'json' => 'application/json',
            'html' => 'text/html; charset=utf-8',
            'text' => 'text/plain; charset=utf-8',
            default => 'text/plain; charset=utf-8'
        };
        
        $response->setHeader('Content-Type', $contentType);
        
        // Add security headers for sensitive errors
        if ($classification->isSecurity) {
            $response->setHeader('X-Content-Type-Options', 'nosniff');
            $response->setHeader('X-Frame-Options', 'DENY');
        }
        
        // Add cache control for errors
        $response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->setHeader('Pragma', 'no-cache');
        $response->setHeader('Expires', '0');
    }

    /**
     * Handle failure in the error handler itself
     */
    private function handleHandlerFailure(
        Throwable $originalException,
        Throwable $handlerException,
        ?Request $request
    ): Response {
        // Log the handler failure if possible
        try {
            $this->logger->critical('Error handler failure', [
                'original_exception' => [
                    'class' => get_class($originalException),
                    'message' => $originalException->getMessage(),
                    'file' => $originalException->getFile(),
                    'line' => $originalException->getLine()
                ],
                'handler_exception' => [
                    'class' => get_class($handlerException),
                    'message' => $handlerException->getMessage(),
                    'file' => $handlerException->getFile(),
                    'line' => $handlerException->getLine()
                ]
            ]);
        } catch (Throwable $e) {
            // If even logging fails, there's nothing more we can do
        }

        // Return a basic error response
        $format = $this->determineResponseFormat($request);
        $message = $this->debug 
            ? "Error handler failed: {$handlerException->getMessage()}" 
            : "A critical error occurred.";

        $content = match ($format) {
            'json' => json_encode(['error' => true, 'message' => $message]),
            'html' => "<html><body><h1>Critical Error</h1><p>{$message}</p></body></html>",
            default => "CRITICAL ERROR: {$message}"
        };

        return new Response($content, 500);
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'debug' => false,
            'log_exceptions' => true,
            'log_context' => true,
            'max_context_collection_time' => 2.0,
            'continue_on_collector_failure' => true
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
     * Get the classifier
     */
    public function getClassifier(): ExceptionClassifier
    {
        return $this->classifier;
    }

    /**
     * Get the context manager
     */
    public function getContextManager(): ContextManager
    {
        return $this->contextManager;
    }

    /**
     * Get the logger
     */
    public function getLogger(): ErrorLogger
    {
        return $this->logger;
    }
}