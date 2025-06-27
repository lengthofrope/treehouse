<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Security;

/**
 * AES-256-CBC Encryption and Decryption
 *
 * Provides secure encryption and decryption using AES-256-CBC cipher with
 * random initialization vectors. Includes payload encryption with expiration
 * support, hashing utilities, and secure random byte generation.
 *
 * Features:
 * - AES-256-CBC encryption with secure IV generation
 * - Payload encryption with optional expiration
 * - SHA-256 hashing with salt support
 * - Timing-safe string comparison
 * - Secure random byte generation
 * - Base64 encoding for safe transport
 *
 * @package LengthOfRope\TreeHouse\Security
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Encryption
{
    /**
     * Encryption key for AES operations
     */
    private string $key;
    
    /**
     * Cipher method used for encryption
     */
    private string $cipher = 'AES-256-CBC';

    /**
     * Create a new encryption instance
     *
     * @param string $key Encryption key (must be at least 32 characters)
     * @throws \InvalidArgumentException If key is empty or too short
     */
    public function __construct(string $key)
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Encryption key cannot be empty');
        }
        
        if (strlen($key) < 32) {
            throw new \InvalidArgumentException('Encryption key must be at least 32 characters');
        }
        
        $this->key = $key;
    }

    /**
     * Encrypt the given data
     *
     * Encrypts data using AES-256-CBC with a randomly generated IV.
     * The IV is prepended to the encrypted data and the result is base64 encoded.
     *
     * @param string $data Data to encrypt
     * @return string Base64 encoded encrypted data with IV
     * @throws \RuntimeException If encryption fails
     */
    public function encrypt(string $data): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($data, $this->cipher, $this->key, 0, $iv);
        
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt the given encrypted data
     *
     * Decrypts base64 encoded data that was encrypted with the encrypt() method.
     * Extracts the IV from the beginning of the data and uses it for decryption.
     *
     * @param string $encryptedData Base64 encoded encrypted data with IV
     * @return string Decrypted data
     * @throws \InvalidArgumentException If data is invalid or decryption fails
     */
    public function decrypt(string $encryptedData): string
    {
        if (empty($encryptedData)) {
            throw new \InvalidArgumentException('Encrypted data cannot be empty');
        }
        
        $data = base64_decode($encryptedData, true);
        
        if ($data === false) {
            throw new \InvalidArgumentException('Invalid encrypted data');
        }
        
        $ivLength = openssl_cipher_iv_length($this->cipher);
        
        if (strlen($data) < $ivLength) {
            throw new \InvalidArgumentException('Invalid encrypted data');
        }
        
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
        
        if ($decrypted === false) {
            throw new \InvalidArgumentException('Decryption failed');
        }
        
        return $decrypted;
    }

    /**
     * Encrypt a payload with optional expiration
     *
     * Encrypts an array payload as JSON. If expiration is provided, wraps the
     * payload with expiration metadata. Useful for creating secure tokens
     * with automatic expiration.
     *
     * @param array $payload Data to encrypt
     * @param int|null $expiresAt Unix timestamp when payload expires (optional)
     * @return string Encrypted payload
     * @throws \InvalidArgumentException If payload cannot be JSON encoded
     */
    public function encryptPayload(array $payload, ?int $expiresAt = null): string
    {
        $data = $payload;
        
        if ($expiresAt !== null) {
            // When expiration is provided as parameter, wrap the payload
            $data = [
                'data' => $payload,
                'expires_at' => $expiresAt
            ];
        }
        
        $json = json_encode($data);
        
        if ($json === false) {
            throw new \InvalidArgumentException('Failed to encode payload');
        }
        
        return $this->encrypt($json);
    }

    /**
     * Decrypt a payload and check expiration
     *
     * Decrypts a payload that was encrypted with encryptPayload(). Automatically
     * checks for expiration and throws an exception if the payload has expired.
     *
     * @param string $encryptedPayload Encrypted payload data
     * @return array Decrypted payload data
     * @throws \InvalidArgumentException If decryption fails, JSON is invalid, or payload expired
     */
    public function decryptPayload(string $encryptedPayload): array
    {
        $json = $this->decrypt($encryptedPayload);
        $payload = json_decode($json, true);
        
        if ($payload === null) {
            throw new \InvalidArgumentException('Failed to decode payload');
        }
        
        // Check for expiration in both formats
        if (isset($payload['expires_at']) && $payload['expires_at'] < time()) {
            throw new \InvalidArgumentException('Payload has expired');
        }
        
        return $payload;
    }

    /**
     * Generate a hash of the given data
     *
     * Creates a SHA-256 hash of the data combined with an optional salt.
     * The salt is appended to the data before hashing.
     *
     * @param string $data Data to hash
     * @param string $salt Optional salt to append to data
     * @return string SHA-256 hash as hexadecimal string
     */
    public function hash(string $data, string $salt = ''): string
    {
        return hash('sha256', $data . $salt);
    }

    /**
     * Verify if the given data matches the hash
     *
     * Performs a timing-safe comparison between the hash of the provided data
     * (with optional salt) and the expected hash value.
     *
     * @param string $data Original data to verify
     * @param string $hash Expected hash value
     * @param string $salt Optional salt used in original hashing
     * @return bool True if hashes match, false otherwise
     */
    public function verifyHash(string $data, string $hash, string $salt = ''): bool
    {
        return $this->constantTimeCompare($this->hash($data, $salt), $hash);
    }

    /**
     * Generate secure random bytes
     *
     * Generates cryptographically secure random bytes using the system's
     * random number generator. Useful for creating keys, tokens, and nonces.
     *
     * @param int $length Number of bytes to generate
     * @return string Random bytes
     * @throws \InvalidArgumentException If length is not positive
     * @throws \Exception If random_bytes() fails
     */
    public function secureRandomBytes(int $length): string
    {
        if ($length <= 0) {
            throw new \InvalidArgumentException('Length must be greater than 0');
        }
        
        return random_bytes($length);
    }

    /**
     * Perform a constant-time string comparison
     *
     * Uses hash_equals() to prevent timing attacks when comparing sensitive
     * strings like hashes or tokens. First checks length equality to avoid
     * unnecessary hash_equals() calls.
     *
     * @param string $string1 First string to compare
     * @param string $string2 Second string to compare
     * @return bool True if strings are identical, false otherwise
     */
    public function constantTimeCompare(string $string1, string $string2): bool
    {
        if (strlen($string1) !== strlen($string2)) {
            return false;
        }
        
        return hash_equals($string1, $string2);
    }

    /**
     * Generate a new encryption key
     *
     * Creates a cryptographically secure 64-character hexadecimal key
     * suitable for use with AES-256-CBC encryption. The key is generated
     * from 32 random bytes.
     *
     * @return string 64-character hexadecimal encryption key
     * @throws \Exception If random_bytes() fails
     */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }
}