<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;

/**
 * JWT Configuration Validator
 *
 * Validates JWT configuration during application startup to ensure proper
 * security settings, detect misconfigurations, and provide recommendations
 * for production deployments. Prevents common JWT security issues.
 *
 * Features:
 * - Comprehensive configuration validation
 * - Security best practices checking
 * - Environment-specific recommendations
 * - Key strength validation
 * - Performance impact assessment
 * - Configuration diagnostics
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtConfigValidator
{
    /**
     * Validation severity levels
     */
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Security recommendations
     */
    private const SECURITY_RECOMMENDATIONS = [
        'secret_min_length' => 32,
        'production_algorithms' => ['HS256', 'RS256', 'ES256'],
        'max_ttl_production' => 86400,        // 24 hours
        'recommended_ttl' => 3600,            // 1 hour
        'max_refresh_ttl' => 2592000,         // 30 days
        'min_key_rotation_interval' => 86400, // 24 hours
    ];

    private ErrorLogger $logger;
    private array $validationResults = [];
    private bool $isProduction;

    /**
     * Create new configuration validator
     *
     * @param ErrorLogger $logger Logger for validation results
     */
    public function __construct(ErrorLogger $logger)
    {
        $this->logger = $logger;
        $this->isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
    }

    /**
     * Validate JWT configuration
     *
     * @param JwtConfig $config JWT configuration to validate
     * @param bool $throwOnError Whether to throw exception on errors
     * @return array Validation results
     * @throws InvalidArgumentException If configuration has critical errors
     */
    public function validate(JwtConfig $config, bool $throwOnError = true): array
    {
        $this->validationResults = [];

        // Core configuration validation
        $this->validateCoreSettings($config);
        $this->validateSecuritySettings($config);
        $this->validateAlgorithmSettings($config);
        $this->validateTimingSettings($config);
        $this->validateKeySettings($config);
        
        // Environment-specific validation
        if ($this->isProduction) {
            $this->validateProductionSettings($config);
        } else {
            $this->validateDevelopmentSettings($config);
        }

        // Performance and recommendations
        $this->validatePerformanceSettings($config);
        $this->generateRecommendations($config);

        // Log validation results
        $this->logValidationResults();

        // Check for critical errors
        $criticalErrors = $this->getCriticalErrors();
        if (!empty($criticalErrors) && $throwOnError) {
            throw new InvalidArgumentException(
                'JWT configuration has critical errors: ' . implode(', ', $criticalErrors),
                'JWT_CONFIG_CRITICAL_ERRORS'
            );
        }

        return $this->getValidationSummary();
    }

    /**
     * Quick validation check
     *
     * @param JwtConfig $config JWT configuration
     * @return bool True if configuration is valid
     */
    public function isValid(JwtConfig $config): bool
    {
        try {
            $this->validate($config, false);
            return empty($this->getCriticalErrors());
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get validation summary
     *
     * @return array Validation summary
     */
    public function getValidationSummary(): array
    {
        $summary = [
            'valid' => empty($this->getCriticalErrors()),
            'total_issues' => count($this->validationResults),
            'by_severity' => [],
            'issues' => $this->validationResults,
            'critical_errors' => $this->getCriticalErrors(),
            'recommendations' => $this->getRecommendations(),
        ];

        // Count by severity
        foreach ($this->validationResults as $result) {
            $severity = $result['severity'];
            $summary['by_severity'][$severity] = ($summary['by_severity'][$severity] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * Validate core settings
     *
     * @param JwtConfig $config JWT configuration
     */
    private function validateCoreSettings(JwtConfig $config): void
    {
        // Check secret existence
        try {
            $secret = $config->getSecret();
            if (empty($secret)) {
                $this->addValidationResult(
                    'missing_secret',
                    'JWT secret is not configured',
                    self::SEVERITY_CRITICAL,
                    'Set JWT_SECRET environment variable or configure secret in JWT config'
                );
            }
        } catch (\Exception $e) {
            $this->addValidationResult(
                'secret_error',
                'Error accessing JWT secret: ' . $e->getMessage(),
                self::SEVERITY_CRITICAL,
                'Ensure JWT secret is properly configured and accessible'
            );
        }

        // Check algorithm
        $algorithm = $config->getAlgorithm();
        if (empty($algorithm)) {
            $this->addValidationResult(
                'missing_algorithm',
                'JWT algorithm is not specified',
                self::SEVERITY_ERROR,
                'Specify a supported JWT algorithm (HS256, RS256, ES256)'
            );
        }

        // Check required claims configuration
        $requiredClaims = $config->getRequiredClaims();
        if (empty($requiredClaims)) {
            $this->addValidationResult(
                'no_required_claims',
                'No required claims configured',
                self::SEVERITY_WARNING,
                'Consider setting required claims for enhanced security'
            );
        }
    }

    /**
     * Validate security settings
     *
     * @param JwtConfig $config JWT configuration
     */
    private function validateSecuritySettings(JwtConfig $config): void
    {
        try {
            $secret = $config->getSecret();
            
            // Check secret strength
            if (strlen($secret) < self::SECURITY_RECOMMENDATIONS['secret_min_length']) {
                $this->addValidationResult(
                    'weak_secret',
                    sprintf(
                        'JWT secret is too short (%d chars). Minimum recommended: %d chars',
                        strlen($secret),
                        self::SECURITY_RECOMMENDATIONS['secret_min_length']
                    ),
                    $this->isProduction ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING,
                    'Use a longer, more secure secret key'
                );
            }

            // Check for common weak secrets
            $weakSecrets = ['secret', 'password', '123456', 'test', 'default'];
            if (in_array(strtolower($secret), $weakSecrets)) {
                $this->addValidationResult(
                    'common_weak_secret',
                    'JWT secret uses a common weak value',
                    self::SEVERITY_CRITICAL,
                    'Use a strong, randomly generated secret'
                );
            }

            // Check secret entropy
            if ($this->calculateEntropy($secret) < 4.0) {
                $this->addValidationResult(
                    'low_entropy_secret',
                    'JWT secret has low entropy (not random enough)',
                    $this->isProduction ? self::SEVERITY_ERROR : self::SEVERITY_WARNING,
                    'Use a randomly generated secret with high entropy'
                );
            }
        } catch (\Exception $e) {
            // Secret validation already handled in core settings
        }

        // Check blacklist settings
        if (!$config->isBlacklistEnabled() && $this->isProduction) {
            $this->addValidationResult(
                'blacklist_disabled',
                'Token blacklisting is disabled in production',
                self::SEVERITY_WARNING,
                'Enable blacklisting for better security in production'
            );
        }
    }

    /**
     * Validate algorithm settings
     *
     * @param JwtConfig $config JWT configuration
     */
    private function validateAlgorithmSettings(JwtConfig $config): void
    {
        $algorithm = $config->getAlgorithm();
        
        // Check if algorithm is supported
        if (!in_array($algorithm, self::SECURITY_RECOMMENDATIONS['production_algorithms'])) {
            $this->addValidationResult(
                'unsupported_algorithm',
                "Algorithm '{$algorithm}' is not recommended for production",
                self::SEVERITY_ERROR,
                'Use HS256, RS256, or ES256 for production deployments'
            );
        }

        // Algorithm-specific validation
        switch ($algorithm) {
            case 'HS256':
            case 'HS384':
            case 'HS512':
                // HMAC algorithms - already validated secret above
                break;

            case 'RS256':
            case 'RS384':
            case 'RS512':
                $this->validateRsaKeys($config);
                break;

            case 'ES256':
            case 'ES384':
            case 'ES512':
                $this->validateEcdsaKeys($config);
                break;

            case 'none':
                $this->addValidationResult(
                    'insecure_algorithm',
                    "Algorithm 'none' is insecure and should never be used",
                    self::SEVERITY_CRITICAL,
                    'Use a secure algorithm like HS256, RS256, or ES256'
                );
                break;

            default:
                $this->addValidationResult(
                    'unknown_algorithm',
                    "Unknown algorithm '{$algorithm}'",
                    self::SEVERITY_ERROR,
                    'Use a supported JWT algorithm'
                );
        }
    }

    /**
     * Validate timing settings
     *
     * @param JwtConfig $config JWT configuration
     */
    private function validateTimingSettings(JwtConfig $config): void
    {
        $ttl = $config->getTtl();
        $refreshTtl = $config->getRefreshTtl();
        
        // Check TTL values
        if ($ttl <= 0) {
            $this->addValidationResult(
                'invalid_ttl',
                'JWT TTL must be positive',
                self::SEVERITY_CRITICAL,
                'Set a positive TTL value'
            );
        }

        if ($ttl > self::SECURITY_RECOMMENDATIONS['max_ttl_production'] && $this->isProduction) {
            $this->addValidationResult(
                'excessive_ttl',
                sprintf(
                    'JWT TTL (%d seconds) is too long for production. Maximum recommended: %d seconds',
                    $ttl,
                    self::SECURITY_RECOMMENDATIONS['max_ttl_production']
                ),
                self::SEVERITY_WARNING,
                'Reduce TTL for better security'
            );
        }

        if ($refreshTtl <= $ttl) {
            $this->addValidationResult(
                'invalid_refresh_ttl',
                'Refresh token TTL must be greater than access token TTL',
                self::SEVERITY_ERROR,
                'Set refresh TTL to be longer than access TTL'
            );
        }

        if ($refreshTtl > self::SECURITY_RECOMMENDATIONS['max_refresh_ttl']) {
            $this->addValidationResult(
                'excessive_refresh_ttl',
                sprintf(
                    'Refresh TTL (%d seconds) is excessive. Maximum recommended: %d seconds',
                    $refreshTtl,
                    self::SECURITY_RECOMMENDATIONS['max_refresh_ttl']
                ),
                self::SEVERITY_WARNING,
                'Consider shorter refresh token lifetime'
            );
        }

        // Check leeway
        $leeway = $config->getLeeway();
        if ($leeway > 300) { // 5 minutes
            $this->addValidationResult(
                'excessive_leeway',
                sprintf('Clock leeway (%d seconds) is excessive', $leeway),
                self::SEVERITY_WARNING,
                'Reduce leeway to improve security'
            );
        }
    }

    /**
     * Validate key settings
     *
     * @param JwtConfig $config JWT configuration
     */
    private function validateKeySettings(JwtConfig $config): void
    {
        $algorithm = $config->getAlgorithm();
        
        // Check for key configuration based on algorithm
        if (in_array($algorithm, ['RS256', 'RS384', 'RS512', 'ES256', 'ES384', 'ES512'])) {
            $privateKey = $config->getPrivateKey();
            $publicKey = $config->getPublicKey();
            
            if (empty($privateKey) && empty($publicKey)) {
                $this->addValidationResult(
                    'missing_keys',
                    "Algorithm '{$algorithm}' requires private/public keys",
                    self::SEVERITY_CRITICAL,
                    'Configure private and public keys for asymmetric algorithms'
                );
            }
        }
    }

    /**
     * Validate RSA keys
     *
     * @param JwtConfig $config JWT configuration
     */
    private function validateRsaKeys(JwtConfig $config): void
    {
        $privateKey = $config->getPrivateKey();
        $publicKey = $config->getPublicKey();
        
        if ($privateKey) {
            $keyResource = openssl_pkey_get_private($privateKey);
            if (!$keyResource) {
                $this->addValidationResult(
                    'invalid_rsa_private_key',
                    'RSA private key is invalid',
                    self::SEVERITY_CRITICAL,
                    'Provide a valid RSA private key'
                );
            } else {
                $keyDetails = openssl_pkey_get_details($keyResource);
                $keySize = $keyDetails['bits'] ?? 0;
                
                if ($keySize < 2048) {
                    $this->addValidationResult(
                        'weak_rsa_key',
                        sprintf('RSA key size (%d bits) is too small. Minimum: 2048 bits', $keySize),
                        self::SEVERITY_ERROR,
                        'Use RSA keys with at least 2048 bits'
                    );
                }
            }
        }
        
        if ($publicKey) {
            $keyResource = openssl_pkey_get_public($publicKey);
            if (!$keyResource) {
                $this->addValidationResult(
                    'invalid_rsa_public_key',
                    'RSA public key is invalid',
                    self::SEVERITY_ERROR,
                    'Provide a valid RSA public key'
                );
            }
        }
    }

    /**
     * Validate ECDSA keys
     *
     * @param JwtConfig $config JWT configuration
     */
    private function validateEcdsaKeys(JwtConfig $config): void
    {
        $privateKey = $config->getPrivateKey();
        $publicKey = $config->getPublicKey();
        
        if ($privateKey) {
            $keyResource = openssl_pkey_get_private($privateKey);
            if (!$keyResource) {
                $this->addValidationResult(
                    'invalid_ecdsa_private_key',
                    'ECDSA private key is invalid',
                    self::SEVERITY_CRITICAL,
                    'Provide a valid ECDSA private key'
                );
            }
        }
        
        if ($publicKey) {
            $keyResource = openssl_pkey_get_public($publicKey);
            if (!$keyResource) {
                $this->addValidationResult(
                    'invalid_ecdsa_public_key',
                    'ECDSA public key is invalid',
                    self::SEVERITY_ERROR,
                    'Provide a valid ECDSA public key'
                );
            }
        }
    }

    /**
     * Validate production-specific settings
     *
     * @param JwtConfig $config JWT configuration
     */
    private function validateProductionSettings(JwtConfig $config): void
    {
        // Check issuer and audience in production
        if (empty($config->getIssuer())) {
            $this->addValidationResult(
                'missing_issuer',
                'JWT issuer not configured for production',
                self::SEVERITY_WARNING,
                'Set issuer claim for better token validation'
            );
        }

        if (empty($config->getAudience())) {
            $this->addValidationResult(
                'missing_audience',
                'JWT audience not configured for production',
                self::SEVERITY_WARNING,
                'Set audience claim for better token validation'
            );
        }

        // Check if using development defaults
        $secret = $config->getSecret();
        if (str_contains(strtolower($secret), 'dev') || str_contains(strtolower($secret), 'test')) {
            $this->addValidationResult(
                'development_secret_in_production',
                'Using development/test secret in production',
                self::SEVERITY_CRITICAL,
                'Use a production-specific secret'
            );
        }
    }

    /**
     * Validate development-specific settings
     *
     * @param JwtConfig $config JWT configuration
     */
    private function validateDevelopmentSettings(JwtConfig $config): void
    {
        // Check for overly secure settings that might hinder development
        $ttl = $config->getTtl();
        if ($ttl < 300) { // 5 minutes
            $this->addValidationResult(
                'very_short_ttl',
                'TTL is very short, may cause frequent re-authentication during development',
                self::SEVERITY_INFO,
                'Consider longer TTL for development convenience'
            );
        }
    }

    /**
     * Validate performance settings
     *
     * @param JwtConfig $config JWT configuration
     */
    private function validatePerformanceSettings(JwtConfig $config): void
    {
        $algorithm = $config->getAlgorithm();
        
        // Performance considerations
        if (str_starts_with($algorithm, 'RS') || str_starts_with($algorithm, 'ES')) {
            $this->addValidationResult(
                'asymmetric_performance',
                "Algorithm '{$algorithm}' has higher CPU overhead than HMAC algorithms",
                self::SEVERITY_INFO,
                'Consider HS256 for better performance if asymmetric crypto is not required'
            );
        }

        if (in_array($algorithm, ['HS512', 'RS512', 'ES512'])) {
            $this->addValidationResult(
                'sha512_performance',
                "Algorithm '{$algorithm}' uses SHA-512 which has higher overhead than SHA-256",
                self::SEVERITY_INFO,
                'Consider SHA-256 variants for better performance'
            );
        }
    }

    /**
     * Generate recommendations
     *
     * @param JwtConfig $config JWT configuration
     */
    private function generateRecommendations(JwtConfig $config): void
    {
        $ttl = $config->getTtl();
        $recommendedTtl = self::SECURITY_RECOMMENDATIONS['recommended_ttl'];
        
        if ($ttl !== $recommendedTtl) {
            $this->addValidationResult(
                'ttl_recommendation',
                sprintf(
                    'Current TTL: %d seconds. Recommended: %d seconds for optimal security/usability balance',
                    $ttl,
                    $recommendedTtl
                ),
                self::SEVERITY_INFO,
                "Consider using {$recommendedTtl} seconds TTL"
            );
        }

        // Algorithm recommendation
        $algorithm = $config->getAlgorithm();
        if ($algorithm !== 'HS256' && $this->isProduction) {
            $this->addValidationResult(
                'algorithm_recommendation',
                'HS256 is recommended for most production use cases',
                self::SEVERITY_INFO,
                'Consider HS256 unless you specifically need asymmetric cryptography'
            );
        }
    }

    /**
     * Add validation result
     *
     * @param string $code Result code
     * @param string $message Result message
     * @param string $severity Result severity
     * @param string $recommendation Recommendation
     */
    private function addValidationResult(string $code, string $message, string $severity, string $recommendation): void
    {
        $this->validationResults[] = [
            'code' => $code,
            'message' => $message,
            'severity' => $severity,
            'recommendation' => $recommendation,
            'timestamp' => time(),
        ];
    }

    /**
     * Get critical errors
     *
     * @return array Critical error messages
     */
    private function getCriticalErrors(): array
    {
        $errors = [];
        foreach ($this->validationResults as $result) {
            if ($result['severity'] === self::SEVERITY_CRITICAL) {
                $errors[] = $result['message'];
            }
        }
        return $errors;
    }

    /**
     * Get recommendations
     *
     * @return array Recommendations
     */
    private function getRecommendations(): array
    {
        $recommendations = [];
        foreach ($this->validationResults as $result) {
            if (!empty($result['recommendation'])) {
                $recommendations[] = $result['recommendation'];
            }
        }
        return array_unique($recommendations);
    }

    /**
     * Log validation results
     */
    private function logValidationResults(): void
    {
        foreach ($this->validationResults as $result) {
            $logLevel = match ($result['severity']) {
                self::SEVERITY_CRITICAL => 'critical',
                self::SEVERITY_ERROR => 'error',
                self::SEVERITY_WARNING => 'warning',
                self::SEVERITY_INFO => 'info',
                default => 'info',
            };
            
            $this->logger->log($logLevel, 'JWT Configuration: ' . $result['message'], [
                'code' => $result['code'],
                'recommendation' => $result['recommendation'],
                'environment' => $this->isProduction ? 'production' : 'development',
            ]);
        }
    }

    /**
     * Calculate entropy of a string
     *
     * @param string $string String to analyze
     * @return float Entropy value
     */
    private function calculateEntropy(string $string): float
    {
        $length = strlen($string);
        if ($length === 0) {
            return 0.0;
        }
        
        $frequency = array_count_values(str_split($string));
        $entropy = 0.0;
        
        foreach ($frequency as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }
        
        return $entropy;
    }
}