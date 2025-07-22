<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * Refresh Token Manager
 *
 * Manages stateless JWT refresh tokens without database dependencies.
 * Provides secure refresh token generation, validation, and access token renewal.
 *
 * Features:
 * - Pure stateless refresh token management
 * - Token family tracking for security
 * - Automatic refresh token rotation
 * - No database dependencies
 * - Configurable refresh policies
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RefreshTokenManager
{
    /**
     * JWT configuration
     */
    private JwtConfig $config;

    /**
     * Token generator for creating new tokens
     */
    private TokenGenerator $tokenGenerator;

    /**
     * Token validator for validating refresh tokens
     */
    private TokenValidator $tokenValidator;

    /**
     * Configuration for refresh token behavior
     */
    private array $refreshConfig;

    /**
     * Create a new refresh token manager instance
     *
     * @param JwtConfig $config JWT configuration
     * @param array $refreshConfig Refresh token configuration
     */
    public function __construct(JwtConfig $config, array $refreshConfig = [])
    {
        $this->config = $config;
        $this->tokenGenerator = new TokenGenerator($config);
        $this->tokenValidator = new TokenValidator($config);
        
        $this->refreshConfig = array_merge([
            'rotation_enabled' => true,
            'family_tracking' => true,
            'max_refresh_count' => 50,
            'grace_period' => 300, // 5 minutes
        ], $refreshConfig);
    }

    /**
     * Generate a new refresh token for a user
     *
     * @param string|int $userId User ID
     * @param array $metadata Additional metadata to include
     * @return array Refresh token data with token and metadata
     */
    public function generateRefreshToken(string|int $userId, array $metadata = []): array
    {
        // Generate unique token ID for this refresh token
        $tokenId = $this->generateTokenId();
        
        // Generate family ID for token rotation tracking
        $familyId = $metadata['family_id'] ?? $this->generateFamilyId();
        
        // Create refresh token with additional claims
        $refreshClaims = [
            'sub' => (string)$userId,
            'user_id' => $userId,
            'type' => 'refresh',
            'jti' => $tokenId,
            'family_id' => $familyId,
            'refresh_count' => $metadata['refresh_count'] ?? 0,
            'parent_token_id' => $metadata['parent_token_id'] ?? null,
        ];
        
        $refreshToken = $this->tokenGenerator->generateCustomToken($refreshClaims, $this->config->getRefreshTtl());
        
        // Create metadata for tracking
        $tokenMetadata = [
            'token_id' => $tokenId,
            'family_id' => $familyId,
            'user_id' => $userId,
            'issued_at' => time(),
            'refresh_count' => $metadata['refresh_count'] ?? 0,
            'parent_token_id' => $metadata['parent_token_id'] ?? null,
        ];
        
        return [
            'token' => $refreshToken,
            'metadata' => $tokenMetadata,
            'expires_at' => time() + $this->config->getRefreshTtl(),
        ];
    }

    /**
     * Refresh an access token using a refresh token
     *
     * @param string $refreshToken The refresh token
     * @param array $additionalClaims Additional claims for the new access token
     * @return array New access token and optionally new refresh token
     * @throws InvalidArgumentException If refresh token is invalid
     */
    public function refreshAccessToken(string $refreshToken, array $additionalClaims = []): array
    {
        // Validate the refresh token
        $claims = $this->tokenValidator->validateRefreshToken($refreshToken);
        
        // Extract token metadata
        $userId = $claims->getSubject();
        $tokenId = $claims->getJwtId();
        $refreshCount = $claims->getClaim('refresh_count') ?? 0;
        $familyId = $claims->getClaim('family_id');
        
        // Validate refresh count limits
        if ($refreshCount >= $this->refreshConfig['max_refresh_count']) {
            throw new InvalidArgumentException(
                'Refresh token has exceeded maximum refresh count'
            );
        }
        
        // Generate new access token
        $accessToken = $this->tokenGenerator->generateAuthToken(
            $userId,
            $additionalClaims
        );
        
        $result = [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->config->getTtl(),
        ];
        
        // Generate new refresh token if rotation is enabled
        if ($this->refreshConfig['rotation_enabled']) {
            $newRefreshData = $this->generateRefreshToken($userId, [
                'family_id' => $familyId,
                'parent_token_id' => $tokenId,
                'refresh_count' => $refreshCount + 1,
            ]);
            
            $result['refresh_token'] = $newRefreshData['token'];
            $result['refresh_expires_in'] = $this->config->getRefreshTtl();
        }
        
        return $result;
    }

    /**
     * Validate a refresh token without refreshing
     *
     * @param string $refreshToken The refresh token to validate
     * @return array Token metadata and validation status
     */
    public function validateRefreshToken(string $refreshToken): array
    {
        try {
            $claims = $this->tokenValidator->validateRefreshToken($refreshToken);
            
            return [
                'valid' => true,
                'user_id' => $claims->getSubject(),
                'token_id' => $claims->getJwtId(),
                'family_id' => $claims->getClaim('family_id'),
                'issued_at' => $claims->getIssuedAt(),
                'expires_at' => $claims->getExpiration(),
                'refresh_count' => $claims->getClaim('refresh_count') ?? 0,
            ];
            
        } catch (InvalidArgumentException $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Generate access and refresh token pair for a user
     *
     * @param string|int $userId User ID
     * @param array $userClaims User data to include in access token
     * @return array Complete token pair with metadata
     */
    public function generateTokenPair(string|int $userId, array $userClaims = []): array
    {
        // Generate access token
        $accessToken = $this->tokenGenerator->generateAuthToken($userId, $userClaims);
        
        // Generate refresh token
        $refreshData = $this->generateRefreshToken($userId);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshData['token'],
            'token_type' => 'Bearer',
            'expires_in' => $this->config->getTtl(),
            'refresh_expires_in' => $this->config->getRefreshTtl(),
            'metadata' => $refreshData['metadata'],
        ];
    }

    /**
     * Check if a refresh token is near expiration
     *
     * @param string $refreshToken The refresh token
     * @param int $threshold Threshold in seconds before expiration
     * @return bool True if token expires within threshold
     */
    public function isNearExpiration(string $refreshToken, int $threshold = 3600): bool
    {
        try {
            $claims = $this->tokenValidator->validateRefreshToken($refreshToken);
            $expiresAt = $claims->getExpiration();
            
            return ($expiresAt - time()) <= $threshold;
            
        } catch (InvalidArgumentException $e) {
            return true; // Consider invalid tokens as expired
        }
    }

    /**
     * Get refresh token metadata without validation
     *
     * @param string $refreshToken The refresh token
     * @return array Token metadata (for debugging/inspection)
     */
    public function getTokenMetadata(string $refreshToken): array
    {
        try {
            $decoder = new JwtDecoder($this->config);
            $tokenData = $decoder->decodeWithoutVerification($refreshToken);
            
            $payload = $tokenData['payload'];
            
            return [
                'user_id' => $payload['sub'] ?? null,
                'token_id' => $payload['jti'] ?? null,
                'family_id' => $payload['family_id'] ?? null,
                'issued_at' => $payload['iat'] ?? null,
                'expires_at' => $payload['exp'] ?? null,
                'refresh_count' => $payload['refresh_count'] ?? 0,
                'token_type' => $payload['type'] ?? null,
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to decode token',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get refresh token configuration
     *
     * @return array Current refresh configuration
     */
    public function getConfig(): array
    {
        return $this->refreshConfig;
    }

    /**
     * Update refresh token configuration
     *
     * @param array $config New configuration values
     * @return void
     */
    public function updateConfig(array $config): void
    {
        $this->refreshConfig = array_merge($this->refreshConfig, $config);
    }

    /**
     * Generate a unique token ID
     *
     * @return string Unique token identifier
     */
    private function generateTokenId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate a unique family ID for token rotation
     *
     * @return string Unique family identifier
     */
    private function generateFamilyId(): string
    {
        return bin2hex(random_bytes(12));
    }

    /**
     * Create a refresh token manager with custom configuration
     *
     * @param array $jwtConfig JWT configuration
     * @param array $refreshConfig Refresh token configuration
     * @return self New refresh token manager instance
     */
    public static function create(array $jwtConfig, array $refreshConfig = []): self
    {
        $config = new JwtConfig($jwtConfig);
        return new self($config, $refreshConfig);
    }
}