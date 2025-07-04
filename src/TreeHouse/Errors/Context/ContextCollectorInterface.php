<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Context;

use Throwable;

/**
 * Interface for collecting contextual information during error handling
 */
interface ContextCollectorInterface
{
    /**
     * Collect context data for the given exception
     *
     * @param Throwable $exception The exception that occurred
     * @return array The collected context data
     */
    public function collect(Throwable $exception): array;

    /**
     * Get the priority of this collector (higher numbers = higher priority)
     *
     * @return int Priority level (0-100)
     */
    public function getPriority(): int;

    /**
     * Check if this collector should run for the given exception
     *
     * @param Throwable $exception The exception that occurred
     * @return bool True if this collector should run
     */
    public function shouldCollect(Throwable $exception): bool;

    /**
     * Get the name/identifier of this collector
     *
     * @return string Collector name
     */
    public function getName(): string;
}