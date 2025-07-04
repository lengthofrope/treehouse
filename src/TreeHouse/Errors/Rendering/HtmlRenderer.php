<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Rendering;

use LengthOfRope\TreeHouse\Errors\Classification\ClassificationResult;
use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\View\ViewFactory;
use Throwable;

/**
 * HTML error renderer for web browser responses
 */
class HtmlRenderer implements RendererInterface
{
    private ?ViewFactory $viewFactory;
    private string $templatePath;
    private bool $fallbackToBuiltIn;

    public function __construct(?ViewFactory $viewFactory = null, string $templatePath = '', bool $fallbackToBuiltIn = true)
    {
        $this->viewFactory = $viewFactory;
        $this->templatePath = $templatePath ?: 'errors';
        $this->fallbackToBuiltIn = $fallbackToBuiltIn;
    }

    /**
     * Render an error response as HTML
     */
    public function render(
        Throwable $exception,
        ClassificationResult $classification,
        array $context,
        ?Request $request = null,
        bool $debug = false
    ): string {
        $data = $this->prepareTemplateData($exception, $classification, $context, $debug);
        
        // Select appropriate template
        $template = $this->selectTemplate($exception, $classification, $debug);
        
        return $this->renderTemplate($template, $data);
    }

    /**
     * Prepare data for template rendering
     */
    private function prepareTemplateData(
        Throwable $exception,
        ClassificationResult $classification,
        array $context,
        bool $debug
    ): array {
        $statusCode = $this->getHttpStatusCode($exception);
        
        $data = [
            'title' => $this->getErrorTitle($statusCode, $classification),
            'status_code' => $statusCode,
            'status_text' => $this->getStatusText($statusCode),
            'message' => $this->getUserMessage($exception, $debug),
            'category' => $classification->category,
            'severity' => $classification->severity,
            'is_security' => $classification->isSecurity,
            'is_critical' => $classification->isCritical,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => $this->generateRequestId($context),
            'debug' => $debug,
            // Additional data for the error layout
            'icon' => $this->getErrorIcon($statusCode),
            'heading' => $this->getErrorTitle($statusCode, $classification),
            'error_type' => $statusCode >= 500 ? 'error' : ''
        ];

        // Add debug information if in debug mode
        if ($debug) {
            $data['debug_info'] = $this->prepareDebugInfo($exception, $classification, $context);
        }

        // Add suggestions for common errors
        $data['suggestions'] = $this->getErrorSuggestions($statusCode, $classification);

        return $data;
    }

    /**
     * Select appropriate template based on error type
     */
    private function selectTemplate(Throwable $exception, ClassificationResult $classification, bool $debug): string
    {
        $statusCode = $this->getHttpStatusCode($exception);

        // Use debug template in debug mode
        if ($debug) {
            return 'debug';
        }

        // Use specific templates for common status codes
        $templateCandidates = [
            (string) $statusCode,
            $classification->category,
            $classification->severity,
            'generic'
        ];

        // Return the first template that exists
        foreach ($templateCandidates as $template) {
            if ($this->templateExists($template)) {
                return $template;
            }
        }

        // Fall back to generic template
        return 'generic';
    }

    /**
     * Render template with data
     */
    private function renderTemplate(string $template, array $data): string
    {
        // Try to use ViewFactory first
        if ($this->viewFactory) {
            try {
                $templatePath = $this->templatePath . '/' . $template;
                return $this->viewFactory->render($templatePath, $data);
            } catch (\Exception $e) {
                // Fall back to built-in templates if ViewFactory fails
                if (!$this->fallbackToBuiltIn) {
                    throw $e;
                }
            }
        }

        // Fall back to built-in templates
        return $this->renderBuiltInTemplate($template, $data);
    }

