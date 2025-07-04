<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Context;

use LengthOfRope\TreeHouse\Http\Request;
use Throwable;

/**
 * Collects HTTP request context information
 */
class RequestCollector implements ContextCollectorInterface
{
    private ?Request $request = null;
    private array $sensitiveHeaders = [
        'authorization',
        'cookie',
        'x-api-key',
        'x-auth-token',
        'x-csrf-token'
    ];

    private array $sensitiveParams = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'key',
        'api_key',
        'csrf_token'
    ];

    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * Collect request context data
     */
    public function collect(Throwable $exception): array
    {
        if (!$this->request) {
            return $this->collectFromGlobals();
        }

        return [
            'request' => [
                'method' => $this->request->method(),
                'uri' => $this->request->uri(),
                'url' => $this->request->url(),
                'query_string' => http_build_query($this->request->query()),
                'headers' => $this->sanitizeHeaders($this->request->headers()),
                'parameters' => $this->sanitizeParameters($this->request->input()),
                'files' => $this->collectFileInfo($this->request->files()),
                'ip' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
                'referer' => $this->request->header('referer'),
                'content_type' => $this->request->header('content-type'),
                'content_length' => $this->request->header('content-length'),
                'is_ajax' => $this->request->isAjax(),
                'is_secure' => $this->request->isSecure(),
                'timestamp' => time()
            ]
        ];
    }

    /**
     * Collect request data from PHP globals when Request object is not available
     */
    private function collectFromGlobals(): array
    {
        return [
            'request' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'url' => $this->buildUrlFromGlobals(),
                'query_string' => $_SERVER['QUERY_STRING'] ?? '',
                'headers' => $this->sanitizeHeaders($this->getHeadersFromGlobals()),
                'parameters' => $this->sanitizeParameters(array_merge($_GET, $_POST)),
                'files' => $this->collectFileInfoFromGlobals(),
                'ip' => $this->getClientIpFromGlobals(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? '',
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
                'content_length' => $_SERVER['CONTENT_LENGTH'] ?? '',
                'is_ajax' => $this->isAjaxFromGlobals(),
                'is_secure' => $this->isSecureFromGlobals(),
                'timestamp' => time()
            ]
        ];
    }

    /**
     * Build URL from globals
     */
    private function buildUrlFromGlobals(): string
    {
        $scheme = $this->isSecureFromGlobals() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $scheme . '://' . $host . $uri;
    }

    /**
     * Get headers from globals
     */
    private function getHeadersFromGlobals(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }
        
        // Add special headers
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }
        
        return $headers;
    }

    /**
     * Get client IP from globals
     */
    private function getClientIpFromGlobals(): string
    {
        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return 'unknown';
    }

    /**
     * Check if request is AJAX from globals
     */
    private function isAjaxFromGlobals(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if request is secure from globals
     */
    private function isSecureFromGlobals(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    }

    /**
     * Collect file information from globals
     */
    private function collectFileInfoFromGlobals(): array
    {
        if (empty($_FILES)) {
            return [];
        }
        
        $files = [];
        foreach ($_FILES as $key => $file) {
            if (is_array($file['name'])) {
                // Multiple files
                $files[$key] = [];
                foreach ($file['name'] as $index => $name) {
                    $files[$key][] = [
                        'name' => $name,
                        'size' => $file['size'][$index] ?? 0,
                        'type' => $file['type'][$index] ?? '',
                        'error' => $file['error'][$index] ?? 0
                    ];
                }
            } else {
                // Single file
                $files[$key] = [
                    'name' => $file['name'],
                    'size' => $file['size'] ?? 0,
                    'type' => $file['type'] ?? '',
                    'error' => $file['error'] ?? 0
                ];
            }
        }
        
        return $files;
    }

    /**
     * Sanitize headers to remove sensitive information
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        
        foreach ($headers as $name => $value) {
            $lowerName = strtolower($name);
            
            if (in_array($lowerName, $this->sensitiveHeaders, true)) {
                $sanitized[$name] = '[REDACTED]';
            } else {
                $sanitized[$name] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize parameters to remove sensitive information
     */
    private function sanitizeParameters(array $parameters): array
    {
        $sanitized = [];
        
        foreach ($parameters as $key => $value) {
            $lowerKey = strtolower($key);
            
            if (in_array($lowerKey, $this->sensitiveParams, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeParameters($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Collect file information
     */
    private function collectFileInfo(array $files): array
    {
        $fileInfo = [];
        
        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $fileInfo[$key] = [];
                foreach ($file as $index => $singleFile) {
                    $fileInfo[$key][] = [
                        'name' => $singleFile['name'] ?? '',
                        'size' => $singleFile['size'] ?? 0,
                        'type' => $singleFile['type'] ?? '',
                        'error' => $singleFile['error'] ?? 0
                    ];
                }
            } else {
                $fileInfo[$key] = [
                    'name' => $file['name'] ?? '',
                    'size' => $file['size'] ?? 0,
                    'type' => $file['type'] ?? '',
                    'error' => $file['error'] ?? 0
                ];
            }
        }
        
        return $fileInfo;
    }

    /**
     * Get collector priority
     */
    public function getPriority(): int
    {
        return 80; // High priority for request context
    }

    /**
     * Check if this collector should run
     */
    public function shouldCollect(Throwable $exception): bool
    {
        // Always collect request context if available
        return $this->request !== null || !empty($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Get collector name
     */
    public function getName(): string
    {
        return 'request';
    }

    /**
     * Add sensitive header pattern
     */
    public function addSensitiveHeader(string $header): void
    {
        $this->sensitiveHeaders[] = strtolower($header);
    }

    /**
     * Add sensitive parameter pattern
     */
    public function addSensitiveParameter(string $parameter): void
    {
        $this->sensitiveParams[] = strtolower($parameter);
    }
}