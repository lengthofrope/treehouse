<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Classification;

use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;
use LengthOfRope\TreeHouse\Errors\Exceptions\DatabaseException;
use LengthOfRope\TreeHouse\Errors\Exceptions\HttpException;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthorizationException;
use LengthOfRope\TreeHouse\Errors\Exceptions\SystemException;
use LengthOfRope\TreeHouse\Errors\Exceptions\TypeException;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;
use LengthOfRope\TreeHouse\Validation\ValidationException;
use LengthOfRope\TreeHouse\Errors\Logging\LogLevel;
use Throwable;

/**
 * Classifies exceptions to determine severity, category, and reporting requirements
 */
class ExceptionClassifier
{
    /**
     * Exception categories
     */
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_DATABASE = 'database';
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_VALIDATION = 'validation';
    public const CATEGORY_HTTP = 'http';
    public const CATEGORY_AUTHENTICATION = 'authentication';
    public const CATEGORY_AUTHORIZATION = 'authorization';
    public const CATEGORY_TYPE = 'type';
    public const CATEGORY_LOGIC = 'logic';
    public const CATEGORY_RUNTIME = 'runtime';
    public const CATEGORY_UNKNOWN = 'unknown';

    /**
     * Severity levels
     */
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Classification rules mapping exception types to categories and default severities
     */
    private array $classificationRules = [
        // TreeHouse exceptions
        AuthenticationException::class => [
            'category' => self::CATEGORY_AUTHENTICATION,
            'severity' => self::SEVERITY_MEDIUM,
            'should_report' => true,
            'log_level' => LogLevel::WARNING
        ],
        AuthorizationException::class => [
            'category' => self::CATEGORY_AUTHORIZATION,
            'severity' => self::SEVERITY_MEDIUM,
            'should_report' => true,
            'log_level' => LogLevel::WARNING
        ],
        DatabaseException::class => [
            'category' => self::CATEGORY_DATABASE,
            'severity' => self::SEVERITY_HIGH,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ],
        HttpException::class => [
            'category' => self::CATEGORY_HTTP,
            'severity' => self::SEVERITY_LOW,
            'should_report' => false,
            'log_level' => LogLevel::INFO
        ],
        SystemException::class => [
            'category' => self::CATEGORY_SYSTEM,
            'severity' => self::SEVERITY_CRITICAL,
            'should_report' => true,
            'log_level' => LogLevel::CRITICAL
        ],
        TypeException::class => [
            'category' => self::CATEGORY_TYPE,
            'severity' => self::SEVERITY_MEDIUM,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ],
        InvalidArgumentException::class => [
            'category' => self::CATEGORY_LOGIC,
            'severity' => self::SEVERITY_MEDIUM,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ],
        ValidationException::class => [
            'category' => self::CATEGORY_VALIDATION,
            'severity' => self::SEVERITY_LOW,
            'should_report' => false,
            'log_level' => LogLevel::INFO
        ],
        
        // PHP built-in exceptions
        'TypeError' => [
            'category' => self::CATEGORY_TYPE,
            'severity' => self::SEVERITY_HIGH,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ],
        'ArgumentCountError' => [
            'category' => self::CATEGORY_TYPE,
            'severity' => self::SEVERITY_HIGH,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ],
        'ParseError' => [
            'category' => self::CATEGORY_SYSTEM,
            'severity' => self::SEVERITY_CRITICAL,
            'should_report' => true,
            'log_level' => LogLevel::CRITICAL
        ],
        'Error' => [
            'category' => self::CATEGORY_SYSTEM,
            'severity' => self::SEVERITY_CRITICAL,
            'should_report' => true,
            'log_level' => LogLevel::CRITICAL
        ],
        'LogicException' => [
            'category' => self::CATEGORY_LOGIC,
            'severity' => self::SEVERITY_HIGH,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ],
        'RuntimeException' => [
            'category' => self::CATEGORY_RUNTIME,
            'severity' => self::SEVERITY_MEDIUM,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ],
        'InvalidArgumentException' => [
            'category' => self::CATEGORY_LOGIC,
            'severity' => self::SEVERITY_MEDIUM,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ],
        'OutOfBoundsException' => [
            'category' => self::CATEGORY_LOGIC,
            'severity' => self::SEVERITY_MEDIUM,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ],
        'BadMethodCallException' => [
            'category' => self::CATEGORY_LOGIC,
            'severity' => self::SEVERITY_HIGH,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ],
        'DomainException' => [
            'category' => self::CATEGORY_LOGIC,
            'severity' => self::SEVERITY_MEDIUM,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ]
    ];

