<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt\Algorithms;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * ECDSA SHA-256 Algorithm Implementation
 *
 * Implements the ES256 (ECDSA using P-256 and SHA-256) algorithm for JWT signatures.
 * This is an asymmetric algorithm that uses elliptic curve cryptography with
 * a private key for signing and a public key for verification.
 *
 * Features:
 * - RFC 7518 compliant ES256 implementation
 * - Elliptic curve cryptography (P-256 curve)
 * - Asymmetric key signing and verification
 * - DER to IEEE P1363 signature format conversion
 * - OpenSSL integration for cryptographic operations
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt\Algorithms
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class EcdsaSha256 implements AlgorithmInterface
{
    /**
     * OpenSSL signature algorithm
     */
    private const OPENSSL_ALGORITHM = OPENSSL_ALGO_SHA256;

    /**
     * P-256 curve signature length (64 bytes: 32 bytes r + 32 bytes s)
     */
    private const SIGNATURE_LENGTH = 64;

    /**
     * Get the algorithm name
     *
     * @return string Algorithm name
     */
    public function getAlgorithmName(): string
    {
        return 'ES256';
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

        $derSignature = '';
        $success = openssl_sign($message, $derSignature, $privateKey, self::OPENSSL_ALGORITHM);

        if (!$success) {
            throw new InvalidArgumentException(
                'Failed to sign message: ' . openssl_error_string(),
                'JWT_ECDSA_SIGN_FAILED'
            );
        }

        // Convert DER signature to IEEE P1363 format
        $p1363Signature = $this->derToP1363($derSignature);

        return $this->base64UrlEncode($p1363Signature);
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
            $p1363Signature = $this->base64UrlDecode($signature);

            // Convert IEEE P1363 signature to DER format
            $derSignature = $this->p1363ToDer($p1363Signature);

            $result = openssl_verify($message, $derSignature, $publicKey, self::OPENSSL_ALGORITHM);

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
            throw new InvalidArgumentException('ECDSA private key cannot be empty', 'JWT_EMPTY_ECDSA_PRIVATE_KEY');
        }

        $privateKey = openssl_pkey_get_private($key);

        if ($privateKey === false) {
            throw new InvalidArgumentException(
                'Invalid ECDSA private key: ' . openssl_error_string(),
                'JWT_INVALID_ECDSA_PRIVATE_KEY'
            );
        }

        // Validate key type
        $keyDetails = openssl_pkey_get_details($privateKey);
        if ($keyDetails === false || $keyDetails['type'] !== OPENSSL_KEYTYPE_EC) {
            throw new InvalidArgumentException(
                'Key must be an ECDSA private key',
                'JWT_INVALID_ECDSA_KEY_TYPE'
            );
        }

        // Validate curve (P-256 for ES256)
        if (!isset($keyDetails['ec']['curve_name']) || $keyDetails['ec']['curve_name'] !== 'prime256v1') {
            throw new InvalidArgumentException(
                'ECDSA key must use P-256 (prime256v1) curve for ES256',
                'JWT_INVALID_ECDSA_CURVE'
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
            throw new InvalidArgumentException('ECDSA public key cannot be empty', 'JWT_EMPTY_ECDSA_PUBLIC_KEY');
        }

        $publicKey = openssl_pkey_get_public($key);

        if ($publicKey === false) {
            throw new InvalidArgumentException(
                'Invalid ECDSA public key: ' . openssl_error_string(),
                'JWT_INVALID_ECDSA_PUBLIC_KEY'
            );
        }

        // Validate key type
        $keyDetails = openssl_pkey_get_details($publicKey);
        if ($keyDetails === false || $keyDetails['type'] !== OPENSSL_KEYTYPE_EC) {
            throw new InvalidArgumentException(
                'Key must be an ECDSA public key',
                'JWT_INVALID_ECDSA_KEY_TYPE'
            );
        }

        return $publicKey;
    }

    /**
     * Convert DER encoded signature to IEEE P1363 format
     *
     * @param string $derSignature DER encoded signature
     * @return string IEEE P1363 signature
     * @throws InvalidArgumentException If conversion fails
     */
    private function derToP1363(string $derSignature): string
    {
        $offset = 0;
        $length = strlen($derSignature);

        // Parse DER SEQUENCE
        if ($offset >= $length || ord($derSignature[$offset++]) !== 0x30) {
            throw new InvalidArgumentException('Invalid DER signature format', 'JWT_INVALID_DER_SIGNATURE');
        }

        // Parse sequence length
        $seqLength = $this->parseDerLength($derSignature, $offset);

        // Parse first INTEGER (r)
        if ($offset >= $length || ord($derSignature[$offset++]) !== 0x02) {
            throw new InvalidArgumentException('Invalid DER signature format: missing r integer', 'JWT_INVALID_DER_SIGNATURE');
        }

        $rLength = $this->parseDerLength($derSignature, $offset);
        $r = substr($derSignature, $offset, $rLength);
        $offset += $rLength;

        // Parse second INTEGER (s)
        if ($offset >= $length || ord($derSignature[$offset++]) !== 0x02) {
            throw new InvalidArgumentException('Invalid DER signature format: missing s integer', 'JWT_INVALID_DER_SIGNATURE');
        }

        $sLength = $this->parseDerLength($derSignature, $offset);
        $s = substr($derSignature, $offset, $sLength);

        // Convert to fixed-length format (32 bytes each for P-256)
        $r = $this->normalizeInteger($r, 32);
        $s = $this->normalizeInteger($s, 32);

        return $r . $s;
    }

    /**
     * Convert IEEE P1363 signature to DER format
     *
     * @param string $p1363Signature IEEE P1363 signature
     * @return string DER encoded signature
     * @throws InvalidArgumentException If conversion fails
     */
    private function p1363ToDer(string $p1363Signature): string
    {
        if (strlen($p1363Signature) !== self::SIGNATURE_LENGTH) {
            throw new InvalidArgumentException(
                'Invalid P1363 signature length: expected ' . self::SIGNATURE_LENGTH . ' bytes',
                'JWT_INVALID_P1363_SIGNATURE'
            );
        }

        $r = substr($p1363Signature, 0, 32);
        $s = substr($p1363Signature, 32, 32);

        // Remove leading zeros but keep at least one byte
        $r = ltrim($r, "\x00") ?: "\x00";
        $s = ltrim($s, "\x00") ?: "\x00";

        // Add leading zero if first bit is set (to keep positive)
        if (ord($r[0]) & 0x80) {
            $r = "\x00" . $r;
        }
        if (ord($s[0]) & 0x80) {
            $s = "\x00" . $s;
        }

        // Build DER structure
        $rDer = "\x02" . chr(strlen($r)) . $r;
        $sDer = "\x02" . chr(strlen($s)) . $s;
        $sequence = $rDer . $sDer;

        return "\x30" . chr(strlen($sequence)) . $sequence;
    }

    /**
     * Parse DER length field
     *
     * @param string $data DER data
     * @param int &$offset Current offset (will be updated)
     * @return int Length value
     */
    private function parseDerLength(string $data, int &$offset): int
    {
        $length = ord($data[$offset++]);

        if ($length & 0x80) {
            $lengthBytes = $length & 0x7f;
            $length = 0;
            for ($i = 0; $i < $lengthBytes; $i++) {
                $length = ($length << 8) | ord($data[$offset++]);
            }
        }

        return $length;
    }

    /**
     * Normalize integer to fixed length with proper padding
     *
     * @param string $integer Integer bytes
     * @param int $length Target length
     * @return string Normalized integer
     */
    private function normalizeInteger(string $integer, int $length): string
    {
        // Remove leading zero bytes (except when needed for sign)
        while (strlen($integer) > 1 && ord($integer[0]) === 0 && !(ord($integer[1]) & 0x80)) {
            $integer = substr($integer, 1);
        }

        // Remove leading zero added for positive sign
        if (strlen($integer) > $length && ord($integer[0]) === 0) {
            $integer = substr($integer, 1);
        }

        // Pad with leading zeros to reach target length
        if (strlen($integer) < $length) {
            $integer = str_repeat("\x00", $length - strlen($integer)) . $integer;
        }

        return $integer;
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