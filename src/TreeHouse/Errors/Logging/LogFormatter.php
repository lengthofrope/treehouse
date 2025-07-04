<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Logging;

use Throwable;

/**
 * Log Formatter
 * 
 * Formats log messages for different output channels with structured data
 * and context interpolation following PSR-3 standards.
 * 
 * @package LengthOfRope\TreeHouse\Errors\Logging
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class LogFormatter
{
    /**
     * Channel configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Create a new log formatter
     *
     * @param array<string, mixed> $config Channel configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Format a log message
     *
     * @param string $level
     * @param string $message
     * @param array<string, mixed> $context
     * @return string
     */
    public function format(string $level, string $message, array $context = []): string
    {
        $format = $this->config['format'] ?? 'default';
        
        return match ($format) {
            'json' => $this->formatJson($level, $message, $context),
            'structured' => $this->formatStructured($level, $message, $context),
            'simple' => $this->formatSimple($level, $message, $context),
            default => $this->formatDefault($level, $message, $context),
        };
    }

    /**
     * Format as JSON
     */
    private function formatJson(string $level, string $message, array $context): string
    {
        $data = [
            'timestamp' => $this->getTimestamp(),
            'level' => strtoupper($level),
            'message' => $this->interpolate($message, $context),
            'context' => $this->sanitizeContext($context),
            'memory_usage' => memory_get_usage(true),
            'process_id' => getmypid(),
        ];

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Format as structured text
     */
    private function formatStructured(string $level, string $message, array $context): string
    {
        $timestamp = $this->getTimestamp();
        $interpolatedMessage = $this->interpolate($message, $context);
        $pid = getmypid();
        $memory = $this->formatBytes(memory_get_usage(true));
        $upperLevel = strtoupper($level);
        
        $line = "[{$timestamp}] {$upperLevel}: {$interpolatedMessage} [PID: {$pid}] [Memory: {$memory}]";
        
        // Add context if present
        if (!empty($context)) {
            $contextString = $this->formatContextForText($context);
            $line .= " {$contextString}";
        }
        
        return $line;
    }

    /**
     * Format as simple text
     */
    private function formatSimple(string $level, string $message, array $context): string
    {
        $timestamp = $this->getTimestamp();
        $interpolatedMessage = $this->interpolate($message, $context);
        $upperLevel = strtoupper($level);
        
        return "[{$timestamp}] {$upperLevel}: {$interpolatedMessage}";
    }

    /**
     * Format with default format
     */
    private function formatDefault(string $level, string $message, array $context): string
    {
        return $this->formatStructured($level, $message, $context);
    }

    /**
     * Interpolate context values into message placeholders
     */
    private function interpolate(string $message, array $context): string
    {
        // Build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            // Check that the value can be cast to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // Interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    /**
     * Sanitize context data for logging
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        
        foreach ($context as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue($key, $value);
        }
        
        return $sanitized;
    }

    /**
     * Sanitize a single value
     */
    private function sanitizeValue(string $key, mixed $value): mixed
    {
        // Hide sensitive fields
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'hash', 'authorization'];
        if (in_array(strtolower($key), $sensitiveFields, true)) {
            return '[HIDDEN]';
        }
        
        // Handle different value types
        if ($value instanceof Throwable) {
            return $this->formatException($value);
        }
        
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return get_class($value) . ' object';
        }
        
        if (is_array($value)) {
            return $this->sanitizeContext($value);
        }
        
        if (is_resource($value)) {
            return 'resource(' . get_resource_type($value) . ')';
        }
        
        return $value;
    }

    /**
     * Format exception for logging
     *
     * @param Throwable $exception
     * @return array<string, mixed>
     */
    private function formatException(Throwable $exception): array
    {
        $data = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
        
        // Add BaseException specific data if available
        if (method_exists($exception, 'getErrorCode')) {
            /** @var \LengthOfRope\TreeHouse\Errors\Exceptions\BaseException $exception */
            $data['error_code'] = $exception->getErrorCode();
        }
        
        if (method_exists($exception, 'getSeverity')) {
            /** @var \LengthOfRope\TreeHouse\Errors\Exceptions\BaseException $exception */
            $data['severity'] = $exception->getSeverity();
        }
        
        if (method_exists($exception, 'getContext')) {
            /** @var \LengthOfRope\TreeHouse\Errors\Exceptions\BaseException $exception */
            $context = $exception->getContext();
            if (!empty($context)) {
                $data['context'] = $this->sanitizeContext($context);
            }
        }
        
        // Include stack trace in debug mode
        if (($this->config['include_trace'] ?? false) || ($_ENV['APP_DEBUG'] ?? false)) {
            $data['trace'] = $exception->getTraceAsString();
        }
        
        return $data;
    }

    /**
     * Format context for text output
     *
     * @param array<string, mixed> $context
     */
    private function formatContextForText(array $context): string
    {
        $parts = [];
        
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) {
                // Handle exceptions directly
                $exceptionInfo = get_class($value) . ': ' . $value->getMessage();
                
                // Always include file info for better debugging
                $exceptionInfo .= ' in ' . $value->getFile() . ':' . $value->getLine();
                
                $parts[] = "{$key}=\"{$exceptionInfo}\"";
            } elseif (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $parts[] = "{$key}=" . json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_array($value)) {
                // Check if this is a formatted exception array
                if (isset($value['class']) && isset($value['message'])) {
                    // This is a formatted exception - show key details
                    $exceptionInfo = $value['class'] . ': ' . $value['message'];
                    $parts[] = "{$key}=\"{$exceptionInfo}\"";
                } else {
                    $parts[] = "{$key}=" . json_encode($value, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $parts[] = "{$key}=" . gettype($value);
            }
        }
        
        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * Get formatted timestamp
     */
    private function getTimestamp(): string
    {
        $format = $this->config['timestamp_format'] ?? 'Y-m-d\TH:i:sP';
        $timezone = $this->config['timezone'] ?? 'UTC';
        
        $date = new \DateTime('now', new \DateTimeZone($timezone));
        return $date->format($format);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        
        return sprintf('%.2f %s', $bytes / (1024 ** $factor), $units[$factor] ?? 'TB');
    }

    /**
     * Set configuration
     *
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}