    /**
     * Security-sensitive exception patterns
     */
    private array $securityPatterns = [
        '/password/i',
        '/token/i',
        '/secret/i',
        '/key/i',
        '/credential/i',
        '/auth/i',
        '/login/i',
        '/permission/i',
        '/access.*denied/i',
        '/unauthorized/i',
        '/forbidden/i'
    ];

    /**
     * Critical system patterns
     */
    private array $criticalPatterns = [
        '/out of memory/i',
        '/disk.*full/i',
        '/cannot allocate/i',
        '/segmentation fault/i',
        '/fatal error/i',
        '/maximum execution time/i',
        '/allowed memory size/i'
    ];

    /**
     * Classify an exception
     */
    public function classify(Throwable $exception): ClassificationResult
    {
        $className = get_class($exception);
        
        // Check for BaseException with predefined classification
        if ($exception instanceof BaseException) {
            return $this->classifyBaseException($exception);
        }
        
        // Use classification rules
        $rules = $this->getClassificationRules($className);
        
        // Analyze message for additional context
        $messageAnalysis = $this->analyzeMessage($exception->getMessage());
        
        // Determine final classification
        $category = $messageAnalysis['category'] ?? $rules['category'];
        $severity = $this->determineSeverity($exception, $rules, $messageAnalysis);
        $shouldReport = $this->shouldReport($exception, $rules, $messageAnalysis);
        $logLevel = $this->determineLogLevel($severity, $rules);
        
        return new ClassificationResult(
            category: $category,
            severity: $severity,
            shouldReport: $shouldReport,
            logLevel: $logLevel,
            isSecurity: $messageAnalysis['is_security'] ?? false,
            isCritical: $messageAnalysis['is_critical'] ?? ($severity === self::SEVERITY_CRITICAL),
            tags: $this->generateTags($exception, $category, $severity),
            metadata: $this->collectMetadata($exception, $rules)
        );
    }

    /**
     * Classify a BaseException using its built-in properties
     */
    private function classifyBaseException(BaseException $exception): ClassificationResult
    {
        $severity = $exception->getSeverity();
        $shouldReport = $exception->shouldReport();
        
        // Determine category from exception type
        $className = get_class($exception);
        $rules = $this->getClassificationRules($className);
        $category = $rules['category'];
        
        // Analyze message for security/critical patterns
        $messageAnalysis = $this->analyzeMessage($exception->getMessage());
        
        return new ClassificationResult(
            category: $category,
            severity: $severity,
            shouldReport: $shouldReport,
            logLevel: $this->mapSeverityToLogLevel($severity),
            isSecurity: $messageAnalysis['is_security'] ?? false,
            isCritical: $severity === self::SEVERITY_CRITICAL,
            tags: $this->generateTags($exception, $category, $severity),
            metadata: array_merge(
                $this->collectMetadata($exception, $rules),
                [
                    'error_code' => $exception->getErrorCode(),
                    'context' => $exception->getContext(),
                    'user_message' => $exception->getUserMessage()
                ]
            )
        );
    }

    /**
     * Get classification rules for an exception class
     */
    private function getClassificationRules(string $className): array
    {
        // Check exact class match
        if (isset($this->classificationRules[$className])) {
            return $this->classificationRules[$className];
        }
        
        // Check parent classes
        foreach ($this->classificationRules as $ruleClass => $rules) {
            if (is_subclass_of($className, $ruleClass)) {
                return $rules;
            }
        }
        
        // Default classification
        return [
            'category' => self::CATEGORY_UNKNOWN,
            'severity' => self::SEVERITY_MEDIUM,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ];
    }

    /**
     * Analyze exception message for patterns
     */
    private function analyzeMessage(string $message): array
    {
        $analysis = [
            'is_security' => false,
            'is_critical' => false,
            'category' => null
        ];
        
        // Check security patterns
        foreach ($this->securityPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $analysis['is_security'] = true;
                $analysis['category'] = self::CATEGORY_SECURITY;
                break;
            }
        }
        
