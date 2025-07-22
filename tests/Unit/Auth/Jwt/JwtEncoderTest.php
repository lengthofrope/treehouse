<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Jwt;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtEncoder;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT Encoder Test
 *
 * Tests the JWT token encoding functionality including header creation,
 * payload encoding, and signature generation.
 *
 * @package Tests\Unit\Auth\Jwt
 */
class JwtEncoderTest extends TestCase
{
    private JwtConfig $config;
    private JwtEncoder $encoder;
    private string $validSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validSecret = str_repeat('a', 32);
        $this->config = new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'HS256',
            'ttl' => 3600,
            'issuer' => 'test-issuer',
            'audience' => 'test-audience',
            'subject' => 'test-subject',
        ]);
        $this->encoder = new JwtEncoder($this->config);
    }

    public function testEncodeBasicToken(): void
    {
        $claims = new ClaimsManager([
            'sub' => 'user123',
            'name' => 'John Doe',
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $token = $this->encoder->encode($claims);

        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token);
        
        // JWT should have exactly 3 parts
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
        
        // Each part should be base64url encoded
        foreach ($parts as $part) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $part);
        }
    }

    public function testEncodeWithDefaults(): void
    {
        $customClaims = [
            'user_id' => 123,
            'name' => 'John Doe',
            'roles' => ['user', 'admin'],
        ];

        $token = $this->encoder->encodeWithDefaults($customClaims);

        $this->assertIsString($token);
        $this->assertCount(3, explode('.', $token));
    }

    public function testEncodeTokenStructure(): void
    {
        $claims = new ClaimsManager([
            'sub' => 'user123',
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $token = $this->encoder->encode($claims);
        $parts = explode('.', $token);

        // Decode header
        $headerJson = $this->base64UrlDecode($parts[0]);
        $header = json_decode($headerJson, true);

        $this->assertEquals('JWT', $header['typ']);
        $this->assertEquals('HS256', $header['alg']);

        // Decode payload
        $payloadJson = $this->base64UrlDecode($parts[1]);
        $payload = json_decode($payloadJson, true);

        $this->assertEquals('user123', $payload['sub']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function testEncodeWithCustomHeader(): void
    {
        $claims = new ClaimsManager([
            'sub' => 'user123',
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $customHeader = [
            'kid' => 'key-id-123',
            'custom' => 'value',
        ];

        $token = $this->encoder->encode($claims, $customHeader);
        $parts = explode('.', $token);

        // Decode header
        $headerJson = $this->base64UrlDecode($parts[0]);
        $header = json_decode($headerJson, true);

        $this->assertEquals('JWT', $header['typ']);
        $this->assertEquals('HS256', $header['alg']);
        $this->assertEquals('key-id-123', $header['kid']);
        $this->assertEquals('value', $header['custom']);
    }

    public function testEncodeWithDefaultsIncludesConfiguredClaims(): void
    {
        $token = $this->encoder->encodeWithDefaults(['custom' => 'value']);
        $parts = explode('.', $token);

        // Decode payload
        $payloadJson = $this->base64UrlDecode($parts[1]);
        $payload = json_decode($payloadJson, true);

        $this->assertEquals('test-issuer', $payload['iss']);
        $this->assertEquals(['test-audience'], $payload['aud']); // Audience is normalized to array
        $this->assertEquals('test-subject', $payload['sub']);
        $this->assertEquals('value', $payload['custom']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('nbf', $payload);
    }

    public function testEncodeWithInvalidHeaderTypeThrowsException(): void
    {
        $claims = new ClaimsManager(['sub' => 'user123']);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header must contain typ: JWT');

        $this->encoder->encode($claims, ['typ' => 'INVALID']);
    }

    public function testEncodeWithMissingAlgorithmThrowsException(): void
    {
        $claims = new ClaimsManager(['sub' => 'user123']);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header must contain algorithm');

        $this->encoder->encode($claims, ['alg' => '']);
    }

    public function testEncodeWithUnsupportedAlgorithmThrowsException(): void
    {
        $claims = new ClaimsManager(['sub' => 'user123']);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported algorithm: INVALID');

        $this->encoder->encode($claims, ['alg' => 'INVALID']);
    }

    public function testEncodeRs256RequiresPrivateKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Private key or public key is required for RS256 algorithm');

        new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'RS256',
        ]);
    }

    public function testEncodeEs256RequiresPrivateKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Private key or public key is required for ES256 algorithm');

        new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'ES256',
        ]);
    }

    public function testEncodeTimestampsAreCorrect(): void
    {
        $beforeEncode = time();
        $token = $this->encoder->encodeWithDefaults(['custom' => 'value']);
        $afterEncode = time();

        $parts = explode('.', $token);
        $payloadJson = $this->base64UrlDecode($parts[1]);
        $payload = json_decode($payloadJson, true);

        // Check timestamps are within reasonable range
        $this->assertGreaterThanOrEqual($beforeEncode, $payload['iat']);
        $this->assertLessThanOrEqual($afterEncode, $payload['iat']);
        $this->assertGreaterThanOrEqual($beforeEncode, $payload['nbf']);
        $this->assertLessThanOrEqual($afterEncode, $payload['nbf']);
        $this->assertEquals($payload['iat'] + 3600, $payload['exp']);
    }

    public function testEncodeJsonFailureThrowsException(): void
    {
        // Create claims that cannot be JSON encoded
        $claims = new ClaimsManager();
        $resource = fopen('php://memory', 'r');
        $claims->setClaim('resource', $resource);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to encode claims to JSON');

        try {
            $this->encoder->encode($claims);
        } finally {
            fclose($resource);
        }
    }

    public function testEncodeConsistentOutput(): void
    {
        $claims = new ClaimsManager([
            'sub' => 'user123',
            'iat' => 1640995200, // Fixed timestamp
            'exp' => 1640998800,
            'nbf' => 1640995200,
        ]);

        $token1 = $this->encoder->encode($claims);
        $token2 = $this->encoder->encode($claims);

        // Same claims should produce same token
        $this->assertEquals($token1, $token2);
    }

    public function testEncodeHandlesComplexClaims(): void
    {
        $claims = new ClaimsManager([
            'sub' => 'user123',
            'iat' => time(),
            'exp' => time() + 3600,
            'user_data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'roles' => ['user', 'admin'],
                'settings' => [
                    'theme' => 'dark',
                    'notifications' => true,
                ],
            ],
            'permissions' => ['read', 'write', 'delete'],
            'unicode' => 'Hello 🌍 World! 测试',
        ]);

        $token = $this->encoder->encode($claims);
        $this->assertIsString($token);

        $parts = explode('.', $token);
        $payloadJson = $this->base64UrlDecode($parts[1]);
        $payload = json_decode($payloadJson, true);

        $this->assertEquals('user123', $payload['sub']);
        $this->assertEquals('John Doe', $payload['user_data']['name']);
        $this->assertEquals(['user', 'admin'], $payload['user_data']['roles']);
        $this->assertEquals(['read', 'write', 'delete'], $payload['permissions']);
        $this->assertEquals('Hello 🌍 World! 测试', $payload['unicode']);
    }

    public function testEncodeConfigWithoutOptionalClaims(): void
    {
        $config = new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'HS256',
            'ttl' => 3600,
        ]);
        $encoder = new JwtEncoder($config);

        $token = $encoder->encodeWithDefaults(['custom' => 'value']);
        $parts = explode('.', $token);

        $payloadJson = $this->base64UrlDecode($parts[1]);
        $payload = json_decode($payloadJson, true);

        $this->assertEquals('value', $payload['custom']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('nbf', $payload);
        
        // Optional claims should not be present
        $this->assertArrayNotHasKey('iss', $payload);
        $this->assertArrayNotHasKey('aud', $payload);
        $this->assertArrayNotHasKey('sub', $payload);
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}