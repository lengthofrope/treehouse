<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Auth\Jwt\Algorithms\AlgorithmInterface;
use LengthOfRope\TreeHouse\Auth\Jwt\Algorithms\HmacSha256;
use LengthOfRope\TreeHouse\Auth\Jwt\Algorithms\RsaSha256;
use LengthOfRope\TreeHouse\Auth\Jwt\Algorithms\EcdsaSha256;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * JWT Token Encoder
 *
 * Encodes JWT tokens according to RFC 7519 specification. Handles header creation,
 * payload encoding, signature generation, and token assembly.
 *
 * Features:
 * - RFC 7519 compliant JWT encoding
 * - Multiple algorithm support (HS256, RS256, ES256)
 * - Header and payload validation
 * - Base64URL encoding/decoding
 * - Secure token generation
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtEncoder
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
     * Create a new JWT encoder instance
     *
     * @param JwtConfig $config JWT configuration
     */
    public function __construct(JwtConfig $config)
    {
        $this->config = $config;
        $this->initializeAlgorithms();
    }

    /**
     * Encode a JWT token
     *
     * @param ClaimsManager $claims Claims to encode
     * @param array $header Additional header claims
     * @return string Encoded JWT token
     * @throws InvalidArgumentException If encoding fails
     */
    public function encode(ClaimsManager $claims, array $header = []): string
    {
        // Build header
        $header = $this->buildHeader($header);

        // Get algorithm
        $algorithm = $this->getAlgorithm($header['alg']);

        // Encode header and payload
        $encodedHeader = $this->base64UrlEncode($this->jsonEncode($header));
        $encodedPayload = $this->base64UrlEncode($claims->toJson());

        // Create message to sign
        $message = $encodedHeader . '.' . $encodedPayload;

        // Get signing key
        $signingKey = $this->getSigningKey($header['alg']);

        // Generate signature
        $signature = $algorithm->sign($message, $signingKey);

        // Assemble token
        return $message . '.' . $signature;
    }

    /**
     * Encode claims with automatic timestamps
     *
     * @param array $customClaims Custom claims to include
     * @param array $header Additional header claims
     * @return string Encoded JWT token
     */
    public function encodeWithDefaults(array $customClaims = [], array $header = []): string
    {
        $now = Carbon::now()->getTimestamp();
        
        $claims = new ClaimsManager();

        // Set standard claims
        $claims->setIssuedAt($now);
        $claims->setExpiration($now + $this->config->getTtl());
        $claims->setNotBefore($now);

        // Set configured claims
        if ($issuer = $this->config->getIssuer()) {
            $claims->setIssuer($issuer);
        }

        if ($audience = $this->config->getAudience()) {
            $claims->setAudience($audience);
        }

        if ($subject = $this->config->getSubject()) {
            $claims->setSubject($subject);
        }

        // Add custom claims
        foreach ($customClaims as $name => $value) {
            $claims->setClaim($name, $value);
        }

        return $this->encode($claims, $header);
    }

    /**
     * Build JWT header
     *
     * @param array $additionalHeader Additional header claims
     * @return array Complete header
     */
    private function buildHeader(array $additionalHeader = []): array
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->config->getAlgorithm(),
        ];

        // Merge additional header claims
        $header = array_merge($header, $additionalHeader);

        // Validate header
        $this->validateHeader($header);

        return $header;
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
            throw new InvalidArgumentException('Header must contain typ: JWT', 'JWT_INVALID_HEADER_TYPE');
        }

        if (!isset($header['alg']) || empty($header['alg'])) {
            throw new InvalidArgumentException('Header must contain algorithm', 'JWT_MISSING_ALGORITHM');
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
     * Get signing key for algorithm
     *
     * @param string $algorithmName Algorithm name
     * @return string Signing key
     * @throws InvalidArgumentException If key is not available
     */
    private function getSigningKey(string $algorithmName): string
    {
        switch ($algorithmName) {
            case 'HS256':
                return $this->config->getSecret();

            case 'RS256':
            case 'ES256':
                $privateKey = $this->config->getPrivateKey();
                if (empty($privateKey)) {
                    throw new InvalidArgumentException(
                        'Private key required for ' . $algorithmName . ' algorithm',
                        'JWT_MISSING_PRIVATE_KEY'
                    );
                }
                return $privateKey;

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
     * JSON encode data with error handling
     *
     * @param mixed $data Data to encode
     * @return string JSON string
     * @throws InvalidArgumentException If encoding fails
     */
    private function jsonEncode(mixed $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new InvalidArgumentException(
                'JSON encoding failed: ' . json_last_error_msg(),
                'JWT_JSON_ENCODE_FAILED'
            );
        }

        return $json;
    }
}