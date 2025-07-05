<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron\Exceptions;

use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;
use Throwable;

/**
 * Base Cron Exception
 *
 * Base exception class for all cron-related exceptions in the TreeHouse framework.
 * Follows TreeHouse exception patterns for consistent error handling and reporting.
 *
 * @package LengthOfRope\TreeHouse\Cron\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class CronException extends BaseException
{
    /**
     * Job name associated with this exception
     */
    protected ?string $jobName = null;

    /**
     * Cron operation that failed
     */
    protected ?string $operation = null;

    /**
     * Create a new cron exception
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $context Additional context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        // Set appropriate defaults for cron exceptions
        $this->setSeverity('medium');
        $this->setStatusCode(500);
        $this->setUserMessage('A cron job error occurred. Please contact support if this persists.');
        
        // Extract job name and operation from context if provided
        if (isset($context['job_name'])) {
            $this->jobName = $context['job_name'];
        }
        if (isset($context['operation'])) {
            $this->operation = $context['operation'];
        }

        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * Generate error code specific to cron exceptions
     */
    protected function generateErrorCode(): void
    {
        if (empty($this->errorCode)) {
            $className = (new \ReflectionClass($this))->getShortName();
            $prefix = 'TH_CRON_' . strtoupper(preg_replace('/Exception$/', '', $className));
            $this->errorCode = $prefix . '_' . str_pad((string)random_int(1, 999), 3, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Get the job name associated with this exception
     */
    public function getJobName(): ?string
    {
        return $this->jobName;
    }

    /**
     * Get the operation that failed
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Create exception for job execution failure
     *
     * @param string $jobName Job name
     * @param string $reason Failure reason
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function jobExecutionFailed(
        string $jobName,
        string $reason,
        ?Throwable $previous = null,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'job_name' => $jobName,
            'operation' => 'execute',
            'reason' => $reason,
            'timestamp' => time(),
        ], $additionalContext);

        $exception = new static(
            "Job execution failed: $jobName - $reason",
            500,
            $previous,
            $context
        );

        $exception->setSeverity('high')
                  ->setUserMessage("The scheduled job '$jobName' encountered an error.")
                  ->setStatusCode(500);

        return $exception;
    }

    /**
     * Create exception for invalid cron expression
     *
     * @param string $expression Invalid cron expression
     * @param string $reason Validation failure reason
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function invalidCronExpression(
        string $expression,
        string $reason,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'expression' => $expression,
            'operation' => 'validate_expression',
            'reason' => $reason,
        ], $additionalContext);

        $exception = new static(
            "Invalid cron expression '$expression': $reason",
            400,
            null,
            $context
        );

        $exception->setSeverity('medium')
                  ->setUserMessage('The cron schedule configuration is invalid.')
                  ->setStatusCode(400);

        return $exception;
    }

    /**
     * Create exception for scheduler failure
     *
     * @param string $reason Scheduler failure reason
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function schedulerFailed(
        string $reason,
        ?Throwable $previous = null,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'operation' => 'schedule',
            'reason' => $reason,
            'scheduler_pid' => getmypid(),
            'timestamp' => time(),
        ], $additionalContext);

        $exception = new static(
            "Cron scheduler failed: $reason",
            500,
            $previous,
            $context
        );

        $exception->setSeverity('critical')
                  ->setUserMessage('The cron scheduler encountered a critical error.')
                  ->setStatusCode(500);

        return $exception;
    }

    /**
     * Create exception for job registration failure
     *
     * @param string $jobClass Job class name
     * @param string $reason Registration failure reason
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function jobRegistrationFailed(
        string $jobClass,
        string $reason,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'job_class' => $jobClass,
            'operation' => 'register',
            'reason' => $reason,
        ], $additionalContext);

        $exception = new static(
            "Failed to register job '$jobClass': $reason",
            500,
            null,
            $context
        );

        $exception->setSeverity('high')
                  ->setUserMessage('A cron job could not be registered properly.')
                  ->setStatusCode(500);

        return $exception;
    }

    /**
     * Create exception for job timeout
     *
     * @param string $jobName Job name
     * @param int $timeout Timeout value in seconds
     * @param array<string, mixed> $additionalContext Additional context
     * @return static
     */
    public static function jobTimeout(
        string $jobName,
        int $timeout,
        array $additionalContext = []
    ): static {
        $context = array_merge([
            'job_name' => $jobName,
            'operation' => 'execute',
            'timeout' => $timeout,
            'reason' => 'timeout',
        ], $additionalContext);

        $exception = new static(
            "Job '$jobName' exceeded timeout of {$timeout} seconds",
            408,
            null,
            $context
        );

        $exception->setSeverity('high')
                  ->setUserMessage("The job '$jobName' took too long to complete.")
                  ->setStatusCode(408);

        return $exception;
    }

    /**
     * Convert exception to array with cron-specific fields
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['job_name'] = $this->jobName;
        $array['operation'] = $this->operation;
        $array['category'] = 'cron';
        
        return $array;
    }
}