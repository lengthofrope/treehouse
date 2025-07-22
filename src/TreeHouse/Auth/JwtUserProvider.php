<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenValidator;
use LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT User Provider
 *
 * Provides pure stateless user resolution from JWT tokens.
 * This provider operates in stateless mode only, with user data
 * embedded in JWT claims.
 *
 * Features:
 * - Pure stateless user resolution from JWT payload
 * - User data embedded in JWT claims
 * - Integration with existing UserProvider interface
 * - Enhanced error handling and validation
 * - Simplified configuration
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtUserProvider implements UserProvider
{
    /**
     * The JWT configuration
     */
    protected JwtConfig $jwtConfig;

    /**
     * The token validator instance
     */
    protected TokenValidator $tokenValidator;

    /**
     * Configuration for this provider
     */
    protected array $config;

    /**
     * Create a new JwtUserProvider instance
     *
     * @param JwtConfig $jwtConfig JWT configuration
     * @param array $config Provider configuration
     */
    public function __construct(JwtConfig $jwtConfig, array $config = [])
    {
        $this->jwtConfig = $jwtConfig;
        $this->tokenValidator = new TokenValidator($jwtConfig);
        $this->config = array_merge([
            'user_claim' => 'user',
            'embed_user_data' => true,
            'required_user_fields' => ['id', 'email'],
        ], $config);
    }

    /**
     * Retrieve a user by their unique identifier
     *
     * For JWT provider, this expects the identifier to be a JWT token.
     * In pure stateless mode, we cannot retrieve by user ID without a token.
     *
     * @param mixed $identifier User identifier (JWT token expected)
     * @return mixed User instance or null if not found
     */
    public function retrieveById(mixed $identifier): mixed
    {
        // Handle JWT token identifier
        if ($this->isJwtToken($identifier)) {
            return $this->retrieveFromJwt($identifier);
        }

        // In pure stateless mode, we can't retrieve by ID without a token
        return null;
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token
     *
     * JWT authentication doesn't use remember tokens in the traditional sense.
     * Always returns null in stateless mode.
     *
     * @param mixed $identifier User identifier
     * @param string $token Remember me token
     * @return mixed Always returns null for stateless JWT
     */
    public function retrieveByToken(mixed $identifier, string $token): mixed
    {
        // JWT doesn't use remember tokens in stateless mode
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage
     *
     * JWT authentication doesn't use remember tokens in the traditional sense.
     * No-op in stateless mode.
     *
     * @param mixed $user User instance
     * @param string $token New remember me token
     * @return void
     */
    public function updateRememberToken(mixed $user, string $token): void
    {
        // JWT doesn't use remember tokens in stateless mode
        // No operation needed
    }

    /**
     * Retrieve a user by the given credentials
     *
     * This method validates credentials against JWT tokens only.
     * For stateless JWT, only token-based authentication is supported.
     *
     * @param array $credentials User credentials (JWT token expected)
     * @return mixed User instance or null if not found
     */
    public function retrieveByCredentials(array $credentials): mixed
    {
        // Check if JWT token is provided
        if (isset($credentials['token']) && $this->isJwtToken($credentials['token'])) {
            return $this->retrieveFromJwt($credentials['token']);
        }

        // Stateless JWT provider only supports token-based authentication
        return null;
    }

    /**
     * Validate a user against the given credentials
     *
     * For JWT provider, this validates the JWT token signature and claims.
     * Only JWT token validation is supported in stateless mode.
     *
     * @param mixed $user User instance
     * @param array $credentials User credentials
     * @return bool True if credentials are valid, false otherwise
     */
    public function validateCredentials(mixed $user, array $credentials): bool
    {
        // If JWT token is provided, validate it
        if (isset($credentials['token']) && $this->isJwtToken($credentials['token'])) {
            try {
                $claims = $this->tokenValidator->validateAuthToken($credentials['token']);
                $tokenUserId = $claims->getSubject();
                $userUserId = $this->getUserId($user);
                
                return $tokenUserId == $userUserId;
            } catch (InvalidArgumentException $e) {
                return false;
            }
        }

        // Stateless JWT provider only supports token validation
        return false;
    }

    /**
     * Rehash the user's password if required
     *
     * JWT authentication doesn't use passwords in stateless mode.
     * No-op for stateless JWT.
     *
     * @param mixed $user User instance
     * @param array $credentials User credentials
     * @param bool $force Force rehashing even if not required
     * @return void
     */
    public function rehashPasswordIfRequired(mixed $user, array $credentials, bool $force = false): void
    {
        // JWT doesn't use passwords in stateless mode
        // No operation needed
    }

    /**
     * Check if the provider is in stateless mode
     *
     * @return bool Always true for this implementation
     */
    public function isStatelessMode(): bool
    {
        return true;
    }

    /**
     * Create a user instance from JWT claims
     *
     * @param ClaimsManager $claims JWT claims
     * @return mixed User instance or null
     */
    public function createUserFromClaims(ClaimsManager $claims): mixed
    {
        $userId = $claims->getSubject();
        if ($userId === null) {
            return null;
        }

        // Check if user data is embedded in claims
        $userData = $claims->getClaim($this->config['user_claim']);
        if ($userData && is_array($userData)) {
            return $this->createUserFromData($userData);
        }

        // Create minimal user with just ID and claims
        $user = [
            'id' => $userId,
            'jwt_claims' => $claims->getAllClaims()
        ];

        // Add any custom claims as user properties
        $customClaims = $claims->getCustomClaims();
        foreach ($customClaims as $key => $value) {
            if (!isset($user[$key])) {
                $user[$key] = $value;
            }
        }

        return $user;
    }

    /**
     * Get the JWT configuration
     *
     * @return JwtConfig
     */
    public function getJwtConfig(): JwtConfig
    {
        return $this->jwtConfig;
    }

    /**
     * Get the provider configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if a string appears to be a JWT token
     *
     * @param mixed $token Potential JWT token
     * @return bool
     */
    protected function isJwtToken(mixed $token): bool
    {
        if (!is_string($token)) {
            return false;
        }

        // JWT tokens have 3 parts separated by dots
        $parts = explode('.', $token);
        return count($parts) === 3 && 
               !empty($parts[0]) && 
               !empty($parts[1]) && 
               !empty($parts[2]);
    }

    /**
     * Retrieve user from JWT token
     *
     * @param string $token JWT token
     * @return mixed User instance or null
     */
    protected function retrieveFromJwt(string $token): mixed
    {
        try {
            $claims = $this->tokenValidator->validateAuthToken($token);
            
            // In stateless mode, create user from claims
            return $this->createUserFromClaims($claims);
            
        } catch (InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * Create user instance from user data array
     *
     * @param array $userData User data
     * @return mixed User instance or null if validation fails
     */
    protected function createUserFromData(array $userData): mixed
    {
        // Validate required fields
        foreach ($this->config['required_user_fields'] as $field) {
            if (!isset($userData[$field])) {
                return null;
            }
        }

        return $userData;
    }

    /**
     * Get user ID from user instance
     *
     * @param mixed $user User instance
     * @return mixed User ID
     */
    protected function getUserId(mixed $user): mixed
    {
        if (is_object($user)) {
            if (method_exists($user, 'getAuthIdentifier')) {
                return $user->getAuthIdentifier();
            }
            
            return $user->id ?? null;
        }

        if (is_array($user)) {
            return $user['id'] ?? null;
        }

        return null;
    }
}