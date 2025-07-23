<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * JWT Debug Manager
 *
 * Provides comprehensive debugging capabilities for JWT operations including
 * token analysis, validation debugging, performance monitoring, and detailed
 * error reporting. Designed for development and troubleshooting environments.
 *
 * Features:
 * - Detailed token structure analysis
 * - Step-by-step validation debugging
 * - Performance profiling
 * - Error context collection
 * - Debug mode toggles
 * - Trace logging
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtDebugger
{
    /**
     * Debug levels
     */
    public const DEBUG_OFF = 0;
    public const DEBUG_BASIC = 1;
    public const DEBUG_DETAILED = 2;
    public const DEBUG_VERBOSE = 3;

    /**
     * Default configuration
     */
    private const DEFAULTS = [
        'enabled' => false,
        'level' => self::DEBUG_BASIC,
        'log_to_file' => true,
        'log_to_output' => false,
        'include_payload' => false,        // Security: don't log sensitive data by default
        'include_signatures' => false,    // Security: don't log signatures
        'performance_monitoring' => true,
        'max_log_entries' => 1000,
        'trace_requests' => true,
    ];

    private JwtConfig $jwtConfig;
    private ErrorLogger $logger;
    private array $config;
    private array $debugTrace = [];
    private array $performanceData = [];
    private int $currentTraceId;

    /**
     * Create new JWT debugger
     *
     * @param JwtConfig $jwtConfig JWT configuration
     * @param ErrorLogger $logger Logger instance
     * @param array $config Debug configuration
     */
    public function __construct(JwtConfig $jwtConfig, ErrorLogger $logger, array $config = [])
    {
        $this->jwtConfig = $jwtConfig;
        $this->logger = $logger;
        $this->config = array_merge(self::DEFAULTS, $config);
        $this->currentTraceId = 0;
        
        $this->validateConfig();
    }

    /**
     * Start debug trace for operation
     *
     * @param string $operation Operation name
     * @param array $context Operation context
     * @return int Trace ID
     */
    public function startTrace(string $operation, array $context = []): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $traceId = ++$this->currentTraceId;
        $timestamp = Carbon::now()->getTimestamp();
        
        $trace = [
            'id' => $traceId,
            'operation' => $operation,
            'context' => $this->sanitizeContext($context),
            'start_time' => $timestamp,
            'start_memory' => memory_get_usage(true),
            'steps' => [],
            'performance' => [],
            'errors' => [],
            'warnings' => [],
        ];

        $this->debugTrace[$traceId] = $trace;
        
        $this->log('debug', "Started trace {$traceId}: {$operation}", [
            'trace_id' => $traceId,
            'context' => $context,
        ]);

        return $traceId;
    }

    /**
     * Add step to debug trace
     *
     * @param int $traceId Trace ID
     * @param string $step Step description
     * @param array $data Step data
     * @return self
     */
    public function addStep(int $traceId, string $step, array $data = []): self
    {
        if (!$this->isEnabled() || !isset($this->debugTrace[$traceId])) {
            return $this;
        }

        $timestamp = Carbon::now()->getTimestamp();
        $stepData = [
            'step' => $step,
            'data' => $this->sanitizeStepData($data),
            'timestamp' => $timestamp,
            'memory_usage' => memory_get_usage(true),
        ];

        $this->debugTrace[$traceId]['steps'][] = $stepData;
        
        if ($this->config['level'] >= self::DEBUG_DETAILED) {
            $this->log('debug', "Trace {$traceId} step: {$step}", [
                'trace_id' => $traceId,
                'step_data' => $stepData,
            ]);
        }

        return $this;
    }

    /**
     * Add performance measurement
     *
     * @param int $traceId Trace ID
     * @param string $metric Metric name
     * @param float $value Metric value
     * @param string $unit Metric unit
     * @return self
     */
    public function addPerformance(int $traceId, string $metric, float $value, string $unit = 'ms'): self
    {
        if (!$this->isEnabled() || !$this->config['performance_monitoring']) {
            return $this;
        }

        if (!isset($this->debugTrace[$traceId])) {
            return $this;
        }

        $this->debugTrace[$traceId]['performance'][$metric] = [
            'value' => $value,
            'unit' => $unit,
            'timestamp' => Carbon::now()->getTimestamp(),
        ];

        return $this;
    }

    /**
     * Add error to debug trace
     *
     * @param int $traceId Trace ID
     * @param string $error Error message
     * @param array $context Error context
     * @return self
     */
    public function addError(int $traceId, string $error, array $context = []): self
    {
        if (!$this->isEnabled() || !isset($this->debugTrace[$traceId])) {
            return $this;
        }

        $errorData = [
            'message' => $error,
            'context' => $this->sanitizeContext($context),
            'timestamp' => Carbon::now()->getTimestamp(),
        ];

        $this->debugTrace[$traceId]['errors'][] = $errorData;
        
        $this->log('error', "Trace {$traceId} error: {$error}", [
            'trace_id' => $traceId,
            'error_context' => $context,
        ]);

        return $this;
    }

    /**
     * Add warning to debug trace
     *
     * @param int $traceId Trace ID
     * @param string $warning Warning message
     * @param array $context Warning context
     * @return self
     */
    public function addWarning(int $traceId, string $warning, array $context = []): self
    {
        if (!$this->isEnabled() || !isset($this->debugTrace[$traceId])) {
            return $this;
        }

        $warningData = [
            'message' => $warning,
            'context' => $this->sanitizeContext($context),
            'timestamp' => Carbon::now()->getTimestamp(),
        ];

        $this->debugTrace[$traceId]['warnings'][] = $warningData;
        
        if ($this->config['level'] >= self::DEBUG_DETAILED) {
            $this->log('warning', "Trace {$traceId} warning: {$warning}", [
                'trace_id' => $traceId,
                'warning_context' => $context,
            ]);
        }

        return $this;
    }

    /**
     * Finish debug trace
     *
     * @param int $traceId Trace ID
     * @param bool $success Operation success
     * @param mixed $result Operation result
     * @return array Complete trace data
     */
    public function finishTrace(int $traceId, bool $success, mixed $result = null): array
    {
        if (!$this->isEnabled() || !isset($this->debugTrace[$traceId])) {
            return [];
        }

        $trace = &$this->debugTrace[$traceId];
        $endTime = Carbon::now()->getTimestamp();
        
        $trace['end_time'] = $endTime;
        $trace['end_memory'] = memory_get_usage(true);
        $trace['duration'] = $endTime - $trace['start_time'];
        $trace['memory_delta'] = $trace['end_memory'] - $trace['start_memory'];
        $trace['success'] = $success;
        $trace['result_summary'] = $this->summarizeResult($result);

        $this->log('info', "Finished trace {$traceId}: {$trace['operation']}", [
            'trace_id' => $traceId,
            'success' => $success,
            'duration' => $trace['duration'],
            'memory_delta' => $trace['memory_delta'],
            'steps_count' => count($trace['steps']),
            'errors_count' => count($trace['errors']),
            'warnings_count' => count($trace['warnings']),
        ]);

        // Store performance data
        if ($this->config['performance_monitoring']) {
            $this->recordPerformanceMetrics($trace);
        }

        // Clean up old traces
        $this->cleanupTraces();

        return $trace;
    }

    /**
     * Debug JWT token structure
     *
     * @param string $token JWT token
     * @return array Debug information
     */
    public function debugTokenStructure(string $token): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $traceId = $this->startTrace('token_structure_debug', ['token_length' => strlen($token)]);

        try {
            // Parse token parts
            $parts = explode('.', $token);
            $this->addStep($traceId, 'split_token_parts', ['parts_count' => count($parts)]);

            if (count($parts) !== 3) {
                $this->addError($traceId, 'Invalid token format', ['expected_parts' => 3, 'actual_parts' => count($parts)]);
                $this->finishTrace($traceId, false);
                return ['error' => 'Invalid JWT format'];
            }

            $debugInfo = [
                'structure' => [
                    'header_length' => strlen($parts[0]),
                    'payload_length' => strlen($parts[1]),
                    'signature_length' => strlen($parts[2]),
                    'total_length' => strlen($token),
                ],
                'header' => [],
                'payload' => [],
                'signature_info' => [],
                'validation_steps' => [],
            ];

            // Decode header
            try {
                $headerDecoded = $this->base64UrlDecode($parts[0]);
                $header = json_decode($headerDecoded, true);
                $this->addStep($traceId, 'decode_header', ['header' => $header]);
                
                $debugInfo['header'] = [
                    'raw_length' => strlen($headerDecoded),
                    'decoded' => $header,
                    'algorithm' => $header['alg'] ?? 'unknown',
                    'type' => $header['typ'] ?? 'unknown',
                ];
            } catch (\Exception $e) {
                $this->addError($traceId, 'Header decode failed', ['error' => $e->getMessage()]);
                $debugInfo['header']['error'] = $e->getMessage();
            }

            // Decode payload (if allowed)
            try {
                $payloadDecoded = $this->base64UrlDecode($parts[1]);
                $payload = json_decode($payloadDecoded, true);
                $this->addStep($traceId, 'decode_payload', ['payload_size' => strlen($payloadDecoded)]);
                
                $debugInfo['payload'] = [
                    'raw_length' => strlen($payloadDecoded),
                    'claims_count' => count($payload ?? []),
                ];

                if ($this->config['include_payload']) {
                    $debugInfo['payload']['decoded'] = $payload;
                } else {
                    $debugInfo['payload']['claims'] = array_keys($payload ?? []);
                }

                // Analyze claims
                if ($payload) {
                    $debugInfo['payload']['timing'] = $this->analyzeTokenTiming($payload);
                    $debugInfo['payload']['standard_claims'] = $this->analyzeStandardClaims($payload);
                }
            } catch (\Exception $e) {
                $this->addError($traceId, 'Payload decode failed', ['error' => $e->getMessage()]);
                $debugInfo['payload']['error'] = $e->getMessage();
            }

            // Analyze signature (without revealing it)
            $debugInfo['signature_info'] = [
                'raw_length' => strlen($parts[2]),
                'base64_length' => strlen($this->base64UrlDecode($parts[2])),
                'algorithm_expected' => $header['alg'] ?? 'unknown',
            ];

            $this->finishTrace($traceId, true, $debugInfo);
            return $debugInfo;

        } catch (\Exception $e) {
            $this->addError($traceId, 'Token debug failed', ['error' => $e->getMessage()]);
            $this->finishTrace($traceId, false);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Debug token validation process
     *
     * @param string $token JWT token
     * @return array Validation debug information
     */
    public function debugTokenValidation(string $token): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $traceId = $this->startTrace('token_validation_debug', ['token_length' => strlen($token)]);
        $validator = new TokenValidator($this->jwtConfig);

        $validationSteps = [];

        try {
            // Step 1: Structure validation
            $this->addStep($traceId, 'validate_structure');
            $structureValid = $this->validateTokenStructure($token);
            $validationSteps['structure'] = [
                'valid' => $structureValid,
                'details' => $structureValid ? 'Valid JWT structure' : 'Invalid JWT structure',
            ];

            if (!$structureValid) {
                $this->addError($traceId, 'Structure validation failed');
                $this->finishTrace($traceId, false);
                return ['validation_steps' => $validationSteps];
            }

            // Step 2: Algorithm validation
            $this->addStep($traceId, 'validate_algorithm');
            $parts = explode('.', $token);
            $header = json_decode($this->base64UrlDecode($parts[0]), true);
            $algorithmValid = in_array($header['alg'] ?? '', ['HS256', 'RS256', 'ES256']);
            $validationSteps['algorithm'] = [
                'valid' => $algorithmValid,
                'algorithm' => $header['alg'] ?? 'unknown',
                'supported' => ['HS256', 'RS256', 'ES256'],
            ];

            // Step 3: Signature validation
            $this->addStep($traceId, 'validate_signature');
            try {
                $claims = $validator->validate($token);
                $validationSteps['signature'] = [
                    'valid' => true,
                    'details' => 'Signature is valid',
                ];
            } catch (\Exception $e) {
                $this->addError($traceId, 'Signature validation failed', ['error' => $e->getMessage()]);
                $validationSteps['signature'] = [
                    'valid' => false,
                    'error' => $e->getMessage(),
                ];
            }

            // Step 4: Claims validation
            if (isset($claims)) {
                $this->addStep($traceId, 'validate_claims');
                $claimsValidation = $this->validateClaims($claims->getAllClaims());
                $validationSteps['claims'] = $claimsValidation;
            }

            $allValid = !in_array(false, array_column($validationSteps, 'valid'));
            $this->finishTrace($traceId, $allValid);

            return [
                'overall_valid' => $allValid,
                'validation_steps' => $validationSteps,
                'trace_id' => $traceId,
            ];

        } catch (\Exception $e) {
            $this->addError($traceId, 'Validation debug failed', ['error' => $e->getMessage()]);
            $this->finishTrace($traceId, false);
            return ['error' => $e->getMessage(), 'validation_steps' => $validationSteps];
        }
    }

    /**
     * Get debug trace
     *
     * @param int|null $traceId Specific trace ID or null for all
     * @return array Debug trace data
     */
    public function getTrace(?int $traceId = null): array
    {
        if ($traceId !== null) {
            return $this->debugTrace[$traceId] ?? [];
        }

        return $this->debugTrace;
    }

    /**
     * Get performance metrics
     *
     * @return array Performance data
     */
    public function getPerformanceMetrics(): array
    {
        return $this->performanceData;
    }

    /**
     * Clear debug traces
     *
     * @return self
     */
    public function clearTraces(): self
    {
        $this->debugTrace = [];
        $this->performanceData = [];
        $this->currentTraceId = 0;
        return $this;
    }

    /**
     * Check if debugging is enabled
     *
     * @return bool True if enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] && $this->config['level'] > self::DEBUG_OFF;
    }

    /**
     * Set debug level
     *
     * @param int $level Debug level
     * @return self
     */
    public function setLevel(int $level): self
    {
        $this->config['level'] = $level;
        return $this;
    }

    /**
     * Enable or disable debugging
     *
     * @param bool $enabled Whether to enable debugging
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->config['enabled'] = $enabled;
        return $this;
    }

    /**
     * Sanitize context data for logging
     *
     * @param array $context Context data
     * @return array Sanitized context
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        
        foreach ($context as $key => $value) {
            // Skip sensitive data
            if (in_array(strtolower($key), ['password', 'secret', 'token', 'key', 'signature'])) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }
            
            if (is_string($value) && strlen($value) > 1000) {
                $sanitized[$key] = substr($value, 0, 1000) . '... [TRUNCATED]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize step data
     *
     * @param array $data Step data
     * @return array Sanitized data
     */
    private function sanitizeStepData(array $data): array
    {
        return $this->sanitizeContext($data);
    }

    /**
     * Summarize operation result
     *
     * @param mixed $result Operation result
     * @return array Result summary
     */
    private function summarizeResult(mixed $result): array
    {
        if ($result === null) {
            return ['type' => 'null'];
        }
        
        if (is_bool($result)) {
            return ['type' => 'boolean', 'value' => $result];
        }
        
        if (is_string($result)) {
            return ['type' => 'string', 'length' => strlen($result)];
        }
        
        if (is_array($result)) {
            return ['type' => 'array', 'count' => count($result)];
        }
        
        if (is_object($result)) {
            return ['type' => 'object', 'class' => get_class($result)];
        }
        
        return ['type' => gettype($result)];
    }

    /**
     * Record performance metrics
     *
     * @param array $trace Trace data
     */
    private function recordPerformanceMetrics(array $trace): void
    {
        $operation = $trace['operation'];
        
        if (!isset($this->performanceData[$operation])) {
            $this->performanceData[$operation] = [
                'count' => 0,
                'total_duration' => 0,
                'total_memory' => 0,
                'min_duration' => null,
                'max_duration' => null,
                'avg_duration' => 0,
            ];
        }
        
        $metrics = &$this->performanceData[$operation];
        $metrics['count']++;
        $metrics['total_duration'] += $trace['duration'];
        $metrics['total_memory'] += $trace['memory_delta'];
        
        if ($metrics['min_duration'] === null || $trace['duration'] < $metrics['min_duration']) {
            $metrics['min_duration'] = $trace['duration'];
        }
        
        if ($metrics['max_duration'] === null || $trace['duration'] > $metrics['max_duration']) {
            $metrics['max_duration'] = $trace['duration'];
        }
        
        $metrics['avg_duration'] = $metrics['total_duration'] / $metrics['count'];
    }

    /**
     * Clean up old traces
     */
    private function cleanupTraces(): void
    {
        if (count($this->debugTrace) > $this->config['max_log_entries']) {
            $this->debugTrace = array_slice($this->debugTrace, -$this->config['max_log_entries'], null, true);
        }
    }

    /**
     * Validate token structure
     *
     * @param string $token JWT token
     * @return bool True if structure is valid
     */
    private function validateTokenStructure(string $token): bool
    {
        $parts = explode('.', $token);
        return count($parts) === 3 && 
               !empty($parts[0]) && 
               !empty($parts[1]) && 
               !empty($parts[2]);
    }

    /**
     * Analyze token timing
     *
     * @param array $payload Token payload
     * @return array Timing analysis
     */
    private function analyzeTokenTiming(array $payload): array
    {
        $now = Carbon::now()->getTimestamp();
        $analysis = [];
        
        if (isset($payload['iat'])) {
            $analysis['issued_at'] = [
                'timestamp' => $payload['iat'],
                'human' => date('Y-m-d H:i:s', $payload['iat']),
                'age_seconds' => $now - $payload['iat'],
            ];
        }
        
        if (isset($payload['exp'])) {
            $analysis['expires_at'] = [
                'timestamp' => $payload['exp'],
                'human' => date('Y-m-d H:i:s', $payload['exp']),
                'ttl_seconds' => $payload['exp'] - $now,
                'expired' => $payload['exp'] < $now,
            ];
        }
        
        if (isset($payload['nbf'])) {
            $analysis['not_before'] = [
                'timestamp' => $payload['nbf'],
                'human' => date('Y-m-d H:i:s', $payload['nbf']),
                'valid' => $payload['nbf'] <= $now,
            ];
        }
        
        return $analysis;
    }

    /**
     * Analyze standard claims
     *
     * @param array $payload Token payload
     * @return array Claims analysis
     */
    private function analyzeStandardClaims(array $payload): array
    {
        $standardClaims = ['iss', 'sub', 'aud', 'exp', 'nbf', 'iat', 'jti'];
        $analysis = [
            'present' => [],
            'missing' => [],
            'custom' => [],
        ];
        
        foreach ($payload as $claim => $value) {
            if (in_array($claim, $standardClaims)) {
                $analysis['present'][] = $claim;
            } else {
                $analysis['custom'][] = $claim;
            }
        }
        
        $analysis['missing'] = array_diff($standardClaims, $analysis['present']);
        
        return $analysis;
    }

    /**
     * Validate claims
     *
     * @param array $claims Token claims
     * @return array Validation results
     */
    private function validateClaims(array $claims): array
    {
        $validation = [
            'valid' => true,
            'issues' => [],
        ];
        
        $now = Carbon::now()->getTimestamp();
        
        // Check expiration
        if (isset($claims['exp']) && $claims['exp'] < $now) {
            $validation['valid'] = false;
            $validation['issues'][] = 'Token is expired';
        }
        
        // Check not before
        if (isset($claims['nbf']) && $claims['nbf'] > $now) {
            $validation['valid'] = false;
            $validation['issues'][] = 'Token is not yet valid';
        }
        
        // Check required claims
        $requiredClaims = $this->jwtConfig->getRequiredClaims();
        foreach ($requiredClaims as $required) {
            if (!isset($claims[$required])) {
                $validation['valid'] = false;
                $validation['issues'][] = "Missing required claim: {$required}";
            }
        }
        
        return $validation;
    }

    /**
     * Base64URL decode
     *
     * @param string $data Data to decode
     * @return string Decoded data
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Log debug message
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Log context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->config['log_to_file']) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Validate configuration
     *
     * @throws InvalidArgumentException If configuration is invalid
     */
    private function validateConfig(): void
    {
        if (!in_array($this->config['level'], [self::DEBUG_OFF, self::DEBUG_BASIC, self::DEBUG_DETAILED, self::DEBUG_VERBOSE])) {
            throw new InvalidArgumentException('Invalid debug level', 'INVALID_DEBUG_LEVEL');
        }
        
        if ($this->config['max_log_entries'] <= 0) {
            throw new InvalidArgumentException('Max log entries must be positive', 'INVALID_MAX_LOG_ENTRIES');
        }
    }
}