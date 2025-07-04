<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Rendering;

use LengthOfRope\TreeHouse\Errors\Classification\ClassificationResult;
use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;
use LengthOfRope\TreeHouse\Http\Request;
use Throwable;

/**
 * JSON error renderer for API responses
 */
class JsonRenderer implements RendererInterface
{
    /**
     * Render an error response as JSON
     */
    public function render(
        Throwable $exception,
        ClassificationResult $classification,
        array $context,
        ?Request $request = null,
        bool $debug = false
    ): string {
        $data = $this->prepareErrorData($exception, $classification, $context, $debug);
        
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Prepare error data for JSON response
     */
    private function prepareErrorData(
        Throwable $exception,
        ClassificationResult $classification,
        array $context,
        bool $debug
    ): array {
        $data = [
            'error' => true,
            'type' => $classification->category,
            'severity' => $classification->severity,
            'message' => $this->getUserMessage($exception, $debug),
            'timestamp' => date('c'), // ISO 8601 format
            'request_id' => $this->generateRequestId($context)
        ];

        // Add error code if available
        if ($exception instanceof BaseException && $exception->getErrorCode()) {
            $data['code'] = $exception->getErrorCode();
        }

        // Add HTTP status code
        $data['status'] = $this->getHttpStatusCode($exception);

        // Add validation errors if this is a validation exception
        if ($this->isValidationException($exception)) {
            $data['errors'] = $this->getValidationErrors($exception);
        }

        // Add security information for security-related errors
        if ($classification->isSecurity && !$debug) {
            $data['security_notice'] = 'This request has been logged for security monitoring.';
        }

        // Add debug information if in debug mode
        if ($debug) {
            $data['debug'] = $this->prepareDebugData($exception, $classification, $context);
        }

        // Add metadata
        $data['meta'] = [
            'classification' => [
                'category' => $classification->category,
                'severity' => $classification->severity,
                'is_security' => $classification->isSecurity,
                'is_critical' => $classification->isCritical,
                'tags' => $classification->tags
            ],
            'context_collected' => !empty($context),
            'debug_mode' => $debug
        ];

        return $data;
    }

    /**
     * Get user-friendly message
     */
    private function getUserMessage(Throwable $exception, bool $debug): string
    {
        // Use user message from BaseException if available
        if ($exception instanceof BaseException && $exception->getUserMessage()) {
            return $exception->getUserMessage();
        }

        // In production, use generic messages for security
        if (!$debug) {
            return match (true) {
                $this->isValidationException($exception) => 'The given data was invalid.',
                str_contains(get_class($exception), 'Authentication') => 'Authentication required.',
                str_contains(get_class($exception), 'Authorization') => 'Access denied.',
                str_contains(get_class($exception), 'NotFound') => 'The requested resource was not found.',
                str_contains(get_class($exception), 'Database') => 'A database error occurred.',
                str_contains(get_class($exception), 'System') => 'A system error occurred.',
                default => 'An error occurred while processing your request.'
            };
        }

        // In debug mode, show actual exception message
        return $exception->getMessage();
    }

    /**
     * Get HTTP status code from exception
     */
    private function getHttpStatusCode(Throwable $exception): int
    {
        if ($exception instanceof BaseException) {
            return $exception->getStatusCode();
        }

        // Default status codes for common exceptions
        return match (true) {
            $this->isValidationException($exception) => 422,
            str_contains(get_class($exception), 'Authentication') => 401,
            str_contains(get_class($exception), 'Authorization') => 403,
            str_contains(get_class($exception), 'NotFound') => 404,
            str_contains(get_class($exception), 'InvalidArgument') => 400,
            default => 500
        };
    }

    /**
     * Check if exception is a validation exception
     */
    private function isValidationException(Throwable $exception): bool
    {
        return str_contains(get_class($exception), 'Validation') ||
               (method_exists($exception, 'getErrors') && is_callable([$exception, 'getErrors']));
    }

    /**
     * Get validation errors from validation exception
     */
    private function getValidationErrors(Throwable $exception): array
    {
        if (method_exists($exception, 'getErrors') && is_callable([$exception, 'getErrors'])) {
            /** @var mixed $errors */
            $errors = call_user_func([$exception, 'getErrors']);
            
            // Ensure errors are in the expected format
            if (is_array($errors)) {
                return $errors;
            }
        }

        return [];
    }

    /**
     * Prepare debug data
     */
    private function prepareDebugData(
        Throwable $exception,
        ClassificationResult $classification,
        array $context
    ): array {
        $debug = [
            'exception' => [
                'class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->formatStackTrace($exception->getTrace())
            ],
            'classification' => $classification->toArray(),
            'previous' => null
        ];

        // Add BaseException specific data
        if ($exception instanceof BaseException) {
            $debug['exception']['error_code'] = $exception->getErrorCode();
            $debug['exception']['severity'] = $exception->getSeverity();
            $debug['exception']['context'] = $exception->getContext();
            $debug['exception']['reportable'] = $exception->shouldReport();
        }

        // Add previous exception if available
        if ($exception->getPrevious()) {
            $debug['previous'] = [
                'class' => get_class($exception->getPrevious()),
                'message' => $exception->getPrevious()->getMessage(),
                'file' => $exception->getPrevious()->getFile(),
                'line' => $exception->getPrevious()->getLine()
            ];
        }

        // Add context data (sanitized)
        $debug['context'] = $this->sanitizeContextForDebug($context);

        return $debug;
    }

    /**
     * Format stack trace for JSON output
     */
    private function formatStackTrace(array $trace): array
    {
        $formatted = [];
        
        foreach ($trace as $index => $frame) {
            $formatted[] = [
                'file' => $frame['file'] ?? '[internal]',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '',
                'class' => $frame['class'] ?? '',
                'type' => $frame['type'] ?? '',
                'args' => isset($frame['args']) ? $this->formatTraceArgs($frame['args']) : []
            ];
        }
        
        return $formatted;
    }

    /**
     * Format trace arguments for JSON output
     */
    private function formatTraceArgs(array $args): array
    {
        $formatted = [];
        
        foreach ($args as $arg) {
            if (is_object($arg)) {
                $formatted[] = get_class($arg) . ' Object';
            } elseif (is_array($arg)) {
                $formatted[] = 'Array(' . count($arg) . ')';
            } elseif (is_string($arg)) {
                $formatted[] = strlen($arg) > 100 ? substr($arg, 0, 100) . '...' : $arg;
            } else {
                $formatted[] = $arg;
            }
        }
        
        return $formatted;
    }

    /**
     * Sanitize context data for debug output
     */
    private function sanitizeContextForDebug(array $context): array
    {
        // Remove sensitive data even in debug mode
        $sanitized = $context;
        
        // Remove or redact sensitive context data
        if (isset($sanitized['request']['parameters'])) {
            $sanitized['request']['parameters'] = $this->redactSensitiveData($sanitized['request']['parameters']);
        }
        
        if (isset($sanitized['request']['headers'])) {
            $sanitized['request']['headers'] = $this->redactSensitiveData($sanitized['request']['headers']);
        }
        
        if (isset($sanitized['environment']['config'])) {
            $sanitized['environment']['config'] = $this->redactSensitiveData($sanitized['environment']['config']);
        }
        
        return $sanitized;
    }

    /**
     * Redact sensitive data from arrays
     */
    private function redactSensitiveData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization', 'cookie'];
        
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($lowerKey, $sensitiveKey)) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }
            
            if (is_array($value)) {
                $data[$key] = $this->redactSensitiveData($value);
            }
        }
        
        return $data;
    }

    /**
     * Generate a request ID from context
     */
    private function generateRequestId(array $context): string
    {
        // Try to get existing request ID from context
        if (isset($context['request']['headers']['x-request-id'])) {
            return $context['request']['headers']['x-request-id'];
        }
        
        // Generate a new request ID
        return uniqid('req_', true);
    }

    /**
     * Check if this renderer can handle the given request
     */
    public function canRender(?Request $request): bool
    {
        if (!$request) {
            return false;
        }

        // Check if client expects JSON
        if ($request->expectsJson()) {
            return true;
        }

        // Check Accept header
        $accept = $request->header('accept', '');
        return str_contains($accept, 'application/json');
    }

    /**
     * Get the content type for this renderer
     */
    public function getContentType(): string
    {
        return 'application/json';
    }

    /**
     * Get the priority of this renderer
     */
    public function getPriority(): int
    {
        return 80; // High priority for JSON API responses
    }

    /**
     * Get the name/identifier of this renderer
     */
    public function getName(): string
    {
        return 'json';
    }
}