<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Exceptions;

use Throwable;

/**
 * HTTP Exception
 * 
 * Thrown for HTTP-specific errors with appropriate status codes.
 * Provides mapping between exception types and HTTP status codes
 * for proper client communication.
 * 
 * @package LengthOfRope\TreeHouse\Errors\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class HttpException extends BaseException
{
    /**
     * Default error severity for HTTP errors
     */
    protected string $severity = 'medium';

    /**
     * HTTP headers to include in the response
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Create a new HTTP exception
     *
     * @param int $statusCode HTTP status code
     * @param string $message Exception message
     * @param array<string, string> $headers HTTP headers
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        int $statusCode = 500,
        string $message = '',
        array $headers = [],
        ?Throwable $previous = null,
        array $context = []
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        
        // Set default message based on status code if not provided
        if (empty($message)) {
            $message = $this->getDefaultMessage($statusCode);
        }

        // Set severity based on status code
        $this->severity = $this->getSeverityForStatusCode($statusCode);
        
        // Set reportable based on status code
        $this->reportable = $this->shouldReportStatusCode($statusCode);

        $context['status_code'] = $statusCode;
        $context['headers'] = $headers;

        parent::__construct($message, $statusCode, $previous, $context);
        
        $this->userMessage = $this->getUserFriendlyMessage($statusCode);
    }

    /**
     * Get HTTP headers
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set HTTP headers
     *
     * @param array<string, string> $headers
     * @return static
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Add an HTTP header
     *
     * @param string $name
     * @param string $value
     * @return static
     */
    public function addHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Get default message for status code
     */
    private function getDefaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => "I'm a teapot",
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
            default => 'HTTP Error',
        };
    }

    /**
     * Get severity level for status code
     */
    private function getSeverityForStatusCode(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'critical',
            $statusCode >= 400 => 'medium',
            $statusCode >= 300 => 'low',
            default => 'low',
        };
    }

    /**
     * Determine if status code should be reported
     */
    private function shouldReportStatusCode(int $statusCode): bool
    {
        // Don't report client errors (4xx) except for specific cases
        if ($statusCode >= 400 && $statusCode < 500) {
            return in_array($statusCode, [429, 431, 451]); // Rate limiting, large headers, legal
        }
        
        // Report all server errors (5xx)
        return $statusCode >= 500;
    }

    /**
     * Get user-friendly message for status code
     */
    private function getUserFriendlyMessage(int $statusCode): string
    {
        return match (true) {
            $statusCode === 400 => 'Your request could not be processed. Please check your input and try again.',
            $statusCode === 401 => 'You need to log in to access this resource.',
            $statusCode === 403 => 'You do not have permission to access this resource.',
            $statusCode === 404 => 'The page or resource you are looking for could not be found.',
            $statusCode === 405 => 'This action is not allowed for this resource.',
            $statusCode === 408 => 'Your request took too long to process. Please try again.',
            $statusCode === 409 => 'There was a conflict with your request. Please try again.',
            $statusCode === 413 => 'The file or data you are trying to upload is too large.',
            $statusCode === 422 => 'Your request contains invalid data. Please check and try again.',
            $statusCode === 429 => 'You are making too many requests. Please wait and try again.',
            $statusCode >= 500 => 'We are experiencing technical difficulties. Please try again later.',
            default => 'An error occurred while processing your request.',
        };
    }

    /**
     * Create a 400 Bad Request exception
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return static
     */
    public static function badRequest(string $message = '', array $context = []): static
    {
        return new static(400, $message, [], null, $context);
    }

    /**
     * Create a 401 Unauthorized exception
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return static
     */
    public static function unauthorized(string $message = '', array $context = []): static
    {
        return new static(401, $message, ['WWW-Authenticate' => 'Bearer'], null, $context);
    }

    /**
     * Create a 403 Forbidden exception
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return static
     */
    public static function forbidden(string $message = '', array $context = []): static
    {
        return new static(403, $message, [], null, $context);
    }

    /**
     * Create a 404 Not Found exception
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return static
     */
    public static function notFound(string $message = '', array $context = []): static
    {
        return new static(404, $message, [], null, $context);
    }

    /**
     * Create a 405 Method Not Allowed exception
     *
     * @param array<string> $allowedMethods
     * @param string $message
     * @param array<string, mixed> $context
     * @return static
     */
    public static function methodNotAllowed(array $allowedMethods = [], string $message = '', array $context = []): static
    {
        $headers = [];
        if (!empty($allowedMethods)) {
            $headers['Allow'] = implode(', ', $allowedMethods);
        }
        
        return new static(405, $message, $headers, null, $context);
    }

    /**
     * Create a 422 Unprocessable Entity exception
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return static
     */
    public static function unprocessableEntity(string $message = '', array $context = []): static
    {
        return new static(422, $message, [], null, $context);
    }

    /**
     * Create a 429 Too Many Requests exception
     *
     * @param int|null $retryAfter Seconds to wait before retrying
     * @param string $message
     * @param array<string, mixed> $context
     * @return static
     */
    public static function tooManyRequests(?int $retryAfter = null, string $message = '', array $context = []): static
    {
        $headers = [];
        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) $retryAfter;
        }
        
        return new static(429, $message, $headers, null, $context);
    }

    /**
     * Create a 500 Internal Server Error exception
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return static
     */
    public static function internalServerError(string $message = '', array $context = []): static
    {
        return new static(500, $message, [], null, $context);
    }

    /**
     * Create a 503 Service Unavailable exception
     *
     * @param int|null $retryAfter Seconds to wait before retrying
     * @param string $message
     * @param array<string, mixed> $context
     * @return static
     */
    public static function serviceUnavailable(?int $retryAfter = null, string $message = '', array $context = []): static
    {
        $headers = [];
        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) $retryAfter;
        }
        
        return new static(503, $message, $headers, null, $context);
    }

    /**
     * Convert to array with HTTP-specific information
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['http'] = [
            'status_code' => $this->statusCode,
            'headers' => $this->headers,
        ];
        
        return $array;
    }
}