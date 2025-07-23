<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenValidator;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenIntrospector;
use LengthOfRope\TreeHouse\Auth\Jwt\RefreshTokenManager;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

if (!function_exists('jwtDecode')) {
    /**
     * Decode a JWT token without validation (for inspection)
     *
     * @param string $token JWT token to decode
     * @param array|null $config Optional JWT configuration
     * @return array Decoded token data or error information
     */
    function jwtDecode(string $token, ?array $config = null): array
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

if (!function_exists('jwtClaims')) {
    /**
     * Extract claims from a JWT token safely
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return array Extracted claims or error information
     */
    function jwtClaims(string $token, ?array $config = null): array
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

if (!function_exists('jwtValid')) {
    /**
     * Check if a JWT token is valid
     *
     * @param string $token JWT token to validate
     * @param array|null $config Optional JWT configuration
     * @return bool True if valid, false otherwise
     */
    function jwtValid(string $token, ?array $config = null): bool
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

if (!function_exists('jwtValidate')) {
    /**
     * Validate a JWT token and return detailed results
     *
     * @param string $token JWT token to validate
     * @param array|null $config Optional JWT configuration
     * @return array Validation results with detailed error information
     */
    function jwtValidate(string $token, ?array $config = null): array
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

if (!function_exists('jwtGenerate')) {
    /**
     * Generate a JWT token for a user
     *
     * @param string|int $userId User ID
     * @param array $claims Additional claims to include
     * @param array|null $config Optional JWT configuration
     * @return string Generated JWT token
     */
    function jwtGenerate(string|int $userId, array $claims = [], ?array $config = null): string
    {
        $jwtConfig = $config ? new JwtConfig($config) : getDefaultJwtConfig();
        $generator = new TokenGenerator($jwtConfig);
        
        return $generator->generateAuthToken($userId, $claims);
    }
}

if (!function_exists('jwtRefresh')) {
    /**
     * Refresh an access token using a refresh token
     *
     * @param string $refreshToken Refresh token
     * @param array $additionalClaims Additional claims for new access token
     * @param array|null $config Optional JWT configuration
     * @return array New token pair or error information
     */
    function jwtRefresh(string $refreshToken, array $additionalClaims = [], ?array $config = null): array
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

if (!function_exists('jwtInfo')) {
    /**
     * Get human-readable information about a JWT token
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return array Human-readable token information
     */
    function jwtInfo(string $token, ?array $config = null): array
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

if (!function_exists('jwtExpired')) {
    /**
     * Check if a JWT token is expired
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return bool True if expired, false if active or invalid
     */
    function jwtExpired(string $token, ?array $config = null): bool
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

if (!function_exists('jwtExpiresIn')) {
    /**
     * Get the number of seconds until a JWT token expires
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return int|null Seconds until expiration, null if invalid/expired
     */
    function jwtExpiresIn(string $token, ?array $config = null): ?int
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

if (!function_exists('jwtUserId')) {
    /**
     * Extract user ID from a JWT token
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return string|null User ID or null if not found
     */
    function jwtUserId(string $token, ?array $config = null): ?string
    {
        try {
            $claims = jwtClaims($token, $config);
            
            if (isset($claims['error'])) {
                return null;
            }
            
            return $claims['standard']['sub'] ?? null;
            
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('jwtCreatePair')) {
    /**
     * Create an access and refresh token pair for a user
     *
     * @param string|int $userId User ID
     * @param array $userClaims User data to include in access token
     * @param array|null $config Optional JWT configuration
     * @return array Token pair with access and refresh tokens
     */
    function jwtCreatePair(string|int $userId, array $userClaims = [], ?array $config = null): array
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

if (!function_exists('jwtCompare')) {
    /**
     * Compare two JWT tokens for similarity
     *
     * @param string $token1 First JWT token
     * @param string $token2 Second JWT token
     * @param array|null $config Optional JWT configuration
     * @return array Comparison results
     */
    function jwtCompare(string $token1, string $token2, ?array $config = null): array
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

if (!function_exists('jwtSecurityCheck')) {
    /**
     * Assess the security characteristics of a JWT token
     *
     * @param string $token JWT token
     * @param array|null $config Optional JWT configuration
     * @return array Security assessment
     */
    function jwtSecurityCheck(string $token, ?array $config = null): array
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
            // Try to get config from application container first
            try {
                if (function_exists('app')) {
                    $authManager = app('auth');
                    if ($authManager && method_exists($authManager, 'getJwtConfig')) {
                        $defaultConfig = $authManager->getJwtConfig();
                        return $defaultConfig;
                    }
                }
            } catch (\Exception $e) {
                // Try fallback auth helper
                try {
                    if (function_exists('auth')) {
                        $authManager = auth();
                        if ($authManager && method_exists($authManager, 'getJwtConfig')) {
                            $defaultConfig = $authManager->getJwtConfig();
                            return $defaultConfig;
                        }
                    }
                } catch (\Exception $e) {
                    // Fall through to environment config
                }
            }
            
            // Fallback to environment variables
            $config = [
                'secret' => \LengthOfRope\TreeHouse\Support\Env::get('JWT_SECRET', 'default-secret-key-change-in-production'),
                'algorithm' => \LengthOfRope\TreeHouse\Support\Env::get('JWT_ALGORITHM', 'HS256'),
                'ttl' => (int) \LengthOfRope\TreeHouse\Support\Env::get('JWT_TTL', 900),
                'refresh_ttl' => (int) \LengthOfRope\TreeHouse\Support\Env::get('JWT_REFRESH_TTL', 604800),
                'issuer' => \LengthOfRope\TreeHouse\Support\Env::get('JWT_ISSUER') ?? \LengthOfRope\TreeHouse\Support\Env::get('APP_NAME', 'TreeHouse'),
                'audience' => \LengthOfRope\TreeHouse\Support\Env::get('JWT_AUDIENCE') ?? \LengthOfRope\TreeHouse\Support\Env::get('APP_URL', 'http://localhost'),
            ];
            
            $defaultConfig = new JwtConfig($config);
        }
        
        return $defaultConfig;
    }
}

if (!function_exists('jwtConfig')) {
    /**
     * Create a JWT configuration from array
     *
     * @param array $config Configuration array
     * @return JwtConfig JWT configuration instance
     */
    function jwtConfig(array $config): JwtConfig
    {
        return new JwtConfig($config);
    }
}