    /**
     * Check if a template exists
     */
    private function templateExists(string $template): bool
    {
        if ($this->viewFactory) {
            try {
                $templatePath = $this->templatePath . '/' . $template;
                // Try to check if template exists - this is a simple approach
                // In a real implementation, ViewFactory might have a method to check existence
                return true; // For now, assume all templates exist and let renderTemplate handle fallback
            } catch (\Exception $e) {
                return false;
            }
        }

        // Check if built-in template exists
        return in_array($template, ['generic', 'debug', '404', '500', 'security', 'critical']);
    }

    /**
     * Render built-in template with data (fallback)
     */
    private function renderBuiltInTemplate(string $template, array $data): string
    {
        $builtInTemplates = $this->getDefaultTemplates();
        $templateHtml = $builtInTemplates[$template] ?? $builtInTemplates['generic'];
        
        // Simple template rendering - replace placeholders
        $html = $templateHtml;
        
        foreach ($data as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $placeholder = '{{' . $key . '}}';
                $html = str_replace($placeholder, (string) $value, $html);
            }
        }

        // Handle debug info separately
        if (isset($data['debug_info']) && is_array($data['debug_info'])) {
            $debugHtml = $this->renderDebugInfo($data['debug_info']);
            $html = str_replace('{{debug_info}}', $debugHtml, $html);
        } else {
            $html = str_replace('{{debug_info}}', '', $html);
        }

        // Handle suggestions
        if (isset($data['suggestions']) && is_array($data['suggestions'])) {
            $suggestionsHtml = $this->renderSuggestions($data['suggestions']);
            $html = str_replace('{{suggestions}}', $suggestionsHtml, $html);
        } else {
            $html = str_replace('{{suggestions}}', '', $html);
        }

