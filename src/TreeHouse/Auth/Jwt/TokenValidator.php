<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT Token Validator
 *
 * High-level utility for validating JWT tokens with specific business logic.
 * Provides validation methods for different token types and use cases.
 *
 * Features:
 * - Token type validation
 * - User-specific token validation
 * - Scope and permission validation
 * - Refresh token validation
 * - Custom validation rules
 * - Blacklist checking integration
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class TokenValidator
{
    /**
     * JWT configuration
     */
    private JwtConfig $config;

    /**
     * JWT decoder
     */
    private JwtDecoder $decoder;

    /**
     * Create a new token validator instance
     *
     * @param JwtConfig $config JWT configuration
     */
    public function __construct(JwtConfig $config)
    {
        $this->config = $config;
        $this->decoder = new JwtDecoder($config);
    }

    /**
     * Validate a token and return claims
     *
     * @param string $token JWT token to validate
     * @return ClaimsManager Validated claims
     * @throws InvalidArgumentException If token is invalid
     */
    public function validate(string $token): ClaimsManager
    {
        return $this->decoder->decode($token, true);
    }

    /**
     * Validate an authentication token
     *
     * @param string $token JWT token
     * @param string|int|null $expectedUserId Expected user ID (optional)
     * @return ClaimsManager Validated claims
     * @throws InvalidArgumentException If token is invalid
     */
    public function validateAuthToken(string $token, string|int|null $expectedUserId = null): ClaimsManager
    {
        $claims = $this->validate($token);

        // Validate token type
        $tokenType = $claims->getClaim('type');
        if ($tokenType !== 'auth') {
            throw new InvalidArgumentException(
                'Invalid token type: expected auth, got ' . ($tokenType ?? 'null'),
                'JWT_INVALID_TOKEN_TYPE'
            );
        }

        // Validate user ID if provided
        if ($expectedUserId !== null) {
            $this->validateUserId($claims, $expectedUserId);
        }

        return $claims;
    }

    /**
     * Validate an API token
     *
     * @param string $token JWT token
     * @param array $requiredScopes Required scopes (optional)
     * @param string|int|null $expectedUserId Expected user ID (optional)
     * @return ClaimsManager Validated claims
     * @throws InvalidArgumentException If token is invalid
     */
    public function validateApiToken(
        string $token,
        array $requiredScopes = [],
        string|int|null $expectedUserId = null
    ): ClaimsManager {
        $claims = $this->validate($token);

        // Validate token type
        $tokenType = $claims->getClaim('type');
        if ($tokenType !== 'api') {
            throw new InvalidArgumentException(
                'Invalid token type: expected api, got ' . ($tokenType ?? 'null'),
                'JWT_INVALID_TOKEN_TYPE'
            );
        }

        // Validate user ID if provided
        if ($expectedUserId !== null) {
            $this->validateUserId($claims, $expectedUserId);
        }

        // Validate scopes if required
        if (!empty($requiredScopes)) {
            $this->validateScopes($claims, $requiredScopes);
        }

        return $claims;
    }

    /**
     * Validate a refresh token
     *
     * @param string $token JWT refresh token
     * @param string|int|null $expectedUserId Expected user ID (optional)
     * @return ClaimsManager Validated claims
     * @throws InvalidArgumentException If token is invalid
     */
    public function validateRefreshToken(string $token, string|int|null $expectedUserId = null): ClaimsManager
    {
        $claims = $this->validate($token);

        // Validate token type
        $tokenType = $claims->getClaim('type');
        if ($tokenType !== 'refresh') {
            throw new InvalidArgumentException(
                'Invalid token type: expected refresh, got ' . ($tokenType ?? 'null'),
                'JWT_INVALID_TOKEN_TYPE'
            );
        }

        // Validate user ID if provided
        if ($expectedUserId !== null) {
            $this->validateUserId($claims, $expectedUserId);
        }

        // Validate JWT ID is present
        $jwtId = $claims->getJwtId();
        if (empty($jwtId)) {
            throw new InvalidArgumentException(
                'Refresh token must have a JWT ID (jti)',
                'JWT_MISSING_JTI'
            );
        }

        return $claims;
    }

    /**
     * Validate a session token
     *
     * @param string $token JWT session token
     * @param string|null $expectedSessionId Expected session ID (optional)
     * @param string|int|null $expectedUserId Expected user ID (optional)
     * @return ClaimsManager Validated claims
     * @throws InvalidArgumentException If token is invalid
     */
    public function validateSessionToken(
        string $token,
        ?string $expectedSessionId = null,
        string|int|null $expectedUserId = null
    ): ClaimsManager {
        $claims = $this->validate($token);

        // Validate token type
        $tokenType = $claims->getClaim('type');
        if ($tokenType !== 'session') {
            throw new InvalidArgumentException(
                'Invalid token type: expected session, got ' . ($tokenType ?? 'null'),
                'JWT_INVALID_TOKEN_TYPE'
            );
        }

        // Validate user ID if provided
        if ($expectedUserId !== null) {
            $this->validateUserId($claims, $expectedUserId);
        }

        // Validate session ID if provided
        if ($expectedSessionId !== null) {
            $sessionId = $claims->getClaim('session_id');
            if ($sessionId !== $expectedSessionId) {
                throw new InvalidArgumentException(
                    'Invalid session ID: expected ' . $expectedSessionId . ', got ' . ($sessionId ?? 'null'),
                    'JWT_INVALID_SESSION_ID'
                );
            }
        }

        return $claims;
    }

    /**
     * Validate a password reset token
     *
     * @param string $token JWT password reset token
     * @param string|null $expectedEmail Expected email (optional)
     * @param string|int|null $expectedUserId Expected user ID (optional)
     * @return ClaimsManager Validated claims
     * @throws InvalidArgumentException If token is invalid
     */
    public function validatePasswordResetToken(
        string $token,
        ?string $expectedEmail = null,
        string|int|null $expectedUserId = null
    ): ClaimsManager {
        $claims = $this->validate($token);

        // Validate token type
        $tokenType = $claims->getClaim('type');
        if ($tokenType !== 'password_reset') {
            throw new InvalidArgumentException(
                'Invalid token type: expected password_reset, got ' . ($tokenType ?? 'null'),
                'JWT_INVALID_TOKEN_TYPE'
            );
        }

        // Validate user ID if provided
        if ($expectedUserId !== null) {
            $this->validateUserId($claims, $expectedUserId);
        }

        // Validate email if provided
        if ($expectedEmail !== null) {
            $email = $claims->getClaim('email');
            if ($email !== $expectedEmail) {
                throw new InvalidArgumentException(
                    'Invalid email: expected ' . $expectedEmail . ', got ' . ($email ?? 'null'),
                    'JWT_INVALID_EMAIL'
                );
            }
        }

        return $claims;
    }

    /**
     * Validate an email verification token
     *
     * @param string $token JWT email verification token
     * @param string|null $expectedEmail Expected email (optional)
     * @param string|int|null $expectedUserId Expected user ID (optional)
     * @return ClaimsManager Validated claims
     * @throws InvalidArgumentException If token is invalid
     */
    public function validateEmailVerificationToken(
        string $token,
        ?string $expectedEmail = null,
        string|int|null $expectedUserId = null
    ): ClaimsManager {
        $claims = $this->validate($token);

        // Validate token type
        $tokenType = $claims->getClaim('type');
        if ($tokenType !== 'email_verification') {
            throw new InvalidArgumentException(
                'Invalid token type: expected email_verification, got ' . ($tokenType ?? 'null'),
                'JWT_INVALID_TOKEN_TYPE'
            );
        }

        // Validate user ID if provided
        if ($expectedUserId !== null) {
            $this->validateUserId($claims, $expectedUserId);
        }

        // Validate email if provided
        if ($expectedEmail !== null) {
            $email = $claims->getClaim('email');
            if ($email !== $expectedEmail) {
                throw new InvalidArgumentException(
                    'Invalid email: expected ' . $expectedEmail . ', got ' . ($email ?? 'null'),
                    'JWT_INVALID_EMAIL'
                );
            }
        }

        return $claims;
    }

    /**
     * Validate user permissions in token
     *
     * @param ClaimsManager $claims Token claims
     * @param array $requiredPermissions Required permissions
     * @return void
     * @throws InvalidArgumentException If permissions are insufficient
     */
    public function validatePermissions(ClaimsManager $claims, array $requiredPermissions): void
    {
        if (empty($requiredPermissions)) {
            return;
        }

        $tokenPermissions = $claims->getClaim('permissions', []);
        if (!is_array($tokenPermissions)) {
            throw new InvalidArgumentException(
                'Token permissions must be an array',
                'JWT_INVALID_PERMISSIONS_FORMAT'
            );
        }

        $missingPermissions = array_diff($requiredPermissions, $tokenPermissions);
        if (!empty($missingPermissions)) {
            throw new InvalidArgumentException(
                'Missing required permissions: ' . implode(', ', $missingPermissions),
                'JWT_INSUFFICIENT_PERMISSIONS'
            );
        }
    }

    /**
     * Validate user roles in token
     *
     * @param ClaimsManager $claims Token claims
     * @param array $requiredRoles Required roles (any one is sufficient)
     * @return void
     * @throws InvalidArgumentException If roles are insufficient
     */
    public function validateRoles(ClaimsManager $claims, array $requiredRoles): void
    {
        if (empty($requiredRoles)) {
            return;
        }

        $tokenRoles = $claims->getClaim('roles', []);
        if (!is_array($tokenRoles)) {
            throw new InvalidArgumentException(
                'Token roles must be an array',
                'JWT_INVALID_ROLES_FORMAT'
            );
        }

        $hasRequiredRole = !empty(array_intersect($requiredRoles, $tokenRoles));
        if (!$hasRequiredRole) {
            throw new InvalidArgumentException(
                'User must have one of the following roles: ' . implode(', ', $requiredRoles),
                'JWT_INSUFFICIENT_ROLES'
            );
        }
    }

    /**
     * Check if token is still valid (not expired or blacklisted)
     *
     * @param string $token JWT token
     * @return bool True if valid
     */
    public function isValid(string $token): bool
    {
        try {
            $this->validate($token);
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Extract claims without validation (for inspection)
     *
     * @param string $token JWT token
     * @return array Token data
     * @throws InvalidArgumentException If token format is invalid
     */
    public function extractClaims(string $token): array
    {
        return $this->decoder->decodeWithoutVerification($token);
    }

    /**
     * Validate user ID in claims
     *
     * @param ClaimsManager $claims Token claims
     * @param string|int $expectedUserId Expected user ID
     * @return void
     * @throws InvalidArgumentException If user ID doesn't match
     */
    private function validateUserId(ClaimsManager $claims, string|int $expectedUserId): void
    {
        $userId = $claims->getClaim('user_id');
        $subject = $claims->getSubject();

        // Check both user_id claim and subject
        if ($userId != $expectedUserId && $subject != $expectedUserId) {
            throw new InvalidArgumentException(
                'Invalid user ID: expected ' . $expectedUserId . ', got ' . ($userId ?? $subject ?? 'null'),
                'JWT_INVALID_USER_ID'
            );
        }
    }

    /**
     * Validate scopes in claims
     *
     * @param ClaimsManager $claims Token claims
     * @param array $requiredScopes Required scopes
     * @return void
     * @throws InvalidArgumentException If scopes are insufficient
     */
    private function validateScopes(ClaimsManager $claims, array $requiredScopes): void
    {
        $tokenScopes = $claims->getClaim('scopes', []);
        if (!is_array($tokenScopes)) {
            throw new InvalidArgumentException(
                'Token scopes must be an array',
                'JWT_INVALID_SCOPES_FORMAT'
            );
        }

        $missingScopes = array_diff($requiredScopes, $tokenScopes);
        if (!empty($missingScopes)) {
            throw new InvalidArgumentException(
                'Missing required scopes: ' . implode(', ', $missingScopes),
                'JWT_INSUFFICIENT_SCOPES'
            );
        }
    }
}