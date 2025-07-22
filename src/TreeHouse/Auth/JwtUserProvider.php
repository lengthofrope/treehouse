<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenValidator;
use LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager;
use LengthOfRope\TreeHouse\Security\Hash;
use InvalidArgumentException;

/**
 * JWT User Provider
 *
 * Provides stateless user resolution from JWT tokens. This provider
 * can work in two modes:
 * 1. Pure stateless mode: User data embedded in JWT claims
 * 2. Hybrid mode: User ID in JWT, additional data from database
 *
 * Features:
 * - Stateless user resolution from JWT payload
 * - Support for embedded user data in JWT claims
 * - Fallback to database provider for hybrid scenarios
 * - Integration with existing UserProvider interface
 * - Configurable user data inclusion in tokens
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
     * The hash instance for password validation
     */
    protected Hash $hash;

    /**
     * The fallback user provider for hybrid mode
     */
    protected ?UserProvider $fallbackProvider = null;

    /**
     * Configuration for this provider
     */
    protected array $config;

    /**
     * Create a new JwtUserProvider instance
     *
     * @param JwtConfig $jwtConfig JWT configuration
     * @param Hash $hash Hash instance for password validation
     * @param array $config Provider configuration
     * @param UserProvider|null $fallbackProvider Fallback provider for hybrid mode
     */
    public function __construct(
        JwtConfig $jwtConfig,
        Hash $hash,
        array $config = [],
        ?UserProvider $fallbackProvider = null
    ) {
        $this->jwtConfig = $jwtConfig;
        $this->tokenValidator = new TokenValidator($jwtConfig);
        $this->hash = $hash;
        $this->config = array_merge([
            'mode' => 'stateless', // 'stateless' or 'hybrid'
            'user_claim' => 'user_data',
            'embed_user_data' => false,
            'required_user_fields' => ['id', 'email'],
        ], $config);
        $this->fallbackProvider = $fallbackProvider;
    }

    /**
     * Retrieve a user by their unique identifier
     *
     * For JWT provider, this expects the identifier to be a JWT token
     * or uses fallback provider in hybrid mode
     *
     * @param mixed $identifier User identifier (JWT token or user ID)
     * @return mixed User instance or null if not found
     */
    public function retrieveById(mixed $identifier): mixed
    {
        // In hybrid mode, delegate to fallback provider for user ID lookups
        if ($this->config['mode'] === 'hybrid' && $this->fallbackProvider) {
            if (!$this->isJwtToken($identifier)) {
                return $this->fallbackProvider->retrieveById($identifier);
            }
        }

        // Handle JWT token identifier
        if ($this->isJwtToken($identifier)) {
            return $this->retrieveFromJwt($identifier);
        }

        // In pure stateless mode, we can't retrieve by ID without fallback
        if ($this->config['mode'] === 'stateless') {
            return null;
        }

        // Use fallback provider if available
        if ($this->fallbackProvider) {
            return $this->fallbackProvider->retrieveById($identifier);
        }

        return null;
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token
     *
     * JWT authentication doesn't use remember tokens in the traditional sense
     * This delegates to fallback provider if available
     *
     * @param mixed $identifier User identifier
     * @param string $token Remember me token
     * @return mixed User instance or null if not found
     */
    public function retrieveByToken(mixed $identifier, string $token): mixed
    {
        // JWT doesn't use remember tokens, delegate to fallback
        if ($this->fallbackProvider) {
            return $this->fallbackProvider->retrieveByToken($identifier, $token);
        }

        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage
     *
     * JWT authentication doesn't use remember tokens in the traditional sense
     * This delegates to fallback provider if available
     *
     * @param mixed $user User instance
     * @param string $token New remember me token
     * @return void
     */
    public function updateRememberToken(mixed $user, string $token): void
    {
        // JWT doesn't use remember tokens, delegate to fallback
        if ($this->fallbackProvider) {
            $this->fallbackProvider->updateRememberToken($user, $token);
        }
    }

    /**
     * Retrieve a user by the given credentials
     *
     * This method validates credentials against JWT tokens or
     * delegates to fallback provider for traditional login
     *
     * @param array $credentials User credentials (email/password or JWT token)
     * @return mixed User instance or null if not found
     */
    public function retrieveByCredentials(array $credentials): mixed
    {
        // Check if JWT token is provided
        if (isset($credentials['token']) && $this->isJwtToken($credentials['token'])) {
            return $this->retrieveFromJwt($credentials['token']);
        }

        // For traditional email/password credentials, use fallback provider
        if ($this->fallbackProvider && (isset($credentials['email']) || isset($credentials['username']))) {
            return $this->fallbackProvider->retrieveByCredentials($credentials);
        }

        return null;
    }

    /**
     * Validate a user against the given credentials
     *
     * For JWT provider, this validates the JWT token signature and claims
     * For traditional credentials, delegates to fallback provider
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

        // For password validation, use fallback provider or hash directly
        if (isset($credentials['password'])) {
            if ($this->fallbackProvider) {
                return $this->fallbackProvider->validateCredentials($user, $credentials);
            }

            // Direct password validation
            $userPassword = $this->getUserPassword($user);
            if ($userPassword === null) {
                return false;
            }

            return $this->hash->check($credentials['password'], $userPassword);
        }

        return false;
    }

    /**
     * Rehash the user's password if required
     *
     * Delegates to fallback provider if available
     *
     * @param mixed $user User instance
     * @param array $credentials User credentials
     * @param bool $force Force rehashing even if not required
     * @return void
     */
    public function rehashPasswordIfRequired(mixed $user, array $credentials, bool $force = false): void
    {
        if ($this->fallbackProvider) {
            $this->fallbackProvider->rehashPasswordIfRequired($user, $credentials, $force);
        }
    }

    /**
     * Set the fallback user provider for hybrid mode
     *
     * @param UserProvider $provider Fallback user provider
     * @return void
     */
    public function setFallbackProvider(UserProvider $provider): void
    {
        $this->fallbackProvider = $provider;
    }

    /**
     * Get the fallback user provider
     *
     * @return UserProvider|null
     */
    public function getFallbackProvider(): ?UserProvider
    {
        return $this->fallbackProvider;
    }

    /**
     * Check if the provider is in hybrid mode
     *
     * @return bool
     */
    public function isHybridMode(): bool
    {
        return $this->config['mode'] === 'hybrid';
    }

    /**
     * Check if the provider is in stateless mode
     *
     * @return bool
     */
    public function isStatelessMode(): bool
    {
        return $this->config['mode'] === 'stateless';
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
            
            // In hybrid mode, get additional user data from database
            if ($this->config['mode'] === 'hybrid' && $this->fallbackProvider) {
                $userId = $claims->getSubject();
                $user = $this->fallbackProvider->retrieveById($userId);
                
                if ($user) {
                    // Add JWT claims to user data
                    if (is_array($user)) {
                        $user['jwt_claims'] = $claims->getAllClaims();
                    } elseif (is_object($user)) {
                        $user->jwt_claims = $claims->getAllClaims();
                    }
                }
                
                return $user;
            }

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
     * @return mixed User instance
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

    /**
     * Get user password from user instance
     *
     * @param mixed $user User instance
     * @return string|null User password hash
     */
    protected function getUserPassword(mixed $user): ?string
    {
        if (is_object($user)) {
            if (method_exists($user, 'getAuthPassword')) {
                return $user->getAuthPassword();
            }
            
            return $user->password ?? null;
        }

        if (is_array($user)) {
            return $user['password'] ?? null;
        }

        return null;
    }
}