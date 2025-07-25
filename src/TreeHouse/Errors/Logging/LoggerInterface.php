<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Logging;

/**
 * PSR-3 Logger Interface
 * 
 * Zero-dependency implementation of PSR-3 Logger Interface for TreeHouse Framework.
 * Describes a logger instance.
 * 
 * The message MUST be a string or object implementing __toString().
 * 
 * The message MAY contain placeholders in the form: {foo} where foo
 * will be replaced by the context data in key "foo".
 * 
 * The context array can contain arbitrary data, the only assumption that
 * can be made by implementors is that if an Exception instance is given
 * to produce a stack trace, it MUST be in a key named "exception".
 * 
 * @package LengthOfRope\TreeHouse\Errors\Logging
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface LoggerInterface
{
    /**
     * System is unusable.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = []): void;

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void;

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void;

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void;

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void;

    /**
     * Normal but significant events.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []): void;

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []): void;

    /**
     * Detailed debug information.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []): void;

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     *
     * @throws \LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void;
}