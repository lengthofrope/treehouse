<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Jwt;

use LengthOfRope\TreeHouse\Auth\Jwt\TokenIntrospector;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use PHPUnit\Framework\TestCase;

/**
 * TokenIntrospector Test Suite
 *
 * Comprehensive tests for JWT token introspection utilities including:
 * - Token structure analysis and validation
 * - Claim extraction and categorization
 * - Security assessment and recommendations
 * - Timing analysis and expiration tracking
 * - Token comparison and metadata extraction
 *
 * @package Tests\Unit\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class TokenIntrospectorTest extends TestCase
{
    private TokenIntrospector $introspector;
    private JwtConfig $jwtConfig;
    private TokenGenerator $tokenGenerator;
    private string $validToken;
    private string $expiredToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create JWT config for testing
        $this->jwtConfig = new JwtConfig([
            'secret' => 'test-secret-key-32-characters-long',
            'algorithm' => 'HS256',
            'ttl' => 3600, // 1 hour
            'refresh_ttl' => 604800, // 1 week
            'issuer' => 'test-app',
            'audience' => 'test-users',
        ]);

        // Create introspector and token generator
        $this->introspector = new TokenIntrospector($this->jwtConfig);
        $this->tokenGenerator = new TokenGenerator($this->jwtConfig);

        // Generate test tokens
        $this->validToken = $this->tokenGenerator->generateAuthToken(123, [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'role' => 'admin',
        ]);

        // Generate expired token
        $this->expiredToken = $this->tokenGenerator->generateCustomToken([
            'sub' => '456',
            'email' => 'expired@example.com',
        ], -3600); // Expired 1 hour ago
    }

    public function testIntrospectValidToken(): void
    {
        $result = $this->introspector->introspect($this->validToken);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('structure', $result);
        $this->assertArrayHasKey('header', $result);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('claims', $result);
        $this->assertArrayHasKey('security', $result);
        $this->assertArrayHasKey('timing', $result);
        $this->assertArrayHasKey('metadata', $result);

        // Check structure
        $this->assertTrue($result['structure']['valid']);
        $this->assertEquals(3, $result['structure']['parts']);

        // Check header
        $this->assertEquals('HS256', $result['header']['algorithm']);
        $this->assertEquals('JWT', $result['header']['type']);

        // Check claims
        $this->assertArrayHasKey('standard', $result['claims']);
        $this->assertArrayHasKey('custom', $result['claims']);
        $this->assertEquals('123', $result['claims']['standard']['sub']);

        // Check timing
        $this->assertEquals('active', $result['timing']['status']);
    }

    public function testIntrospectInvalidToken(): void
    {
        $result = $this->introspector->introspect('invalid.token.format');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('structure', $result);
    }

    public function testIntrospectEmptyToken(): void
    {
        $result = $this->introspector->introspect('');

        $this->assertFalse($result['valid']);
        $this->assertEquals('Invalid token structure', $result['error']);
        $this->assertFalse($result['structure']['valid']);
        $this->assertEquals('Empty token', $result['structure']['error']);
    }

    public function testIntrospectMalformedToken(): void
    {
        $result = $this->introspector->introspect('only.two');

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['structure']['valid']);
        $this->assertStringContainsString('Invalid part count', $result['structure']['error']);
    }

    public function testExtractClaimsFromValidToken(): void
    {
        $claims = $this->introspector->extractClaims($this->validToken);

        $this->assertArrayHasKey('standard', $claims);
        $this->assertArrayHasKey('custom', $claims);
        $this->assertArrayHasKey('all', $claims);

        // Check structure
        $this->assertArrayHasKey('standard', $claims);
        $this->assertArrayHasKey('custom', $claims);
        $this->assertArrayHasKey('all', $claims);
        $this->assertIsArray($claims['standard']);
        $this->assertIsArray($claims['custom']);
        $this->assertIsArray($claims['all']);
        
        // Check that user ID is present
        $this->assertEquals('123', $claims['standard']['sub']);
    }

    public function testExtractClaimsFromInvalidToken(): void
    {
        $claims = $this->introspector->extractClaims('invalid.token');

        $this->assertArrayHasKey('error', $claims);
        $this->assertEquals('Failed to extract claims', $claims['error']);
    }

    public function testGetTokenInfoValid(): void
    {
        $info = $this->introspector->getTokenInfo($this->validToken);

        $this->assertArrayHasKey('summary', $info);
        $this->assertArrayHasKey('user_id', $info);
        $this->assertArrayHasKey('algorithm', $info);
        $this->assertArrayHasKey('status', $info);
        $this->assertArrayHasKey('issuer', $info);
        $this->assertArrayHasKey('audience', $info);
        $this->assertArrayHasKey('issued', $info);
        $this->assertArrayHasKey('expires', $info);
        $this->assertArrayHasKey('token_type', $info);
        
        $this->assertEquals('Valid JWT token', $info['summary']);
        $this->assertEquals('123', $info['user_id']);
        $this->assertEquals('HS256', $info['algorithm']);
        $this->assertEquals('active', $info['status']);
    }

    public function testGetTokenInfoInvalid(): void
    {
        $info = $this->introspector->getTokenInfo('invalid.token');

        $this->assertEquals('Invalid token', $info['summary']);
        $this->assertArrayHasKey('error', $info);
    }

    public function testValidateStructureValid(): void
    {
        $structure = $this->introspector->validateStructure($this->validToken);

        $this->assertTrue($structure['valid']);
        $this->assertEquals(3, $structure['parts']);
        $this->assertFalse($structure['header_empty']);
        $this->assertFalse($structure['payload_empty']);
        $this->assertFalse($structure['signature_empty']);
        $this->assertIsInt($structure['total_length']);
    }

    public function testValidateStructureInvalid(): void
    {
        $structure = $this->introspector->validateStructure('incomplete.token');

        $this->assertFalse($structure['valid']);
        $this->assertEquals(2, $structure['parts']);
    }

    public function testGetTimingInfoValid(): void
    {
        $timing = $this->introspector->getTimingInfo($this->validToken);

        $this->assertEquals('active', $timing['status']);
        $this->assertIsInt($timing['current_time']);
        $this->assertArrayHasKey('issued_at', $timing);
        $this->assertArrayHasKey('expires_at', $timing);
        $this->assertArrayHasKey('expires_in_seconds', $timing);
        $this->assertArrayHasKey('lifetime_seconds', $timing);
        $this->assertIsString($timing['lifetime_formatted']);
    }

    public function testGetTimingInfoExpired(): void
    {
        $timing = $this->introspector->getTimingInfo($this->expiredToken);

        $this->assertEquals('expired', $timing['status']);
        $this->assertArrayHasKey('expired_seconds_ago', $timing);
        $this->assertGreaterThan(0, $timing['expired_seconds_ago']);
    }

    public function testGetTimingInfoInvalid(): void
    {
        $timing = $this->introspector->getTimingInfo('invalid.token');

        $this->assertArrayHasKey('error', $timing);
        $this->assertEquals('Failed to extract timing information', $timing['error']);
    }

    public function testAssessTokenSecurityGood(): void
    {
        $security = $this->introspector->assessTokenSecurity($this->validToken);

        $this->assertIsInt($security['score']);
        $this->assertIsString($security['level']);
        $this->assertIsArray($security['warnings']);
        $this->assertIsArray($security['recommendations']);
        $this->assertGreaterThan(50, $security['score']); // Should be reasonably secure
    }

    public function testAssessTokenSecurityWithMissingClaims(): void
    {
        // Create token with minimal claims (missing recommended ones)
        $minimalToken = $this->tokenGenerator->generateCustomToken([
            'sub' => '123',
            // Missing iss, aud claims
        ]);

        $security = $this->introspector->assessTokenSecurity($minimalToken);

        $this->assertIsInt($security['score']);
        $this->assertIsArray($security['warnings']);
        
        // Token with minimal claims should have some warnings or lower score
        $this->assertTrue($security['score'] < 100 || !empty($security['warnings']));
    }

    public function testCompareTokensSameUser(): void
    {
        $token1 = $this->tokenGenerator->generateAuthToken(999, ['role' => 'admin']);
        $token2 = $this->tokenGenerator->generateAuthToken(999, ['role' => 'user']);

        $comparison = $this->introspector->compareTokens($token1, $token2);

        $this->assertTrue($comparison['comparable']);
        $this->assertTrue($comparison['same_user']);
        $this->assertTrue($comparison['same_issuer']);
        $this->assertTrue($comparison['same_audience']);
        $this->assertTrue($comparison['same_algorithm']);
        $this->assertArrayHasKey('differences', $comparison);
    }

    public function testCompareTokensDifferentUsers(): void
    {
        $token1 = $this->tokenGenerator->generateAuthToken(111);
        $token2 = $this->tokenGenerator->generateAuthToken(222);

        $comparison = $this->introspector->compareTokens($token1, $token2);

        $this->assertTrue($comparison['comparable']);
        $this->assertFalse($comparison['same_user']);
        $this->assertTrue($comparison['same_issuer']);
        $this->assertTrue($comparison['same_audience']);
    }

    public function testCompareTokensOneInvalid(): void
    {
        $comparison = $this->introspector->compareTokens($this->validToken, 'invalid.token');

        $this->assertFalse($comparison['comparable']);
        $this->assertTrue($comparison['token1_valid']);
        $this->assertFalse($comparison['token2_valid']);
    }

    public function testCreateStaticMethod(): void
    {
        $config = [
            'secret' => 'test-secret',
            'algorithm' => 'HS256',
        ];

        $introspector = TokenIntrospector::create($config);
        $this->assertInstanceOf(TokenIntrospector::class, $introspector);
    }

    public function testComplexTokenStructure(): void
    {
        // Create token with complex custom claims
        $complexToken = $this->tokenGenerator->generateCustomToken([
            'sub' => '777',
            'type' => 'api',
            'scopes' => ['read', 'write', 'delete'],
            'metadata' => [
                'client_id' => 'app-123',
                'session_id' => 'sess-456',
            ],
            'permissions' => ['admin.users', 'admin.settings'],
            'nested' => [
                'deep' => [
                    'value' => 'test'
                ]
            ]
        ]);

        $result = $this->introspector->introspect($complexToken);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('scopes', $result['claims']['custom']);
        $this->assertArrayHasKey('metadata', $result['claims']['custom']);
        $this->assertEquals('api', $result['metadata']['token_type']);
        $this->assertEquals(['read', 'write', 'delete'], $result['metadata']['scopes']);
    }

    public function testRefreshTokenIntrospection(): void
    {
        $refreshToken = $this->tokenGenerator->generateRefreshToken('888', 'token-id-123');
        $result = $this->introspector->introspect($refreshToken);

        $this->assertTrue($result['valid']);
        $this->assertEquals('refresh', $result['claims']['custom']['type']);
        $this->assertEquals('token-id-123', $result['claims']['standard']['jti']);
        $this->assertEquals('888', $result['claims']['standard']['sub']);
    }

    public function testSecurityAssessmentWithSensitiveData(): void
    {
        // Create token with potentially sensitive data
        $sensitiveToken = $this->tokenGenerator->generateCustomToken([
            'sub' => '999',
            'user_password' => 'secret123', // Sensitive
            'api_key' => 'key-456', // Sensitive
            'secret_value' => 'hidden', // Sensitive
        ]);

        $security = $this->introspector->assessTokenSecurity($sensitiveToken);

        $this->assertLessThan(70, $security['score']); // Should be penalized
        $this->assertNotEmpty($security['warnings']);
        
        // Check for sensitive data warnings
        $hasPasswordWarning = false;
        foreach ($security['warnings'] as $warning) {
            if (str_contains($warning, 'sensitive data')) {
                $hasPasswordWarning = true;
                break;
            }
        }
        $this->assertTrue($hasPasswordWarning);
    }

    public function testTimingAnalysisFormats(): void
    {
        $timing = $this->introspector->getTimingInfo($this->validToken);

        // Check timestamp formatting
        $this->assertArrayHasKey('formatted', $timing['issued_at']);
        $this->assertArrayHasKey('iso', $timing['issued_at']);
        $this->assertArrayHasKey('relative', $timing['issued_at']);
        
        $this->assertArrayHasKey('formatted', $timing['expires_at']);
        $this->assertArrayHasKey('iso', $timing['expires_at']);
        $this->assertArrayHasKey('relative', $timing['expires_at']);

        // Check formats are strings
        $this->assertIsString($timing['issued_at']['formatted']);
        $this->assertIsString($timing['issued_at']['iso']);
        $this->assertIsString($timing['issued_at']['relative']);
    }

    public function testTokenWithoutExpiration(): void
    {
        // Create a token manually without expiration to test security warnings
        $encoder = new \LengthOfRope\TreeHouse\Auth\Jwt\JwtEncoder($this->jwtConfig);
        $claims = new \LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager([
            'sub' => '123',
            'iat' => time(),
            'iss' => 'test-app',
            'aud' => 'test-users',
            // Explicitly no exp claim
        ]);
        $noExpToken = $encoder->encode($claims);

        $timing = $this->introspector->getTimingInfo($noExpToken);
        $security = $this->introspector->assessTokenSecurity($noExpToken);

        $this->assertEquals('active', $timing['status']);
        $this->assertNull($timing['expires_at']['timestamp']);
        
        // Security should warn about missing expiration
        $hasExpirationWarning = false;
        foreach ($security['warnings'] as $warning) {
            if (str_contains($warning, 'no expiration')) {
                $hasExpirationWarning = true;
                break;
            }
        }
        $this->assertTrue($hasExpirationWarning);
    }

    public function testLongLivedTokenWarning(): void
    {
        // Create token that expires in 2 days
        $longToken = $this->tokenGenerator->generateCustomToken([
            'sub' => '123',
        ], 172800); // 2 days

        $security = $this->introspector->assessTokenSecurity($longToken);

        // Should warn about long expiration
        $hasLongExpirationWarning = false;
        foreach ($security['warnings'] as $warning) {
            if (str_contains($warning, '24 hours')) {
                $hasLongExpirationWarning = true;
                break;
            }
        }
        $this->assertTrue($hasLongExpirationWarning);
    }

    public function testMetadataExtraction(): void
    {
        $metadataToken = $this->tokenGenerator->generateCustomToken([
            'sub' => '555',
            'type' => 'refresh',
            'family_id' => 'family-123',
            'parent_token_id' => 'parent-456',
            'refresh_count' => 3,
            'scopes' => ['read', 'write'],
            'roles' => ['admin', 'user'],
            'permissions' => ['manage-users'],
        ]);

        $result = $this->introspector->introspect($metadataToken);
        $metadata = $result['metadata'];

        $this->assertEquals('refresh', $metadata['token_type']);
        $this->assertEquals('555', $metadata['user_id']);
        $this->assertEquals('family-123', $metadata['family_id']);
        $this->assertEquals('parent-456', $metadata['parent_token_id']);
        $this->assertEquals(3, $metadata['refresh_count']);
        $this->assertEquals(['read', 'write'], $metadata['scopes']);
        $this->assertEquals(['admin', 'user'], $metadata['roles']);
        $this->assertEquals(['manage-users'], $metadata['permissions']);
    }
}