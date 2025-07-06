<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers;

use LengthOfRope\TreeHouse\Http\Request;

/**
 * Header-based Key Resolver
 *
 * Resolves rate limiting keys based on HTTP headers, typically used
 * for API key authentication or custom client identification.
 * Falls back to IP-based resolution if configured.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class HeaderKeyResolver implements KeyResolverInterface
{
    /**
     * Resolver configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Create a new header key resolver
     *
     * @param array<string, mixed> $config Resolver configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Resolve the rate limiting key for a request
     *
     * @param Request $request HTTP request
     * @return string|null Rate limiting key or null if cannot be resolved
     */
    public function resolveKey(Request $request): ?string
    {
        // Try primary header
        $headerValue = $this->getHeaderValue($request, $this->config['header']);
        
        if ($headerValue !== null) {
            $prefix = $this->config['header_prefix'];
            return "{$prefix}:{$headerValue}";
        }
        
        // Try fallback headers
        foreach ($this->config['fallback_headers'] as $fallbackHeader) {
            $headerValue = $this->getHeaderValue($request, $fallbackHeader);
            if ($headerValue !== null) {
                $prefix = $this->config['header_prefix'];
                return "{$prefix}:{$headerValue}";
            }
        }
        
        // Fall back to IP if configured
        if ($this->config['fallback_to_ip']) {
            $ip = $this->getClientIp($request);
            if ($ip !== null) {
                $prefix = $this->config['ip_prefix'];
                return "{$prefix}:{$ip}";
            }
        }
        
        return null;
    }

    /**
     * Get the resolver name
     */
    public function getName(): string
    {
        return 'header';
    }

    /**
     * Check if this resolver can handle the request
     *
     * @param Request $request HTTP request
     * @return bool True if this resolver can generate a key for the request
     */
    public function canResolve(Request $request): bool
    {
        // Check primary header
        if ($this->getHeaderValue($request, $this->config['header']) !== null) {
            return true;
        }
        
        // Check fallback headers
        foreach ($this->config['fallback_headers'] as $fallbackHeader) {
            if ($this->getHeaderValue($request, $fallbackHeader) !== null) {
                return true;
            }
        }
        
        // Check IP fallback
        return $this->config['fallback_to_ip'] && $this->getClientIp($request) !== null;
    }

    /**
     * Get default configuration
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        return [
            'header' => 'X-API-Key',
            'fallback_headers' => ['Authorization', 'X-Auth-Token', 'X-Client-ID'],
            'header_prefix' => 'header',
            'ip_prefix' => 'ip',
            'fallback_to_ip' => true,
            'case_sensitive' => false,
            'extract_bearer_token' => true, // Extract token from "Bearer <token>" format
            'hash_value' => false, // Hash the header value for privacy
        ];
    }

    /**
     * Set resolver configuration
     *
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get current configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get header value from request
     *
     * @param Request $request HTTP request
     * @param string $headerName Header name
     * @return string|null Header value or null if not present
     */
    private function getHeaderValue(Request $request, string $headerName): ?string
    {
        // Try TreeHouse Request header() method first
        if (method_exists($request, 'header')) {
            $value = $request->header($headerName);
            if ($value !== null) {
                return $this->processHeaderValue($value);
            }
        }
        
        // Manual header detection
        $value = $this->getHeaderFromServer($headerName);
        if ($value !== null) {
            return $this->processHeaderValue($value);
        }
        
        return null;
    }

    /**
     * Get header value from $_SERVER
     *
     * @param string $headerName Header name
     * @return string|null Header value or null if not present
     */
    private function getHeaderFromServer(string $headerName): ?string
    {
        // Normalize header name for $_SERVER lookup
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
        
        if (isset($_SERVER[$serverKey])) {
            return $_SERVER[$serverKey];
        }
        
        // Try case-insensitive search if configured
        if (!$this->config['case_sensitive']) {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $headerKey = substr($key, 5); // Remove 'HTTP_' prefix
                    $normalizedKey = str_replace('_', '-', strtolower($headerKey));
                    $normalizedTarget = strtolower($headerName);
                    
                    if ($normalizedKey === $normalizedTarget) {
                        return $value;
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Process header value (extract bearer token, hash, etc.)
     *
     * @param string $value Raw header value
     * @return string Processed header value
     */
    private function processHeaderValue(string $value): string
    {
        $value = trim($value);
        
        // Extract bearer token if configured
        if ($this->config['extract_bearer_token'] && str_starts_with(strtolower($value), 'bearer ')) {
            $value = trim(substr($value, 7));
        }
        
        // Hash value if configured (for privacy)
        if ($this->config['hash_value']) {
            $value = hash('sha256', $value);
        }
        
        return $value;
    }

    /**
     * Get client IP address from request
     *
     * @param Request $request HTTP request
     * @return string|null Client IP address
     */
    private function getClientIp(Request $request): ?string
    {
        // Try TreeHouse Request ip() method first
        if (method_exists($request, 'ip')) {
            return $request->ip();
        }
        
        // Manual IP detection
        $ipHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipHeaders as $header) {
            $ip = $_SERVER[$header] ?? null;
            if (!empty($ip)) {
                // Handle comma-separated IPs (X-Forwarded-For)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }

    /**
     * Get the primary header name being used
     *
     * @return string Header name
     */
    public function getPrimaryHeader(): string
    {
        return $this->config['header'];
    }

    /**
     * Get all fallback headers
     *
     * @return array<string> Fallback header names
     */
    public function getFallbackHeaders(): array
    {
        return $this->config['fallback_headers'];
    }

    /**
     * Check which header was found for the request
     *
     * @param Request $request HTTP request
     * @return string|null Header name that was found, or null if none
     */
    public function getFoundHeader(Request $request): ?string
    {
        // Check primary header
        if ($this->getHeaderValue($request, $this->config['header']) !== null) {
            return $this->config['header'];
        }
        
        // Check fallback headers
        foreach ($this->config['fallback_headers'] as $fallbackHeader) {
            if ($this->getHeaderValue($request, $fallbackHeader) !== null) {
                return $fallbackHeader;
            }
        }
        
        return null;
    }

    /**
     * Get debugging information
     *
     * @param Request $request HTTP request
     * @return array<string, mixed>
     */
    public function getDebugInfo(Request $request): array
    {
        $foundHeader = $this->getFoundHeader($request);
        $headerValue = $foundHeader ? $this->getHeaderValue($request, $foundHeader) : null;
        
        return [
            'resolver' => $this->getName(),
            'primary_header' => $this->config['header'],
            'fallback_headers' => $this->config['fallback_headers'],
            'found_header' => $foundHeader,
            'header_value' => $headerValue ? '[REDACTED]' : null, // Don't log actual values
            'resolved_key' => $this->resolveKey($request),
            'fallback_to_ip' => $this->config['fallback_to_ip'],
            'client_ip' => $this->getClientIp($request),
        ];
    }

    /**
     * Create a header resolver for API keys
     *
     * @param string $headerName Header name (default: X-API-Key)
     * @param array<string, mixed> $config Additional configuration
     * @return static
     */
    public static function forApiKey(string $headerName = 'X-API-Key', array $config = []): static
    {
        $config['header'] = $headerName;
        return new static($config);
    }

    /**
     * Create a header resolver for Authorization header
     *
     * @param array<string, mixed> $config Additional configuration
     * @return static
     */
    public static function forAuthorization(array $config = []): static
    {
        $config['header'] = 'Authorization';
        $config['extract_bearer_token'] = true;
        return new static($config);
    }
}