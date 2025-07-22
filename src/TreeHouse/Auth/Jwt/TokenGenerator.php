<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT Token Generator
 *
 * High-level utility for generating JWT tokens with common patterns.
 * Provides convenient methods for creating tokens with standard claims
 * and user-specific data.
 *
 * Features:
 * - User authentication tokens
 * - API access tokens
 * - Refresh tokens
 * - Custom claim tokens
 * - Token expiration management
 * - Automatic claim population
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class TokenGenerator
{
    /**
     * JWT configuration
     */
    private JwtConfig $config;

    /**
     * JWT encoder
     */
    private JwtEncoder $encoder;

    /**
     * Create a new token generator instance
     *
     * @param JwtConfig $config JWT configuration
     */
    public function __construct(JwtConfig $config)
    {
        $this->config = $config;
        $this->encoder = new JwtEncoder($config);
    }

    /**
     * Generate an authentication token for a user
     *
     * @param string|int $userId User ID
     * @param array $userData Additional user data to include
     * @param array $permissions User permissions
     * @param array $roles User roles
     * @return string JWT token
     */
    public function generateAuthToken(
        string|int $userId,
        array $userData = [],
        array $permissions = [],
        array $roles = []
    ): string {
        $claims = [
            'sub' => (string)$userId,
            'user_id' => $userId,
            'type' => 'auth',
        ];

        // Add user data
        if (!empty($userData)) {
            $claims['user'] = $userData;
        }

        // Add permissions
        if (!empty($permissions)) {
            $claims['permissions'] = $permissions;
        }

        // Add roles
        if (!empty($roles)) {
            $claims['roles'] = $roles;
        }

        return $this->encoder->encodeWithDefaults($claims);
    }

    /**
     * Generate an API access token
     *
     * @param string|int $userId User ID
     * @param array $scopes API scopes
     * @param string|null $clientId Client ID for OAuth-like usage
     * @return string JWT token
     */
    public function generateApiToken(
        string|int $userId,
        array $scopes = [],
        ?string $clientId = null
    ): string {
        $claims = [
            'sub' => (string)$userId,
            'user_id' => $userId,
            'type' => 'api',
        ];

        // Add scopes
        if (!empty($scopes)) {
            $claims['scopes'] = $scopes;
        }

        // Add client ID
        if ($clientId !== null) {
            $claims['client_id'] = $clientId;
        }

        return $this->encoder->encodeWithDefaults($claims);
    }

    /**
     * Generate a refresh token
     *
     * @param string|int $userId User ID
     * @param string $tokenId Unique token identifier
     * @return string JWT refresh token
     */
    public function generateRefreshToken(string|int $userId, string $tokenId): string
    {
        $now = time();
        
        $claims = [
            'sub' => (string)$userId,
            'user_id' => $userId,
            'type' => 'refresh',
            'jti' => $tokenId,
            'iat' => $now,
            'exp' => $now + $this->config->getRefreshTtl(),
            'nbf' => $now,
        ];

        // Set configured claims
        if ($issuer = $this->config->getIssuer()) {
            $claims['iss'] = $issuer;
        }

        if ($audience = $this->config->getAudience()) {
            $claims['aud'] = $audience;
        }

        $claimsManager = new ClaimsManager($claims);
        return $this->encoder->encode($claimsManager);
    }

    /**
     * Generate a token with custom claims and expiration
     *
     * @param array $customClaims Custom claims to include
     * @param int|null $expiresIn Expiration time in seconds (null for default TTL)
     * @param array $header Additional header claims
     * @return string JWT token
     */
    public function generateCustomToken(
        array $customClaims = [],
        ?int $expiresIn = null,
        array $header = []
    ): string {
        if ($expiresIn !== null) {
            // Create claims with custom expiration
            $now = time();
            $claims = array_merge($customClaims, [
                'iat' => $now,
                'exp' => $now + $expiresIn,
                'nbf' => $now,
            ]);

            // Set configured claims
            if ($issuer = $this->config->getIssuer()) {
                $claims['iss'] = $issuer;
            }

            if ($audience = $this->config->getAudience()) {
                $claims['aud'] = $audience;
            }

            if ($subject = $this->config->getSubject()) {
                $claims['sub'] = $subject;
            }

            $claimsManager = new ClaimsManager($claims);
            return $this->encoder->encode($claimsManager, $header);
        }

        // Use default TTL
        return $this->encoder->encodeWithDefaults($customClaims, $header);
    }

    /**
     * Generate a session token (short-lived)
     *
     * @param string|int $userId User ID
     * @param string $sessionId Session identifier
     * @param int $ttl Token TTL in seconds (default: 15 minutes)
     * @return string JWT token
     */
    public function generateSessionToken(
        string|int $userId,
        string $sessionId,
        int $ttl = 900
    ): string {
        $claims = [
            'sub' => (string)$userId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'type' => 'session',
        ];

        return $this->generateCustomToken($claims, $ttl);
    }

    /**
     * Generate a password reset token
     *
     * @param string|int $userId User ID
     * @param string $email User email
     * @param int $ttl Token TTL in seconds (default: 1 hour)
     * @return string JWT token
     */
    public function generatePasswordResetToken(
        string|int $userId,
        string $email,
        int $ttl = 3600
    ): string {
        $claims = [
            'sub' => (string)$userId,
            'user_id' => $userId,
            'email' => $email,
            'type' => 'password_reset',
            'jti' => $this->generateUniqueId(),
        ];

        return $this->generateCustomToken($claims, $ttl);
    }

    /**
     * Generate an email verification token
     *
     * @param string|int $userId User ID
     * @param string $email Email to verify
     * @param int $ttl Token TTL in seconds (default: 24 hours)
     * @return string JWT token
     */
    public function generateEmailVerificationToken(
        string|int $userId,
        string $email,
        int $ttl = 86400
    ): string {
        $claims = [
            'sub' => (string)$userId,
            'user_id' => $userId,
            'email' => $email,
            'type' => 'email_verification',
            'jti' => $this->generateUniqueId(),
        ];

        return $this->generateCustomToken($claims, $ttl);
    }

    /**
     * Generate a token for API key authentication
     *
     * @param string $apiKeyId API key identifier
     * @param array $scopes Allowed scopes
     * @param string|null $userId Associated user ID (optional)
     * @return string JWT token
     */
    public function generateApiKeyToken(
        string $apiKeyId,
        array $scopes = [],
        ?string $userId = null
    ): string {
        $claims = [
            'sub' => $apiKeyId,
            'api_key_id' => $apiKeyId,
            'type' => 'api_key',
        ];

        if ($userId !== null) {
            $claims['user_id'] = $userId;
        }

        if (!empty($scopes)) {
            $claims['scopes'] = $scopes;
        }

        return $this->encoder->encodeWithDefaults($claims);
    }

    /**
     * Generate a unique identifier
     *
     * @return string Unique identifier
     */
    private function generateUniqueId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get token expiration time
     *
     * @param int|null $ttl TTL in seconds (null for default)
     * @return int Expiration timestamp
     */
    public function getExpirationTime(?int $ttl = null): int
    {
        return time() + ($ttl ?? $this->config->getTtl());
    }

    /**
     * Check if a token type is valid
     *
     * @param string $type Token type
     * @return bool True if valid
     */
    public function isValidTokenType(string $type): bool
    {
        $validTypes = [
            'auth',
            'api',
            'refresh',
            'session',
            'password_reset',
            'email_verification',
            'api_key',
        ];

        return in_array($type, $validTypes, true);
    }

    /**
     * Create a token generator with custom configuration
     *
     * @param array $config Configuration overrides
     * @return self New token generator instance
     */
    public static function withConfig(array $config): self
    {
        $jwtConfig = new JwtConfig($config);
        return new self($jwtConfig);
    }
}