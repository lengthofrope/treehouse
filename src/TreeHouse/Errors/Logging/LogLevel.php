<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Logging;

/**
 * PSR-3 Log Levels
 * 
 * Describes log levels as defined by RFC 5424.
 * 
 * @package LengthOfRope\TreeHouse\Errors\Logging
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class LogLevel
{
    /**
     * System is unusable
     */
    public const EMERGENCY = 'emergency';

    /**
     * Action must be taken immediately
     */
    public const ALERT = 'alert';

    /**
     * Critical conditions
     */
    public const CRITICAL = 'critical';

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored
     */
    public const ERROR = 'error';

    /**
     * Exceptional occurrences that are not errors
     */
    public const WARNING = 'warning';

    /**
     * Normal but significant events
     */
    public const NOTICE = 'notice';

    /**
     * Interesting events
     */
    public const INFO = 'info';

    /**
     * Detailed debug information
     */
    public const DEBUG = 'debug';

    /**
     * Get all available log levels
     *
     * @return array<string>
     */
    public static function getAllLevels(): array
    {
        return [
            self::EMERGENCY,
            self::ALERT,
            self::CRITICAL,
            self::ERROR,
            self::WARNING,
            self::NOTICE,
            self::INFO,
            self::DEBUG,
        ];
    }

    /**
     * Check if a log level is valid
     *
     * @param mixed $level
     * @return bool
     */
    public static function isValidLevel(mixed $level): bool
    {
        return in_array($level, self::getAllLevels(), true);
    }

    /**
     * Get numeric priority for a log level (lower number = higher priority)
     *
     * @param string $level
     * @return int
     */
    public static function getPriority(string $level): int
    {
        return match ($level) {
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7,
            default => 999,
        };
    }
}