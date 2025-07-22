<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenValidator;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenIntrospector;
use LengthOfRope\TreeHouse\Auth\Jwt\RefreshTokenManager;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

if (!function_exists('jwt_decode')) {
    /**
     * Decode a JWT token without validation (for inspection)
     *
     * @param string $token JWT token to decode
     * @param array|null $config Optional JWT configuration
     * @return array Decoded token data or error information
     */
    function jwt_decode(string $token, ?array $config = null): array
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $introspector = new TokenIntrospector($jwtConfig);
            
            return $introspector->introspect($token);
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Failed to decode token',
                'details' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('jwt_claims')) {
    /**
     * Extract claims from a JWT token safely
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return array Extracted claims or error information
     */
    function jwt_claims(string $token, ?array $config = null): array
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $introspector = new TokenIntrospector($jwtConfig);
            
            return $introspector->extractClaims($token);
            
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to extract claims',
                'details' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('jwt_valid')) {
    /**
     * Check if a JWT token is valid
     *
     * @param string $token JWT token to validate
     * @param array|null $config Optional JWT configuration
     * @return bool True if valid, false otherwise
     */
    function jwt_valid(string $token, ?array $config = null): bool
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $validator = new TokenValidator($jwtConfig);
            
            $validator->validate($token);
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('jwt_validate')) {
    /**
     * Validate a JWT token and return detailed results
     *
     * @param string $token JWT token to validate
     * @param array|null $config Optional JWT configuration
     * @return array Validation results with detailed error information
     */
    function jwt_validate(string $token, ?array $config = null): array
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $validator = new TokenValidator($jwtConfig);
            
            $claims = $validator->validate($token);
            
            return [
                'valid' => true,
                'claims' => $claims->getAllClaims(),
                'user_id' => $claims->getSubject(),
                'expires_at' => $claims->getExpiration(),
                'issued_at' => $claims->getIssuedAt(),
            ];
            
        } catch (InvalidArgumentException $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Validation failed',
                'details' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('jwt_generate')) {
    /**
     * Generate a JWT token for a user
     *
     * @param string|int $userId User ID
     * @param array $claims Additional claims to include
     * @param array|null $config Optional JWT configuration
     * @return string Generated JWT token
     */
    function jwt_generate(string|int $userId, array $claims = [], ?array $config = null): string
    {
        $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
        $generator = new TokenGenerator($jwtConfig);
        
        return $generator->generateAuthToken($userId, $claims);
    }
}

if (!function_exists('jwt_refresh')) {
    /**
     * Refresh an access token using a refresh token
     *
     * @param string $refreshToken Refresh token
     * @param array $additionalClaims Additional claims for new access token
     * @param array|null $config Optional JWT configuration
     * @return array New token pair or error information
     */
    function jwt_refresh(string $refreshToken, array $additionalClaims = [], ?array $config = null): array
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $refreshManager = new RefreshTokenManager($jwtConfig);
            
            return $refreshManager->refreshAccessToken($refreshToken, $additionalClaims);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to refresh token',
                'details' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('jwt_info')) {
    /**
     * Get human-readable information about a JWT token
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return array Human-readable token information
     */
    function jwt_info(string $token, ?array $config = null): array
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $introspector = new TokenIntrospector($jwtConfig);
            
            return $introspector->getTokenInfo($token);
            
        } catch (\Exception $e) {
            return [
                'summary' => 'Invalid token',
                'error' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('jwt_expired')) {
    /**
     * Check if a JWT token is expired
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return bool True if expired, false if active or invalid
     */
    function jwt_expired(string $token, ?array $config = null): bool
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $introspector = new TokenIntrospector($jwtConfig);
            
            $timing = $introspector->getTimingInfo($token);
            return isset($timing['status']) && $timing['status'] === 'expired';
            
        } catch (\Exception $e) {
            return true; // Consider invalid tokens as expired
        }
    }
}

