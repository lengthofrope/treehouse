<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT Claims Manager
 *
 * Manages JWT claims including standard claims (iss, exp, iat, aud, sub, nbf, jti)
 * and custom claims. Provides claim validation, manipulation, and serialization.
 *
 * Features:
 * - Standard JWT claims management
 * - Custom claims support
 * - Claim validation and type checking
 * - Expiration and timing validation
 * - Claim serialization and normalization
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ClaimsManager
{
    /**
     * Standard JWT claims
     */
    public const STANDARD_CLAIMS = [
        'iss', // Issuer
        'sub', // Subject
        'aud', // Audience
        'exp', // Expiration Time
        'nbf', // Not Before
        'iat', // Issued At
        'jti', // JWT ID
    ];

    /**
     * Claims storage
     */
    private array $claims = [];

    /**
     * Create a new claims manager instance
     *
     * @param array $claims Initial claims array
     */
    public function __construct(array $claims = [])
    {
        $this->setClaims($claims);
    }

    /**
     * Set multiple claims at once
     *
     * @param array $claims Claims to set
     * @return self
     */
    public function setClaims(array $claims): self
    {
        foreach ($claims as $name => $value) {
            $this->setClaim($name, $value);
        }

        return $this;
    }

    /**
     * Set a single claim
     *
     * @param string $name Claim name
     * @param mixed $value Claim value
     * @return self
     * @throws InvalidArgumentException If claim is invalid
     */
    public function setClaim(string $name, mixed $value): self
    {
        $this->validateClaim($name, $value);
        $this->claims[$name] = $this->normalizeClaim($name, $value);

        return $this;
    }

    /**
     * Get a claim value
     *
     * @param string $name Claim name
     * @param mixed $default Default value if claim not found
     * @return mixed Claim value
     */
    public function getClaim(string $name, mixed $default = null): mixed
    {
        return $this->claims[$name] ?? $default;
    }

    /**
     * Check if a claim exists
     *
     * @param string $name Claim name
     * @return bool True if claim exists
     */
    public function hasClaim(string $name): bool
    {
        return isset($this->claims[$name]);
    }

    /**
     * Remove a claim
     *
     * @param string $name Claim name
     * @return self
     */
    public function removeClaim(string $name): self
    {
        unset($this->claims[$name]);

        return $this;
    }

    /**
     * Get all claims
     *
     * @return array All claims
     */
    public function getAllClaims(): array
    {
        return $this->claims;
    }

    /**
     * Set issuer claim
     *
     * @param string $issuer Issuer
     * @return self
     */
    public function setIssuer(string $issuer): self
    {
        return $this->setClaim('iss', $issuer);
    }

    /**
     * Get issuer claim
     *
     * @return string|null Issuer
     */
    public function getIssuer(): ?string
    {
        return $this->getClaim('iss');
    }

    /**
     * Set subject claim
     *
     * @param string $subject Subject
     * @return self
     */
    public function setSubject(string $subject): self
    {
        return $this->setClaim('sub', $subject);
    }

    /**
     * Get subject claim
     *
     * @return string|null Subject
     */
    public function getSubject(): ?string
    {
        return $this->getClaim('sub');
    }

    /**
     * Set audience claim
     *
     * @param string|array $audience Audience
     * @return self
     */
    public function setAudience(string|array $audience): self
    {
        return $this->setClaim('aud', $audience);
    }

    /**
     * Get audience claim
     *
     * @return string|array|null Audience
     */
    public function getAudience(): string|array|null
    {
        return $this->getClaim('aud');
    }

    /**
     * Set expiration time claim
     *
     * @param int $expiration Expiration timestamp
     * @return self
     */
    public function setExpiration(int $expiration): self
    {
        return $this->setClaim('exp', $expiration);
    }

    /**
     * Get expiration time claim
     *
     * @return int|null Expiration timestamp
     */
    public function getExpiration(): ?int
    {
        return $this->getClaim('exp');
    }

    /**
     * Set not before claim
     *
     * @param int $notBefore Not before timestamp
     * @return self
     */
    public function setNotBefore(int $notBefore): self
    {
        return $this->setClaim('nbf', $notBefore);
    }

    /**
     * Get not before claim
     *
     * @return int|null Not before timestamp
     */
    public function getNotBefore(): ?int
    {
        return $this->getClaim('nbf');
    }

    /**
     * Set issued at claim
     *
     * @param int $issuedAt Issued at timestamp
     * @return self
     */
    public function setIssuedAt(int $issuedAt): self
    {
        return $this->setClaim('iat', $issuedAt);
    }

    /**
     * Get issued at claim
     *
     * @return int|null Issued at timestamp
     */
    public function getIssuedAt(): ?int
    {
        return $this->getClaim('iat');
    }

    /**
     * Set JWT ID claim
     *
     * @param string $jwtId JWT ID
     * @return self
     */
    public function setJwtId(string $jwtId): self
    {
        return $this->setClaim('jti', $jwtId);
    }

    /**
     * Get JWT ID claim
     *
     * @return string|null JWT ID
     */
    public function getJwtId(): ?string
    {
        return $this->getClaim('jti');
    }

    /**
     * Check if token is expired
     *
     * @param int $leeway Leeway in seconds
     * @return bool True if expired
     */
    public function isExpired(int $leeway = 0): bool
    {
        $exp = $this->getExpiration();
        
        if ($exp === null) {
            return false;
        }

        return (time() - $leeway) >= $exp;
    }

    /**
     * Check if token is not yet valid
     *
     * @param int $leeway Leeway in seconds
     * @return bool True if not yet valid
     */
    public function isNotYetValid(int $leeway = 0): bool
    {
        $nbf = $this->getNotBefore();
        
        if ($nbf === null) {
            return false;
        }

        return (time() + $leeway) < $nbf;
    }

    /**
     * Validate all required claims are present
     *
     * @param array $requiredClaims Required claim names
     * @return void
     * @throws InvalidArgumentException If required claims are missing
     */
    public function validateRequiredClaims(array $requiredClaims): void
    {
        $missing = [];

        foreach ($requiredClaims as $claim) {
            if (!$this->hasClaim($claim)) {
                $missing[] = $claim;
            }
        }

        if (!empty($missing)) {
            throw new InvalidArgumentException(
                'Missing required claims: ' . implode(', ', $missing),
                'JWT_MISSING_REQUIRED_CLAIMS'
            );
        }
    }

    /**
     * Validate timing claims
     *
     * @param int $leeway Clock skew leeway in seconds
     * @return void
     * @throws InvalidArgumentException If timing validation fails
     */
    public function validateTiming(int $leeway = 0): void
    {
        if ($this->isExpired($leeway)) {
            throw new InvalidArgumentException('Token has expired', 'JWT_TOKEN_EXPIRED');
        }

        if ($this->isNotYetValid($leeway)) {
            throw new InvalidArgumentException('Token is not yet valid', 'JWT_TOKEN_NOT_YET_VALID');
        }
    }

    /**
     * Get custom claims (non-standard claims)
     *
     * @return array Custom claims
     */
    public function getCustomClaims(): array
    {
        $custom = [];

        foreach ($this->claims as $name => $value) {
            if (!in_array($name, self::STANDARD_CLAIMS)) {
                $custom[$name] = $value;
            }
        }

        return $custom;
    }

    /**
     * Get standard claims only
     *
     * @return array Standard claims
     */
    public function getStandardClaims(): array
    {
        $standard = [];

        foreach (self::STANDARD_CLAIMS as $claim) {
            if ($this->hasClaim($claim)) {
                $standard[$claim] = $this->getClaim($claim);
            }
        }

        return $standard;
    }

    /**
     * Create claims from array
     *
     * @param array $data Claims data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Convert claims to JSON string
     *
     * @return string JSON representation
     * @throws InvalidArgumentException If JSON encoding fails
     */
    public function toJson(): string
    {
        $json = json_encode($this->claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new InvalidArgumentException('Failed to encode claims to JSON', 'JWT_JSON_ENCODE_FAILED');
        }

        return $json;
    }

    /**
     * Validate a claim value
     *
     * @param string $name Claim name
     * @param mixed $value Claim value
     * @return void
     * @throws InvalidArgumentException If claim is invalid
     */
    private function validateClaim(string $name, mixed $value): void
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Claim name cannot be empty', 'JWT_EMPTY_CLAIM_NAME');
        }

        // Validate specific claim types
        switch ($name) {
            case 'exp':
            case 'iat':
            case 'nbf':
                if (!is_int($value) || $value <= 0) {
                    throw new InvalidArgumentException(
                        "Claim '{$name}' must be a positive integer timestamp",
                        'JWT_INVALID_TIMESTAMP_CLAIM'
                    );
                }
                break;

            case 'iss':
            case 'sub':
            case 'jti':
                if (!is_string($value) || empty($value)) {
                    throw new InvalidArgumentException(
                        "Claim '{$name}' must be a non-empty string",
                        'JWT_INVALID_STRING_CLAIM'
                    );
                }
                break;

            case 'aud':
                if (!is_string($value) && !is_array($value)) {
                    throw new InvalidArgumentException(
                        "Claim 'aud' must be a string or array",
                        'JWT_INVALID_AUDIENCE_CLAIM'
                    );
                }
                if (is_array($value) && empty($value)) {
                    throw new InvalidArgumentException(
                        "Audience array cannot be empty",
                        'JWT_EMPTY_AUDIENCE_ARRAY'
                    );
                }
                break;
        }
    }

    /**
     * Normalize a claim value
     *
     * @param string $name Claim name
     * @param mixed $value Claim value
     * @return mixed Normalized value
     */
    private function normalizeClaim(string $name, mixed $value): mixed
    {
        // Normalize audience to array if it's a single string
        if ($name === 'aud' && is_string($value)) {
            return [$value];
        }

        return $value;
    }
}