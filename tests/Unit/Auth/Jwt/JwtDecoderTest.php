<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Jwt;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtDecoder;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtEncoder;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT Decoder Test
 *
 * Tests the JWT token decoding functionality including token parsing,
 * signature verification, and claims validation.
 *
 * @package Tests\Unit\Auth\Jwt
 */
class JwtDecoderTest extends TestCase
{
    private JwtConfig $config;
    private JwtEncoder $encoder;
    private JwtDecoder $decoder;
    private string $validSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validSecret = str_repeat('a', 32);
        $this->config = new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'HS256',
            'ttl' => 3600,
            'required_claims' => ['iss', 'sub', 'iat', 'exp'],
            'issuer' => 'test-issuer',
            'audience' => 'test-audience',
            'leeway' => 10,
        ]);
        $this->encoder = new JwtEncoder($this->config);
        $this->decoder = new JwtDecoder($this->config);
    }

    public function testDecodeValidToken(): void
    {
        $originalClaims = new ClaimsManager([
            'iss' => 'test-issuer',
            'sub' => 'user123',
            'aud' => 'test-audience',
            'iat' => time(),
            'exp' => time() + 3600,
            'custom' => 'value',
        ]);

        $token = $this->encoder->encode($originalClaims);
        $decodedClaims = $this->decoder->decode($token);

        $this->assertEquals('test-issuer', $decodedClaims->getIssuer());
        $this->assertEquals('user123', $decodedClaims->getSubject());
        $this->assertEquals('value', $decodedClaims->getClaim('custom'));
    }

    public function testDecodeWithoutVerification(): void
    {
        $originalClaims = new ClaimsManager([
            'sub' => 'user123',
            'iat' => time(),
            'exp' => time() + 3600,
            'custom' => 'value',
        ]);

        $token = $this->encoder->encode($originalClaims);
        $decoded = $this->decoder->decodeWithoutVerification($token);

        $this->assertArrayHasKey('header', $decoded);
        $this->assertArrayHasKey('payload', $decoded);
        
        $this->assertEquals('JWT', $decoded['header']['typ']);
        $this->assertEquals('HS256', $decoded['header']['alg']);
        $this->assertEquals('user123', $decoded['payload']['sub']);
        $this->assertEquals('value', $decoded['payload']['custom']);
    }

    public function testDecodeWithoutVerificationParameter(): void
    {
        $originalClaims = new ClaimsManager([
            'iss' => 'test-issuer',
            'sub' => 'user123',
            'aud' => 'test-audience',
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $token = $this->encoder->encode($originalClaims);
        $decodedClaims = $this->decoder->decode($token, false);

        $this->assertEquals('user123', $decodedClaims->getSubject());
    }

    public function testDecodeEmptyTokenThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT token cannot be empty');

        $this->decoder->decode('');
    }

    public function testDecodeInvalidFormatThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT token must have exactly 3 parts separated by dots');

        $this->decoder->decode('invalid.token');
    }

    public function testDecodeEmptyPartsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT token parts cannot be empty');

        $this->decoder->decode('header..signature');
    }

    public function testDecodeInvalidHeaderJsonThrowsException(): void
    {
        $invalidHeaderToken = 'aW52YWxpZC1qc29u.eyJzdWIiOiJ1c2VyMTIzIn0.signature';
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JWT header JSON');

        $this->decoder->decode($invalidHeaderToken);
    }

    public function testDecodeInvalidPayloadJsonThrowsException(): void
    {
        $validHeader = $this->base64UrlEncode('{"typ":"JWT","alg":"HS256"}');
        $invalidPayload = 'aW52YWxpZC1qc29u';
        $signature = 'signature';
        
        $invalidToken = $validHeader . '.' . $invalidPayload . '.' . $signature;
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JWT payload JSON');

        $this->decoder->decode($invalidToken);
    }

    public function testDecodeInvalidHeaderTypeThrowsException(): void
    {
        $invalidHeader = $this->base64UrlEncode('{"typ":"INVALID","alg":"HS256"}');
        $validPayload = $this->base64UrlEncode('{"sub":"user123"}');
        $signature = 'signature';
        
        $invalidToken = $invalidHeader . '.' . $validPayload . '.' . $signature;
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JWT header: typ must be JWT');

        $this->decoder->decode($invalidToken);
    }

    public function testDecodeMissingAlgorithmThrowsException(): void
    {
        $invalidHeader = $this->base64UrlEncode('{"typ":"JWT"}');
        $validPayload = $this->base64UrlEncode('{"sub":"user123"}');
        $signature = 'signature';
        
        $invalidToken = $invalidHeader . '.' . $validPayload . '.' . $signature;
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JWT header: missing algorithm');

        $this->decoder->decode($invalidToken);
    }

    public function testDecodeUnsupportedAlgorithmThrowsException(): void
    {
        $invalidHeader = $this->base64UrlEncode('{"typ":"JWT","alg":"HS512"}');
        $validPayload = $this->base64UrlEncode('{"sub":"user123"}');
        $signature = 'signature';
        
        $invalidToken = $invalidHeader . '.' . $validPayload . '.' . $signature;
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported algorithm: HS512');

        $this->decoder->decode($invalidToken);
    }

    public function testDecodeInvalidSignatureThrowsException(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'test-issuer',
            'sub' => 'user123',
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $token = $this->encoder->encode($claims);
        $tamperedToken = substr($token, 0, -5) . 'XXXXX'; // Tamper with signature
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT signature verification failed');

        $this->decoder->decode($tamperedToken);
    }

    public function testDecodeMissingRequiredClaimsThrowsException(): void
    {
        $claims = new ClaimsManager([
            'sub' => 'user123',
            'iat' => time(),
            'exp' => time() + 3600,
            // Missing 'iss' required claim
        ]);

        $token = $this->encoder->encode($claims);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required claims: iss');

        $this->decoder->decode($token);
    }

    public function testDecodeExpiredTokenThrowsException(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'test-issuer',
            'sub' => 'user123',
            'iat' => time() - 7200,
            'exp' => time() - 3600, // Expired 1 hour ago
        ]);

        $token = $this->encoder->encode($claims);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token has expired');

        $this->decoder->decode($token);
    }

    public function testDecodeNotYetValidTokenThrowsException(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'test-issuer',
            'sub' => 'user123',
            'iat' => time(),
            'exp' => time() + 3600,
            'nbf' => time() + 1800, // Valid in 30 minutes
        ]);

        $token = $this->encoder->encode($claims);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token is not yet valid');

        $this->decoder->decode($token);
    }

    public function testDecodeWithLeeway(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'test-issuer',
            'sub' => 'user123',
            'aud' => 'test-audience',
            'iat' => time(),
            'exp' => time() - 5, // Expired 5 seconds ago
        ]);

        $token = $this->encoder->encode($claims);
        
        // Should pass with 10 second leeway configured
        $decodedClaims = $this->decoder->decode($token);
        $this->assertEquals('user123', $decodedClaims->getSubject());
    }

    public function testDecodeInvalidIssuerThrowsException(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'wrong-issuer',
            'sub' => 'user123',
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $token = $this->encoder->encode($claims);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid issuer: expected test-issuer, got wrong-issuer');

        $this->decoder->decode($token);
    }

    public function testDecodeInvalidAudienceThrowsException(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'test-issuer',
            'sub' => 'user123',
            'aud' => 'wrong-audience',
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $token = $this->encoder->encode($claims);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid audience: expected test-audience');

        $this->decoder->decode($token);
    }

    public function testDecodeValidAudienceArray(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'test-issuer',
            'sub' => 'user123',
            'aud' => ['test-audience', 'other-audience'],
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $token = $this->encoder->encode($claims);
        $decodedClaims = $this->decoder->decode($token);
        
        $this->assertEquals('user123', $decodedClaims->getSubject());
    }

    public function testDecodeConfigWithoutOptionalValidation(): void
    {
        $config = new JwtConfig([
            'secret' => $this->validSecret,
            'algorithm' => 'HS256',
            'required_claims' => ['sub'],
        ]);
        $decoder = new JwtDecoder($config);

        $claims = new ClaimsManager([
            'sub' => 'user123',
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $token = $this->encoder->encode($claims);
        $decodedClaims = $decoder->decode($token);
        
        $this->assertEquals('user123', $decodedClaims->getSubject());
    }

    public function testDecodeComplexClaims(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'test-issuer',
            'sub' => 'user123',
            'aud' => 'test-audience',
            'iat' => time(),
            'exp' => time() + 3600,
            'user_data' => [
                'name' => 'John Doe',
                'roles' => ['admin', 'user'],
                'settings' => ['theme' => 'dark'],
            ],
            'permissions' => ['read', 'write'],
            'unicode' => 'Hello 🌍 测试',
        ]);

        $token = $this->encoder->encode($claims);
        $decodedClaims = $this->decoder->decode($token);

        $userData = $decodedClaims->getClaim('user_data');
        $this->assertEquals('John Doe', $userData['name']);
        $this->assertEquals(['admin', 'user'], $userData['roles']);
        $this->assertEquals(['read', 'write'], $decodedClaims->getClaim('permissions'));
        $this->assertEquals('Hello 🌍 测试', $decodedClaims->getClaim('unicode'));
    }

    public function testDecodeInvalidBase64UrlThrowsException(): void
    {
        $invalidToken = 'invalid+base64/with=padding.payload.signature';
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid base64url encoding');

        $this->decoder->decode($invalidToken);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}