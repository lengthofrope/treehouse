<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Security;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;

/**
 * Security Headers Manager
 *
 * Manages security headers for HTTP responses to enhance application security
 * and prevent common attacks. Provides configurable security policies and
 * automatic header injection for all HTTP responses, not just JWT-specific ones.
 *
 * Features:
 * - Content Security Policy (CSP) management
 * - CORS headers for APIs
 * - Security headers (HSTS, X-Frame-Options, etc.)
 * - Rate limiting headers
 * - Custom security header injection
 * - Environment-aware configurations
 *
 * @package LengthOfRope\TreeHouse\Security
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class SecurityHeadersManager
{
    /**
     * Default security headers configuration
     */
    private const DEFAULTS = [
        'enabled' => true,
        'cors' => [
            'enabled' => true,
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'Origin', 'X-Requested-With'],
            'exposed_headers' => ['X-RateLimit-Remaining', 'X-RateLimit-Limit', 'X-RateLimit-Reset'],
            'allow_credentials' => false,
            'max_age' => 86400, // 24 hours
        ],
        'csp' => [
            'enabled' => true,
            'default_src' => ["'self'"],
            'script_src' => ["'self'", "'unsafe-inline'"],
            'style_src' => ["'self'", "'unsafe-inline'"],
            'img_src' => ["'self'", 'data:', 'https:'],
            'connect_src' => ["'self'"],
            'font_src' => ["'self'"],
            'object_src' => ["'none'"],
            'media_src' => ["'self'"],
            'frame_src' => ["'none'"],
            'report_uri' => null,
        ],
        'security' => [
            'hsts' => [
                'enabled' => true,
                'max_age' => 31536000, // 1 year
                'include_subdomains' => true,
                'preload' => false,
            ],
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'content_type_options' => 'nosniff',
            'frame_options' => 'DENY',
            'xss_protection' => '1; mode=block',
            'permissions_policy' => [
                'camera' => [],
                'microphone' => [],
                'geolocation' => [],
                'payment' => [],
            ],
        ],
        'rate_limiting' => [
            'include_headers' => true,
            'custom_headers' => [],
        ],
    ];

    private array $config;
    private bool $isProduction;

    /**
     * Create new security headers manager
     *
     * @param array $config Security headers configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge_recursive(self::DEFAULTS, $config);
        $this->isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
        
        $this->validateConfig();
    }

    /**
     * Apply security headers to response
     *
     * @param Response $response HTTP response
     * @param Request|null $request HTTP request for context
     * @param array $context Additional context (rate limits, JWT info, etc.)
     * @return Response Modified response with security headers
     */
    public function applyHeaders(Response $response, ?Request $request = null, array $context = []): Response
    {
        if (!$this->config['enabled']) {
            return $response;
        }

        // Apply CORS headers
        if ($this->config['cors']['enabled'] && $request) {
            $response = $this->applyCorsHeaders($response, $request);
        }

        // Apply Content Security Policy
        if ($this->config['csp']['enabled']) {
            $response = $this->applyCspHeaders($response);
        }

        // Apply general security headers
        $response = $this->applySecurityHeaders($response);

        // Apply rate limiting headers
        if ($this->config['rate_limiting']['include_headers'] && isset($context['rate_limit'])) {
            $response = $this->applyRateLimitHeaders($response, $context['rate_limit']);
        }

        // Apply custom headers
        $response = $this->applyCustomHeaders($response);

        return $response;
    }

    /**
     * Apply CORS headers
     *
     * @param Response $response HTTP response
     * @param Request $request HTTP request
     * @return Response Modified response
     */
    private function applyCorsHeaders(Response $response, Request $request): Response
    {
        $corsConfig = $this->config['cors'];
        $origin = $request->header('Origin');

        // Handle allowed origins
        if ($this->isOriginAllowed($origin, $corsConfig['allowed_origins'])) {
            $response->setHeader('Access-Control-Allow-Origin', $origin ?: '*');
        } elseif (in_array('*', $corsConfig['allowed_origins'])) {
            $response->setHeader('Access-Control-Allow-Origin', '*');
        }

        // Set other CORS headers
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $corsConfig['allowed_methods']));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $corsConfig['allowed_headers']));
        
        if (!empty($corsConfig['exposed_headers'])) {
            $response->setHeader('Access-Control-Expose-Headers', implode(', ', $corsConfig['exposed_headers']));
        }

        if ($corsConfig['allow_credentials']) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        $response->setHeader('Access-Control-Max-Age', (string) $corsConfig['max_age']);

        return $response;
    }

    /**
     * Apply Content Security Policy headers
     *
     * @param Response $response HTTP response
     * @return Response Modified response
     */
    private function applyCspHeaders(Response $response): Response
    {
        $cspConfig = $this->config['csp'];
        $cspDirectives = [];

        // Build CSP directives
        foreach ($cspConfig as $directive => $sources) {
            if ($directive === 'enabled' || $directive === 'report_uri') {
                continue;
            }

            if (is_array($sources) && !empty($sources)) {
                $directiveName = str_replace('_', '-', $directive);
                $cspDirectives[] = $directiveName . ' ' . implode(' ', $sources);
            }
        }

        // Add report URI if configured
        if (!empty($cspConfig['report_uri'])) {
            $cspDirectives[] = 'report-uri ' . $cspConfig['report_uri'];
        }

        if (!empty($cspDirectives)) {
            $cspHeader = implode('; ', $cspDirectives);
            $response->setHeader('Content-Security-Policy', $cspHeader);

            // Add report-only header in development
            if (!$this->isProduction) {
                $response->setHeader('Content-Security-Policy-Report-Only', $cspHeader);
            }
        }

        return $response;
    }

    /**
     * Apply general security headers
     *
     * @param Response $response HTTP response
     * @return Response Modified response
     */
    private function applySecurityHeaders(Response $response): Response
    {
        $securityConfig = $this->config['security'];

        // HSTS (only over HTTPS in production)
        if ($securityConfig['hsts']['enabled'] && $this->isHttps()) {
            $hstsValue = 'max-age=' . $securityConfig['hsts']['max_age'];
            if ($securityConfig['hsts']['include_subdomains']) {
                $hstsValue .= '; includeSubDomains';
            }
            if ($securityConfig['hsts']['preload']) {
                $hstsValue .= '; preload';
            }
            $response->setHeader('Strict-Transport-Security', $hstsValue);
        }

        // X-Content-Type-Options
        if (!empty($securityConfig['content_type_options'])) {
            $response->setHeader('X-Content-Type-Options', $securityConfig['content_type_options']);
        }

        // X-Frame-Options
        if (!empty($securityConfig['frame_options'])) {
            $response->setHeader('X-Frame-Options', $securityConfig['frame_options']);
        }

        // X-XSS-Protection
        if (!empty($securityConfig['xss_protection'])) {
            $response->setHeader('X-XSS-Protection', $securityConfig['xss_protection']);
        }

        // Referrer-Policy
        if (!empty($securityConfig['referrer_policy'])) {
            $response->setHeader('Referrer-Policy', $securityConfig['referrer_policy']);
        }

        // Permissions-Policy
        if (!empty($securityConfig['permissions_policy'])) {
            $permissionsPolicies = [];
            foreach ($securityConfig['permissions_policy'] as $feature => $allowlist) {
                $allowlistStr = empty($allowlist) ? '()' : '(' . implode(' ', $allowlist) . ')';
                $permissionsPolicies[] = $feature . '=' . $allowlistStr;
            }
            if (!empty($permissionsPolicies)) {
                $response->setHeader('Permissions-Policy', implode(', ', $permissionsPolicies));
            }
        }

        return $response;
    }

    /**
     * Apply rate limiting headers
     *
     * @param Response $response HTTP response
     * @param array $rateLimitInfo Rate limit information
     * @return Response Modified response
     */
    private function applyRateLimitHeaders(Response $response, array $rateLimitInfo): Response
    {
        if (isset($rateLimitInfo['remaining'])) {
            $response->setHeader('X-RateLimit-Remaining', (string) $rateLimitInfo['remaining']);
        }
        if (isset($rateLimitInfo['limit'])) {
            $response->setHeader('X-RateLimit-Limit', (string) $rateLimitInfo['limit']);
        }
        if (isset($rateLimitInfo['reset'])) {
            $response->setHeader('X-RateLimit-Reset', (string) $rateLimitInfo['reset']);
        }

        return $response;
    }

    /**
     * Apply custom headers
     *
     * @param Response $response HTTP response
     * @return Response Modified response
     */
    private function applyCustomHeaders(Response $response): Response
    {
        $customHeaders = $this->config['rate_limiting']['custom_headers'];

        foreach ($customHeaders as $name => $value) {
            if (is_string($value)) {
                $response->setHeader($name, $value);
            } elseif (is_callable($value)) {
                $headerValue = $value();
                if (is_string($headerValue)) {
                    $response->setHeader($name, $headerValue);
                }
            }
        }

        return $response;
    }

    /**
     * Create CORS preflight response
     *
     * @param Request $request HTTP request
     * @return Response CORS preflight response
     */
    public function createPreflightResponse(Request $request): Response
    {
        $response = new Response('', 204);
        return $this->applyCorsHeaders($response, $request);
    }

    /**
     * Check if origin is allowed
     *
     * @param string|null $origin Request origin
     * @param array $allowedOrigins Allowed origins list
     * @return bool True if origin is allowed
     */
    private function isOriginAllowed(?string $origin, array $allowedOrigins): bool
    {
        if (!$origin) {
            return false;
        }

        if (in_array('*', $allowedOrigins)) {
            return true;
        }

        foreach ($allowedOrigins as $allowedOrigin) {
            if ($origin === $allowedOrigin) {
                return true;
            }

            // Support wildcard subdomains (e.g., *.example.com)
            if (str_contains($allowedOrigin, '*')) {
                $pattern = str_replace('*', '.*', preg_quote($allowedOrigin, '/'));
                if (preg_match('/^' . $pattern . '$/', $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if request is over HTTPS
     *
     * @return bool True if HTTPS
     */
    private function isHttps(): bool
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }

    /**
     * Get current configuration
     *
     * @return array Current configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Update configuration
     *
     * @param array $config New configuration
     * @return self
     */
    public function updateConfig(array $config): self
    {
        $this->config = array_merge_recursive($this->config, $config);
        $this->validateConfig();
        return $this;
    }

    /**
     * Get security headers summary
     *
     * @return array Headers summary
     */
    public function getHeadersSummary(): array
    {
        return [
            'cors_enabled' => $this->config['cors']['enabled'],
            'csp_enabled' => $this->config['csp']['enabled'],
            'hsts_enabled' => $this->config['security']['hsts']['enabled'],
            'custom_headers_count' => count($this->config['rate_limiting']['custom_headers']),
            'is_production' => $this->isProduction,
            'total_policies' => $this->countActivePolicies(),
        ];
    }

    /**
     * Count active security policies
     *
     * @return int Number of active policies
     */
    private function countActivePolicies(): int
    {
        $count = 0;

        if ($this->config['cors']['enabled']) $count++;
        if ($this->config['csp']['enabled']) $count++;
        if ($this->config['security']['hsts']['enabled']) $count++;
        if (!empty($this->config['security']['referrer_policy'])) $count++;
        if (!empty($this->config['security']['content_type_options'])) $count++;
        if (!empty($this->config['security']['frame_options'])) $count++;
        if (!empty($this->config['security']['xss_protection'])) $count++;
        if (!empty($this->config['security']['permissions_policy'])) $count++;

        return $count;
    }

    /**
     * Validate configuration
     *
     * @throws InvalidArgumentException If configuration is invalid
     */
    private function validateConfig(): void
    {
        // Validate CORS origins
        if (!is_array($this->config['cors']['allowed_origins'])) {
            throw new InvalidArgumentException('CORS allowed_origins must be an array', 'INVALID_CORS_ORIGINS');
        }

        // Validate CORS methods
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'HEAD', 'PATCH'];
        foreach ($this->config['cors']['allowed_methods'] as $method) {
            if (!in_array(strtoupper($method), $validMethods)) {
                throw new InvalidArgumentException("Invalid CORS method: {$method}", 'INVALID_CORS_METHOD');
            }
        }

        // Validate HSTS max-age
        if ($this->config['security']['hsts']['max_age'] < 0) {
            throw new InvalidArgumentException('HSTS max-age must be non-negative', 'INVALID_HSTS_MAX_AGE');
        }

        // Validate CSP directives
        if (!is_array($this->config['csp'])) {
            throw new InvalidArgumentException('CSP configuration must be an array', 'INVALID_CSP_CONFIG');
        }
    }
}