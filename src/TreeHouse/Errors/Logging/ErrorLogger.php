<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Logging;

use Throwable;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * PSR-3 Compliant Error Logger
 * 
 * Zero-dependency implementation of PSR-3 Logger Interface for TreeHouse Framework.
 * Provides structured logging with multiple channels and formatters.
 * 
 * @package LengthOfRope\TreeHouse\Errors\Logging
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ErrorLogger implements LoggerInterface
{
    /**
     * Default log channel
     */
    private string $defaultChannel;

    /**
     * Log channels configuration
     *
     * @var array<string, array<string, mixed>>
     */
    private array $channels;

    /**
     * Log formatters
     *
     * @var array<string, LogFormatter>
     */
    private array $formatters = [];

    /**
     * Create a new error logger
     *
     * @param string $defaultChannel Default channel name
     * @param array<string, array<string, mixed>> $channels Channel configurations
     */
    public function __construct(string $defaultChannel = 'file', array $channels = [])
    {
        $this->defaultChannel = $defaultChannel;
        $this->channels = $channels;
        
        // Set default file channel if none provided
        if (empty($this->channels)) {
            $this->channels = [
                'file' => [
                    'driver' => 'file',
                    'path' => getcwd() . '/storage/logs',
                    'filename' => 'treehouse.log',
                    'max_files' => 30,
                    'level' => LogLevel::DEBUG,
                ],
            ];
        }
    }

    /**
     * System is unusable.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        if (!LogLevel::isValidLevel($level)) {
            throw InvalidArgumentException::invalidValue('level', $level, LogLevel::getAllLevels());
        }

        $this->writeLog($level, (string) $message, $context);
    }

    /**
     * Log an exception with full context
     *
     * @param Throwable $exception
     * @param array<string, mixed> $context
     * @return void
     */
    public function logException(Throwable $exception, array $context = []): void
    {
        $level = $this->getExceptionLogLevel($exception);
        
        $context = array_merge($context, [
            'exception' => $exception,
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->log($level, $exception->getMessage(), $context);
    }

    /**
     * Get appropriate log level for exception
     */
    private function getExceptionLogLevel(Throwable $exception): string
    {
        // Check if it's a BaseException with severity
        if (method_exists($exception, 'getSeverity')) {
            /** @var \LengthOfRope\TreeHouse\Errors\Exceptions\BaseException $exception */
            return match ($exception->getSeverity()) {
                'critical' => LogLevel::CRITICAL,
                'high' => LogLevel::ERROR,
                'medium' => LogLevel::WARNING,
                'low' => LogLevel::NOTICE,
                default => LogLevel::ERROR,
            };
        }

        // Default mapping for standard exceptions
        return match (get_class($exception)) {
            'Error', 'ParseError', 'TypeError' => LogLevel::CRITICAL,
            'RuntimeException', 'LogicException' => LogLevel::ERROR,
            'InvalidArgumentException' => LogLevel::WARNING,
            default => LogLevel::ERROR,
        };
    }

    /**
     * Write log entry to configured channels
     *
     * @param string $level
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function writeLog(string $level, string $message, array $context): void
    {
        $channel = $this->getChannel($this->defaultChannel);
        
        if (!$this->shouldLog($level, $channel)) {
            return;
        }

        $formatter = $this->getFormatter($channel);
        $formattedMessage = $formatter->format($level, $message, $context);

        $this->writeToChannel($channel, $formattedMessage);
    }

    /**
     * Check if message should be logged based on channel level
     */
    private function shouldLog(string $level, array $channel): bool
    {
        $channelLevel = $channel['level'] ?? LogLevel::DEBUG;
        $levelPriority = LogLevel::getPriority($level);
        $channelPriority = LogLevel::getPriority($channelLevel);
        
        return $levelPriority <= $channelPriority;
    }

    /**
     * Get channel configuration
     *
     * @param string $channelName
     * @return array<string, mixed>
     */
    private function getChannel(string $channelName): array
    {
        return $this->channels[$channelName] ?? $this->channels[$this->defaultChannel];
    }

    /**
     * Get formatter for channel
     */
    private function getFormatter(array $channel): LogFormatter
    {
        $driver = $channel['driver'] ?? 'file';
        
        if (!isset($this->formatters[$driver])) {
            $this->formatters[$driver] = new LogFormatter($channel);
        }
        
        return $this->formatters[$driver];
    }

    /**
     * Write formatted message to channel
     */
    private function writeToChannel(array $channel, string $message): void
    {
        $driver = $channel['driver'] ?? 'file';
        
        match ($driver) {
            'file' => $this->writeToFile($channel, $message),
            'syslog' => $this->writeToSyslog($channel, $message),
            'error_log' => $this->writeToErrorLog($message),
            default => $this->writeToFile($channel, $message),
        };
    }

    /**
     * Write to file
     */
    private function writeToFile(array $channel, string $message): void
    {
        $path = $channel['path'] ?? getcwd() . '/storage/logs';
        $filename = $channel['filename'] ?? 'treehouse.log';
        $maxFiles = $channel['max_files'] ?? 30;
        
        // Ensure directory exists
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        
        $logFile = $path . '/' . $filename;
        
        // Rotate logs if needed
        $this->rotateLogsIfNeeded($logFile, $maxFiles);
        
        // Write to file
        file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Write to syslog
     */
    private function writeToSyslog(array $channel, string $message): void
    {
        $facility = $channel['facility'] ?? LOG_USER;
        $ident = $channel['ident'] ?? 'treehouse';
        
        openlog($ident, LOG_PID | LOG_PERROR, $facility);
        syslog(LOG_INFO, $message);
        closelog();
    }

    /**
     * Write to PHP error log
     */
    private function writeToErrorLog(string $message): void
    {
        error_log($message);
    }

    /**
     * Rotate log files if needed
     */
    private function rotateLogsIfNeeded(string $logFile, int $maxFiles): void
    {
        if (!file_exists($logFile)) {
            return;
        }
        
        $fileSize = filesize($logFile);
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if ($fileSize < $maxSize) {
            return;
        }
        
        // Rotate files
        for ($i = $maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $logFile . '.' . $i;
            $newFile = $logFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i === $maxFiles - 1) {
                    unlink($oldFile); // Delete oldest
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        // Move current log to .1
        rename($logFile, $logFile . '.1');
    }

    /**
     * Add a new channel
     *
     * @param string $name
     * @param array<string, mixed> $config
     */
    public function addChannel(string $name, array $config): void
    {
        $this->channels[$name] = $config;
    }

    /**
     * Set default channel
     */
    public function setDefaultChannel(string $channel): void
    {
        $this->defaultChannel = $channel;
    }

    /**
     * Get all channels
     *
     * @return array<string, array<string, mixed>>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }
}