<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt\Algorithms;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * RSA SHA-256 Algorithm Implementation
 *
 * Implements the RS256 (RSA using SHA-256) algorithm for JWT signatures.
 * This is an asymmetric algorithm that uses a private key for signing
 * and a public key for verification.
 *
 * Features:
 * - RFC 7518 compliant RS256 implementation
 * - Asymmetric key signing and verification
 * - Private/public key validation
 * - OpenSSL integration for cryptographic operations
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt\Algorithms
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RsaSha256 implements AlgorithmInterface
{
    /**
     * OpenSSL signature algorithm
     */
    private const OPENSSL_ALGORITHM = OPENSSL_ALGO_SHA256;

    /**
     * Get the algorithm name
     *
     * @return string Algorithm name
     */
    public function getAlgorithmName(): string
    {
        return 'RS256';
    }

    /**
     * Sign the given message with the provided private key
     *
     * @param string $message Message to sign
     * @param string $key Private key in PEM format
     * @return string Base64URL encoded signature
     * @throws InvalidArgumentException If signing fails or key is invalid
     */
    public function sign(string $message, string $key): string
    {
        $privateKey = $this->parsePrivateKey($key);

        $signature = '';
        $success = openssl_sign($message, $signature, $privateKey, self::OPENSSL_ALGORITHM);

        if (!$success) {
            throw new InvalidArgumentException(
                'Failed to sign message: ' . openssl_error_string(),
                'JWT_RSA_SIGN_FAILED'
            );
        }

        return $this->base64UrlEncode($signature);
    }

    /**
     * Verify the signature of the given message
     *
     * @param string $message Original message
     * @param string $signature Base64URL encoded signature to verify
     * @param string $key Public key in PEM format
     * @return bool True if signature is valid
     */
    public function verify(string $message, string $signature, string $key): bool
    {
        try {
            $publicKey = $this->parsePublicKey($key);
            $decodedSignature = $this->base64UrlDecode($signature);

            $result = openssl_verify($message, $decodedSignature, $publicKey, self::OPENSSL_ALGORITHM);

            return $result === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Parse and validate a private key
     *
     * @param string $key Private key string
     * @return \OpenSSLAsymmetricKey Private key resource
     * @throws InvalidArgumentException If key is invalid
     */
    private function parsePrivateKey(string $key): \OpenSSLAsymmetricKey
    {
        if (empty($key)) {
            throw new InvalidArgumentException('RSA private key cannot be empty', 'JWT_EMPTY_RSA_PRIVATE_KEY');
        }

        $privateKey = openssl_pkey_get_private($key);

        if ($privateKey === false) {
            throw new InvalidArgumentException(
                'Invalid RSA private key: ' . openssl_error_string(),
                'JWT_INVALID_RSA_PRIVATE_KEY'
            );
        }

        // Validate key type
        $keyDetails = openssl_pkey_get_details($privateKey);
        if ($keyDetails === false || $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new InvalidArgumentException(
                'Key must be an RSA private key',
                'JWT_INVALID_RSA_KEY_TYPE'
            );
        }

        // Check minimum key size (2048 bits recommended)
        if ($keyDetails['bits'] < 2048) {
            throw new InvalidArgumentException(
                'RSA key must be at least 2048 bits for security (current: ' . $keyDetails['bits'] . ' bits)',
                'JWT_WEAK_RSA_KEY'
            );
        }

        return $privateKey;
    }

    /**
     * Parse and validate a public key
     *
     * @param string $key Public key string
     * @return \OpenSSLAsymmetricKey Public key resource
     * @throws InvalidArgumentException If key is invalid
     */
    private function parsePublicKey(string $key): \OpenSSLAsymmetricKey
    {
        if (empty($key)) {
            throw new InvalidArgumentException('RSA public key cannot be empty', 'JWT_EMPTY_RSA_PUBLIC_KEY');
        }

        $publicKey = openssl_pkey_get_public($key);

        if ($publicKey === false) {
            throw new InvalidArgumentException(
                'Invalid RSA public key: ' . openssl_error_string(),
                'JWT_INVALID_RSA_PUBLIC_KEY'
            );
        }

        // Validate key type
        $keyDetails = openssl_pkey_get_details($publicKey);
        if ($keyDetails === false || $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new InvalidArgumentException(
                'Key must be an RSA public key',
                'JWT_INVALID_RSA_KEY_TYPE'
            );
        }

        return $publicKey;
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
     * Base64URL decode data
     *
     * @param string $data Data to decode
     * @return string Decoded data
     * @throws InvalidArgumentException If decoding fails
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64url signature', 'JWT_INVALID_BASE64URL');
        }

        return $decoded;
    }
}