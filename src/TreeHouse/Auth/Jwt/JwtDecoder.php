<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Auth\Jwt\Algorithms\AlgorithmInterface;
use LengthOfRope\TreeHouse\Auth\Jwt\Algorithms\HmacSha256;
use LengthOfRope\TreeHouse\Auth\Jwt\Algorithms\RsaSha256;
use LengthOfRope\TreeHouse\Auth\Jwt\Algorithms\EcdsaSha256;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT Token Decoder
 *
 * Decodes and validates JWT tokens according to RFC 7519 specification.
 * Handles token parsing, signature verification, and claims extraction.
 *
 * Features:
 * - RFC 7519 compliant JWT decoding
 * - Multiple algorithm support (HS256, RS256, ES256)
 * - Signature verification
 * - Claims validation and extraction
 * - Base64URL decoding
 * - Timing attack protection
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtDecoder
{
    /**
     * JWT configuration
     */
    private JwtConfig $config;

    /**
     * Available algorithm instances
     */
    private array $algorithms = [];

    /**
     * Create a new JWT decoder instance
     *
     * @param JwtConfig $config JWT configuration
     */
    public function __construct(JwtConfig $config)
    {
        $this->config = $config;
        $this->initializeAlgorithms();
    }

    /**
     * Decode and validate a JWT token
     *
     * @param string $token JWT token to decode
     * @param bool $verify Whether to verify signature (default: true)
     * @return ClaimsManager Decoded claims
     * @throws InvalidArgumentException If token is invalid
     */
    public function decode(string $token, bool $verify = true): ClaimsManager
    {
        // Parse token parts
        $parts = $this->parseToken($token);
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        // Decode header
        $header = $this->decodeHeader($encodedHeader);

        // Decode payload
        $payload = $this->decodePayload($encodedPayload);

        // Create claims manager
        $claims = ClaimsManager::fromArray($payload);

        // Verify signature if requested
        if ($verify) {
            $this->verifySignature($encodedHeader, $encodedPayload, $encodedSignature, $header);
        }

        // Validate claims
        $this->validateClaims($claims);

        return $claims;
    }

    /**
     * Decode token without verification (for debugging/inspection)
     *
     * @param string $token JWT token to decode
     * @return array Array with 'header' and 'payload' keys
     * @throws InvalidArgumentException If token format is invalid
     */
    public function decodeWithoutVerification(string $token): array
    {
        // Parse token parts
        $parts = $this->parseToken($token);
        [$encodedHeader, $encodedPayload] = $parts;

        // Decode header and payload
        $header = $this->decodeHeader($encodedHeader);
        $payload = $this->decodePayload($encodedPayload);

        return [
            'header' => $header,
            'payload' => $payload,
        ];
    }

    /**
     * Parse JWT token into its components
     *
     * @param string $token JWT token
     * @return array Array of [header, payload, signature]
     * @throws InvalidArgumentException If token format is invalid
     */
    private function parseToken(string $token): array
    {
        if (empty($token)) {
            throw new InvalidArgumentException('JWT token cannot be empty', 'JWT_EMPTY_TOKEN');
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException(
                'JWT token must have exactly 3 parts separated by dots',
                'JWT_INVALID_FORMAT'
            );
        }

        [$header, $payload, $signature] = $parts;

        if (empty($header) || empty($payload) || empty($signature)) {
            throw new InvalidArgumentException('JWT token parts cannot be empty', 'JWT_EMPTY_PARTS');
        }

        return [$header, $payload, $signature];
    }

    /**
     * Decode JWT header
     *
     * @param string $encodedHeader Base64URL encoded header
     * @return array Decoded header
     * @throws InvalidArgumentException If header is invalid
     */
    private function decodeHeader(string $encodedHeader): array
    {
        $headerJson = $this->base64UrlDecode($encodedHeader);
        $header = json_decode($headerJson, true);

        if ($header === null) {
            throw new InvalidArgumentException(
                'Invalid JWT header JSON: ' . json_last_error_msg(),
                'JWT_INVALID_HEADER_JSON'
            );
        }

        $this->validateHeader($header);

        return $header;
    }

    /**
     * Decode JWT payload
     *
     * @param string $encodedPayload Base64URL encoded payload
     * @return array Decoded payload
     * @throws InvalidArgumentException If payload is invalid
     */
    private function decodePayload(string $encodedPayload): array
    {
        $payloadJson = $this->base64UrlDecode($encodedPayload);
        $payload = json_decode($payloadJson, true);

        if ($payload === null) {
            throw new InvalidArgumentException(
                'Invalid JWT payload JSON: ' . json_last_error_msg(),
                'JWT_INVALID_PAYLOAD_JSON'
            );
        }

        return $payload;
    }

    /**
     * Verify JWT signature
     *
     * @param string $encodedHeader Base64URL encoded header
     * @param string $encodedPayload Base64URL encoded payload
     * @param string $encodedSignature Base64URL encoded signature
     * @param array $header Decoded header
     * @return void
     * @throws InvalidArgumentException If signature is invalid
     */
    private function verifySignature(string $encodedHeader, string $encodedPayload, string $encodedSignature, array $header): void
    {
        $algorithm = $this->getAlgorithm($header['alg']);
        $verificationKey = $this->getVerificationKey($header['alg']);
        $message = $encodedHeader . '.' . $encodedPayload;

        $isValid = $algorithm->verify($message, $encodedSignature, $verificationKey);

        if (!$isValid) {
            throw new InvalidArgumentException('JWT signature verification failed', 'JWT_SIGNATURE_INVALID');
        }
    }

    /**
     * Validate JWT header
     *
     * @param array $header Header to validate
     * @return void
     * @throws InvalidArgumentException If header is invalid
     */
    private function validateHeader(array $header): void
    {
        // Validate required fields
        if (!isset($header['typ']) || $header['typ'] !== 'JWT') {
            throw new InvalidArgumentException('Invalid JWT header: typ must be JWT', 'JWT_INVALID_HEADER_TYPE');
        }

        if (!isset($header['alg']) || empty($header['alg'])) {
            throw new InvalidArgumentException('Invalid JWT header: missing algorithm', 'JWT_MISSING_ALGORITHM');
        }

        // Validate algorithm is supported
        if (!$this->isAlgorithmSupported($header['alg'])) {
            throw new InvalidArgumentException(
                'Unsupported algorithm: ' . $header['alg'],
                'JWT_UNSUPPORTED_ALGORITHM'
            );
        }
    }

    /**
     * Validate JWT claims
     *
     * @param ClaimsManager $claims Claims to validate
     * @return void
     * @throws InvalidArgumentException If claims are invalid
     */
    private function validateClaims(ClaimsManager $claims): void
    {
        // Validate required claims
        $requiredClaims = $this->config->getRequiredClaims();
        $claims->validateRequiredClaims($requiredClaims);

        // Validate timing claims
        $leeway = $this->config->getLeeway();
        $claims->validateTiming($leeway);

        // Validate issuer if configured
        $expectedIssuer = $this->config->getIssuer();
        if ($expectedIssuer !== null) {
            $actualIssuer = $claims->getIssuer();
            if ($actualIssuer !== $expectedIssuer) {
                throw new InvalidArgumentException(
                    'Invalid issuer: expected ' . $expectedIssuer . ', got ' . ($actualIssuer ?? 'null'),
                    'JWT_INVALID_ISSUER'
                );
            }
        }

        // Validate audience if configured
        $expectedAudience = $this->config->getAudience();
        if ($expectedAudience !== null) {
            $actualAudience = $claims->getAudience();
            if (!$this->isValidAudience($actualAudience, $expectedAudience)) {
                throw new InvalidArgumentException(
                    'Invalid audience: expected ' . $expectedAudience,
                    'JWT_INVALID_AUDIENCE'
                );
            }
        }
    }

    /**
     * Check if audience is valid
     *
     * @param string|array|null $actualAudience Actual audience from token
     * @param string $expectedAudience Expected audience
     * @return bool True if valid
     */
    private function isValidAudience(string|array|null $actualAudience, string $expectedAudience): bool
    {
        if ($actualAudience === null) {
            return false;
        }

        if (is_string($actualAudience)) {
            return $actualAudience === $expectedAudience;
        }

        if (is_array($actualAudience)) {
            return in_array($expectedAudience, $actualAudience, true);
        }

        return false;
    }

    /**
     * Get algorithm instance
     *
     * @param string $algorithmName Algorithm name
     * @return AlgorithmInterface Algorithm instance
     * @throws InvalidArgumentException If algorithm is not supported
     */
    private function getAlgorithm(string $algorithmName): AlgorithmInterface
    {
        if (!isset($this->algorithms[$algorithmName])) {
            throw new InvalidArgumentException(
                'Algorithm not available: ' . $algorithmName,
                'JWT_ALGORITHM_NOT_AVAILABLE'
            );
        }

        return $this->algorithms[$algorithmName];
    }

    /**
     * Get verification key for algorithm
     *
     * @param string $algorithmName Algorithm name
     * @return string Verification key
     * @throws InvalidArgumentException If key is not available
     */
    private function getVerificationKey(string $algorithmName): string
    {
        switch ($algorithmName) {
            case 'HS256':
                return $this->config->getSecret();

            case 'RS256':
            case 'ES256':
                $publicKey = $this->config->getPublicKey();
                if (empty($publicKey)) {
                    // If no public key configured, try to use the secret (for HMAC-like usage)
                    // or the private key (for development/testing)
                    $privateKey = $this->config->getPrivateKey();
                    if (!empty($privateKey)) {
                        return $privateKey;
                    }
                    
                    throw new InvalidArgumentException(
                        'Public key required for ' . $algorithmName . ' algorithm verification',
                        'JWT_MISSING_PUBLIC_KEY'
                    );
                }
                return $publicKey;

            default:
                throw new InvalidArgumentException(
                    'Unknown algorithm: ' . $algorithmName,
                    'JWT_UNKNOWN_ALGORITHM'
                );
        }
    }

    /**
     * Check if algorithm is supported
     *
     * @param string $algorithmName Algorithm name
     * @return bool True if supported
     */
    private function isAlgorithmSupported(string $algorithmName): bool
    {
        return isset($this->algorithms[$algorithmName]);
    }

    /**
     * Initialize algorithm instances
     *
     * @return void
     */
    private function initializeAlgorithms(): void
    {
        $this->algorithms = [
            'HS256' => new HmacSha256(),
            'RS256' => new RsaSha256(),
            'ES256' => new EcdsaSha256(),
        ];
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
            throw new InvalidArgumentException('Invalid base64url encoding', 'JWT_INVALID_BASE64URL');
        }

        return $decoded;
    }
}