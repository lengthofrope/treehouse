<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT Token Introspector
 *
 * Provides enhanced utilities for inspecting JWT tokens without validation.
 * Useful for debugging, monitoring, and analyzing token structure and content.
 *
 * Features:
 * - Safe token decoding without validation
 * - Comprehensive token analysis
 * - Security assessment and warnings
 * - Human-readable token information
 * - Token structure validation
 * - Claim extraction and formatting
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class TokenIntrospector
{
    /**
     * JWT configuration
     */
    private JwtConfig $config;

    /**
     * JWT decoder for token inspection
     */
    private JwtDecoder $decoder;

    /**
     * Create a new token introspector instance
     *
     * @param JwtConfig $config JWT configuration
     */
    public function __construct(JwtConfig $config)
    {
        $this->config = $config;
        $this->decoder = new JwtDecoder($config);
    }

    /**
     * Perform comprehensive token introspection
     *
     * @param string $token JWT token to inspect
     * @return array Comprehensive token analysis
     */
    public function introspect(string $token): array
    {
        try {
            // Basic token structure analysis
            $structure = $this->analyzeStructure($token);
            if (!$structure['valid']) {
                return [
                    'valid' => false,
                    'error' => 'Invalid token structure',
                    'structure' => $structure,
                ];
            }

            // Decode token without verification
            $decoded = $this->decoder->decodeWithoutVerification($token);
            
            // Extract and analyze components
            $header = $decoded['header'];
            $payload = $decoded['payload'];
            
            return [
                'valid' => true,
                'structure' => $structure,
                'header' => $this->analyzeHeader($header),
                'payload' => $this->analyzePayload($payload),
                'claims' => $this->categorizePayloadClaims($payload),
                'security' => $this->assessSecurity($header, $payload),
                'timing' => $this->analyzeTimings($payload),
                'metadata' => $this->extractMetadata($header, $payload),
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Failed to introspect token',
                'details' => $e->getMessage(),
                'structure' => $this->analyzeStructure($token),
            ];
        }
    }

    /**
     * Extract token claims in a structured format
     *
     * @param string $token JWT token
     * @return array Extracted claims with metadata
     */
    public function extractClaims(string $token): array
    {
        try {
            $decoded = $this->decoder->decodeWithoutVerification($token);
            return $this->categorizePayloadClaims($decoded['payload']);
            
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to extract claims',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get human-readable token information
     *
     * @param string $token JWT token
     * @return array Human-readable token summary
     */
    public function getTokenInfo(string $token): array
    {
        $introspection = $this->introspect($token);
        
        if (!$introspection['valid']) {
            return [
                'summary' => 'Invalid token',
                'error' => $introspection['error'] ?? 'Unknown error',
            ];
        }

        $payload = $introspection['payload'];
        $claims = $introspection['claims'];
        $timing = $introspection['timing'];

        return [
            'summary' => 'Valid JWT token',
            'user_id' => $claims['standard']['sub'] ?? 'Unknown',
            'token_type' => $claims['custom']['type'] ?? 'Unknown',
            'algorithm' => $introspection['header']['algorithm'] ?? 'Unknown',
            'issued' => $timing['issued_at']['formatted'] ?? 'Unknown',
            'expires' => $timing['expires_at']['formatted'] ?? 'Unknown',
            'status' => $timing['status'],
            'issuer' => $claims['standard']['iss'] ?? 'Unknown',
            'audience' => $claims['standard']['aud'] ?? 'Unknown',
        ];
    }

    /**
     * Validate token structure without decoding
     *
     * @param string $token JWT token
     * @return array Structure validation results
     */
    public function validateStructure(string $token): array
    {
        return $this->analyzeStructure($token);
    }

    /**
     * Extract token timing information
     *
     * @param string $token JWT token
     * @return array Token timing analysis
     */
    public function getTimingInfo(string $token): array
    {
        try {
            $decoded = $this->decoder->decodeWithoutVerification($token);
            return $this->analyzeTimings($decoded['payload']);
            
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to extract timing information',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Assess token security characteristics
     *
     * @param string $token JWT token
     * @return array Security assessment
     */
    public function assessTokenSecurity(string $token): array
    {
        try {
            $decoded = $this->decoder->decodeWithoutVerification($token);
            return $this->assessSecurity($decoded['header'], $decoded['payload']);
            
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to assess token security',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Compare two tokens for similarity
     *
     * @param string $token1 First JWT token
     * @param string $token2 Second JWT token
     * @return array Token comparison results
     */
    public function compareTokens(string $token1, string $token2): array
    {
        $info1 = $this->introspect($token1);
        $info2 = $this->introspect($token2);

        if (!$info1['valid'] || !$info2['valid']) {
            return [
                'comparable' => false,
                'token1_valid' => $info1['valid'],
                'token2_valid' => $info2['valid'],
            ];
        }

        $claims1 = $info1['claims'];
        $claims2 = $info2['claims'];

        return [
            'comparable' => true,
            'same_user' => ($claims1['standard']['sub'] ?? null) === ($claims2['standard']['sub'] ?? null),
            'same_issuer' => ($claims1['standard']['iss'] ?? null) === ($claims2['standard']['iss'] ?? null),
            'same_audience' => ($claims1['standard']['aud'] ?? null) === ($claims2['standard']['aud'] ?? null),
            'same_algorithm' => $info1['header']['algorithm'] === $info2['header']['algorithm'],
            'timing_overlap' => $this->analyzeTimingOverlap($info1['timing'], $info2['timing']),
            'differences' => $this->findClaimDifferences($claims1, $claims2),
        ];
    }

    /**
     * Analyze token structure
     *
     * @param string $token JWT token
     * @return array Structure analysis
     */
    private function analyzeStructure(string $token): array
    {
        if (empty($token)) {
            return [
                'valid' => false,
                'error' => 'Empty token',
            ];
        }

        $parts = explode('.', $token);
        $partCount = count($parts);

        if ($partCount !== 3) {
            return [
                'valid' => false,
                'error' => "Invalid part count: expected 3, got {$partCount}",
                'parts' => $partCount,
            ];
        }

        [$header, $payload, $signature] = $parts;

        return [
            'valid' => true,
            'parts' => 3,
            'header_length' => strlen($header),
            'payload_length' => strlen($payload),
            'signature_length' => strlen($signature),
            'total_length' => strlen($token),
            'header_empty' => empty($header),
            'payload_empty' => empty($payload),
            'signature_empty' => empty($signature),
        ];
    }

    /**
     * Analyze token header
     *
     * @param array $header Token header
     * @return array Header analysis
     */
    private function analyzeHeader(array $header): array
    {
        return [
            'algorithm' => $header['alg'] ?? 'Unknown',
            'type' => $header['typ'] ?? 'Unknown',
            'key_id' => $header['kid'] ?? null,
            'critical' => $header['crit'] ?? null,
            'content_type' => $header['cty'] ?? null,
            'custom_headers' => array_diff_key($header, array_flip(['alg', 'typ', 'kid', 'crit', 'cty'])),
            'header_count' => count($header),
        ];
    }

    /**
     * Analyze token payload
     *
     * @param array $payload Token payload
     * @return array Payload analysis
     */
    private function analyzePayload(array $payload): array
    {
        $standardClaims = ['iss', 'sub', 'aud', 'exp', 'nbf', 'iat', 'jti'];
        $customClaims = array_diff_key($payload, array_flip($standardClaims));

        return [
            'claim_count' => count($payload),
            'standard_claims' => array_intersect_key($payload, array_flip($standardClaims)),
            'custom_claims' => $customClaims,
            'has_expiry' => isset($payload['exp']),
            'has_not_before' => isset($payload['nbf']),
            'has_issued_at' => isset($payload['iat']),
            'has_jwt_id' => isset($payload['jti']),
        ];
    }

    /**
     * Extract and categorize claims
     *
     * @param array $payload Token payload
     * @return array Categorized claims
     */
    private function categorizePayloadClaims(array $payload): array
    {
        $standardClaims = [
            'iss' => $payload['iss'] ?? null,
            'sub' => $payload['sub'] ?? null,
            'aud' => $payload['aud'] ?? null,
            'exp' => $payload['exp'] ?? null,
            'nbf' => $payload['nbf'] ?? null,
            'iat' => $payload['iat'] ?? null,
            'jti' => $payload['jti'] ?? null,
        ];

        $customClaims = array_diff_key($payload, $standardClaims);

        return [
            'standard' => array_filter($standardClaims, fn($value) => $value !== null),
            'custom' => $customClaims,
            'all' => $payload,
        ];
    }

    /**
     * Assess token security
     *
     * @param array $header Token header
     * @param array $payload Token payload
     * @return array Security assessment
     */
    private function assessSecurity(array $header, array $payload): array
    {
        $warnings = [];
        $score = 100;

        // Algorithm strength
        $algorithm = $header['alg'] ?? 'none';
        if ($algorithm === 'none') {
            $warnings[] = 'Token is not signed (algorithm: none)';
            $score -= 50;
        } elseif (str_starts_with($algorithm, 'HS')) {
            $warnings[] = 'Using symmetric key algorithm (consider asymmetric)';
            $score -= 10;
        }

        // Expiration
        if (!isset($payload['exp'])) {
            $warnings[] = 'Token has no expiration time';
            $score -= 30;
        } elseif ($payload['exp'] > time() + 86400) {
            $warnings[] = 'Token expires more than 24 hours from now';
            $score -= 10;
        }

        // Required claims
        $requiredClaims = ['iss', 'aud', 'sub'];
        foreach ($requiredClaims as $claim) {
            if (!isset($payload[$claim])) {
                $warnings[] = "Missing recommended claim: {$claim}";
                $score -= 5;
            }
        }

        // Sensitive data check
        $sensitiveKeys = ['password', 'secret', 'key', 'token'];
        foreach ($payload as $key => $value) {
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains(strtolower($key), $sensitive)) {
                    $warnings[] = "Potentially sensitive data in claim: {$key}";
                    $score -= 15;
                }
            }
        }

        return [
            'score' => max(0, $score),
            'level' => $this->getSecurityLevel($score),
            'warnings' => $warnings,
            'recommendations' => $this->getSecurityRecommendations($warnings),
        ];
    }

    /**
     * Analyze token timings
     *
     * @param array $payload Token payload
     * @return array Timing analysis
     */
    private function analyzeTimings(array $payload): array
    {
        $now = time();
        $iat = $payload['iat'] ?? null;
        $exp = $payload['exp'] ?? null;
        $nbf = $payload['nbf'] ?? null;

        $result = [
            'current_time' => $now,
            'issued_at' => $this->formatTimestamp($iat),
            'expires_at' => $this->formatTimestamp($exp),
            'not_before' => $this->formatTimestamp($nbf),
        ];

        // Determine token status
        if ($exp && $now > $exp) {
            $result['status'] = 'expired';
            $result['expired_seconds_ago'] = $now - $exp;
        } elseif ($nbf && $now < $nbf) {
            $result['status'] = 'not_yet_valid';
            $result['valid_in_seconds'] = $nbf - $now;
        } else {
            $result['status'] = 'active';
            if ($exp) {
                $result['expires_in_seconds'] = $exp - $now;
            }
        }

        // Calculate token lifetime
        if ($iat && $exp) {
            $result['lifetime_seconds'] = $exp - $iat;
            $result['lifetime_formatted'] = $this->formatDuration($exp - $iat);
        }

        return $result;
    }

    /**
     * Format timestamp for display
     *
     * @param int|null $timestamp Unix timestamp
     * @return array Formatted timestamp information
     */
    private function formatTimestamp(?int $timestamp): array
    {
        if ($timestamp === null) {
            return ['timestamp' => null, 'formatted' => null];
        }

        return [
            'timestamp' => $timestamp,
            'formatted' => date('Y-m-d H:i:s T', $timestamp),
            'iso' => date('c', $timestamp),
            'relative' => $this->getRelativeTime($timestamp),
        ];
    }

    /**
     * Extract token metadata
     *
     * @param array $header Token header
     * @param array $payload Token payload
     * @return array Token metadata
     */
    private function extractMetadata(array $header, array $payload): array
    {
        return [
            'token_type' => $payload['type'] ?? 'unknown',
            'user_id' => $payload['sub'] ?? $payload['user_id'] ?? null,
            'family_id' => $payload['family_id'] ?? null,
            'parent_token_id' => $payload['parent_token_id'] ?? null,
            'refresh_count' => $payload['refresh_count'] ?? null,
            'scopes' => $payload['scopes'] ?? $payload['scope'] ?? null,
            'roles' => $payload['roles'] ?? $payload['role'] ?? null,
            'permissions' => $payload['permissions'] ?? null,
        ];
    }

    /**
     * Get security level based on score
     *
     * @param int $score Security score
     * @return string Security level
     */
    private function getSecurityLevel(int $score): string
    {
        if ($score >= 90) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'fair';
        if ($score >= 30) return 'poor';
        return 'critical';
    }

    /**
     * Get security recommendations
     *
     * @param array $warnings Security warnings
     * @return array Recommendations
     */
    private function getSecurityRecommendations(array $warnings): array
    {
        $recommendations = [];

        foreach ($warnings as $warning) {
            if (str_contains($warning, 'not signed')) {
                $recommendations[] = 'Use a signing algorithm (HS256, RS256, or ES256)';
            } elseif (str_contains($warning, 'no expiration')) {
                $recommendations[] = 'Set an appropriate expiration time (exp claim)';
            } elseif (str_contains($warning, 'symmetric key')) {
                $recommendations[] = 'Consider using asymmetric signing (RS256 or ES256)';
            } elseif (str_contains($warning, 'sensitive data')) {
                $recommendations[] = 'Avoid including sensitive data in JWT claims';
            }
        }

        return array_unique($recommendations);
    }

    /**
     * Analyze timing overlap between tokens
     *
     * @param array $timing1 First token timing
     * @param array $timing2 Second token timing
     * @return array Timing overlap analysis
     */
    private function analyzeTimingOverlap(array $timing1, array $timing2): array
    {
        $exp1 = $timing1['expires_at']['timestamp'] ?? null;
        $exp2 = $timing2['expires_at']['timestamp'] ?? null;
        $iat1 = $timing1['issued_at']['timestamp'] ?? null;
        $iat2 = $timing2['issued_at']['timestamp'] ?? null;

        return [
            'both_have_expiry' => $exp1 !== null && $exp2 !== null,
            'overlapping_validity' => $exp1 && $exp2 && $iat1 && $iat2 
                ? !($exp1 < $iat2 || $exp2 < $iat1) 
                : false,
            'same_lifetime' => $exp1 && $exp2 && $iat1 && $iat2 
                ? ($exp1 - $iat1) === ($exp2 - $iat2) 
                : false,
        ];
    }

    /**
     * Find differences between claim sets
     *
     * @param array $claims1 First token claims
     * @param array $claims2 Second token claims
     * @return array Claim differences
     */
    private function findClaimDifferences(array $claims1, array $claims2): array
    {
        $allKeys = array_unique(array_merge(
            array_keys($claims1['all']),
            array_keys($claims2['all'])
        ));

        $differences = [];
        foreach ($allKeys as $key) {
            $value1 = $claims1['all'][$key] ?? null;
            $value2 = $claims2['all'][$key] ?? null;

            if ($value1 !== $value2) {
                $differences[$key] = [
                    'token1' => $value1,
                    'token2' => $value2,
                ];
            }
        }

        return $differences;
    }

    /**
     * Get relative time description
     *
     * @param int $timestamp Unix timestamp
     * @return string Relative time description
     */
    private function getRelativeTime(int $timestamp): string
    {
        $diff = time() - $timestamp;
        $absDiff = abs($diff);

        if ($absDiff < 60) {
            return $diff >= 0 ? 'just now' : 'in a few seconds';
        } elseif ($absDiff < 3600) {
            $minutes = round($absDiff / 60);
            return $diff >= 0 ? "{$minutes} minutes ago" : "in {$minutes} minutes";
        } elseif ($absDiff < 86400) {
            $hours = round($absDiff / 3600);
            return $diff >= 0 ? "{$hours} hours ago" : "in {$hours} hours";
        } else {
            $days = round($absDiff / 86400);
            return $diff >= 0 ? "{$days} days ago" : "in {$days} days";
        }
    }

    /**
     * Format duration in human-readable format
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        } elseif ($seconds < 3600) {
            $minutes = round($seconds / 60);
            return "{$minutes} minutes";
        } elseif ($seconds < 86400) {
            $hours = round($seconds / 3600);
            return "{$hours} hours";
        } else {
            $days = round($seconds / 86400);
            return "{$days} days";
        }
    }

    /**
     * Create a token introspector with custom configuration
     *
     * @param array $config JWT configuration
     * @return self New token introspector instance
     */
    public static function create(array $config): self
    {
        $jwtConfig = new JwtConfig($config);
        return new self($jwtConfig);
    }
}