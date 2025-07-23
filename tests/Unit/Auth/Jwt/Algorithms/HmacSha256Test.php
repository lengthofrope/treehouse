<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Jwt\Algorithms;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Auth\Jwt\Algorithms\HmacSha256;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * HMAC SHA-256 Algorithm Test
 *
 * Tests the HMAC SHA-256 JWT algorithm implementation including
 * signing, verification, and security validations.
 *
 * @package Tests\Unit\Auth\Jwt\Algorithms
 */
class HmacSha256Test extends TestCase
{
    private HmacSha256 $algorithm;
    private string $validKey;
    private string $message;

    protected function setUp(): void
    {
        parent::setUp();
        $this->algorithm = new HmacSha256();
        $this->validKey = str_repeat('a', 32); // 32 character key
        $this->message = 'test.message.to.sign';
    }

    public function testGetAlgorithmName(): void
    {
        $this->assertEquals('HS256', $this->algorithm->getAlgorithmName());
    }

    public function testSignWithValidKey(): void
    {
        $signature = $this->algorithm->sign($this->message, $this->validKey);
        
        $this->assertIsString($signature);
        $this->assertNotEmpty($signature);
        
        // Verify it's base64url encoded (no padding, uses - and _ instead of + and /)
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $signature);
    }

    public function testSignWithDifferentKeysProducesDifferentSignatures(): void
    {
        $key1 = str_repeat('a', 32);
        $key2 = str_repeat('b', 32);
        
        $signature1 = $this->algorithm->sign($this->message, $key1);
        $signature2 = $this->algorithm->sign($this->message, $key2);
        
        $this->assertNotEquals($signature1, $signature2);
    }

    public function testSignWithDifferentMessagesProducesDifferentSignatures(): void
    {
        $message1 = 'first.message';
        $message2 = 'second.message';
        
        $signature1 = $this->algorithm->sign($message1, $this->validKey);
        $signature2 = $this->algorithm->sign($message2, $this->validKey);
        
        $this->assertNotEquals($signature1, $signature2);
    }

    public function testVerifyWithValidSignature(): void
    {
        $signature = $this->algorithm->sign($this->message, $this->validKey);
        $isValid = $this->algorithm->verify($this->message, $signature, $this->validKey);
        
        $this->assertTrue($isValid);
    }

    public function testVerifyWithInvalidSignature(): void
    {
        $signature = $this->algorithm->sign($this->message, $this->validKey);
        $tamperedSignature = substr($signature, 0, -1) . 'X'; // Tamper with signature
        
        $isValid = $this->algorithm->verify($this->message, $tamperedSignature, $this->validKey);
        
        $this->assertFalse($isValid);
    }

    public function testVerifyWithWrongKey(): void
    {
        $key1 = str_repeat('a', 32);
        $key2 = str_repeat('b', 32);
        
        $signature = $this->algorithm->sign($this->message, $key1);
        $isValid = $this->algorithm->verify($this->message, $signature, $key2);
        
        $this->assertFalse($isValid);
    }

    public function testVerifyWithWrongMessage(): void
    {
        $signature = $this->algorithm->sign($this->message, $this->validKey);
        $wrongMessage = $this->message . '.tampered';
        
        $isValid = $this->algorithm->verify($wrongMessage, $signature, $this->validKey);
        
        $this->assertFalse($isValid);
    }

    public function testSignWithEmptyKeyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HMAC key cannot be empty');
        
        $this->algorithm->sign($this->message, '');
    }

    public function testSignWithShortKeyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HMAC key must be at least 32 bytes long for security');
        
        $this->algorithm->sign($this->message, 'short_key');
    }

    public function testVerifyWithEmptyKeyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HMAC key cannot be empty');
        
        $this->algorithm->verify($this->message, 'signature', '');
    }

    public function testVerifyWithShortKeyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HMAC key must be at least 32 bytes long for security');
        
        $this->algorithm->verify($this->message, 'signature', 'short');
    }

    public function testVerifyReturnsFalseOnSigningException(): void
    {
        // This test checks that verify returns false if sign throws an exception
        // We'll test with an invalid signature format that can't be re-signed
        $isValid = $this->algorithm->verify($this->message, 'invalid-signature', $this->validKey);
        
        $this->assertFalse($isValid);
    }

    public function testSignatureIsConsistent(): void
    {
        // Same message and key should always produce the same signature
        $signature1 = $this->algorithm->sign($this->message, $this->validKey);
        $signature2 = $this->algorithm->sign($this->message, $this->validKey);
        
        $this->assertEquals($signature1, $signature2);
    }

    public function testSignatureLength(): void
    {
        $signature = $this->algorithm->sign($this->message, $this->validKey);
        
        // SHA-256 hash is 32 bytes = 256 bits
        // Base64url encoding without padding should be 43 characters
        $this->assertEquals(43, strlen($signature));
    }

    public function testMinimumKeyLength(): void
    {
        // Test exactly 32 characters should work
        $minKey = str_repeat('a', 32);
        $signature = $this->algorithm->sign($this->message, $minKey);
        
        $this->assertIsString($signature);
        $this->assertTrue($this->algorithm->verify($this->message, $signature, $minKey));
    }

    public function testLongerKeyWorks(): void
    {
        // Test longer key should also work
        $longKey = str_repeat('a', 64);
        $signature = $this->algorithm->sign($this->message, $longKey);
        
        $this->assertIsString($signature);
        $this->assertTrue($this->algorithm->verify($this->message, $signature, $longKey));
    }

    public function testBinaryKey(): void
    {
        // Test with binary key data
        $binaryKey = random_bytes(32);
        $signature = $this->algorithm->sign($this->message, $binaryKey);
        
        $this->assertIsString($signature);
        $this->assertTrue($this->algorithm->verify($this->message, $signature, $binaryKey));
    }

    public function testEmptyMessage(): void
    {
        // Test with empty message
        $emptyMessage = '';
        $signature = $this->algorithm->sign($emptyMessage, $this->validKey);
        
        $this->assertIsString($signature);
        $this->assertTrue($this->algorithm->verify($emptyMessage, $signature, $this->validKey));
    }

    public function testUnicodeMessage(): void
    {
        // Test with Unicode message
        $unicodeMessage = 'Hello 🌍 World! 测试';
        $signature = $this->algorithm->sign($unicodeMessage, $this->validKey);
        
        $this->assertIsString($signature);
        $this->assertTrue($this->algorithm->verify($unicodeMessage, $signature, $this->validKey));
    }

    public function testTimingSafeComparison(): void
    {
        // Test that signature comparison is timing-safe
        // This is difficult to test directly, but we can verify the behavior
        $signature = $this->algorithm->sign($this->message, $this->validKey);
        
        // Create signatures of different lengths
        $shortSig = substr($signature, 0, 10);
        $longSig = $signature . 'extra';
        
        // Both should return false quickly (length check)
        $this->assertFalse($this->algorithm->verify($this->message, $shortSig, $this->validKey));
        $this->assertFalse($this->algorithm->verify($this->message, $longSig, $this->validKey));
    }

    public function testBase64UrlEncoding(): void
    {
        $signature = $this->algorithm->sign($this->message, $this->validKey);
        
        // Should not contain standard base64 characters + and /
        $this->assertStringNotContainsString('+', $signature);
        $this->assertStringNotContainsString('/', $signature);
        
        // Should not contain padding
        $this->assertStringNotContainsString('=', $signature);
        
        // Should only contain base64url characters
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $signature);
    }
}