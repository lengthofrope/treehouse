<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Jwt;

use LengthOfRope\TreeHouse\Auth\Jwt\RefreshTokenManager;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * RefreshTokenManager Test Suite
 *
 * Comprehensive tests for the stateless refresh token manager including:
 * - Refresh token generation and validation
 * - Access token refreshing with rotation
 * - Token family tracking for security
 * - Configuration management
 * - Error handling and edge cases
 *
 * @package Tests\Unit\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RefreshTokenManagerTest extends TestCase
{
    private RefreshTokenManager $manager;
    private JwtConfig $jwtConfig;
    private TokenGenerator $tokenGenerator;
    private array $testRefreshConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Create JWT config for testing
        $this->jwtConfig = new JwtConfig([
            'secret' => 'test-secret-key-32-characters-long',
            'algorithm' => 'HS256',
            'ttl' => 900, // 15 minutes
            'refresh_ttl' => 604800, // 1 week
            'issuer' => 'test-app',
            'audience' => 'test-users',
        ]);

        // Test refresh configuration
        $this->testRefreshConfig = [
            'rotation_enabled' => true,
            'family_tracking' => true,
            'max_refresh_count' => 10,
            'grace_period' => 300,
        ];

        // Create refresh token manager
        $this->manager = new RefreshTokenManager($this->jwtConfig, $this->testRefreshConfig);
        
        // Create token generator for testing
        $this->tokenGenerator = new TokenGenerator($this->jwtConfig);
    }

    public function testConstructorWithDefaultConfig(): void
    {
        $manager = new RefreshTokenManager($this->jwtConfig);
        $config = $manager->getConfig();
        
        $this->assertTrue($config['rotation_enabled']);
        $this->assertTrue($config['family_tracking']);
        $this->assertEquals(50, $config['max_refresh_count']);
        $this->assertEquals(300, $config['grace_period']);
    }

    public function testConstructorWithCustomConfig(): void
    {
        $customConfig = [
            'rotation_enabled' => false,
            'max_refresh_count' => 25,
        ];
        
        $manager = new RefreshTokenManager($this->jwtConfig, $customConfig);
        $config = $manager->getConfig();
        
        $this->assertFalse($config['rotation_enabled']);
        $this->assertEquals(25, $config['max_refresh_count']);
        $this->assertTrue($config['family_tracking']); // Should keep default
    }

    public function testGenerateRefreshToken(): void
    {
        $result = $this->manager->generateRefreshToken(123);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('expires_at', $result);
        
        // Check metadata structure
        $metadata = $result['metadata'];
        $this->assertEquals(123, $metadata['user_id']);
        $this->assertIsString($metadata['token_id']);
        $this->assertIsString($metadata['family_id']);
        $this->assertEquals(0, $metadata['refresh_count']);
        $this->assertNull($metadata['parent_token_id']);
        $this->assertIsInt($metadata['issued_at']);
        
        // Check token format
        $this->assertIsString($result['token']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_.]+$/', $result['token']);
        
        // Check expiration
        $expectedExpiry = time() + $this->jwtConfig->getRefreshTtl();
        $this->assertEqualsWithDelta($expectedExpiry, $result['expires_at'], 5);
    }

    public function testGenerateRefreshTokenWithMetadata(): void
    {
        $metadata = [
            'family_id' => 'test-family-123',
            'parent_token_id' => 'parent-token-456',
            'refresh_count' => 5,
        ];
        
        $result = $this->manager->generateRefreshToken(456, $metadata);
        $resultMetadata = $result['metadata'];
        
        $this->assertEquals('test-family-123', $resultMetadata['family_id']);
        $this->assertEquals('parent-token-456', $resultMetadata['parent_token_id']);
        $this->assertEquals(5, $resultMetadata['refresh_count']);
        $this->assertEquals(456, $resultMetadata['user_id']);
    }

    public function testRefreshAccessToken(): void
    {
        // Generate initial refresh token
        $refreshData = $this->manager->generateRefreshToken(789);
        $refreshToken = $refreshData['token'];
        
        // Refresh the access token
        $result = $this->manager->refreshAccessToken($refreshToken);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        
        // Check token type and expiry
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertEquals($this->jwtConfig->getTtl(), $result['expires_in']);
        
        // Should include new refresh token (rotation enabled)
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('refresh_expires_in', $result);
        $this->assertEquals($this->jwtConfig->getRefreshTtl(), $result['refresh_expires_in']);
        
        // New refresh token should be different
        $this->assertNotEquals($refreshToken, $result['refresh_token']);
    }

    public function testRefreshAccessTokenWithAdditionalClaims(): void
    {
        $refreshData = $this->manager->generateRefreshToken(321);
        $refreshToken = $refreshData['token'];
        
        $additionalClaims = [
            'role' => 'admin',
            'permissions' => ['read', 'write'],
        ];
        
        $result = $this->manager->refreshAccessToken($refreshToken, $additionalClaims);
        
        $this->assertArrayHasKey('access_token', $result);
        // Additional claims should be embedded in the new access token
        // We can't easily verify this without decoding the token, but the method should succeed
        $this->assertIsString($result['access_token']);
    }

    public function testRefreshAccessTokenWithInvalidToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->refreshAccessToken('invalid.token.here');
    }

    public function testRefreshAccessTokenExceedsMaxCount(): void
    {
        // Create a refresh token with max refresh count
        $metadata = ['refresh_count' => 10]; // Equals max_refresh_count
        $refreshData = $this->manager->generateRefreshToken(111, $metadata);
        $refreshToken = $refreshData['token'];
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Refresh token has exceeded maximum refresh count');
        
        $this->manager->refreshAccessToken($refreshToken);
    }

    public function testRefreshAccessTokenWithRotationDisabled(): void
    {
        // Create manager with rotation disabled
        $config = array_merge($this->testRefreshConfig, ['rotation_enabled' => false]);
        $manager = new RefreshTokenManager($this->jwtConfig, $config);
        
        $refreshData = $manager->generateRefreshToken(222);
        $refreshToken = $refreshData['token'];
        
        $result = $manager->refreshAccessToken($refreshToken);
        
        // Should not include new refresh token
        $this->assertArrayNotHasKey('refresh_token', $result);
        $this->assertArrayNotHasKey('refresh_expires_in', $result);
        $this->assertArrayHasKey('access_token', $result);
    }

    public function testValidateRefreshToken(): void
    {
        $refreshData = $this->manager->generateRefreshToken(333);
        $refreshToken = $refreshData['token'];
        
        $result = $this->manager->validateRefreshToken($refreshToken);
        
        $this->assertTrue($result['valid']);
        $this->assertEquals('333', $result['user_id']);
        $this->assertIsString($result['token_id']);
        $this->assertIsString($result['family_id']);
        $this->assertIsInt($result['issued_at']);
        $this->assertIsInt($result['expires_at']);
        $this->assertEquals(0, $result['refresh_count']);
    }

    public function testValidateInvalidRefreshToken(): void
    {
        $result = $this->manager->validateRefreshToken('invalid.token.here');
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('error_code', $result);
    }

    public function testGenerateTokenPair(): void
    {
        $userClaims = [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];
        
        $result = $this->manager->generateTokenPair(444, $userClaims);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('refresh_expires_in', $result);
        $this->assertArrayHasKey('metadata', $result);
        
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertEquals($this->jwtConfig->getTtl(), $result['expires_in']);
        $this->assertEquals($this->jwtConfig->getRefreshTtl(), $result['refresh_expires_in']);
        
        // Check metadata
        $metadata = $result['metadata'];
        $this->assertEquals(444, $metadata['user_id']);
        $this->assertEquals(0, $metadata['refresh_count']);
    }

    public function testIsNearExpiration(): void
    {
        // Create a refresh token with long TTL
        $longTtlConfig = new JwtConfig([
            'secret' => 'test-secret-key-32-characters-long',
            'algorithm' => 'HS256',
            'ttl' => 900,
            'refresh_ttl' => 7200, // 2 hours
            'issuer' => 'test-app',
            'audience' => 'test-users',
        ]);
        
        $manager = new RefreshTokenManager($longTtlConfig);
        $refreshData = $manager->generateRefreshToken(555);
        $refreshToken = $refreshData['token'];
        
        // Should not be near expiration with default threshold (1 hour)
        $this->assertFalse($manager->isNearExpiration($refreshToken));
        
        // Should be near expiration with very high threshold (3 hours)
        $this->assertTrue($manager->isNearExpiration($refreshToken, 10800));
    }

    public function testIsNearExpirationWithInvalidToken(): void
    {
        // Invalid tokens should be considered expired
        $this->assertTrue($this->manager->isNearExpiration('invalid.token.here'));
    }

    public function testGetTokenMetadata(): void
    {
        $refreshData = $this->manager->generateRefreshToken(666);
        $refreshToken = $refreshData['token'];
        
        $metadata = $this->manager->getTokenMetadata($refreshToken);
        
        $this->assertEquals('666', $metadata['user_id']);
        $this->assertIsString($metadata['token_id']);
        $this->assertIsString($metadata['family_id']);
        $this->assertIsInt($metadata['issued_at']);
        $this->assertIsInt($metadata['expires_at']);
        $this->assertEquals(0, $metadata['refresh_count']);
        $this->assertEquals('refresh', $metadata['token_type']);
    }

    public function testGetTokenMetadataWithInvalidToken(): void
    {
        $metadata = $this->manager->getTokenMetadata('invalid.token.here');
        
        $this->assertArrayHasKey('error', $metadata);
        $this->assertArrayHasKey('details', $metadata);
        $this->assertEquals('Failed to decode token', $metadata['error']);
    }

    public function testGetConfig(): void
    {
        $config = $this->manager->getConfig();
        
        $this->assertEquals($this->testRefreshConfig, $config);
    }

    public function testUpdateConfig(): void
    {
        $newConfig = [
            'max_refresh_count' => 20,
            'grace_period' => 600,
        ];
        
        $this->manager->updateConfig($newConfig);
        $config = $this->manager->getConfig();
        
        $this->assertEquals(20, $config['max_refresh_count']);
        $this->assertEquals(600, $config['grace_period']);
        $this->assertTrue($config['rotation_enabled']); // Should keep existing values
    }

    public function testCreateStaticMethod(): void
    {
        $jwtConfig = [
            'secret' => 'test-secret-key-32-characters-long',
            'algorithm' => 'HS256',
            'ttl' => 900,
            'refresh_ttl' => 604800,
        ];
        
        $refreshConfig = [
            'rotation_enabled' => false,
            'max_refresh_count' => 15,
        ];
        
        $manager = RefreshTokenManager::create($jwtConfig, $refreshConfig);
        
        $this->assertInstanceOf(RefreshTokenManager::class, $manager);
        
        $config = $manager->getConfig();
        $this->assertFalse($config['rotation_enabled']);
        $this->assertEquals(15, $config['max_refresh_count']);
    }

    public function testTokenRotationTracking(): void
    {
        // Generate initial refresh token
        $refreshData1 = $this->manager->generateRefreshToken(777);
        $familyId = $refreshData1['metadata']['family_id'];
        
        // Refresh to get new token
        $result1 = $this->manager->refreshAccessToken($refreshData1['token']);
        $refreshToken2 = $result1['refresh_token'];
        
        // Validate the new refresh token has correct family tracking
        $metadata2 = $this->manager->getTokenMetadata($refreshToken2);
        $this->assertEquals($familyId, $metadata2['family_id']);
        $this->assertEquals(1, $metadata2['refresh_count']);
        
        // Refresh again
        $result2 = $this->manager->refreshAccessToken($refreshToken2);
        $refreshToken3 = $result2['refresh_token'];
        
        // Check family tracking continues
        $metadata3 = $this->manager->getTokenMetadata($refreshToken3);
        $this->assertEquals($familyId, $metadata3['family_id']);
        $this->assertEquals(2, $metadata3['refresh_count']);
    }

    public function testMultipleUserTokens(): void
    {
        // Generate tokens for different users
        $user1Data = $this->manager->generateRefreshToken(111);
        $user2Data = $this->manager->generateRefreshToken(222);
        
        // Tokens should be different
        $this->assertNotEquals($user1Data['token'], $user2Data['token']);
        $this->assertNotEquals(
            $user1Data['metadata']['family_id'], 
            $user2Data['metadata']['family_id']
        );
        
        // Both should validate correctly
        $validation1 = $this->manager->validateRefreshToken($user1Data['token']);
        $validation2 = $this->manager->validateRefreshToken($user2Data['token']);
        
        $this->assertTrue($validation1['valid']);
        $this->assertTrue($validation2['valid']);
        $this->assertEquals('111', $validation1['user_id']);
        $this->assertEquals('222', $validation2['user_id']);
    }

    public function testRefreshTokenUniqueness(): void
    {
        // Generate multiple refresh tokens for the same user
        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $data = $this->manager->generateRefreshToken(999);
            $tokens[] = $data['token'];
        }
        
        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(5, $uniqueTokens);
    }

    public function testConfigurationValidation(): void
    {
        // Test with extreme values
        $extremeConfig = [
            'max_refresh_count' => 1,
            'grace_period' => 0,
        ];
        
        $manager = new RefreshTokenManager($this->jwtConfig, $extremeConfig);
        $refreshData = $manager->generateRefreshToken(888);
        
        // Should work with first refresh
        $result = $manager->refreshAccessToken($refreshData['token']);
        $this->assertIsArray($result);
        
        // Should fail on second refresh due to max count
        $this->expectException(InvalidArgumentException::class);
        $manager->refreshAccessToken($result['refresh_token']);
    }
}