        // Check critical patterns
        foreach ($this->criticalPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $analysis['is_critical'] = true;
                break;
            }
        }
        
        return $analysis;
    }

    /**
     * Determine final severity
     */
    private function determineSeverity(Throwable $exception, array $rules, array $messageAnalysis): string
    {
        // Critical patterns override everything
        if ($messageAnalysis['is_critical']) {
            return self::SEVERITY_CRITICAL;
        }
        
        // Security issues are at least medium severity
        if ($messageAnalysis['is_security']) {
            $defaultSeverity = $rules['severity'];
            return $this->getHigherSeverity($defaultSeverity, self::SEVERITY_MEDIUM);
        }
        
        return $rules['severity'];
    }

    /**
     * Determine if exception should be reported
     */
    private function shouldReport(Throwable $exception, array $rules, array $messageAnalysis): bool
    {
        // Always report security issues
        if ($messageAnalysis['is_security']) {
            return true;
        }
        
        // Always report critical issues
        if ($messageAnalysis['is_critical']) {
            return true;
        }
        
        return $rules['should_report'];
    }

    /**
     * Determine log level
     */
    private function determineLogLevel(string $severity, array $rules): string
    {
        return $this->mapSeverityToLogLevel($severity);
    }

    /**
     * Map severity to PSR-3 log level
     */
    private function mapSeverityToLogLevel(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_LOW => LogLevel::INFO,
            self::SEVERITY_MEDIUM => LogLevel::WARNING,
            self::SEVERITY_HIGH => LogLevel::ERROR,
            self::SEVERITY_CRITICAL => LogLevel::CRITICAL,
            default => LogLevel::ERROR
        };
    }

    /**
     * Get the higher of two severities
     */
    private function getHigherSeverity(string $severity1, string $severity2): string
    {
        $severityOrder = [
            self::SEVERITY_LOW => 1,
            self::SEVERITY_MEDIUM => 2,
            self::SEVERITY_HIGH => 3,
            self::SEVERITY_CRITICAL => 4
        ];
        
        $level1 = $severityOrder[$severity1] ?? 2;
        $level2 = $severityOrder[$severity2] ?? 2;
        
        return $level1 >= $level2 ? $severity1 : $severity2;
    }

    /**
     * Generate tags for the exception
     */
    private function generateTags(Throwable $exception, string $category, string $severity): array
    {
        $tags = [
            'category:' . $category,
            'severity:' . $severity,
            'class:' . get_class($exception)
        ];
        
        // Add HTTP status tag for HTTP exceptions
        if ($exception instanceof HttpException) {
            $tags[] = 'http_status:' . $exception->getStatusCode();
        }
        
        // Add environment tag
        $tags[] = 'env:' . ($_ENV['APP_ENV'] ?? 'production');
        
        return $tags;
    }

    /**
     * Collect metadata about the exception
     */
    private function collectMetadata(Throwable $exception, array $rules): array
    {
        $metadata = [
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'classification_rule' => $rules
        ];
        
        // Add previous exception info if available
        if ($exception->getPrevious()) {
            $metadata['previous_exception'] = [
                'class' => get_class($exception->getPrevious()),
                'message' => $exception->getPrevious()->getMessage(),
                'file' => $exception->getPrevious()->getFile(),
                'line' => $exception->getPrevious()->getLine()
            ];
        }
        
        return $metadata;
    }

    /**
     * Add custom classification rule
     */
    public function addRule(string $exceptionClass, array $rule): void
    {
        $this->classificationRules[$exceptionClass] = array_merge([
            'category' => self::CATEGORY_UNKNOWN,
            'severity' => self::SEVERITY_MEDIUM,
            'should_report' => true,
            'log_level' => LogLevel::ERROR
        ], $rule);
    }

    /**
     * Get all available categories
     */
    public function getCategories(): array
    {
        return [
            self::CATEGORY_SECURITY,
            self::CATEGORY_DATABASE,
            self::CATEGORY_SYSTEM,
            self::CATEGORY_VALIDATION,
            self::CATEGORY_HTTP,
            self::CATEGORY_AUTHENTICATION,
            self::CATEGORY_AUTHORIZATION,
            self::CATEGORY_TYPE,
            self::CATEGORY_LOGIC,
            self::CATEGORY_RUNTIME,
            self::CATEGORY_UNKNOWN
        ];
    }

    /**
     * Get all available severities
     */
    public function getSeverities(): array
    {
        return [
            self::SEVERITY_LOW,
            self::SEVERITY_MEDIUM,
            self::SEVERITY_HIGH,
            self::SEVERITY_CRITICAL
        ];
    }
}