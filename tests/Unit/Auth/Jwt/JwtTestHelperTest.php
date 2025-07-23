<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Jwt;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtTestHelper;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * Test JwtTestHelper functionality
 */
class JwtTestHelperTest extends TestCase
{
    protected function setUp(): void
    {
        JwtTestHelper::reset();
    }

    protected function tearDown(): void
    {
        JwtTestHelper::reset();
    }

    public function testCanCreateTestConfig(): void
    {
        $config = JwtTestHelper::createTestConfig();
        
        $this->assertInstanceOf(JwtConfig::class, $config);
        $this->assertEquals('HS256', $config->getAlgorithm());
        $this->assertEquals('treehouse-test', $config->getIssuer());
    }

    public function testCanCreateTestConfigWithOverrides(): void
    {
        $config = JwtTestHelper::createTestConfig([
            'algorithm' => 'RS256',
            'ttl' => 7200
        ]);
        
        $this->assertEquals('RS256', $config->getAlgorithm());
        $this->assertEquals(7200, $config->getTtl());
    }

    public function testCanCreateTestToken(): void
    {
        $token = JwtTestHelper::createTestToken(123);
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertEquals(3, count(explode('.', $token))); // Valid JWT structure
    }

    public function testCanCreateTestTokenWithClaims(): void
    {
        $token = JwtTestHelper::createTestToken(123, ['role' => 'admin']);
        
        $this->assertIsString($token);
        $claims = JwtTestHelper::extractClaims($token);
        $this->assertEquals(123, $claims['user_id']);
        $this->assertEquals('admin', $claims['role']);
    }

    public function testCanCreateExpiredToken(): void
    {
        $token = JwtTestHelper::createExpiredToken(123, 3600);
        
        $this->assertIsString($token);
        $claims = JwtTestHelper::extractClaims($token);
        $this->assertLessThan(time(), $claims['exp']);
    }

    public function testCanCreateNotYetValidToken(): void
    {
        $token = JwtTestHelper::createNotYetValidToken(123, 3600);
        
        $this->assertIsString($token);
        $claims = JwtTestHelper::extractClaims($token);
        $this->assertGreaterThan(time(), $claims['nbf']);
    }

    public function testCanCreateMalformedTokens(): void
    {
        $malformedTypes = [
            'missing_signature',
            'extra_part',
            'invalid_header',
            'invalid_payload',
            'empty_parts',
            'wrong_signature'
        ];
        
        foreach ($malformedTypes as $type) {
            $token = JwtTestHelper::createMalformedToken($type);
            $this->assertIsString($token);
            $this->assertNotEmpty($token);
        }
    }

    public function testCanCreateTokenWithInvalidSignature(): void
    {
        $token = JwtTestHelper::createTokenWithInvalidSignature(123);
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertEquals(3, count(explode('.', $token)));
    }

    public function testCanGetTestUser(): void
    {
        $user = JwtTestHelper::getTestUser(1);
        
        $this->assertIsArray($user);
        $this->assertEquals(1, $user['id']);
        $this->assertArrayHasKey('email', $user);
        $this->assertArrayHasKey('name', $user);
        $this->assertArrayHasKey('role', $user);
        $this->assertArrayHasKey('permissions', $user);
    }

    public function testCanGetAllTestUsers(): void
    {
        $users = JwtTestHelper::getTestUser();
        
        $this->assertIsArray($users);
        $this->assertArrayHasKey('user1', $users);
        $this->assertArrayHasKey('user2', $users);
        $this->assertArrayHasKey('user3', $users);
    }

    public function testAssertTokenValid(): void
    {
        $token = JwtTestHelper::createTestToken(123);
        
        // Should not throw exception
        $claims = JwtTestHelper::assertTokenValid($token);
        $this->assertNotNull($claims);
    }

    public function testAssertTokenInvalid(): void
    {
        $invalidToken = JwtTestHelper::createMalformedToken('missing_signature');
        
        // Should not throw exception if token is invalid as expected
        JwtTestHelper::assertTokenInvalid($invalidToken);
        $this->assertTrue(true); // If we get here, the assertion passed
    }

    public function testAssertTokenHasClaim(): void
    {
        $token = JwtTestHelper::createTestToken(123, ['role' => 'admin']);
        
        // Should not throw exception
        JwtTestHelper::assertTokenHasClaim($token, 'role', 'admin');
        JwtTestHelper::assertTokenHasClaim($token, 'user_id', 123);
        $this->assertTrue(true);
    }

    public function testAssertTokenExpired(): void
    {
        $expiredToken = JwtTestHelper::createExpiredToken(123);
        
        // Should not throw exception if token is expired as expected
        JwtTestHelper::assertTokenExpired($expiredToken);
        $this->assertTrue(true);
    }

    public function testCanMockTime(): void
    {
        $testTime = Carbon::parse('2024-01-01 12:00:00');
        JwtTestHelper::mockTime($testTime);
        
        // Create token during mocked time
        $token = JwtTestHelper::createTestToken(123);
        $claims = JwtTestHelper::extractClaims($token);
        
        $this->assertEquals($testTime->getTimestamp(), $claims['iat']);
    }

    public function testCanTravelInTime(): void
    {
        JwtTestHelper::travelTo('2024-01-01 12:00:00');
        
        // Travel forward
        JwtTestHelper::travelForward(3600);
        $token1 = JwtTestHelper::createTestToken(123);
        
        // Travel backward
        JwtTestHelper::travelBackward(1800);
        $token2 = JwtTestHelper::createTestToken(123);
        
        $claims1 = JwtTestHelper::extractClaims($token1);
        $claims2 = JwtTestHelper::extractClaims($token2);
        
        $this->assertGreaterThan($claims2['iat'], $claims1['iat']);
    }

    public function testCanCreateTestTokenPair(): void
    {
        $tokenPair = JwtTestHelper::createTestTokenPair(123);
        
        $this->assertIsArray($tokenPair);
        $this->assertArrayHasKey('access_token', $tokenPair);
        $this->assertArrayHasKey('refresh_token', $tokenPair);
        $this->assertIsString($tokenPair['access_token']);
        $this->assertIsString($tokenPair['refresh_token']);
    }

    public function testCanExtractClaims(): void
    {
        $token = JwtTestHelper::createTestToken(123, ['role' => 'admin']);
        $claims = JwtTestHelper::extractClaims($token);
        
        $this->assertIsArray($claims);
        $this->assertEquals(123, $claims['user_id']);
        $this->assertEquals('admin', $claims['role']);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function testCanGetScenarioConfigs(): void
    {
        $scenarios = [
            'short_ttl',
            'long_ttl',
            'no_refresh',
            'strict_timing',
            'lenient_timing',
            'custom_issuer',
            'custom_audience'
        ];
        
        foreach ($scenarios as $scenario) {
            $config = JwtTestHelper::getScenarioConfig($scenario);
            $this->assertInstanceOf(JwtConfig::class, $config);
        }
    }

    public function testResetClearsState(): void
    {
        // Mock time and create config
        JwtTestHelper::mockTime('2024-01-01 12:00:00');
        JwtTestHelper::createTestConfig(['ttl' => 9999]);
        
        // Reset should clear everything
        JwtTestHelper::reset();
        
        // Time should be back to normal
        $now = time();
        $token = JwtTestHelper::createTestToken(123);
        $claims = JwtTestHelper::extractClaims($token);
        
        // Should be close to current time (within 5 seconds)
        $this->assertLessThan(5, abs($now - $claims['iat']));
    }
}