        return $html;
    }

    /**
     * Render debug information as HTML
     */
    private function renderDebugInfo(array $debugInfo): string
    {
        $html = '<div class="debug-section">';
        $html .= '<h3>Debug Information</h3>';
        
        foreach ($debugInfo as $section => $data) {
            $html .= '<div class="debug-subsection">';
            $html .= '<h4>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $section))) . '</h4>';
            
            if (is_array($data)) {
                $html .= '<pre class="debug-data">' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
            } else {
                $html .= '<p>' . htmlspecialchars((string) $data) . '</p>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render suggestions as HTML
     */
    private function renderSuggestions(array $suggestions): string
    {
        if (empty($suggestions)) {
            return '';
        }

        $html = '<div class="suggestions-section">';
        $html .= '<h3>What can you do?</h3>';
        $html .= '<ul class="suggestions-list">';
        
        foreach ($suggestions as $suggestion) {
            $html .= '<li>' . htmlspecialchars($suggestion) . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get error title
     */
    private function getErrorTitle(int $statusCode, ClassificationResult $classification): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Authentication Required',
            403 => 'Access Denied',
            404 => 'Page Not Found',
            405 => 'Method Not Allowed',
            422 => 'Invalid Data',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            default => 'Error ' . $statusCode
        };
    }

    /**
     * Get status text
     */
    private function getStatusText(int $statusCode): string
    {
        $statusTexts = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable'
        ];

        return $statusTexts[$statusCode] ?? 'Unknown Status';
    }

    /**
     * Get user-friendly message
     */
    private function getUserMessage(Throwable $exception, bool $debug): string
    {
        // In debug mode, always show actual exception message
        if ($debug) {
            return $exception->getMessage();
        }

        // Use user message from BaseException if available
        if ($exception instanceof BaseException && $exception->getUserMessage()) {
            return $exception->getUserMessage();
        }

        // In production, use generic messages for security
        return match (true) {
            str_contains(get_class($exception), 'Validation') => 'The submitted data contains errors. Please check your input and try again.',
            str_contains(get_class($exception), 'Authentication') => 'You need to log in to access this resource.',
            str_contains(get_class($exception), 'Authorization') => 'You don\'t have permission to access this resource.',
            str_contains(get_class($exception), 'NotFound') => 'The page you\'re looking for could not be found.',
            str_contains(get_class($exception), 'Database') => 'We\'re experiencing technical difficulties. Please try again later.',
            str_contains(get_class($exception), 'System') => 'A system error occurred. Our team has been notified.',
            default => 'Something went wrong. Please try again or contact support if the problem persists.'
        };
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
            str_contains(get_class($exception), 'Validation') => 422,
            str_contains(get_class($exception), 'Authentication') => 401,
            str_contains(get_class($exception), 'Authorization') => 403,
            str_contains(get_class($exception), 'NotFound') => 404,
            str_contains(get_class($exception), 'InvalidArgument') => 400,
            default => 500
        };
    }

    /**
     * Prepare debug information
     */
    private function prepareDebugInfo(
        Throwable $exception,
        ClassificationResult $classification,
        array $context
    ): array {
        $debug = [
            'exception' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ],
            'classification' => $classification->toArray()
        ];

        // Add BaseException specific data
        if ($exception instanceof BaseException) {
            $debug['exception']['error_code'] = $exception->getErrorCode();
            $debug['exception']['severity'] = $exception->getSeverity();
            $debug['exception']['context'] = $exception->getContext();
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

        // Add sanitized context
        $debug['context'] = $this->sanitizeContextForDebug($context);

        return $debug;
    }

    /**
     * Sanitize context data for debug output
     */
    private function sanitizeContextForDebug(array $context): array
    {
        // Remove sensitive data even in debug mode
        $sanitized = $context;
        
        if (isset($sanitized['request']['parameters'])) {
            $sanitized['request']['parameters'] = $this->redactSensitiveData($sanitized['request']['parameters']);
        }
        
        if (isset($sanitized['request']['headers'])) {
            $sanitized['request']['headers'] = $this->redactSensitiveData($sanitized['request']['headers']);
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
     * Get error suggestions
     */
    private function getErrorSuggestions(int $statusCode, ClassificationResult $classification): array
    {
        return match ($statusCode) {
            400 => [
                'Check that all required fields are filled out correctly',
                'Verify that the data format matches what\'s expected',
                'Try refreshing the page and submitting again'
            ],
            401 => [
                'Log in to your account',
                'Check that your credentials are correct',
                'Contact support if you\'ve forgotten your password'
            ],
            403 => [
                'Contact an administrator for access',
                'Check that you\'re logged in with the correct account',
                'Verify that you have the necessary permissions'
            ],
            404 => [
                'Check the URL for typos',
                'Use the navigation menu to find what you\'re looking for',
                'Go back to the homepage and start over'
            ],
            422 => [
                'Review the form for validation errors',
                'Correct any highlighted fields',
                'Make sure all required information is provided'
            ],
            429 => [
                'Wait a few minutes before trying again',
                'Reduce the frequency of your requests',
                'Contact support if you need higher rate limits'
            ],
            500 => [
                'Try refreshing the page',
                'Wait a few minutes and try again',
                'Contact support if the problem persists'
            ],
            503 => [
                'The service is temporarily unavailable',
                'Try again in a few minutes',
                'Check our status page for updates'
            ],
            default => [
                'Try refreshing the page',
                'Contact support if the problem continues'
            ]
        };
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
     * Get error icon based on status code
     */
    private function getErrorIcon(int $statusCode): string
    {
        return match ($statusCode) {
            404 => '🔍',
            403 => '🔒',
            401 => '🔐',
            422 => '📝',
            429 => '⏱️',
            500, 502, 503 => '⚠️',
            default => '❓'
        };
    }

    /**
     * Get default HTML templates
     */
    private function getDefaultTemplates(): array
    {
        return [
            'generic' => $this->getGenericTemplate(),
            'debug' => $this->getDebugTemplate(),
            404 => $this->get404Template(),
            500 => $this->get500Template(),
            'security' => $this->getSecurityTemplate(),
            'critical' => $this->getCriticalTemplate()
        ];
    }

    /**
     * Get generic error template
     */
    private function getGenericTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{title}}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px; background: #f8f9fa; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #dc3545; color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 2.5em; font-weight: 300; }
        .header p { margin: 10px 0 0; opacity: 0.9; }
        .content { padding: 30px; }
        .message { font-size: 1.1em; line-height: 1.6; margin-bottom: 30px; }
        .meta { background: #f8f9fa; padding: 20px; border-radius: 4px; font-size: 0.9em; color: #666; }
        .meta strong { color: #333; }
        {{suggestions}}
        .suggestions-section { margin-top: 30px; }
        .suggestions-section h3 { color: #28a745; margin-bottom: 15px; }
        .suggestions-list { margin: 0; padding-left: 20px; }
        .suggestions-list li { margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{status_code}}</h1>
            <p>{{status_text}}</p>
        </div>
        <div class="content">
            <div class="message">{{message}}</div>
            <div class="meta">
                <strong>Request ID:</strong> {{request_id}}<br>
                <strong>Time:</strong> {{timestamp}}
            </div>
            {{suggestions}}
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get debug error template
     */
    private function getDebugTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{title}} - Debug Mode</title>
    <style>
        body { font-family: "Monaco", "Menlo", "Ubuntu Mono", monospace; margin: 0; padding: 20px; background: #1e1e1e; color: #d4d4d4; font-size: 14px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: #dc3545; color: white; padding: 20px; border-radius: 4px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 1.8em; }
        .header p { margin: 10px 0 0; opacity: 0.9; }
        .section { background: #252526; border: 1px solid #3e3e42; border-radius: 4px; margin-bottom: 20px; }
        .section-header { background: #2d2d30; padding: 15px; border-bottom: 1px solid #3e3e42; font-weight: bold; }
        .section-content { padding: 20px; }
        .debug-data { background: #1e1e1e; border: 1px solid #3e3e42; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; }
        .meta { color: #9cdcfe; }
        .string { color: #ce9178; }
        .number { color: #b5cea8; }
        .boolean { color: #569cd6; }
        .null { color: #569cd6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{status_code}} - {{title}}</h1>
            <p>{{message}}</p>
        </div>
        <div class="section">
            <div class="section-header">Error Details</div>
            <div class="section-content">
                <strong>Category:</strong> {{category}}<br>
                <strong>Severity:</strong> {{severity}}<br>
                <strong>Request ID:</strong> {{request_id}}<br>
                <strong>Time:</strong> {{timestamp}}
            </div>
        </div>
        {{debug_info}}
    </div>
</body>
</html>';
    }

    /**
     * Get 404 error template
     */
    private function get404Template(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px; background: #f8f9fa; color: #333; text-align: center; }
        .container { max-width: 500px; margin: 0 auto; }
        .icon { font-size: 6em; margin-bottom: 20px; }
        h1 { font-size: 2.5em; margin-bottom: 10px; color: #6c757d; }
        p { font-size: 1.1em; line-height: 1.6; margin-bottom: 30px; color: #6c757d; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; }
        .btn:hover { background: #0056b3; }
        {{suggestions}}
        .suggestions-section { margin-top: 40px; text-align: left; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔍</div>
        <h1>Page Not Found</h1>
        <p>{{message}}</p>
        <a href="/" class="btn">Go Home</a>
        {{suggestions}}
    </div>
</body>
</html>';
    }

    /**
     * Get 500 error template
     */
    private function get500Template(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px; background: #f8f9fa; color: #333; text-align: center; }
        .container { max-width: 500px; margin: 0 auto; }
        .icon { font-size: 6em; margin-bottom: 20px; }
        h1 { font-size: 2.5em; margin-bottom: 10px; color: #dc3545; }
        p { font-size: 1.1em; line-height: 1.6; margin-bottom: 30px; color: #6c757d; }
        .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; }
        .btn:hover { background: #1e7e34; }
        .meta { background: #fff; padding: 20px; border-radius: 4px; margin-top: 30px; text-align: left; font-size: 0.9em; color: #666; }
        {{suggestions}}
        .suggestions-section { margin-top: 30px; text-align: left; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⚠️</div>
        <h1>Server Error</h1>
        <p>{{message}}</p>
        <a href="javascript:location.reload()" class="btn">Try Again</a>
        <div class="meta">
            <strong>Request ID:</strong> {{request_id}}<br>
            <strong>Time:</strong> {{timestamp}}
        </div>
        {{suggestions}}
    </div>
</body>
</html>';
    }

    /**
     * Get security error template
     */
    private function getSecurityTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alert</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px; background: #fff3cd; color: #856404; }
        .container { max-width: 600px; margin: 0 auto; background: white; border: 2px solid #ffeaa7; border-radius: 8px; padding: 30px; }
        .icon { font-size: 4em; text-align: center; margin-bottom: 20px; }
        h1 { color: #856404; text-align: center; margin-bottom: 20px; }
        p { line-height: 1.6; margin-bottom: 20px; }
        .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .meta { background: #f8f9fa; padding: 15px; border-radius: 4px; font-size: 0.9em; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔒</div>
        <h1>Security Alert</h1>
        <p>{{message}}</p>
        <div class="alert">
            <strong>Notice:</strong> This security event has been logged and will be reviewed by our security team.
        </div>
        <div class="meta">
            <strong>Request ID:</strong> {{request_id}}<br>
            <strong>Time:</strong> {{timestamp}}
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get critical error template
     */
    private function getCriticalTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Critical System Error</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px; background: #f8d7da; color: #721c24; }
        .container { max-width: 600px; margin: 0 auto; background: white; border: 2px solid #f5c6cb; border-radius: 8px; padding: 30px; }
        .icon { font-size: 4em; text-align: center; margin-bottom: 20px; }
        h1 { color: #721c24; text-align: center; margin-bottom: 20px; }
        p { line-height: 1.6; margin-bottom: 20px; }
        .alert { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .meta { background: #f8f9fa; padding: 15px; border-radius: 4px; font-size: 0.9em; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚨</div>
        <h1>Critical System Error</h1>
        <p>{{message}}</p>
        <div class="alert">
            <strong>System Alert:</strong> A critical error has occurred and our technical team has been automatically notified.
        </div>
        <div class="meta">
            <strong>Request ID:</strong> {{request_id}}<br>
            <strong>Time:</strong> {{timestamp}}
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Check if this renderer can handle the given request
     */
    public function canRender(?Request $request): bool
    {
        if (!$request) {
            return false;
        }

        // Check Accept header for HTML
        $accept = $request->header('accept', '');
        
        // If no accept header or it contains HTML, use HTML renderer
        if (empty($accept) || str_contains($accept, 'text/html') || str_contains($accept, '*/*')) {
            return true;
        }
        
        // Don't handle explicit JSON requests
        if (str_contains($accept, 'application/json')) {
            return false;
        }
        
        // Default to HTML for web requests
        return true;
    }

    /**
     * Get the content type for this renderer
     */
    public function getContentType(): string
    {
        return 'text/html; charset=utf-8';
    }

    /**
     * Get the priority of this renderer
     */
    public function getPriority(): int
    {
        return 70; // High priority for HTML web responses
    }

    /**
     * Get the name/identifier of this renderer
     */
    public function getName(): string
    {
        return 'html';
    }

    /**
     * Set custom template (deprecated - use ViewFactory templates instead)
     */
    public function setTemplate(string $key, string $template): void
    {
        // This method is deprecated when using ViewFactory
        // Templates should be managed through the ViewFactory system
        throw new \RuntimeException('setTemplate is deprecated when using ViewFactory. Use template files instead.');
    }

    /**
     * Get template (deprecated - use ViewFactory templates instead)
     */
    public function getTemplate(string $key): ?string
    {
        // This method is deprecated when using ViewFactory
        // Templates should be managed through the ViewFactory system
        throw new \RuntimeException('getTemplate is deprecated when using ViewFactory. Use template files instead.');
    }
}