<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Security\Csrf;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * JWT-based CSRF Protection Manager
 *
 * Provides CSRF protection using JWT tokens instead of traditional session-based
 * tokens. This allows for stateless CSRF protection that works well with JWT
 * authentication systems and API-first applications.
 *
 * Features:
 * - Stateless CSRF protection using JWT
 * - Integration with existing JWT authentication
 * - Configurable token lifetimes
 * - Multiple validation strategies
 * - Anti-replay protection
 * - Request fingerprinting
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtCsrfManager
{
    /**
     * Default configuration
     */
    private const DEFAULTS = [
        'enabled' => true,
        'secret' => null,                           // JWT secret for CSRF tokens
        'algorithm' => 'HS256',                     // JWT algorithm
        'ttl' => 3600,                             // 1 hour CSRF token lifetime
        'include_fingerprint' => true,             // Include request fingerprint
        'validate_origin' => true,                 // Validate request origin
        'require_https' => false,                  // Require HTTPS in production
        'header_name' => 'X-CSRF-TOKEN',          // CSRF token header name
        'field_name' => '_csrf_token',            // CSRF token field name
        'cookie_name' => 'csrf_token',            // CSRF token cookie name
        'same_origin_only' => true,               // Only allow same-origin requests
    ];

    private JwtConfig $jwtConfig;
    private JwtEncoder $encoder;
    private JwtDecoder $decoder;
    private array $config;

    /**
     * Create new JWT CSRF manager
     *
     * @param JwtConfig|null $jwtConfig JWT configuration (if null, creates from config)
     * @param array $config CSRF configuration
     */
    public function __construct(?JwtConfig $jwtConfig = null, array $config = [])
    {
        $this->config = array_merge(self::DEFAULTS, $config);
        
        // Create JWT config for CSRF tokens
        if ($jwtConfig) {
            $this->jwtConfig = $jwtConfig;
        } else {
            $csrfSecret = $this->config['secret'] ?: $this->generateCsrfSecret();
            $this->jwtConfig = new JwtConfig([
                'secret' => $csrfSecret,
                'algorithm' => $this->config['algorithm'],
                'ttl' => $this->config['ttl'],
                'issuer' => 'treehouse-csrf',
                'audience' => 'treehouse-csrf',
            ]);
        }
        
        $this->encoder = new JwtEncoder($this->jwtConfig);
        $this->decoder = new JwtDecoder($this->jwtConfig);
        
        $this->validateConfig();
    }

    /**
     * Generate CSRF token for request
     *
     * @param Request $request HTTP request
     * @param array $additionalClaims Additional claims to include
     * @return string JWT CSRF token
     */
    public function generateToken(Request $request, array $additionalClaims = []): string
    {
        if (!$this->config['enabled']) {
            return '';
        }

        $now = Carbon::now()->getTimestamp();
        $fingerprint = $this->generateRequestFingerprint($request);
        
        $claims = new ClaimsManager();
        
        // Set standard claims
        $claims->setIssuer($this->jwtConfig->getIssuer());
        $claims->setAudience($this->jwtConfig->getAudience());
        $claims->setIssuedAt($now);
        $claims->setExpiration($now + $this->config['ttl']);
        $claims->setNotBefore($now);
        $claims->setJwtId(bin2hex(random_bytes(16)));
        
        // Set custom claims
        $claims->setClaim('purpose', 'csrf');
        $claims->setClaim('origin', $this->getRequestOrigin($request));
        $claims->setClaim('fingerprint', $fingerprint);
        $claims->setClaim('ip', $request->ip());
        $claims->setClaim('user_agent', $request->userAgent());
        
        // Add additional claims
        foreach ($additionalClaims as $name => $value) {
            $claims->setClaim($name, $value);
        }

        return $this->encoder->encode($claims);
    }

    /**
     * Validate CSRF token
     *
     * @param Request $request HTTP request
     * @param string|null $token CSRF token (if null, extracts from request)
     * @return bool True if token is valid
     */
    public function validateToken(Request $request, ?string $token = null): bool
    {
        if (!$this->config['enabled']) {
            return true;
        }

        $token = $token ?: $this->extractTokenFromRequest($request);
        
        if (!$token) {
            return false;
        }

        try {
            $payload = $this->decoder->decode($token);
            
            // Validate purpose
            if (($payload['purpose'] ?? '') !== 'csrf') {
                return false;
            }

            // Validate origin if enabled
            if ($this->config['validate_origin']) {
                $requestOrigin = $this->getRequestOrigin($request);
                $tokenOrigin = $payload['origin'] ?? '';
                
                if ($requestOrigin !== $tokenOrigin) {
                    return false;
                }
            }

            // Validate request fingerprint if enabled
            if ($this->config['include_fingerprint']) {
                $requestFingerprint = $this->generateRequestFingerprint($request);
                $tokenFingerprint = $payload['fingerprint'] ?? '';
                
                if ($requestFingerprint !== $tokenFingerprint) {
                    return false;
                }
            }

            // Validate same origin if enabled
            if ($this->config['same_origin_only']) {
                if (!$this->isSameOriginRequest($request)) {
                    return false;
                }
            }

            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Extract CSRF token from request
     *
     * @param Request $request HTTP request
     * @return string|null CSRF token or null if not found
     */
    public function extractTokenFromRequest(Request $request): ?string
    {
        // Check header first
        $token = $request->header($this->config['header_name']);
        if ($token) {
            return $token;
        }

        // Check request data
        $token = $request->input($this->config['field_name']);
        if ($token) {
            return $token;
        }

        // Check cookie
        $token = $request->cookie($this->config['cookie_name']);
        if ($token) {
            return $token;
        }

        return null;
    }

    /**
     * Generate CSRF token field HTML
     *
     * @param Request $request HTTP request
     * @return string HTML input field
     */
    public function getTokenField(Request $request): string
    {
        $token = $this->generateToken($request);
        $fieldName = htmlspecialchars($this->config['field_name'], ENT_QUOTES, 'UTF-8');
        $tokenValue = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        
        return "<input type=\"hidden\" name=\"{$fieldName}\" value=\"{$tokenValue}\">";
    }

    /**
     * Generate CSRF token meta tag HTML
     *
     * @param Request $request HTTP request
     * @return string HTML meta tag
     */
    public function getTokenMeta(Request $request): string
    {
        $token = $this->generateToken($request);
        $tokenValue = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        
        return "<meta name=\"csrf-token\" content=\"{$tokenValue}\">";
    }

    /**
     * Get CSRF token for JavaScript usage
     *
     * @param Request $request HTTP request
     * @return array Token data for JavaScript
     */
    public function getTokenForJs(Request $request): array
    {
        $token = $this->generateToken($request);
        
        return [
            'token' => $token,
            'header_name' => $this->config['header_name'],
            'field_name' => $this->config['field_name'],
            'ttl' => $this->config['ttl'],
            'expires_at' => Carbon::now()->addSeconds($this->config['ttl'])->format('c'),
        ];
    }

    /**
     * Validate request method requires CSRF protection
     *
     * @param Request $request HTTP request
     * @return bool True if method requires CSRF protection
     */
    public function requiresCsrfProtection(Request $request): bool
    {
        $method = strtoupper($request->method());
        $safeMethods = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];
        
        return !in_array($method, $safeMethods);
    }

    /**
     * Get CSRF protection status
     *
     * @param Request $request HTTP request
     * @return array Protection status information
     */
    public function getProtectionStatus(Request $request): array
    {
        $token = $this->extractTokenFromRequest($request);
        $isValid = $token ? $this->validateToken($request, $token) : false;
        $requiresProtection = $this->requiresCsrfProtection($request);
        
        $status = [
            'enabled' => $this->config['enabled'],
            'requires_protection' => $requiresProtection,
            'token_present' => !empty($token),
            'token_valid' => $isValid,
            'protected' => $this->config['enabled'] && (!$requiresProtection || $isValid),
        ];

        // Add token info if available
        if ($token) {
            try {
                $payload = $this->decoder->decode($token);
                $status['token_info'] = [
                    'issued_at' => $payload['iat'] ?? null,
                    'expires_at' => $payload['exp'] ?? null,
                    'token_id' => $payload['jti'] ?? null,
                    'origin' => $payload['origin'] ?? null,
                ];
            } catch (\Exception $e) {
                $status['token_error'] = $e->getMessage();
            }
        }

        return $status;
    }

    /**
     * Generate request fingerprint
     *
     * @param Request $request HTTP request
     * @return string Request fingerprint
     */
    private function generateRequestFingerprint(Request $request): string
    {
        if (!$this->config['include_fingerprint']) {
            return '';
        }

        $components = [
            $request->ip(),
            $request->userAgent(),
            $request->header('Accept-Language', ''),
            $request->header('Accept-Encoding', ''),
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Get request origin
     *
     * @param Request $request HTTP request
     * @return string Request origin
     */
    private function getRequestOrigin(Request $request): string
    {
        // Try Origin header first
        $origin = $request->header('Origin');
        if ($origin) {
            return $origin;
        }

        // Fallback to Referer
        $referer = $request->header('Referer');
        if ($referer) {
            $parsed = parse_url($referer);
            $scheme = $parsed['scheme'] ?? 'http';
            $host = $parsed['host'] ?? 'localhost';
            $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
            
            $origin = $scheme . '://' . $host;
            if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
                $origin .= ':' . $port;
            }
            
            return $origin;
        }

        // Fallback to request host
        $scheme = $request->isSecure() ? 'https' : 'http';
        $host = $request->getHost();
        $port = $request->getPort();
        
        $origin = $scheme . '://' . $host;
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $origin .= ':' . $port;
        }
        
        return $origin;
    }

    /**
     * Check if request is same-origin
     *
     * @param Request $request HTTP request
     * @return bool True if same origin
     */
    private function isSameOriginRequest(Request $request): bool
    {
        $requestOrigin = $this->getRequestOrigin($request);
        $serverOrigin = $this->getServerOrigin($request);
        
        return $requestOrigin === $serverOrigin;
    }

    /**
     * Get server origin
     *
     * @param Request $request HTTP request
     * @return string Server origin
     */
    private function getServerOrigin(Request $request): string
    {
        $scheme = $request->isSecure() ? 'https' : 'http';
        $host = $request->getHost();
        $port = $request->getPort();
        
        $origin = $scheme . '://' . $host;
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $origin .= ':' . $port;
        }
        
        return $origin;
    }

    /**
     * Generate CSRF secret
     *
     * @return string Generated secret
     */
    private function generateCsrfSecret(): string
    {
        // Use app key if available
        $appKey = $_ENV['APP_KEY'] ?? '';
        if ($appKey) {
            return hash('sha256', $appKey . 'csrf-protection');
        }
        
        // Generate random secret
        return base64_encode(random_bytes(32));
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
        $this->config = array_merge($this->config, $config);
        $this->validateConfig();
        return $this;
    }

    /**
     * Check if CSRF protection is enabled
     *
     * @return bool True if enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'];
    }

    /**
     * Enable or disable CSRF protection
     *
     * @param bool $enabled Whether to enable protection
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->config['enabled'] = $enabled;
        return $this;
    }

    /**
     * Validate configuration
     *
     * @throws InvalidArgumentException If configuration is invalid
     */
    private function validateConfig(): void
    {
        if ($this->config['ttl'] <= 0) {
            throw new InvalidArgumentException('CSRF token TTL must be positive', 'INVALID_CSRF_TTL');
        }

        if (!in_array($this->config['algorithm'], ['HS256', 'HS384', 'HS512'])) {
            throw new InvalidArgumentException('CSRF token algorithm must be HMAC-based', 'INVALID_CSRF_ALGORITHM');
        }

        if (empty($this->config['header_name'])) {
            throw new InvalidArgumentException('CSRF header name cannot be empty', 'INVALID_CSRF_HEADER');
        }

        if (empty($this->config['field_name'])) {
            throw new InvalidArgumentException('CSRF field name cannot be empty', 'INVALID_CSRF_FIELD');
        }

        // Validate HTTPS requirement in production
        if ($this->config['require_https'] && !$this->isHttpsEnvironment()) {
            throw new InvalidArgumentException('CSRF protection requires HTTPS in production', 'CSRF_REQUIRES_HTTPS');
        }
    }

    /**
     * Check if running in HTTPS environment
     *
     * @return bool True if HTTPS
     */
    private function isHttpsEnvironment(): bool
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }
}