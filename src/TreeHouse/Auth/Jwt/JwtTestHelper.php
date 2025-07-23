<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * JWT Testing Helper
 *
 * Provides utilities for testing JWT functionality including token creation,
 * validation testing, mock configurations, and assertion helpers. Designed
 * to simplify JWT testing in unit and integration tests.
 *
 * Features:
 * - Test token generation
 * - Mock JWT configurations
 * - Expired/invalid token creation
 * - Test assertions
 * - Time manipulation helpers
 * - Mock user data
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtTestHelper
{
    /**
     * Default test configuration
     */
    private const TEST_DEFAULTS = [
        'secret' => 'test-secret-key-for-jwt-testing-32chars',
        'algorithm' => 'HS256',
        'ttl' => 3600,
        'refresh_ttl' => 86400,
        'issuer' => 'treehouse-test',
        'audience' => 'treehouse-test',
        'subject' => 'test-subject',
        // RS256 test keys
        'private_key' => '-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKB
UmHhIXbhF5P6k9B8L+dP7eK5n5n6iYO1yO1VPAcQR8hqh1Gg1Oz0X1c7qdqF5g
TEST-RSA-PRIVATE-KEY-FOR-TESTING-ONLY
-----END PRIVATE KEY-----',
        'public_key' => '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAu1SU1L7VLPHCgVJh4SF2
4ReT+pPQfC/nT+3iuZ+Z+omDtcjtVTwHEEfIaodRoNTs9F9XO6naheY
TEST-RSA-PUBLIC-KEY-FOR-TESTING-ONLY
-----END PUBLIC KEY-----',
    ];

    /**
     * Test users data
     */
    private const TEST_USERS = [
        'user1' => [
            'id' => 1,
            'email' => 'user1@test.com',
            'name' => 'Test User 1',
            'role' => 'user',
            'permissions' => ['read'],
        ],
        'user2' => [
            'id' => 2,
            'email' => 'user2@test.com',
            'name' => 'Test User 2',
            'role' => 'admin',
            'permissions' => ['read', 'write', 'delete'],
        ],
        'user3' => [
            'id' => 3,
            'email' => 'user3@test.com',
            'name' => 'Test User 3',
            'role' => 'moderator',
            'permissions' => ['read', 'write'],
        ],
    ];

    private static ?JwtConfig $testConfig = null;
    private static ?Carbon $mockTime = null;

    /**
     * Create test JWT configuration
     *
     * @param array $overrides Configuration overrides
     * @return JwtConfig Test configuration
     */
    public static function createTestConfig(array $overrides = []): JwtConfig
    {
        $config = array_merge(self::TEST_DEFAULTS, $overrides);
        return new JwtConfig($config);
    }

    /**
     * Get default test configuration
     *
     * @return JwtConfig Default test configuration
     */
    public static function getTestConfig(): JwtConfig
    {
        if (self::$testConfig === null) {
            self::$testConfig = self::createTestConfig();
        }
        return self::$testConfig;
    }

    /**
     * Create test token for user
     *
     * @param string|int $userId User identifier
     * @param array $customClaims Additional claims
     * @param array $configOverrides Configuration overrides
     * @return string Test JWT token
     */
    public static function createTestToken(
        string|int $userId = 1,
        array $customClaims = [],
        array $configOverrides = []
    ): string {
        $config = self::createTestConfig($configOverrides);
        $generator = new TokenGenerator($config);
        
        $baseClaims = [
            'user_id' => $userId,
            'email' => self::getTestUser($userId)['email'] ?? "user{$userId}@test.com",
        ];
        
        // Merge custom claims with base claims and generate custom token
        $allClaims = array_merge($baseClaims, $customClaims);
        
        return $generator->generateCustomToken($allClaims);
    }

    /**
     * Create expired test token
     *
     * @param string|int $userId User identifier
     * @param int $expiredBySeconds How many seconds ago the token expired
     * @param array $customClaims Additional claims
     * @return string Expired JWT token
     */
    public static function createExpiredToken(
        string|int $userId = 1,
        int $expiredBySeconds = 3600,
        array $customClaims = []
    ): string {
        $now = Carbon::now()->getTimestamp();
        $expiredTime = $now - $expiredBySeconds;
        
        // Use valid config, but create expired claims manually
        $config = self::getTestConfig();
        $claims = new ClaimsManager();
        
        // Set standard claims with expired time
        $claims->setIssuer($config->getIssuer());
        $claims->setAudience($config->getAudience());
        $claims->setSubject((string)$userId);
        $claims->setIssuedAt($expiredTime - 1000);
        $claims->setExpiration($expiredTime);
        $claims->setNotBefore($expiredTime - 1000);
        $claims->setJwtId(bin2hex(random_bytes(16)));
        
        // Set custom claims
        $claims->setClaim('user_id', $userId);
        foreach ($customClaims as $name => $value) {
            $claims->setClaim($name, $value);
        }
        
        $encoder = new JwtEncoder($config);
        return $encoder->encode($claims);
    }

    /**
     * Create token that's not yet valid
     *
     * @param string|int $userId User identifier
     * @param int $validInSeconds How many seconds until the token becomes valid
     * @param array $customClaims Additional claims
     * @return string Not-yet-valid JWT token
     */
    public static function createNotYetValidToken(
        string|int $userId = 1,
        int $validInSeconds = 3600,
        array $customClaims = []
    ): string {
        $now = Carbon::now()->getTimestamp();
        $futureTime = $now + $validInSeconds;
        
        $config = self::getTestConfig();
        $claims = new ClaimsManager();
        
        // Set standard claims with future not-before time
        $claims->setIssuer($config->getIssuer());
        $claims->setAudience($config->getAudience());
        $claims->setSubject((string)$userId);
        $claims->setIssuedAt($now);
        $claims->setExpiration($now + $config->getTtl());
        $claims->setNotBefore($futureTime);
        $claims->setJwtId(bin2hex(random_bytes(16)));
        
        // Set custom claims
        $claims->setClaim('user_id', $userId);
        foreach ($customClaims as $name => $value) {
            $claims->setClaim($name, $value);
        }
        
        $encoder = new JwtEncoder($config);
        return $encoder->encode($claims);
    }

    /**
     * Create malformed token
     *
     * @param string $type Type of malformation (missing_signature, invalid_json, wrong_parts, etc.)
     * @return string Malformed JWT token
     */
    public static function createMalformedToken(string $type = 'missing_signature'): string
    {
        $validToken = self::createTestToken();
        $parts = explode('.', $validToken);
        
        return match ($type) {
            'missing_signature' => $parts[0] . '.' . $parts[1],
            'extra_part' => $validToken . '.extra',
            'invalid_header' => 'invalid-header.' . $parts[1] . '.' . $parts[2],
            'invalid_payload' => $parts[0] . '.invalid-payload.' . $parts[2],
            'empty_parts' => '..',
            'wrong_signature' => $parts[0] . '.' . $parts[1] . '.wrong-signature',
            default => $validToken,
        };
    }

    /**
     * Create token with invalid signature
     *
     * @param string|int $userId User identifier
     * @param array $customClaims Additional claims
     * @return string Token with invalid signature
     */
    public static function createTokenWithInvalidSignature(
        string|int $userId = 1,
        array $customClaims = []
    ): string {
        // Create token with different secret
        $config = self::createTestConfig(['secret' => 'different-secret-key-for-invalid-sig']);
        $generator = new TokenGenerator($config);
        
        $baseClaims = [
            'user_id' => $userId,
            'email' => self::getTestUser($userId)['email'] ?? "user{$userId}@test.com",
        ];
        
        // Merge custom claims with base claims and generate custom token
        $allClaims = array_merge($baseClaims, $customClaims);
        
        return $generator->generateCustomToken($allClaims);
    }

    /**
     * Get test user data
     *
     * @param string|int|null $userId User identifier or null for all users
     * @return array Test user data
     */
    public static function getTestUser(string|int|null $userId = null): array
    {
        if ($userId === null) {
            return self::TEST_USERS;
        }
        
        $userKey = is_string($userId) ? $userId : "user{$userId}";
        return self::TEST_USERS[$userKey] ?? [
            'id' => $userId,
            'email' => "user{$userId}@test.com",
            'name' => "Test User {$userId}",
            'role' => 'user',
            'permissions' => ['read'],
        ];
    }

    /**
     * Assert token is valid
     *
     * @param string $token JWT token to validate
     * @param JwtConfig|null $config JWT configuration (uses test config if null)
     * @return ClaimsManager Validated claims
     * @throws \Exception If token is invalid
     */
    public static function assertTokenValid(string $token, ?JwtConfig $config = null): ClaimsManager
    {
        $config = $config ?? self::getTestConfig();
        $validator = new TokenValidator($config);
        
        try {
            return $validator->validate($token);
        } catch (\Exception $e) {
            throw new \Exception("Token validation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Assert token is invalid
     *
     * @param string $token JWT token to validate
     * @param JwtConfig|null $config JWT configuration (uses test config if null)
     * @param string|null $expectedError Expected error message pattern
     * @return void
     * @throws \Exception If token is unexpectedly valid
     */
    public static function assertTokenInvalid(
        string $token,
        ?JwtConfig $config = null,
        ?string $expectedError = null
    ): void {
        $config = $config ?? self::getTestConfig();
        $validator = new TokenValidator($config);
        
        try {
            $validator->validate($token);
            throw new \Exception("Token was expected to be invalid but is valid");
        } catch (\Exception $e) {
            if ($expectedError && !str_contains($e->getMessage(), $expectedError)) {
                throw new \Exception(
                    "Token failed validation but with unexpected error. Expected: {$expectedError}, Got: {$e->getMessage()}"
                );
            }
            // Expected behavior - token is invalid
        }
    }

    /**
     * Assert token has specific claim
     *
     * @param string $token JWT token
     * @param string $claimName Claim name
     * @param mixed $expectedValue Expected claim value (null to just check existence)
     * @param JwtConfig|null $config JWT configuration
     * @return void
     * @throws \Exception If claim assertion fails
     */
    public static function assertTokenHasClaim(
        string $token,
        string $claimName,
        mixed $expectedValue = null,
        ?JwtConfig $config = null
    ): void {
        $claims = self::assertTokenValid($token, $config);
        
        if (!$claims->hasClaim($claimName)) {
            throw new \Exception("Token does not have claim: {$claimName}");
        }
        
        if ($expectedValue !== null) {
            $actualValue = $claims->getClaim($claimName);
            if ($actualValue !== $expectedValue) {
                throw new \Exception(
                    "Claim {$claimName} has unexpected value. Expected: " . 
                    json_encode($expectedValue) . ", Got: " . json_encode($actualValue)
                );
            }
        }
    }

    /**
     * Assert token is expired
     *
     * @param string $token JWT token
     * @param JwtConfig|null $config JWT configuration
     * @return void
     * @throws \Exception If token is not expired
     */
    public static function assertTokenExpired(string $token, ?JwtConfig $config = null): void
    {
        self::assertTokenInvalid($token, $config, 'expired');
    }

    /**
     * Assert token is not yet valid
     *
     * @param string $token JWT token
     * @param JwtConfig|null $config JWT configuration
     * @return void
     * @throws \Exception If token is already valid
     */
    public static function assertTokenNotYetValid(string $token, ?JwtConfig $config = null): void
    {
        self::assertTokenInvalid($token, $config, 'not yet valid');
    }

    /**
     * Mock time for testing
     *
     * @param Carbon|string|int $time Time to mock
     * @return void
     */
    public static function mockTime(Carbon|string|int $time): void
    {
        if (is_string($time)) {
            $time = Carbon::parse($time);
        } elseif (is_int($time)) {
            $time = Carbon::createFromTimestamp($time);
        }
        
        self::$mockTime = $time;
        Carbon::setTestNow($time);
    }

    /**
     * Clear time mock
     *
     * @return void
     */
    public static function clearTimeMock(): void
    {
        self::$mockTime = null;
        Carbon::clearTestNow();
    }

    /**
     * Travel to specific time
     *
     * @param Carbon|string|int $time Time to travel to
     * @return void
     */
    public static function travelTo(Carbon|string|int $time): void
    {
        self::mockTime($time);
    }

    /**
     * Travel forward in time
     *
     * @param int $seconds Seconds to travel forward
     * @return void
     */
    public static function travelForward(int $seconds): void
    {
        $currentTime = self::$mockTime ?? Carbon::now();
        self::mockTime($currentTime->addSeconds($seconds));
    }

    /**
     * Travel backward in time
     *
     * @param int $seconds Seconds to travel backward
     * @return void
     */
    public static function travelBackward(int $seconds): void
    {
        $currentTime = self::$mockTime ?? Carbon::now();
        self::mockTime($currentTime->subSeconds($seconds));
    }

    /**
     * Create test token pair (access + refresh)
     *
     * @param string|int $userId User identifier
     * @param array $userClaims User claims for access token
     * @param array $configOverrides Configuration overrides
     * @return array Token pair [access_token, refresh_token]
     */
    public static function createTestTokenPair(
        string|int $userId = 1,
        array $userClaims = [],
        array $configOverrides = []
    ): array {
        $config = self::createTestConfig($configOverrides);
        $refreshManager = new RefreshTokenManager($config);
        
        $claims = array_merge([
            'email' => self::getTestUser($userId)['email'] ?? "user{$userId}@test.com",
        ], $userClaims);
        
        return $refreshManager->generateTokenPair($userId, $claims);
    }

    /**
     * Extract claims from token without validation
     *
     * @param string $token JWT token
     * @return array Token claims
     */
    public static function extractClaims(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid JWT format', 'INVALID_JWT_FORMAT');
        }
        
        $payload = $parts[1];
        $remainder = strlen($payload) % 4;
        if ($remainder) {
            $payload .= str_repeat('=', 4 - $remainder);
        }
        
        $decoded = base64_decode(strtr($payload, '-_', '+/'));
        return json_decode($decoded, true) ?? [];
    }

    /**
     * Get test configuration for specific scenario
     *
     * @param string $scenario Scenario name (short_ttl, long_ttl, rsa_keys, etc.)
     * @return JwtConfig Configuration for scenario
     */
    public static function getScenarioConfig(string $scenario): JwtConfig
    {
        return match ($scenario) {
            'short_ttl' => self::createTestConfig(['ttl' => 60, 'refresh_ttl' => 120]), // 1 minute access, 2 minute refresh
            'long_ttl' => self::createTestConfig(['ttl' => 86400, 'refresh_ttl' => 172800]), // 24 hours access, 48 hours refresh
            'no_refresh' => self::createTestConfig(['ttl' => 3600, 'refresh_ttl' => 7200]), // Valid config but with refresh
            'strict_timing' => self::createTestConfig(['leeway' => 0]),
            'lenient_timing' => self::createTestConfig(['leeway' => 300]),
            'custom_issuer' => self::createTestConfig(['issuer' => 'custom-test-issuer']),
            'custom_audience' => self::createTestConfig(['audience' => 'custom-test-audience']),
            default => self::getTestConfig(),
        };
    }

    /**
     * Reset all test state
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$testConfig = null;
        self::clearTimeMock();
    }
}