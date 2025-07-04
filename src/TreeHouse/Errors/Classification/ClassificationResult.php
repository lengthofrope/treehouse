<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Classification;

/**
 * Result of exception classification containing all determined properties
 */
class ClassificationResult
{
    public function __construct(
        public readonly string $category,
        public readonly string $severity,
        public readonly bool $shouldReport,
        public readonly string $logLevel,
        public readonly bool $isSecurity,
        public readonly bool $isCritical,
        public readonly array $tags,
        public readonly array $metadata
    ) {}

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'severity' => $this->severity,
            'should_report' => $this->shouldReport,
            'log_level' => $this->logLevel,
            'is_security' => $this->isSecurity,
            'is_critical' => $this->isCritical,
            'tags' => $this->tags,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Convert to JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Check if this is a high-priority issue
     */
    public function isHighPriority(): bool
    {
        return $this->isCritical || 
               $this->isSecurity || 
               $this->severity === ExceptionClassifier::SEVERITY_HIGH ||
               $this->severity === ExceptionClassifier::SEVERITY_CRITICAL;
    }

    /**
     * Check if this should be escalated to administrators
     */
    public function shouldEscalate(): bool
    {
        return $this->isCritical || 
               ($this->isSecurity && $this->severity !== ExceptionClassifier::SEVERITY_LOW);
    }

    /**
     * Get a human-readable summary
     */
    public function getSummary(): string
    {
        $parts = [
            ucfirst($this->severity),
            $this->category,
            'exception'
        ];

        if ($this->isSecurity) {
            $parts[] = '(security)';
        }

        if ($this->isCritical) {
            $parts[] = '(critical)';
        }

        return implode(' ', $parts);
    }

    /**
     * Check if exception has a specific tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * Get tags by prefix
     */
    public function getTagsByPrefix(string $prefix): array
    {
        return array_values(array_filter($this->tags, fn($tag) => str_starts_with($tag, $prefix . ':')));
    }

    /**
     * Get metadata value by key
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}