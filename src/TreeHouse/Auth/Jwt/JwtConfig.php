<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT Configuration Manager
 *
 * Manages JWT configuration including algorithm selection, secret management,
 * token lifetimes, and validation settings. Provides secure defaults and
 * validates configuration parameters.
 *
 * Features:
 * - Algorithm configuration and validation
 * - Secret key management and validation
 * - Token lifetime configuration
 * - Blacklist and refresh token settings
 * - Environment variable integration
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtConfig
{
    /**
     * Supported JWT algorithms
     */
    public const SUPPORTED_ALGORITHMS = ['HS256', 'RS256', 'ES256'];

    /**
     * Default configuration values
     */
    private const DEFAULTS = [
        'algorithm' => 'HS256',
        'ttl' => 900, // 15 minutes
        'refresh_ttl' => 1209600, // 2 weeks
        'blacklist_enabled' => true,
        'blacklist_grace_period' => 0,
        'leeway' => 0,
        'required_claims' => ['iss', 'iat', 'exp', 'nbf', 'sub'],
    ];

    /**
     * Configuration array
     */
    private array $config;

    /**
     * Create a new JWT configuration instance
     *
     * @param array $config Configuration array
     * @throws InvalidArgumentException If configuration is invalid
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::DEFAULTS, $config);
        $this->validateConfig();
    }

    /**
     * Get the JWT secret key
     *
     * @return string JWT secret key
     * @throws InvalidArgumentException If secret is not configured
     */
    public function getSecret(): string
    {
        if (!isset($this->config['secret']) || empty($this->config['secret'])) {
            throw new InvalidArgumentException('JWT secret is required but not configured', 'JWT_MISSING_SECRET');
        }

        $secret = $this->config['secret'];
        
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('JWT secret must be at least 32 characters long', 'JWT_SECRET_TOO_SHORT');
        }

        return $secret;
    }

    /**
     * Get the JWT algorithm
     *
     * @return string Algorithm name
     */
    public function getAlgorithm(): string
    {
        return $this->config['algorithm'];
    }

    /**
     * Get the access token TTL in seconds
     *
     * @return int TTL in seconds
     */
    public function getTtl(): int
    {
        return $this->config['ttl'];
    }

    /**
     * Get the refresh token TTL in seconds
     *
     * @return int Refresh TTL in seconds
     */
    public function getRefreshTtl(): int
    {
        return $this->config['refresh_ttl'];
    }

    /**
     * Check if blacklisting is enabled
     *
     * @return bool True if blacklisting is enabled
     */
    public function isBlacklistEnabled(): bool
    {
        return $this->config['blacklist_enabled'];
    }

    /**
     * Get the blacklist grace period in seconds
     *
     * @return int Grace period in seconds
     */
    public function getBlacklistGracePeriod(): int
    {
        return $this->config['blacklist_grace_period'];
    }

    /**
     * Get the clock leeway in seconds
     *
     * @return int Leeway in seconds
     */
    public function getLeeway(): int
    {
        return $this->config['leeway'];
    }

    /**
     * Get the required claims array
     *
     * @return array Required claims
     */
    public function getRequiredClaims(): array
    {
        return $this->config['required_claims'];
    }

    /**
     * Get the issuer claim
     *
     * @return string|null Issuer or null if not configured
     */
    public function getIssuer(): ?string
    {
        return $this->config['issuer'] ?? null;
    }

    /**
     * Get the audience claim
     *
     * @return string|null Audience or null if not configured
     */
    public function getAudience(): ?string
    {
        return $this->config['audience'] ?? null;
    }

    /**
     * Get the subject claim
     *
     * @return string|null Subject or null if not configured
     */
    public function getSubject(): ?string
    {
        return $this->config['subject'] ?? null;
    }

    /**
     * Get the private key for RSA/ECDSA algorithms
     *
     * @return string|null Private key or null if not configured
     */
    public function getPrivateKey(): ?string
    {
        return $this->config['private_key'] ?? null;
    }

    /**
     * Get the public key for RSA/ECDSA algorithms
     *
     * @return string|null Public key or null if not configured
     */
    public function getPublicKey(): ?string
    {
        return $this->config['public_key'] ?? null;
    }

    /**
     * Get a configuration value by key
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set a configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return void
     * @throws InvalidArgumentException If trying to set invalid configuration
     */
    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
        
        // Re-validate configuration after changes
        if (in_array($key, ['algorithm', 'secret', 'ttl', 'refresh_ttl'])) {
            $this->validateConfig();
        }
    }

    /**
     * Get all configuration as array
     *
     * @return array Complete configuration
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Validate the configuration
     *
     * @return void
     * @throws InvalidArgumentException If configuration is invalid
     */
    private function validateConfig(): void
    {
        // Validate algorithm
        if (!in_array($this->config['algorithm'], self::SUPPORTED_ALGORITHMS)) {
            throw new InvalidArgumentException(
                'Unsupported JWT algorithm: ' . $this->config['algorithm'] . '. Supported: ' . implode(', ', self::SUPPORTED_ALGORITHMS),
                'JWT_UNSUPPORTED_ALGORITHM'
            );
        }

        // Validate TTL values
        if ($this->config['ttl'] <= 0) {
            throw new InvalidArgumentException('JWT TTL must be greater than 0', 'JWT_INVALID_TTL');
        }

        if ($this->config['refresh_ttl'] <= 0) {
            throw new InvalidArgumentException('JWT refresh TTL must be greater than 0', 'JWT_INVALID_REFRESH_TTL');
        }

        if ($this->config['refresh_ttl'] <= $this->config['ttl']) {
            throw new InvalidArgumentException('JWT refresh TTL must be greater than access token TTL', 'JWT_INVALID_TTL_RELATIONSHIP');
        }

        // Validate leeway
        if ($this->config['leeway'] < 0) {
            throw new InvalidArgumentException('JWT leeway cannot be negative', 'JWT_INVALID_LEEWAY');
        }

        // Validate grace period
        if ($this->config['blacklist_grace_period'] < 0) {
            throw new InvalidArgumentException('JWT blacklist grace period cannot be negative', 'JWT_INVALID_GRACE_PERIOD');
        }

        // Validate required claims
        if (!is_array($this->config['required_claims'])) {
            throw new InvalidArgumentException('JWT required claims must be an array', 'JWT_INVALID_REQUIRED_CLAIMS');
        }

        // Validate RSA/ECDSA key requirements
        if (in_array($this->config['algorithm'], ['RS256', 'ES256'])) {
            if (empty($this->config['private_key']) && empty($this->config['public_key'])) {
                throw new InvalidArgumentException(
                    'Private key or public key is required for ' . $this->config['algorithm'] . ' algorithm',
                    'JWT_MISSING_KEYS'
                );
            }
        }
    }
}