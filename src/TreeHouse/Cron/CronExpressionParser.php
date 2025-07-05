<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron;

use LengthOfRope\TreeHouse\Cron\Exceptions\CronException;

/**
 * Cron Expression Parser
 * 
 * Parses and evaluates cron expressions to determine when jobs should run.
 * Supports standard cron syntax with 5 fields: minute hour day month weekday.
 * 
 * @package LengthOfRope\TreeHouse\Cron
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class CronExpressionParser
{
    /**
     * Field names in order
     */
    private const FIELDS = ['minute', 'hour', 'day', 'month', 'weekday'];

    /**
     * Field ranges
     */
    private const RANGES = [
        'minute' => [0, 59],
        'hour' => [0, 23], 
        'day' => [1, 31],
        'month' => [1, 12],
        'weekday' => [0, 7], // 0 and 7 both represent Sunday
    ];

    /**
     * Month name mappings
     */
    private const MONTH_NAMES = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
        'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    /**
     * Weekday name mappings
     */
    private const WEEKDAY_NAMES = [
        'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3,
        'thu' => 4, 'fri' => 5, 'sat' => 6,
    ];

    /**
     * Parsed cron expression
     *
     * @var array<string, array<int>>
     */
    private array $parsedExpression = [];

    /**
     * Original cron expression
     */
    private string $expression = '';

    /**
     * Create a new cron expression parser
     *
     * @param string $expression Cron expression
     * @throws CronException If expression is invalid
     */
    public function __construct(string $expression)
    {
        $this->expression = trim($expression);
        $this->parsedExpression = $this->parseExpression($this->expression);
    }

    /**
     * Check if the cron should run at the given time
     *
     * @param int|null $timestamp Unix timestamp (null for current time)
     * @return bool True if cron should run
     */
    public function isDue(?int $timestamp = null): bool
    {
        $timestamp = $timestamp ?? time();
        
        $minute = (int) date('i', $timestamp);
        $hour = (int) date('G', $timestamp);
        $day = (int) date('j', $timestamp);
        $month = (int) date('n', $timestamp);
        $weekday = (int) date('w', $timestamp);

        return $this->matchesField('minute', $minute) &&
               $this->matchesField('hour', $hour) &&
               $this->matchesField('day', $day) &&
               $this->matchesField('month', $month) &&
               $this->matchesField('weekday', $weekday);
    }

    /**
     * Get the next run time after the given timestamp
     *
     * @param int|null $afterTimestamp Unix timestamp to start from (null for current time)
     * @param int $maxIterations Maximum iterations to prevent infinite loops
     * @return int|null Next run timestamp or null if none found
     */
    public function getNextRunTime(?int $afterTimestamp = null, int $maxIterations = 366 * 24 * 60): ?int
    {
        $timestamp = $afterTimestamp ?? time();
        
        // Start from the next minute
        $timestamp = $timestamp + 60 - ($timestamp % 60);
        
        $iterations = 0;
        while ($iterations < $maxIterations) {
            if ($this->isDue($timestamp)) {
                return $timestamp;
            }
            
            $timestamp += 60; // Check next minute
            $iterations++;
        }
        
        return null; // No valid time found within reasonable range
    }

    /**
     * Get the previous run time before the given timestamp
     *
     * @param int|null $beforeTimestamp Unix timestamp to start from (null for current time)
     * @param int $maxIterations Maximum iterations to prevent infinite loops
     * @return int|null Previous run timestamp or null if none found
     */
    public function getPreviousRunTime(?int $beforeTimestamp = null, int $maxIterations = 366 * 24 * 60): ?int
    {
        $timestamp = $beforeTimestamp ?? time();
        
        // Start from the previous minute
        $timestamp = $timestamp - ($timestamp % 60) - 60;
        
        $iterations = 0;
        while ($iterations < $maxIterations) {
            if ($this->isDue($timestamp)) {
                return $timestamp;
            }
            
            $timestamp -= 60; // Check previous minute
            $iterations++;
        }
        
        return null; // No valid time found within reasonable range
    }

    /**
     * Get multiple upcoming run times
     *
     * @param int $count Number of upcoming times to get
     * @param int|null $afterTimestamp Unix timestamp to start from
     * @return array<int> Array of timestamps
     */
    public function getUpcomingRunTimes(int $count = 5, ?int $afterTimestamp = null): array
    {
        $times = [];
        $timestamp = $afterTimestamp ?? time();
        
        for ($i = 0; $i < $count; $i++) {
            $nextTime = $this->getNextRunTime($timestamp);
            if ($nextTime === null) {
                break;
            }
            
            $times[] = $nextTime;
            $timestamp = $nextTime;
        }
        
        return $times;
    }

    /**
     * Get the original cron expression
     *
     * @return string Cron expression
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get parsed expression
     *
     * @return array<string, array<int>> Parsed cron fields
     */
    public function getParsedExpression(): array
    {
        return $this->parsedExpression;
    }

    /**
     * Get human-readable description of the cron expression
     *
     * @return string Human-readable description
     */
    public function getDescription(): string
    {
        $parts = explode(' ', $this->expression);
        
        // Handle special cases
        if ($this->expression === '* * * * *') {
            return 'Every minute';
        }
        
        if ($this->expression === '0 * * * *') {
            return 'Every hour';
        }
        
        if ($this->expression === '0 0 * * *') {
            return 'Daily at midnight';
        }
        
        if ($this->expression === '0 0 * * 0') {
            return 'Weekly on Sunday at midnight';
        }
        
        if ($this->expression === '0 0 1 * *') {
            return 'Monthly on the 1st at midnight';
        }
        
        // Build description from parts
        $description = '';
        
        // Minute
        if ($parts[0] !== '*') {
            $description .= "At minute {$parts[0]}";
        }
        
        // Hour
        if ($parts[1] !== '*') {
            $hour = (int) $parts[1];
            $time = sprintf('%02d:00', $hour);
            $description = $description ? str_replace('At minute', "At $time", $description) : "At $time";
        }
        
        // Day
        if ($parts[2] !== '*') {
            $description .= " on day {$parts[2]} of the month";
        }
        
        // Month
        if ($parts[3] !== '*') {
            $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                          'July', 'August', 'September', 'October', 'November', 'December'];
            $month = (int) $parts[3];
            $description .= " in {$monthNames[$month]}";
        }
        
        // Weekday
        if ($parts[4] !== '*') {
            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $weekday = (int) $parts[4];
            if ($weekday === 7) $weekday = 0; // Convert 7 to 0 for Sunday
            $description .= " on {$dayNames[$weekday]}";
        }
        
        return $description ?: 'Custom schedule';
    }

    /**
     * Parse a cron expression into its components
     *
     * @param string $expression Cron expression
     * @return array<string, array<int>> Parsed fields
     * @throws CronException If expression is invalid
     */
    private function parseExpression(string $expression): array
    {
        $parts = preg_split('/\s+/', $expression);
        
        if (count($parts) !== 5) {
            throw CronException::invalidCronExpression(
                $expression,
                'Expression must have exactly 5 fields (minute hour day month weekday)'
            );
        }
        
        $parsed = [];
        
        foreach (self::FIELDS as $index => $field) {
            $parsed[$field] = $this->parseField($parts[$index], $field);
        }
        
        return $parsed;
    }

    /**
     * Parse a single cron field
     *
     * @param string $value Field value
     * @param string $field Field name
     * @return array<int> Valid values for this field
     * @throws CronException If field is invalid
     */
    private function parseField(string $value, string $field): array
    {
        $range = self::RANGES[$field];
        
        // Handle wildcard
        if ($value === '*') {
            return range($range[0], $range[1]);
        }
        
        $values = [];
        
        // Handle comma-separated values
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            
            // Handle ranges (e.g., 1-5)
            if (strpos($part, '-') !== false) {
                $values = array_merge($values, $this->parseRange($part, $field));
            }
            // Handle step values (e.g., */5, 0-30/10)
            elseif (strpos($part, '/') !== false) {
                $values = array_merge($values, $this->parseStep($part, $field));
            }
            // Handle single values
            else {
                $values[] = $this->parseValue($part, $field);
            }
        }
        
        // Remove duplicates and sort
        $values = array_unique($values);
        sort($values);
        
        return $values;
    }

    /**
     * Parse a range (e.g., 1-5)
     *
     * @param string $range Range string
     * @param string $field Field name
     * @return array<int> Range values
     * @throws CronException If range is invalid
     */
    private function parseRange(string $range, string $field): array
    {
        $parts = explode('-', $range, 2);
        
        if (count($parts) !== 2) {
            throw CronException::invalidCronExpression(
                $this->expression,
                "Invalid range format: $range"
            );
        }
        
        $start = $this->parseValue($parts[0], $field);
        $end = $this->parseValue($parts[1], $field);
        
        if ($start > $end) {
            throw CronException::invalidCronExpression(
                $this->expression,
                "Range start ($start) cannot be greater than end ($end)"
            );
        }
        
        return range($start, $end);
    }

    /**
     * Parse a step value (e.g., star/5, 0-30/10)
     *
     * @param string $step Step string
     * @param string $field Field name
     * @return array<int> Step values
     * @throws CronException If step is invalid
     */
    private function parseStep(string $step, string $field): array
    {
        $parts = explode('/', $step, 2);
        
        if (count($parts) !== 2) {
            throw CronException::invalidCronExpression(
                $this->expression,
                "Invalid step format: $step"
            );
        }
        
        $stepValue = (int) $parts[1];
        if ($stepValue <= 0) {
            throw CronException::invalidCronExpression(
                $this->expression,
                "Step value must be positive: $stepValue"
            );
        }
        
        // Get base range
        if ($parts[0] === '*') {
            $range = self::RANGES[$field];
            $baseValues = range($range[0], $range[1]);
        } else {
            $baseValues = $this->parseField($parts[0], $field);
        }
        
        // Apply step
        $values = [];
        $start = min($baseValues);
        $end = max($baseValues);
        
        for ($i = $start; $i <= $end; $i += $stepValue) {
            if (in_array($i, $baseValues)) {
                $values[] = $i;
            }
        }
        
        return $values;
    }

    /**
     * Parse a single value
     *
     * @param string $value Value string
     * @param string $field Field name
     * @return int Parsed value
     * @throws CronException If value is invalid
     */
    private function parseValue(string $value, string $field): int
    {
        // Handle named values
        if (!is_numeric($value)) {
            $value = strtolower($value);
            
            if ($field === 'month' && isset(self::MONTH_NAMES[$value])) {
                $numericValue = self::MONTH_NAMES[$value];
            } elseif ($field === 'weekday' && isset(self::WEEKDAY_NAMES[$value])) {
                $numericValue = self::WEEKDAY_NAMES[$value];
            } else {
                throw CronException::invalidCronExpression(
                    $this->expression,
                    "Invalid named value for $field: $value"
                );
            }
        } else {
            $numericValue = (int) $value;
        }
        
        // Validate range
        $range = self::RANGES[$field];
        
        // Special case for weekday: 7 should be converted to 0 (Sunday)
        if ($field === 'weekday' && $numericValue === 7) {
            $numericValue = 0;
        }
        
        if ($numericValue < $range[0] || $numericValue > $range[1]) {
            throw CronException::invalidCronExpression(
                $this->expression,
                "Value $numericValue out of range for $field ({$range[0]}-{$range[1]})"
            );
        }
        
        return $numericValue;
    }

    /**
     * Check if a value matches a field
     *
     * @param string $field Field name
     * @param int $value Value to check
     * @return bool True if value matches
     */
    private function matchesField(string $field, int $value): bool
    {
        // Special case for weekday: both 0 and 7 represent Sunday
        if ($field === 'weekday' && $value === 0) {
            return in_array(0, $this->parsedExpression[$field]) || in_array(7, $this->parsedExpression[$field]);
        }
        
        return in_array($value, $this->parsedExpression[$field]);
    }
}