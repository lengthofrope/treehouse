<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Http;

/**
 * HTTP Response Builder
 * 
 * Builds and sends HTTP responses with proper headers,
 * status codes, and content handling.
 * 
 * @package LengthOfRope\TreeHouse\Http
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class Response
{
    /**
     * Response content
     */
    protected string $content = '';

    /**
     * HTTP status code
     */
    protected int $statusCode = 200;

    /**
     * Response headers
     */
    protected array $headers = [];

    /**
     * HTTP status texts
     */
    protected static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /**
     * Create a new Response instance
     * 
     * @param string $content Response content
     * @param int $statusCode HTTP status code
     * @param array $headers Response headers
     */
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Create a successful response
     * 
     * @param string $content Response content
     * @param array $headers Additional headers
     * @return static
     */
    public static function ok(string $content = '', array $headers = []): static
    {
        return new static($content, 200, $headers);
    }

    /**
     * Create a created response
     * 
     * @param string $content Response content
     * @param array $headers Additional headers
     * @return static
     */
    public static function created(string $content = '', array $headers = []): static
    {
        return new static($content, 201, $headers);
    }

    /**
     * Create a no content response
     * 
     * @param array $headers Additional headers
     * @return static
     */
    public static function noContent(array $headers = []): static
    {
        return new static('', 204, $headers);
    }

    /**
     * Create a redirect response
     * 
     * @param string $url Redirect URL
     * @param int $statusCode Redirect status code
     * @param array $headers Additional headers
     * @return static
     */
    public static function redirect(string $url, int $statusCode = 302, array $headers = []): static
    {
        $headers['Location'] = $url;
        return new static('', $statusCode, $headers);
    }

    /**
     * Create a JSON response
     * 
     * @param mixed $data Data to encode as JSON
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     * @param int $options JSON encode options
     * @return static
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = [], int $options = 0): static
    {
        $headers['Content-Type'] = 'application/json';
        $content = json_encode($data, $options | JSON_THROW_ON_ERROR);
        
        return new static($content, $statusCode, $headers);
    }

    /**
     * Create an HTML response
     * 
     * @param string $html HTML content
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     * @return static
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): static
    {
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        return new static($html, $statusCode, $headers);
    }

    /**
     * Create a plain text response
     * 
     * @param string $text Text content
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     * @return static
     */
    public static function text(string $text, int $statusCode = 200, array $headers = []): static
    {
        $headers['Content-Type'] = 'text/plain; charset=utf-8';
        return new static($text, $statusCode, $headers);
    }

    /**
     * Create a file download response
     * 
     * @param string $filePath Path to file
     * @param string|null $filename Download filename
     * @param array $headers Additional headers
     * @return static
     */
    public static function download(string $filePath, ?string $filename = null, array $headers = []): static
    {
        if (!file_exists($filePath)) {
            return static::notFound('File not found');
        }

        $filename = $filename ?: basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        
        $headers = array_merge([
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) filesize($filePath),
        ], $headers);

        $content = file_get_contents($filePath);
        return new static($content ?: '', 200, $headers);
    }

    /**
     * Create a file inline response
     * 
     * @param string $filePath Path to file
     * @param string|null $filename Display filename
     * @param array $headers Additional headers
     * @return static
     */
    public static function file(string $filePath, ?string $filename = null, array $headers = []): static
    {
        if (!file_exists($filePath)) {
            return static::notFound('File not found');
        }

        $filename = $filename ?: basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        
        $headers = array_merge([
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length' => (string) filesize($filePath),
        ], $headers);

        $content = file_get_contents($filePath);
        return new static($content ?: '', 200, $headers);
    }

    /**
     * Create a bad request response
     * 
     * @param string $message Error message
     * @param array $headers Additional headers
     * @return static
     */
    public static function badRequest(string $message = 'Bad Request', array $headers = []): static
    {
        return new static($message, 400, $headers);
    }

    /**
     * Create an unauthorized response
     * 
     * @param string $message Error message
     * @param array $headers Additional headers
     * @return static
     */
    public static function unauthorized(string $message = 'Unauthorized', array $headers = []): static
    {
        return new static($message, 401, $headers);
    }

    /**
     * Create a forbidden response
     * 
     * @param string $message Error message
     * @param array $headers Additional headers
     * @return static
     */
    public static function forbidden(string $message = 'Forbidden', array $headers = []): static
    {
        return new static($message, 403, $headers);
    }

    /**
     * Create a not found response
     * 
     * @param string $message Error message
     * @param array $headers Additional headers
     * @return static
     */
    public static function notFound(string $message = 'Not Found', array $headers = []): static
    {
        return new static($message, 404, $headers);
    }

    /**
     * Create a method not allowed response
     * 
     * @param string $message Error message
     * @param array $headers Additional headers
     * @return static
     */
    public static function methodNotAllowed(string $message = 'Method Not Allowed', array $headers = []): static
    {
        return new static($message, 405, $headers);
    }

    /**
     * Create an unprocessable entity response
     * 
     * @param string $message Error message
     * @param array $headers Additional headers
     * @return static
     */
    public static function unprocessableEntity(string $message = 'Unprocessable Entity', array $headers = []): static
    {
        return new static($message, 422, $headers);
    }

    /**
     * Create an internal server error response
     * 
     * @param string $message Error message
     * @param array $headers Additional headers
     * @return static
     */
    public static function serverError(string $message = 'Internal Server Error', array $headers = []): static
    {
        return new static($message, 500, $headers);
    }

    /**
     * Get the response content
     * 
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set the response content
     * 
     * @param string $content Response content
     * @return static
     */
    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get the status code
     * 
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set the status code
     * 
     * @param int $statusCode HTTP status code
     * @return static
     */
    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Get a header value
     * 
     * @param string $name Header name
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get all headers
     * 
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set a header
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return static
     */
    public function setHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add multiple headers
     * 
     * @param array $headers Headers array
     * @return static
     */
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Remove a header
     * 
     * @param string $name Header name
     * @return static
     */
    public function removeHeader(string $name): static
    {
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * Check if header exists
     * 
     * @param string $name Header name
     * @return bool
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * Set a cookie
     * 
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expires Expiration time
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @param bool $secure Secure flag
     * @param bool $httpOnly HTTP only flag
     * @param string $sameSite SameSite attribute
     * @return static
     */
    public function withCookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        $cookie = $name . '=' . urlencode($value);
        
        if ($expires > 0) {
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s T', $expires);
            $cookie .= '; Max-Age=' . ($expires - time());
        }
        
        if ($path) {
            $cookie .= '; Path=' . $path;
        }
        
        if ($domain) {
            $cookie .= '; Domain=' . $domain;
        }
        
        if ($secure) {
            $cookie .= '; Secure';
        }
        
        if ($httpOnly) {
            $cookie .= '; HttpOnly';
        }
        
        if ($sameSite) {
            $cookie .= '; SameSite=' . $sameSite;
        }
        
        $this->headers['Set-Cookie'] = $cookie;
        return $this;
    }

    /**
     * Get the status text for the current status code
     * 
     * @return string
     */
    public function getStatusText(): string
    {
        return self::$statusTexts[$this->statusCode] ?? 'Unknown Status';
    }

    /**
     * Send the response
     * 
     * @return void
     */
    public function send(): void
    {
        // Send status line
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            
            // Send headers
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        
        // Send content
        echo $this->content;
    }

    /**
     * Convert response to string
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->content;
    }
}