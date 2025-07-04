<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Context;

use Throwable;

/**
 * Manages context collection from multiple collectors
 */
class ContextManager
{
    /**
     * Registered context collectors
     * @var ContextCollectorInterface[]
     */
    private array $collectors = [];

    /**
     * Maximum execution time for context collection (in seconds)
     */
    private float $maxExecutionTime = 2.0;

    /**
     * Whether to continue collecting if one collector fails
     */
    private bool $continueOnFailure = true;

    /**
     * Add a context collector
     */
    public function addCollector(ContextCollectorInterface $collector): void
    {
        $this->collectors[] = $collector;
        
        // Sort collectors by priority (highest first)
        usort($this->collectors, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    }

    /**
     * Remove a collector by name
     */
    public function removeCollector(string $name): void
    {
        $this->collectors = array_filter(
            $this->collectors,
            fn($collector) => $collector->getName() !== $name
        );
    }

    /**
     * Get all registered collectors
     * @return ContextCollectorInterface[]
     */
    public function getCollectors(): array
    {
        return $this->collectors;
    }

    /**
     * Collect context from all applicable collectors
     */
    public function collect(Throwable $exception): array
    {
        $startTime = microtime(true);
        $context = [];
        $errors = [];

        foreach ($this->collectors as $collector) {
            // Check if we've exceeded max execution time
            if ((microtime(true) - $startTime) > $this->maxExecutionTime) {
                $errors[] = [
                    'collector' => $collector->getName(),
                    'error' => 'Context collection timeout exceeded',
                    'max_time' => $this->maxExecutionTime
                ];
                break;
            }

            // Check if collector should run for this exception
            if (!$collector->shouldCollect($exception)) {
                continue;
            }

            try {
                $collectorStartTime = microtime(true);
                $collectorContext = $collector->collect($exception);
                $collectorEndTime = microtime(true);

                // Merge collector context
                $context = array_merge_recursive($context, $collectorContext);

                // Add collection metadata
                $context['_meta']['collectors'][$collector->getName()] = [
                    'executed' => true,
                    'execution_time' => round(($collectorEndTime - $collectorStartTime) * 1000, 2), // ms
                    'priority' => $collector->getPriority()
                ];

            } catch (Throwable $e) {
                $error = [
                    'collector' => $collector->getName(),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ];

                $errors[] = $error;

                // Add error metadata
                $context['_meta']['collectors'][$collector->getName()] = [
                    'executed' => false,
                    'error' => $error,
                    'priority' => $collector->getPriority()
                ];

                // Stop if we shouldn't continue on failure
                if (!$this->continueOnFailure) {
                    break;
                }
            }
        }

        // Add collection metadata
        $context['_meta']['collection'] = [
            'total_time' => round((microtime(true) - $startTime) * 1000, 2), // ms
            'collectors_count' => count($this->collectors),
            'executed_count' => count(array_filter(
                $context['_meta']['collectors'] ?? [],
                fn($meta) => $meta['executed'] ?? false
            )),
            'errors_count' => count($errors),
            'timestamp' => time()
        ];

        // Add errors if any occurred
        if (!empty($errors)) {
            $context['_meta']['errors'] = $errors;
        }

        return $context;
    }

    /**
     * Collect context with a specific timeout
     */
    public function collectWithTimeout(Throwable $exception, float $timeout): array
    {
        $originalTimeout = $this->maxExecutionTime;
        $this->maxExecutionTime = $timeout;
        
        try {
            return $this->collect($exception);
        } finally {
            $this->maxExecutionTime = $originalTimeout;
        }
    }

    /**
     * Collect context from specific collectors only
     */
    public function collectFrom(Throwable $exception, array $collectorNames): array
    {
        $originalCollectors = $this->collectors;
        
        // Filter collectors by name
        $this->collectors = array_filter(
            $this->collectors,
            fn($collector) => in_array($collector->getName(), $collectorNames, true)
        );
        
        try {
            return $this->collect($exception);
        } finally {
            $this->collectors = $originalCollectors;
        }
    }

    /**
     * Get a summary of available collectors
     */
    public function getCollectorsSummary(): array
    {
        $summary = [];
        
        foreach ($this->collectors as $collector) {
            $summary[] = [
                'name' => $collector->getName(),
                'priority' => $collector->getPriority(),
                'class' => get_class($collector)
            ];
        }
        
        return $summary;
    }

    /**
     * Set maximum execution time for context collection
     */
    public function setMaxExecutionTime(float $seconds): void
    {
        $this->maxExecutionTime = max(0.1, $seconds);
    }

    /**
     * Get maximum execution time
     */
    public function getMaxExecutionTime(): float
    {
        return $this->maxExecutionTime;
    }

    /**
     * Set whether to continue collecting if one collector fails
     */
    public function setContinueOnFailure(bool $continue): void
    {
        $this->continueOnFailure = $continue;
    }

    /**
     * Check if collection continues on failure
     */
    public function getContinueOnFailure(): bool
    {
        return $this->continueOnFailure;
    }

    /**
     * Clear all collectors
     */
    public function clearCollectors(): void
    {
        $this->collectors = [];
    }

    /**
     * Check if a collector is registered
     */
    public function hasCollector(string $name): bool
    {
        foreach ($this->collectors as $collector) {
            if ($collector->getName() === $name) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get a specific collector by name
     */
    public function getCollector(string $name): ?ContextCollectorInterface
    {
        foreach ($this->collectors as $collector) {
            if ($collector->getName() === $name) {
                return $collector;
            }
        }
        
        return null;
    }

    /**
     * Create a default context manager with standard collectors
     */
    public static function createDefault(): self
    {
        $manager = new self();
        
        // Add standard collectors
        $manager->addCollector(new EnvironmentCollector());
        $manager->addCollector(new RequestCollector());
        $manager->addCollector(new UserCollector());
        
        return $manager;
    }

    /**
     * Create a minimal context manager for CLI environments
     */
    public static function createMinimal(): self
    {
        $manager = new self();
        
        // Only add environment collector for CLI
        $manager->addCollector(new EnvironmentCollector());
        
        return $manager;
    }
}