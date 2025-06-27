<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Http;

use LengthOfRope\TreeHouse\Support\Arr;
use LengthOfRope\TreeHouse\Support\Str;

/**
 * HTTP Request Handler
 * 
 * Handles incoming HTTP requests, providing access to request data,
 * headers, files, and other request-related information.
 * 
 * @package LengthOfRope\TreeHouse\Http
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class Request
{
    /**
     * Request method
     */
    protected string $method;

    /**
     * Request URI
     */
    protected string $uri;

    /**
     * Request headers
     */
    protected array $headers = [];

    /**
     * GET parameters
     */
    protected array $query = [];

    /**
     * POST/PUT/PATCH data
     */
    protected array $request = [];

    /**
     * Uploaded files
     */
    protected array $files = [];

    /**
     * Server variables
     */
    protected array $server = [];

    /**
     * Cookies
     */
    protected array $cookies = [];

    /**
     * Raw request body
     */
    protected ?string $content = null;

    /**
     * Parsed JSON data
     */
    protected ?array $json = null;

    /**
     * Create a new Request instance
     * 
     * @param array $query GET parameters
     * @param array $request POST/PUT/PATCH data
     * @param array $files Uploaded files
     * @param array $cookies Cookies
     * @param array $server Server variables
     * @param string|null $content Raw request body
     */
    public function __construct(
        array $query = [],
        array $request = [],
        array $files = [],
        array $cookies = [],
        array $server = [],
        ?string $content = null
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->files = $this->convertUploadedFiles($files);
        $this->cookies = $cookies;
        $this->server = $server;
        $this->content = $content;

        $this->method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $this->parseUri();
        $this->headers = $this->parseHeaders();
    }

    /**
     * Create Request from PHP globals
     * 
     * @return static
     */
    public static function createFromGlobals(): static
    {
        return new static(
            $_GET,
            $_POST,
            $_FILES,
            $_COOKIE,
            $_SERVER,
            file_get_contents('php://input') ?: null
        );
    }

    /**
     * Get the request method
     * 
     * @return string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get the request URI
     * 
     * @return string
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Get the full URL
     * 
     * @return string
     */
    public function url(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->getHost();
        $port = $this->getPort();
        
        $url = $scheme . '://' . $host;
        
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $url .= ':' . $port;
        }
        
        return $url . $this->uri;
    }

    /**
     * Get the request path (URI without query string)
     * 
     * @return string
     */
    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);
        return $path ?: '/';
    }

    /**
     * Check if request is secure (HTTPS)
     * 
     * @return bool
     */
    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on' ||
               ($this->server['SERVER_PORT'] ?? '') === '443' ||
               strtolower($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /**
     * Get the host
     * 
     * @return string
     */
    public function getHost(): string
    {
        return $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost';
    }

    /**
     * Get the port
     * 
     * @return int
     */
    public function getPort(): int
    {
        return (int) ($this->server['SERVER_PORT'] ?? 80);
    }

    /**
     * Get a header value
     * 
     * @param string $key Header name
     * @param string|null $default Default value
     * @return string|null
     */
    public function header(string $key, ?string $default = null): ?string
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get all headers
     * 
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Check if header exists
     * 
     * @param string $key Header name
     * @return bool
     */
    public function hasHeader(string $key): bool
    {
        return isset($this->headers[strtolower($key)]);
    }

    /**
     * Get input value from query or request data
     * 
     * @param string|null $key Input key
     * @param mixed $default Default value
     * @return mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = array_merge($this->query, $this->request);
        
        if ($key === null) {
            return $input;
        }
        
        return Arr::get($input, $key, $default);
    }

    /**
     * Get query parameter
     * 
     * @param string|null $key Query key
     * @param mixed $default Default value
     * @return mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        
        return Arr::get($this->query, $key, $default);
    }

    /**
     * Get request data (POST/PUT/PATCH)
     * 
     * @param string|null $key Request key
     * @param mixed $default Default value
     * @return mixed
     */
    public function request(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->request;
        }
        
        return Arr::get($this->request, $key, $default);
    }

    /**
     * Get uploaded file
     * 
     * @param string $key File key
     * @return UploadedFile|null
     */
    public function file(string $key): ?UploadedFile
    {
        return Arr::get($this->files, $key);
    }

    /**
     * Get all uploaded files
     * 
     * @return array
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * Check if file was uploaded
     * 
     * @param string $key File key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);
        return $file !== null && $file->isValid();
    }

    /**
     * Get cookie value
     * 
     * @param string $key Cookie name
     * @param string|null $default Default value
     * @return string|null
     */
    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Get all cookies
     * 
     * @return array
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * Get server variable
     * 
     * @param string $key Server key
     * @param string|null $default Default value
     * @return string|null
     */
    public function server(string $key, ?string $default = null): ?string
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Get raw request body
     * 
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Get JSON data from request body
     * 
     * @param string|null $key JSON key
     * @param mixed $default Default value
     * @return mixed
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->json === null) {
            $this->json = json_decode($this->content ?? '', true) ?? [];
        }
        
        if ($key === null) {
            return $this->json;
        }
        
        return Arr::get($this->json, $key, $default);
    }

    /**
     * Check if request expects JSON response
     * 
     * @return bool
     */
    public function expectsJson(): bool
    {
        return Str::contains($this->header('accept', ''), 'application/json') ||
               $this->isAjax();
    }

    /**
     * Check if request is AJAX
     * 
     * @return bool
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    /**
     * Check if request method matches
     * 
     * @param string $method HTTP method
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Get client IP address
     * 
     * @return string
     */
    public function ip(): string
    {
        $keys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($keys as $key) {
            if (!empty($this->server[$key])) {
                $ip = trim(explode(',', $this->server[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent
     * 
     * @return string
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Parse URI from server variables
     * 
     * @return string
     */
    protected function parseUri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        return $uri;
    }

    /**
     * Parse headers from server variables
     * 
     * @return array
     */
    protected function parseHeaders(): array
    {
        $headers = [];
        
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] = $value;
            }
        }
        
        // Add content type and length if present
        if (isset($this->server['CONTENT_TYPE'])) {
            $headers['content-type'] = $this->server['CONTENT_TYPE'];
        }
        
        if (isset($this->server['CONTENT_LENGTH'])) {
            $headers['content-length'] = $this->server['CONTENT_LENGTH'];
        }
        
        return $headers;
    }

    /**
     * Convert uploaded files array to UploadedFile objects
     * 
     * @param array $files Files array
     * @return array
     */
    protected function convertUploadedFiles(array $files): array
    {
        $converted = [];
        
        foreach ($files as $key => $file) {
            if (is_array($file) && isset($file['tmp_name'])) {
                $converted[$key] = new UploadedFile(
                    $file['tmp_name'],
                    $file['name'] ?? '',
                    $file['type'] ?? '',
                    $file['error'] ?? UPLOAD_ERR_OK,
                    $file['size'] ?? 0
                );
            }
        }
        
        return $converted;
    }
}