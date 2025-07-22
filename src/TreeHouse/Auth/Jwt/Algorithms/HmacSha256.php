<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt\Algorithms;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * HMAC SHA-256 Algorithm Implementation
 *
 * Implements the HS256 (HMAC using SHA-256) algorithm for JWT signatures.
 * This is a symmetric algorithm that uses the same key for signing and verification.
 *
 * Features:
 * - RFC 7518 compliant HS256 implementation
 * - Symmetric key signing and verification
 * - Timing-safe signature verification
 * - Key validation and security checks
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt\Algorithms
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class HmacSha256 implements AlgorithmInterface
{
    /**
     * Minimum key length in bytes for security
     */
    private const MIN_KEY_LENGTH = 32;

    /**
     * Get the algorithm name
     *
     * @return string Algorithm name
     */
    public function getAlgorithmName(): string
    {
        return 'HS256';
    }

    /**
     * Sign the given message with the provided key
     *
     * @param string $message Message to sign
     * @param string $key Signing key
     * @return string Base64URL encoded signature
     * @throws InvalidArgumentException If key is invalid
     */
    public function sign(string $message, string $key): string
    {
        $this->validateKey($key);

        $signature = hash_hmac('sha256', $message, $key, true);

        return $this->base64UrlEncode($signature);
    }

    /**
     * Verify the signature of the given message
     *
     * @param string $message Original message
     * @param string $signature Base64URL encoded signature to verify
     * @param string $key Verification key
     * @return bool True if signature is valid
     * @throws InvalidArgumentException If key is invalid
     */
    public function verify(string $message, string $signature, string $key): bool
    {
        $this->validateKey($key);

        try {
            $expectedSignature = $this->sign($message, $key);
            return $this->constantTimeCompare($signature, $expectedSignature);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate the signing key
     *
     * @param string $key Key to validate
     * @return void
     * @throws InvalidArgumentException If key is invalid
     */
    private function validateKey(string $key): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException('HMAC key cannot be empty', 'JWT_EMPTY_HMAC_KEY');
        }

        if (strlen($key) < self::MIN_KEY_LENGTH) {
            throw new InvalidArgumentException(
                'HMAC key must be at least ' . self::MIN_KEY_LENGTH . ' bytes long for security',
                'JWT_WEAK_HMAC_KEY'
            );
        }
    }

    /**
     * Base64URL encode data
     *
     * @param string $data Data to encode
     * @return string Base64URL encoded string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Perform a constant-time string comparison
     *
     * @param string $signature1 First signature
     * @param string $signature2 Second signature
     * @return bool True if signatures match
     */
    private function constantTimeCompare(string $signature1, string $signature2): bool
    {
        if (strlen($signature1) !== strlen($signature2)) {
            return false;
        }

        return hash_equals($signature1, $signature2);
    }
}