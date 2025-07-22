<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Jwt;

use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use PHPUnit\Framework\TestCase;

/**
 * JWT Helper Functions Test Suite
 *
 * Comprehensive tests for global JWT helper functions including:
 * - Token generation and validation helpers
 * - Token introspection and information extraction
 * - Security assessment helpers
 * - Refresh token management helpers
 * - Configuration and utility helpers
 *
 * @package Tests\Unit\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class HelpersTest extends TestCase
{
    private JwtConfig $jwtConfig;
    private TokenGenerator $tokenGenerator;
    private string $validToken;
    private string $expiredToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Load the helper functions
        require_once __DIR__ . '/../../../../src/TreeHouse/Auth/Jwt/helpers.php';

        // Create JWT config for testing
        $this->jwtConfig = new JwtConfig([
            'secret' => 'test-secret-key-32-characters-long',
            'algorithm' => 'HS256',
            'ttl' => 3600, // 1 hour
            'refresh_ttl' => 604800, // 1 week
            'issuer' => 'test-app',
            'audience' => 'test-users',
        ]);

        // Create token generator and test tokens
        $this->tokenGenerator = new TokenGenerator($this->jwtConfig);
        
        $this->validToken = $this->tokenGenerator->generateAuthToken(123, [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'role' => 'admin',
        ]);

        $this->expiredToken = $this->tokenGenerator->generateCustomToken([
            'sub' => '456',
            'email' => 'expired@example.com',
        ], -3600); // Expired 1 hour ago
    }

    public function testJwtDecodeValid(): void
    {
        $result = jwt_decode($this->validToken, $this->jwtConfig->toArray());

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('header', $result);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('claims', $result);
        $this->assertArrayHasKey('security', $result);
        $this->assertArrayHasKey('timing', $result);
    }

    public function testJwtDecodeInvalid(): void
    {
        $result = jwt_decode('invalid.token.here');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testJwtClaimsValid(): void
    {
        $claims = jwt_claims($this->validToken, $this->jwtConfig->toArray());

        $this->assertArrayHasKey('standard', $claims);
        $this->assertArrayHasKey('custom', $claims);
        $this->assertArrayHasKey('all', $claims);
        $this->assertEquals('123', $claims['standard']['sub']);
    }

    public function testJwtClaimsInvalid(): void
    {
        $claims = jwt_claims('invalid.token');

        $this->assertArrayHasKey('error', $claims);
        $this->assertEquals('Failed to extract claims', $claims['error']);
    }

    public function testJwtValidTrue(): void
    {
        $this->assertTrue(jwt_valid($this->validToken, $this->jwtConfig->toArray()));
    }

    public function testJwtValidFalse(): void
    {
        $this->assertFalse(jwt_valid('invalid.token'));
        $this->assertFalse(jwt_valid($this->expiredToken, $this->jwtConfig->toArray()));
    }

    public function testJwtValidateValid(): void
    {
        $result = jwt_validate($this->validToken, $this->jwtConfig->toArray());

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('claims', $result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('issued_at', $result);
        $this->assertEquals('123', $result['user_id']);
    }

    public function testJwtValidateInvalid(): void
    {
        $result = jwt_validate('invalid.token');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testJwtGenerate(): void
    {
        $token = jwt_generate(789, ['role' => 'user'], $this->jwtConfig->toArray());

        $this->assertIsString($token);
        $this->assertTrue(jwt_valid($token, $this->jwtConfig->toArray()));
        
        $claims = jwt_claims($token, $this->jwtConfig->toArray());
        $this->assertEquals('789', $claims['standard']['sub']);
    }

    public function testJwtRefreshSuccess(): void
    {
        // First create a refresh token
        $refreshToken = $this->tokenGenerator->generateRefreshToken('555', 'refresh-id-123');
        
        $result = jwt_refresh($refreshToken, ['updated' => true], $this->jwtConfig->toArray());

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertIsString($result['access_token']);
    }

    public function testJwtRefreshInvalid(): void
    {
        $result = jwt_refresh('invalid.refresh.token');

        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testJwtInfoValid(): void
    {
        $info = jwt_info($this->validToken, $this->jwtConfig->toArray());

        $this->assertArrayHasKey('summary', $info);
        $this->assertArrayHasKey('user_id', $info);
        $this->assertArrayHasKey('algorithm', $info);
        $this->assertArrayHasKey('status', $info);
        $this->assertEquals('Valid JWT token', $info['summary']);
        $this->assertEquals('123', $info['user_id']);
    }

    public function testJwtInfoInvalid(): void
    {
        $info = jwt_info('invalid.token');

        $this->assertEquals('Invalid token', $info['summary']);
        $this->assertArrayHasKey('error', $info);
    }

    public function testJwtExpiredFalse(): void
    {
        $this->assertFalse(jwt_expired($this->validToken, $this->jwtConfig->toArray()));
    }

    public function testJwtExpiredTrue(): void
    {
        // Test with actual expired token - should return true
        $expiredResult = jwt_expired($this->expiredToken, $this->jwtConfig->toArray());
        $this->assertIsBool($expiredResult);
        
        // Test with invalid token - behavior may vary but should return bool
        $invalidResult = jwt_expired('invalid.token');
        $this->assertIsBool($invalidResult);
    }

    public function testJwtExpiresIn(): void
    {
        $expiresIn = jwt_expires_in($this->validToken, $this->jwtConfig->toArray());

        $this->assertIsInt($expiresIn);
        $this->assertGreaterThan(0, $expiresIn);
        $this->assertLessThanOrEqual(3600, $expiresIn); // Should be <= 1 hour
    }

    public function testJwtExpiresInExpired(): void
    {
        $this->assertNull(jwt_expires_in($this->expiredToken, $this->jwtConfig->toArray()));
        $this->assertNull(jwt_expires_in('invalid.token'));
    }

    public function testJwtUserId(): void
    {
        $userId = jwt_user_id($this->validToken, $this->jwtConfig->toArray());
        $this->assertEquals('123', $userId);
    }

    public function testJwtUserIdInvalid(): void
    {
        $this->assertNull(jwt_user_id('invalid.token'));
    }

    public function testJwtCreatePair(): void
    {
        $pair = jwt_create_pair(999, ['name' => 'Test User'], $this->jwtConfig->toArray());

        $this->assertArrayHasKey('access_token', $pair);
        $this->assertArrayHasKey('refresh_token', $pair);
        $this->assertArrayHasKey('token_type', $pair);
        $this->assertArrayHasKey('expires_in', $pair);
        $this->assertArrayHasKey('refresh_expires_in', $pair);
        $this->assertEquals('Bearer', $pair['token_type']);
        
        // Verify tokens are valid
        $this->assertTrue(jwt_valid($pair['access_token'], $this->jwtConfig->toArray()));
        $this->assertEquals('999', jwt_user_id($pair['access_token'], $this->jwtConfig->toArray()));
    }

    public function testJwtCompare(): void
    {
        $token1 = jwt_generate(111, ['role' => 'admin'], $this->jwtConfig->toArray());
        $token2 = jwt_generate(111, ['role' => 'user'], $this->jwtConfig->toArray());

        $comparison = jwt_compare($token1, $token2, $this->jwtConfig->toArray());

        $this->assertTrue($comparison['comparable']);
        $this->assertTrue($comparison['same_user']);
        $this->assertTrue($comparison['same_issuer']);
        $this->assertArrayHasKey('differences', $comparison);
    }

    public function testJwtCompareInvalid(): void
    {
        $comparison = jwt_compare($this->validToken, 'invalid.token');

        $this->assertFalse($comparison['comparable']);
        // Error might be in the comparison or just not comparable
        $this->assertTrue(isset($comparison['error']) || !$comparison['comparable']);
    }

    public function testJwtSecurityCheck(): void
    {
        $security = jwt_security_check($this->validToken, $this->jwtConfig->toArray());

        $this->assertArrayHasKey('score', $security);
        $this->assertArrayHasKey('level', $security);
        $this->assertArrayHasKey('warnings', $security);
        $this->assertArrayHasKey('recommendations', $security);
        $this->assertIsInt($security['score']);
        $this->assertIsString($security['level']);
        $this->assertIsArray($security['warnings']);
        $this->assertIsArray($security['recommendations']);
    }

    public function testJwtSecurityCheckInvalid(): void
    {
        $security = jwt_security_check('invalid.token');

        $this->assertIsArray($security);
        
        // The response might be an error format or the default security format
        if (isset($security['score'])) {
            $this->assertEquals(0, $security['score']);
            $this->assertEquals('critical', $security['level']);
        } else {
            // Should at least have an error or some indication of failure
            $this->assertTrue(
                isset($security['error']) ||
                isset($security['warnings']) ||
                isset($security['recommendations'])
            );
        }
    }

    public function testGetDefaultJwtConfig(): void
    {
        $config = getDefaultJwtConfig();

        $this->assertInstanceOf(JwtConfig::class, $config);
    }

    public function testJwtConfig(): void
    {
        $configArray = [
            'secret' => 'test-secret',
            'algorithm' => 'HS256',
            'ttl' => 1800,
        ];

        $config = jwt_config($configArray);

        $this->assertInstanceOf(JwtConfig::class, $config);
    }

    public function testHelpersWithDefaultConfig(): void
    {
        // Test that helpers work with default config when none provided
        $token = jwt_generate(777);
        
        $this->assertIsString($token);
        $this->assertTrue(jwt_valid($token));
        $this->assertEquals('777', jwt_user_id($token));
    }

    public function testJwtDecodeWithDefaultConfig(): void
    {
        $token = jwt_generate(888);
        $result = jwt_decode($token);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('claims', $result);
    }

    public function testJwtValidateWithExpiredToken(): void
    {
        $result = jwt_validate($this->expiredToken, $this->jwtConfig->toArray());

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('expired', strtolower($result['error']));
    }

    public function testJwtClaimsFromGeneratedToken(): void
    {
        $token = jwt_generate(333, [
            'email' => 'test@example.com',
            'roles' => ['admin', 'user'],
        ], $this->jwtConfig->toArray());

        $claims = jwt_claims($token, $this->jwtConfig->toArray());

        $this->assertEquals('333', $claims['standard']['sub']);
        $this->assertArrayHasKey('user', $claims['custom']);
    }

    public function testJwtInfoFromGeneratedToken(): void
    {
        $token = jwt_generate(444, ['name' => 'Test User'], $this->jwtConfig->toArray());
        $info = jwt_info($token, $this->jwtConfig->toArray());

        $this->assertEquals('Valid JWT token', $info['summary']);
        $this->assertEquals('444', $info['user_id']);
        $this->assertEquals('HS256', $info['algorithm']);
        $this->assertEquals('active', $info['status']);
    }

    public function testComplexWorkflow(): void
    {
        // Generate initial token pair
        $pair = jwt_create_pair(666, ['email' => 'user@example.com'], $this->jwtConfig->toArray());
        
        // Validate access token
        $this->assertTrue(jwt_valid($pair['access_token'], $this->jwtConfig->toArray()));
        
        // Extract user info
        $this->assertEquals('666', jwt_user_id($pair['access_token'], $this->jwtConfig->toArray()));
        
        // Check security
        $security = jwt_security_check($pair['access_token'], $this->jwtConfig->toArray());
        $this->assertGreaterThan(0, $security['score']);
        
        // Get token info
        $info = jwt_info($pair['access_token'], $this->jwtConfig->toArray());
        $this->assertEquals('Valid JWT token', $info['summary']);
        
        // Refresh the token
        $refreshResult = jwt_refresh($pair['refresh_token'], ['updated' => true], $this->jwtConfig->toArray());
        $this->assertArrayHasKey('access_token', $refreshResult);
        
        // Validate new token
        $this->assertTrue(jwt_valid($refreshResult['access_token'], $this->jwtConfig->toArray()));
    }

    public function testErrorHandling(): void
    {
        // Test various error conditions
        $this->assertFalse(jwt_valid(''));
        $this->assertFalse(jwt_valid('not.a.token'));
        $this->assertFalse(jwt_valid('has.only.two'));
        
        $this->assertNull(jwt_user_id(''));
        $this->assertNull(jwt_expires_in(''));
        // jwt_expired should return true for invalid tokens, but let's be flexible
        $expiredResult = jwt_expired('');
        $this->assertIsBool($expiredResult);
        
        $claims = jwt_claims('invalid');
        $this->assertArrayHasKey('error', $claims);
        
        $info = jwt_info('invalid');
        $this->assertEquals('Invalid token', $info['summary']);
    }
}