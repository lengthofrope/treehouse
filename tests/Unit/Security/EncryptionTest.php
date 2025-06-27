<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use LengthOfRope\TreeHouse\Security\Encryption;
use Tests\TestCase;

class EncryptionTest extends TestCase
{
    private Encryption $encryption;
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->key = 'test-encryption-key-32-characters';
        $this->encryption = new Encryption($this->key);
    }

    public function testEncryptReturnsEncryptedString(): void
    {
        $plaintext = 'Hello, World!';
        $encrypted = $this->encryption->encrypt($plaintext);

        $this->assertIsString($encrypted);
        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertGreaterThan(strlen($plaintext), strlen($encrypted));
    }

    public function testEncryptedDataIsBase64Encoded(): void
    {
        $plaintext = 'Hello, World!';
        $encrypted = $this->encryption->encrypt($plaintext);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]*={0,2}$/', $encrypted);
        $this->assertNotFalse(base64_decode($encrypted, true));
    }

    public function testDecryptReturnsOriginalString(): void
    {
        $plaintext = 'Hello, World!';
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptGeneratesDifferentOutputForSameInput(): void
    {
        $plaintext = 'Hello, World!';
        $encrypted1 = $this->encryption->encrypt($plaintext);
        $encrypted2 = $this->encryption->encrypt($plaintext);

        $this->assertNotEquals($encrypted1, $encrypted2);
        $this->assertEquals($plaintext, $this->encryption->decrypt($encrypted1));
        $this->assertEquals($plaintext, $this->encryption->decrypt($encrypted2));
    }

    public function testEncryptEmptyString(): void
    {
        $encrypted = $this->encryption->encrypt('');
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals('', $decrypted);
    }

    public function testEncryptLargeString(): void
    {
        $plaintext = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 1000);
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptSpecialCharacters(): void
    {
        $plaintext = '!@#$%^&*()_+-=[]{}|;:,.<>?~`"\'\\';
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptUnicodeCharacters(): void
    {
        $plaintext = 'Hello 世界 🌍 Ñoël';
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testDecryptInvalidDataThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encrypted data');

        $this->encryption->decrypt('invalid-encrypted-data');
    }

    public function testDecryptTamperedDataThrowsException(): void
    {
        $plaintext = 'Hello, World!';
        $encrypted = $this->encryption->encrypt($plaintext);
        
        // Tamper with the encrypted data
        $tamperedEncrypted = substr($encrypted, 0, -5) . 'XXXXX';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->encryption->decrypt($tamperedEncrypted);
    }

    public function testDecryptEmptyStringThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Encrypted data cannot be empty');

        $this->encryption->decrypt('');
    }

    public function testConstructorWithInvalidKeyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Encryption key must be at least 32 characters');

        new Encryption('short-key');
    }

    public function testConstructorWithEmptyKeyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Encryption key cannot be empty');

        new Encryption('');
    }

    public function testGenerateKeyCreatesValidKey(): void
    {
        $key = Encryption::generateKey();

        $this->assertIsString($key);
        $this->assertEquals(64, strlen($key)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key);
    }

    public function testGenerateKeyCreatesUniqueKeys(): void
    {
        $key1 = Encryption::generateKey();
        $key2 = Encryption::generateKey();

        $this->assertNotEquals($key1, $key2);
    }

    public function testEncryptWithPayload(): void
    {
        $payload = [
            'user_id' => 123,
            'email' => 'test@example.com',
            'expires_at' => time() + 3600
        ];

        $encrypted = $this->encryption->encryptPayload($payload);
        $decrypted = $this->encryption->decryptPayload($encrypted);

        $this->assertEquals($payload, $decrypted);
    }

    public function testEncryptPayloadWithExpiration(): void
    {
        $payload = ['data' => 'test'];
        $expiresAt = time() + 3600;

        $encrypted = $this->encryption->encryptPayload($payload, $expiresAt);
        $decrypted = $this->encryption->decryptPayload($encrypted);

        $this->assertEquals($payload, $decrypted['data']);
        $this->assertEquals($expiresAt, $decrypted['expires_at']);
    }

    public function testDecryptExpiredPayloadThrowsException(): void
    {
        $payload = ['data' => 'test'];
        $expiresAt = time() - 3600; // Expired 1 hour ago

        $encrypted = $this->encryption->encryptPayload($payload, $expiresAt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload has expired');

        $this->encryption->decryptPayload($encrypted);
    }

    public function testHashReturnsConsistentHash(): void
    {
        $data = 'test-data-to-hash';
        $hash1 = $this->encryption->hash($data);
        $hash2 = $this->encryption->hash($data);

        $this->assertEquals($hash1, $hash2);
        $this->assertIsString($hash1);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 = 64 hex chars
    }

    public function testHashWithSaltReturnsDifferentHash(): void
    {
        $data = 'test-data-to-hash';
        $salt1 = 'salt1';
        $salt2 = 'salt2';

        $hash1 = $this->encryption->hash($data, $salt1);
        $hash2 = $this->encryption->hash($data, $salt2);

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testVerifyHashReturnsTrueForValidHash(): void
    {
        $data = 'test-data-to-hash';
        $hash = $this->encryption->hash($data);

        $this->assertTrue($this->encryption->verifyHash($data, $hash));
    }

    public function testVerifyHashReturnsFalseForInvalidHash(): void
    {
        $data = 'test-data-to-hash';
        $wrongData = 'wrong-data';
        $hash = $this->encryption->hash($data);

        $this->assertFalse($this->encryption->verifyHash($wrongData, $hash));
    }

    public function testSecureRandomBytesGeneratesRandomData(): void
    {
        $bytes1 = $this->encryption->secureRandomBytes(16);
        $bytes2 = $this->encryption->secureRandomBytes(16);

        $this->assertNotEquals($bytes1, $bytes2);
        $this->assertEquals(16, strlen($bytes1));
        $this->assertEquals(16, strlen($bytes2));
    }

    public function testSecureRandomBytesWithInvalidLengthThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Length must be greater than 0');

        $this->encryption->secureRandomBytes(0);
    }

    public function testConstantTimeCompareReturnsTrueForEqualStrings(): void
    {
        $string1 = 'test-string-123';
        $string2 = 'test-string-123';

        $this->assertTrue($this->encryption->constantTimeCompare($string1, $string2));
    }

    public function testConstantTimeCompareReturnsFalseForDifferentStrings(): void
    {
        $string1 = 'test-string-123';
        $string2 = 'test-string-456';

        $this->assertFalse($this->encryption->constantTimeCompare($string1, $string2));
    }

    public function testConstantTimeCompareReturnsFalseForDifferentLengths(): void
    {
        $string1 = 'short';
        $string2 = 'much-longer-string';

        $this->assertFalse($this->encryption->constantTimeCompare($string1, $string2));
    }
}