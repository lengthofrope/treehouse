<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Security;

use LengthOfRope\TreeHouse\Support\Collection;
use LengthOfRope\TreeHouse\Support\Str;

/**
 * Input Sanitization and XSS Protection
 *
 * Provides comprehensive input sanitization utilities to prevent XSS attacks
 * and other security vulnerabilities. This class focuses solely on cleaning and
 * sanitizing input, not on validation.
 *
 * Features:
 * - Type-specific sanitization (string, email, URL, numeric, boolean)
 * - XSS attack prevention and HTML escaping
 * - Fluent array sanitization using Collections
 * - File and path sanitization
 * - HTML attribute and content escaping
 *
 * @package LengthOfRope\TreeHouse\Security
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Sanitizer
{
    /**
     * Sanitize a string by removing HTML tags and trimming whitespace
     *
     * @param string|null $input String to sanitize
     * @return string Sanitized string
     */
    public function sanitizeString(?string $input): string
    {
        if ($input === null) {
            return '';
        }

        return trim(strip_tags($input));
    }

    /**
     * Sanitize an email address
     *
     * @param string $input Email address to sanitize
     * @return string Sanitized email address
     */
    public function sanitizeEmail(string $input): string
    {
        $sanitized = filter_var($input, FILTER_SANITIZE_EMAIL);

        if ($sanitized === false || !filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $sanitized;
    }

    /**
     * Sanitize a URL, removing dangerous protocols
     *
     * @param string $input URL to sanitize
     * @return string Sanitized URL, or an empty string if dangerous
     */
    public function sanitizeUrl(string $input): string
    {
        $sanitized = filter_var($input, FILTER_SANITIZE_URL);

        if ($sanitized === false) {
            return '';
        }

        // Check for dangerous protocols
        $scheme = parse_url($sanitized, PHP_URL_SCHEME);
        if ($scheme && Str::isMatch('/^(javascript|data|vbscript|file)$/i', $scheme)) {
            return '';
        }
        
        if (!filter_var($sanitized, FILTER_VALIDATE_URL)) {
            return '';
        }

        return $sanitized;
    }

    /**
     * Sanitize an integer value
     *
     * @param mixed $input Value to sanitize as integer
     * @return int Sanitized integer value
     */
    public function sanitizeInteger(mixed $input): int
    {
        return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitize a float value
     *
     * @param mixed $input Value to sanitize as float
     * @return float Sanitized float value
     */
    public function sanitizeFloat(mixed $input): float
    {
        return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Sanitize a boolean value.
     *
     * Note: This uses filter_var which is more robust than string comparisons.
     * '1', 'true', 'on', 'yes' are true.
     * '0', 'false', 'off', 'no', '' are false.
     * null is false.
     *
     * @param mixed $input Value to sanitize as boolean
     * @return bool Sanitized boolean value
     */
    public function sanitizeBoolean(mixed $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Sanitize an array based on a set of rules.
     *
     * This method leverages the Collection class for a fluent, declarative approach.
     *
     * @param array $input The input array to sanitize.
     * @param array $rules An associative array where keys are field names and values are the sanitization type ('string', 'email', 'url', 'integer', 'float', 'boolean').
     * @return array The sanitized array.
     *
     * Example:
     * $rules = ['name' => 'string', 'email' => 'email', 'age' => 'integer']
     * $sanitized = $sanitizer->sanitizeArray($input, $rules);
     */
    public function sanitizeArray(array $input, array $rules): array
    {
        $sanitizedData = [];
        foreach (array_keys($rules) as $key) {
            $sanitizedData[$key] = $input[$key] ?? null;
        }

        return Collection::make($sanitizedData)
            ->map(function ($value, $key) use ($rules) {
                $type = $rules[$key] ?? 'string';
                $value = $value ?? '';

                return match ($type) {
                    'email' => $this->sanitizeEmail($value),
                    'url' => $this->sanitizeUrl($value),
                    'integer' => $this->sanitizeInteger($value),
                    'float' => $this->sanitizeFloat($value),
                    'boolean' => $this->sanitizeBoolean($value),
                    default => $this->sanitizeString($value),
                };
            })
            ->all();
    }

    /**
     * Remove XSS attempts from input
     *
     * @param string $input Input string to clean
     * @return string String with XSS attempts removed
     */
    public function removeXssAttempts(string $input): string
    {
        // Remove script tags
        $input = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $input);
        
        // Remove event handlers - use non-greedy matching and handle quotes
        $input = preg_replace('/\s*on\w+\s*=\s*"[^"]*"/i', '', $input);
        $input = preg_replace('/\s*on\w+\s*=\s*\'[^\']*\'/i', '', $input);
        
        // Remove javascript: URLs - use non-greedy matching and handle quotes
        $input = preg_replace('/href\s*=\s*"javascript:[^"]*"/i', 'href=""', $input);
        $input = preg_replace('/href\s*=\s*\'javascript:[^\']*\'/i', 'href=""', $input);
        
        return $input;
    }

    /**
     * Escape HTML special characters
     *
     * @param string $input String to escape
     * @return string HTML-escaped string
     */
    public function escapeHtml(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape HTML attribute values
     *
     * @param string $input String to escape for attribute use
     * @return string Attribute-safe escaped string
     */
    public function escapeAttribute(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML401, 'UTF-8');
    }

    /**
     * Sanitize a filename by removing dangerous characters and path traversal attempts.
     *
     * This method uses Str::slug to create a web-safe filename, which is a more
     * robust approach than simple character replacement.
     *
     * @param string $input Filename to sanitize
     * @return string Safe filename
     */
    public function sanitizeFilename(string $input): string
    {
        // Remove path traversal attempts
        $filename = basename($input);
        
        // Remove dangerous characters
        $filename = preg_replace('/[<>:"|?*]/', '', $filename);
        
        // Remove leading dots
        $filename = ltrim($filename, '.');
        
        // If empty after sanitization, provide default
        if (empty($filename)) {
            return 'untitled';
        }
        
        return $filename;
    }

    /**
     * Sanitize a file path
     *
     * @param string $input File path to sanitize
     * @return string Sanitized relative path
     */
    public function sanitizePath(string $input): string
    {
        // Normalize slashes to forward slashes
        $path = str_replace('\\', '/', $input);

        // Remove path traversal attempts and duplicate slashes
        $path = preg_replace(['#\.\./#', '#/+#'], ['/', '/'], $path);

        // Remove leading/trailing slashes
        return trim($path, '/');
    }

    /**
     * Sanitize SQL input (basic escaping)
     *
     * @param string $input SQL input to sanitize
     * @return string Sanitized SQL input
     * @deprecated Use prepared statements instead
     */
    public function sanitizeSql(string $input): string
    {
        // This is a basic sanitizer, but prepared statements are always preferred.
        return str_replace(
            ["'", ';', '--', '/*', '*/'],
            ["''", '', '', '', ''],
            $input
        );
    }
}