if (!function_exists('jwt_expires_in')) {
    /**
     * Get the number of seconds until a JWT token expires
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return int|null Seconds until expiration, null if invalid/expired
     */
    function jwt_expires_in(string $token, ?array $config = null): ?int
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $introspector = new TokenIntrospector($jwtConfig);
            
            $timing = $introspector->getTimingInfo($token);
            
            if (isset($timing['status']) && $timing['status'] === 'active' && isset($timing['expires_in_seconds'])) {
                return max(0, $timing['expires_in_seconds']);
            }
            
            return null;
            
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('jwt_user_id')) {
    /**
     * Extract user ID from a JWT token
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return string|null User ID or null if not found
     */
    function jwt_user_id(string $token, ?array $config = null): ?string
    {
        try {
            $claims = jwt_claims($token, $config);
            
            if (isset($claims['error'])) {
                return null;
            }
            
            return $claims['standard']['sub'] ?? null;
            
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('jwt_create_pair')) {
    /**
     * Create an access and refresh token pair for a user
     *
     * @param string|int $userId User ID
     * @param array $userClaims User data to include in access token
     * @param array|null $config Optional JWT configuration
     * @return array Token pair with access and refresh tokens
     */
    function jwt_create_pair(string|int $userId, array $userClaims = [], ?array $config = null): array
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $refreshManager = new RefreshTokenManager($jwtConfig);
            
            return $refreshManager->generateTokenPair($userId, $userClaims);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to create token pair',
                'details' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('jwt_compare')) {
    /**
     * Compare two JWT tokens for similarity
     *
     * @param string $token1 First JWT token
     * @param string $token2 Second JWT token
     * @param array|null $config Optional JWT configuration
     * @return array Comparison results
     */
    function jwt_compare(string $token1, string $token2, ?array $config = null): array
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $introspector = new TokenIntrospector($jwtConfig);
            
            return $introspector->compareTokens($token1, $token2);
            
        } catch (\Exception $e) {
            return [
                'comparable' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('jwt_security_check')) {
    /**
     * Assess the security characteristics of a JWT token
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return array Security assessment
     */
    function jwt_security_check(string $token, ?array $config = null): array
    {
        try {
            $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
            $introspector = new TokenIntrospector($jwtConfig);
            
            return $introspector->assessTokenSecurity($token);
            
        } catch (\Exception $e) {
            return [
                'score' => 0,
                'level' => 'critical',
                'warnings' => ['Failed to assess token security'],
                'recommendations' => ['Ensure token is valid'],
                'error' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('getDefaultJwtConfig')) {
    /**
     * Get default JWT configuration from auth manager or environment
     *
     * @return JwtConfig Default JWT configuration
     */
    function getDefaultJwtConfig(): JwtConfig
    {
        static $defaultConfig = null;
        
        if ($defaultConfig === null) {
            // Try to get config from auth manager if available
            try {
                if (function_exists('auth')) {
                    $authManager = auth();
                    if ($authManager && method_exists($authManager, 'getJwtConfig')) {
                        $defaultConfig = $authManager->getJwtConfig();
                        return $defaultConfig;
                    }
                }
            } catch (\Exception $e) {
                // Fall through to environment config if auth manager is not available
            }
            
            // Fallback to environment variables
            $config = [
                'secret' => $_ENV['JWT_SECRET'] ?? 'default-secret-key-change-in-production',
                'algorithm' => $_ENV['JWT_ALGORITHM'] ?? 'HS256',
                'ttl' => (int) ($_ENV['JWT_TTL'] ?? 900),
                'refresh_ttl' => (int) ($_ENV['JWT_REFRESH_TTL'] ?? 604800),
                'issuer' => $_ENV['JWT_ISSUER'] ?? $_ENV['APP_NAME'] ?? 'TreeHouse',
                'audience' => $_ENV['JWT_AUDIENCE'] ?? $_ENV['APP_URL'] ?? 'http://localhost',
            ];
            
            $defaultConfig = new JwtConfig($config);
        }
        
        return $defaultConfig;
    }
}

if (!function_exists('jwt_config')) {
    /**
     * Create a JWT configuration from array
     *
     * @param array $config Configuration array
     * @return JwtConfig JWT configuration instance
     */
    function jwt_config(array $config): JwtConfig
    {
        return new JwtConfig($config);
    }
}