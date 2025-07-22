<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Jwt;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT Configuration Test
 *
 * Tests the JWT configuration management including validation,
 * algorithm support, and security requirements.
 *
 * @package Tests\Unit\Auth\Jwt
 */
class JwtConfigTest extends TestCase
{
    private string $validSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validSecret = str_repeat('a', 32); // 32 character secret
    }

    public function testConstructorWithDefaults(): void
    {
        $config = new JwtConfig(['secret' => $this->validSecret]);

        $this->assertEquals('HS256', $config->getAlgorithm());
        $this->assertEquals(900, $config->getTtl());
        $this->assertEquals(1209600, $config->getRefreshTtl());
        $this->assertTrue($config->isBlacklistEnabled());
        $this->assertEquals(0, $config->getBlacklistGracePeriod());
        $this->assertEquals(0, $config->getLeeway());
        $this->assertEquals(['iss', 'iat', 'exp', 'nbf', 'sub'], $config->getRequiredClaims());
    }

    public function testConstructorWithCustomValues(): void
    {
        $configData = [
            'secret' => $this->validSecret,
            'algorithm' => 'RS256',
            'ttl' => 1800,
            'refresh_ttl' => 2419200,
            'blacklist_enabled' => false,
            'blacklist_grace_period' => 30,
            'leeway' => 10,
            'required_claims' => ['exp', 'iat'],
            'issuer' => 'test-issuer',
            'audience' => 'test-audience',
            'subject' => 'test-subject',
            'private_key' => 'test-private-key',
        ];

        $config = new JwtConfig($configData);

        $this->assertEquals('RS256', $config->getAlgorithm());
        $this->assertEquals(1800, $config->getTtl());
        $this->assertEquals(2419200, $config->getRefreshTtl());
        $this->assertFalse($config->isBlacklistEnabled());
        $this->assertEquals(30, $config->getBlacklistGracePeriod());
        $this->assertEquals(10, $config->getLeeway());
        $this->assertEquals(['exp', 'iat'], $config->getRequiredClaims());
        $this->assertEquals('test-issuer', $config->getIssuer());
        $this->assertEquals('test-audience', $config->getAudience());
        $this->assertEquals('test-subject', $config->getSubject());
    }

    public function testGetSecret(): void
    {
        $config = new JwtConfig(['secret' => $this->validSecret]);
        $this->assertEquals($this->validSecret, $config->getSecret());
    }

    public function testGetSecretThrowsExceptionWhenMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret is required but not configured');

        $config = new JwtConfig([]);
        $config->getSecret();
    }

    public function testGetSecretThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret is required but not configured');

        $config = new JwtConfig(['secret' => '']);
        $config->getSecret();
    }

    public function testGetSecretThrowsExceptionWhenTooShort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret must be at least 32 characters long');

        $config = new JwtConfig(['secret' => 'short']);
        $config->getSecret();
    }

    public function testSupportedAlgorithms(): void
    {
        // Test HS256
        $config = new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'HS256',
        ]);
        $this->assertEquals('HS256', $config->getAlgorithm());
        
        // Test RS256 with private key
        $config = new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'RS256',
            'private_key' => 'test-private-key',
        ]);
        $this->assertEquals('RS256', $config->getAlgorithm());
        
        // Test ES256 with public key
        $config = new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'ES256',
            'public_key' => 'test-public-key',
        ]);
        $this->assertEquals('ES256', $config->getAlgorithm());
    }

    public function testUnsupportedAlgorithmThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported JWT algorithm: HS512');

        new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'HS512',
        ]);
    }

    public function testInvalidTtlThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT TTL must be greater than 0');

        new JwtConfig([
            'secret' => $this->validSecret,
            'ttl' => 0,
        ]);
    }

    public function testInvalidRefreshTtlThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT refresh TTL must be greater than 0');

        new JwtConfig([
            'secret' => $this->validSecret,
            'refresh_ttl' => -1,
        ]);
    }

    public function testRefreshTtlMustBeGreaterThanTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT refresh TTL must be greater than access token TTL');

        new JwtConfig([
            'secret' => $this->validSecret,
            'ttl' => 1000,
            'refresh_ttl' => 500,
        ]);
    }

    public function testInvalidLeewayThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT leeway cannot be negative');

        new JwtConfig([
            'secret' => $this->validSecret,
            'leeway' => -1,
        ]);
    }

    public function testInvalidGracePeriodThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT blacklist grace period cannot be negative');

        new JwtConfig([
            'secret' => $this->validSecret,
            'blacklist_grace_period' => -5,
        ]);
    }

    public function testInvalidRequiredClaimsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT required claims must be an array');

        new JwtConfig([
            'secret' => $this->validSecret,
            'required_claims' => 'invalid',
        ]);
    }

    public function testRsaAlgorithmRequiresKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Private key or public key is required for RS256 algorithm');

        new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'RS256',
        ]);
    }

    public function testEcdsaAlgorithmRequiresKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Private key or public key is required for ES256 algorithm');

        new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'ES256',
        ]);
    }

    public function testGetAndSetMethods(): void
    {
        $config = new JwtConfig(['secret' => $this->validSecret]);

        // Test get method
        $this->assertEquals('HS256', $config->get('algorithm'));
        $this->assertEquals('default', $config->get('non_existent', 'default'));

        // Test set method
        $config->set('custom_claim', 'custom_value');
        $this->assertEquals('custom_value', $config->get('custom_claim'));
    }

    public function testSetInvalidAlgorithmThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported JWT algorithm: INVALID');

        $config = new JwtConfig(['secret' => $this->validSecret]);
        $config->set('algorithm', 'INVALID');
    }

    public function testToArray(): void
    {
        $configData = [
            'secret' => $this->validSecret,
            'algorithm' => 'HS256',
            'issuer' => 'test-issuer',
        ];

        $config = new JwtConfig($configData);
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertEquals($this->validSecret, $array['secret']);
        $this->assertEquals('HS256', $array['algorithm']);
        $this->assertEquals('test-issuer', $array['issuer']);
        $this->assertEquals(900, $array['ttl']); // Default value
    }

    public function testOptionalClaimGetters(): void
    {
        $config = new JwtConfig(['secret' => $this->validSecret]);

        $this->assertNull($config->getIssuer());
        $this->assertNull($config->getAudience());
        $this->assertNull($config->getSubject());
        $this->assertNull($config->getPrivateKey());
        $this->assertNull($config->getPublicKey());
    }

    public function testOptionalClaimGettersWithValues(): void
    {
        $configData = [
            'secret' => $this->validSecret,
            'issuer' => 'test-issuer',
            'audience' => 'test-audience',
            'subject' => 'test-subject',
            'private_key' => 'private-key-content',
            'public_key' => 'public-key-content',
        ];

        $config = new JwtConfig($configData);

        $this->assertEquals('test-issuer', $config->getIssuer());
        $this->assertEquals('test-audience', $config->getAudience());
        $this->assertEquals('test-subject', $config->getSubject());
        $this->assertEquals('private-key-content', $config->getPrivateKey());
        $this->assertEquals('public-key-content', $config->getPublicKey());
    }

    public function testValidRsaConfigurationWithPrivateKey(): void
    {
        $config = new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'RS256',
            'private_key' => 'rsa-private-key',
        ]);

        $this->assertEquals('RS256', $config->getAlgorithm());
        $this->assertEquals('rsa-private-key', $config->getPrivateKey());
    }

    public function testValidEcdsaConfigurationWithPublicKey(): void
    {
        $config = new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'ES256',
            'public_key' => 'ecdsa-public-key',
        ]);

        $this->assertEquals('ES256', $config->getAlgorithm());
        $this->assertEquals('ecdsa-public-key', $config->getPublicKey());
    }
}