<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Support;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DateInterval;
use DatePeriod;

/**
 * Date/time handling utilities
 * 
 * Provides enhanced date/time functionality built on top of PHP's DateTime.
 * 
 * @package LengthOfRope\TreeHouse\Support
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class Carbon extends DateTime
{
    /**
     * Default format for string conversion
     */
    public const DEFAULT_TO_STRING_FORMAT = 'Y-m-d H:i:s';

    /**
     * Format constants
     * Note: ATOM, COOKIE, ISO8601, etc. are inherited from DateTimeInterface
     */

    /**
     * Days of the week
     */
    public const SUNDAY = 0;
    public const MONDAY = 1;
    public const TUESDAY = 2;
    public const WEDNESDAY = 3;
    public const THURSDAY = 4;
    public const FRIDAY = 5;
    public const SATURDAY = 6;

    /**
     * Months of the year
     */
    public const JANUARY = 1;
    public const FEBRUARY = 2;
    public const MARCH = 3;
    public const APRIL = 4;
    public const MAY = 5;
    public const JUNE = 6;
    public const JULY = 7;
    public const AUGUST = 8;
    public const SEPTEMBER = 9;
    public const OCTOBER = 10;
    public const NOVEMBER = 11;
    public const DECEMBER = 12;

    /**
     * Mock time for testing purposes
     */
    private static ?Carbon $testNow = null;

    /**
     * Create a new Carbon instance
     * 
     * @param string|null $time
     * @param DateTimeZone|string|null $timezone
     */
    public function __construct(?string $time = null, DateTimeZone|string|null $timezone = null)
    {
        if (is_string($timezone)) {
            $timezone = new DateTimeZone($timezone);
        }

        parent::__construct($time ?? 'now', $timezone);
    }

    /**
     * Create a Carbon instance from a DateTime instance
     * 
     * @param DateTimeInterface $dateTime
     * @return static
     */
    public static function instance(DateTimeInterface $dateTime): static
    {
        return new static($dateTime->format('Y-m-d H:i:s.u'), $dateTime->getTimezone());
    }

    /**
     * Create a Carbon instance for the current date and time
     * 
     * @param DateTimeZone|string|null $timezone
     * @return static
     */
    public static function now(DateTimeZone|string|null $timezone = null): static
    {
        if (static::$testNow !== null) {
            $instance = clone static::$testNow;
            if ($timezone !== null) {
                $instance->setTimezone(is_string($timezone) ? new DateTimeZone($timezone) : $timezone);
            }
            return $instance;
        }
        
        return new static('now', $timezone);
    }

    /**
     * Create a Carbon instance for today
     * 
     * @param DateTimeZone|string|null $timezone
     * @return static
     */
    public static function today(DateTimeZone|string|null $timezone = null): static
    {
        return static::now($timezone)->startOfDay();
    }

    /**
     * Create a Carbon instance for tomorrow
     * 
     * @param DateTimeZone|string|null $timezone
     * @return static
     */
    public static function tomorrow(DateTimeZone|string|null $timezone = null): static
    {
        return static::today($timezone)->addDay();
    }

    /**
     * Create a Carbon instance for yesterday
     * 
     * @param DateTimeZone|string|null $timezone
     * @return static
     */
    public static function yesterday(DateTimeZone|string|null $timezone = null): static
    {
        return static::today($timezone)->subDay();
    }

    /**
     * Create a Carbon instance from a specific date
     * 
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $hour
     * @param int $minute
     * @param int $second
     * @param DateTimeZone|string|null $timezone
     * @return static
     */
    public static function create(
        int $year,
        int $month = 1,
        int $day = 1,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        DateTimeZone|string|null $timezone = null
    ): static {
        $date = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
        return new static($date, $timezone);
    }

    /**
     * Create a Carbon instance from a timestamp
     *
     * @param int|float $timestamp
     * @param DateTimeZone|null $timezone
     * @return static
     */
    public static function createFromTimestamp(int|float $timestamp, ?DateTimeZone $timezone = null): static
    {
        $instance = new static('now', $timezone);
        $instance->setTimestamp((int) $timestamp);
        return $instance;
    }

    /**
     * Create a Carbon instance from a format
     * 
     * @param string $format
     * @param string $time
     * @param DateTimeZone|string|null $timezone
     * @return static|false
     */
    public static function createFromFormat(string $format, string $time, DateTimeZone|string|null $timezone = null): static|false
    {
        if (is_string($timezone)) {
            $timezone = new DateTimeZone($timezone);
        }

        $dateTime = parent::createFromFormat($format, $time, $timezone);
        
        if ($dateTime === false) {
            return false;
        }

        return static::instance($dateTime);
    }

    /**
     * Parse a string into a Carbon instance
     * 
     * @param string $time
     * @param DateTimeZone|string|null $timezone
     * @return static
     */
    public static function parse(string $time, DateTimeZone|string|null $timezone = null): static
    {
        return new static($time, $timezone);
    }

    /**
     * Set the mock time for testing
     * 
     * @param Carbon|string|null $testNow
     * @return void
     */
    public static function setTestNow(Carbon|string|null $testNow): void
    {
        if (is_string($testNow)) {
            $testNow = new static($testNow);
        }
        
        static::$testNow = $testNow;
    }

    /**
     * Clear the mock time for testing
     * 
     * @return void
     */
    public static function clearTestNow(): void
    {
        static::$testNow = null;
    }

    /**
     * Check if we're currently using mock time
     * 
     * @return bool
     */
    public static function hasTestNow(): bool
    {
        return static::$testNow !== null;
    }

    /**
     * Add years to the instance
     * 
     * @param int $value
     * @return static
     */
    public function addYears(int $value): static
    {
        return $this->add(new DateInterval("P{$value}Y"));
    }

    /**
     * Add a year to the instance
     * 
     * @return static
     */
    public function addYear(): static
    {
        return $this->addYears(1);
    }

    /**
     * Subtract years from the instance
     * 
     * @param int $value
     * @return static
     */
    public function subYears(int $value): static
    {
        return $this->sub(new DateInterval("P{$value}Y"));
    }

    /**
     * Subtract a year from the instance
     * 
     * @return static
     */
    public function subYear(): static
    {
        return $this->subYears(1);
    }

    /**
     * Add months to the instance
     * 
     * @param int $value
     * @return static
     */
    public function addMonths(int $value): static
    {
        return $this->add(new DateInterval("P{$value}M"));
    }

    /**
     * Add a month to the instance
     * 
     * @return static
     */
    public function addMonth(): static
    {
        return $this->addMonths(1);
    }

    /**
     * Subtract months from the instance
     * 
     * @param int $value
     * @return static
     */
    public function subMonths(int $value): static
    {
        return $this->sub(new DateInterval("P{$value}M"));
    }

    /**
     * Subtract a month from the instance
     * 
     * @return static
     */
    public function subMonth(): static
    {
        return $this->subMonths(1);
    }

    /**
     * Add days to the instance
     * 
     * @param int $value
     * @return static
     */
    public function addDays(int $value): static
    {
        return $this->add(new DateInterval("P{$value}D"));
    }

    /**
     * Add a day to the instance
     * 
     * @return static
     */
    public function addDay(): static
    {
        return $this->addDays(1);
    }

    /**
     * Subtract days from the instance
     * 
     * @param int $value
     * @return static
     */
    public function subDays(int $value): static
    {
        return $this->sub(new DateInterval("P{$value}D"));
    }

    /**
     * Subtract a day from the instance
     * 
     * @return static
     */
    public function subDay(): static
    {
        return $this->subDays(1);
    }

    /**
     * Add hours to the instance
     * 
     * @param int $value
     * @return static
     */
    public function addHours(int $value): static
    {
        return $this->add(new DateInterval("PT{$value}H"));
    }

    /**
     * Add an hour to the instance
     * 
     * @return static
     */
    public function addHour(): static
    {
        return $this->addHours(1);
    }

    /**
     * Subtract hours from the instance
     * 
     * @param int $value
     * @return static
     */
    public function subHours(int $value): static
    {
        return $this->sub(new DateInterval("PT{$value}H"));
    }

    /**
     * Subtract an hour from the instance
     * 
     * @return static
     */
    public function subHour(): static
    {
        return $this->subHours(1);
    }

    /**
     * Add minutes to the instance
     * 
     * @param int $value
     * @return static
     */
    public function addMinutes(int $value): static
    {
        return $this->add(new DateInterval("PT{$value}M"));
    }

    /**
     * Add a minute to the instance
     * 
     * @return static
     */
    public function addMinute(): static
    {
        return $this->addMinutes(1);
    }

    /**
     * Subtract minutes from the instance
     * 
     * @param int $value
     * @return static
     */
    public function subMinutes(int $value): static
    {
        return $this->sub(new DateInterval("PT{$value}M"));
    }

    /**
     * Subtract a minute from the instance
     * 
     * @return static
     */
    public function subMinute(): static
    {
        return $this->subMinutes(1);
    }

    /**
     * Add seconds to the instance
     * 
     * @param int $value
     * @return static
     */
    public function addSeconds(int $value): static
    {
        return $this->add(new DateInterval("PT{$value}S"));
    }

    /**
     * Add a second to the instance
     * 
     * @return static
     */
    public function addSecond(): static
    {
        return $this->addSeconds(1);
    }

    /**
     * Subtract seconds from the instance
     * 
     * @param int $value
     * @return static
     */
    public function subSeconds(int $value): static
    {
        return $this->sub(new DateInterval("PT{$value}S"));
    }

    /**
     * Subtract a second from the instance
     * 
     * @return static
     */
    public function subSecond(): static
    {
        return $this->subSeconds(1);
    }

    /**
     * Set the time to the start of the day
     * 
     * @return static
     */
    public function startOfDay(): static
    {
        return $this->setTime(0, 0, 0, 0);
    }

    /**
     * Set the time to the end of the day
     * 
     * @return static
     */
    public function endOfDay(): static
    {
        return $this->setTime(23, 59, 59, 999999);
    }

    /**
     * Set the date to the start of the month
     * 
     * @return static
     */
    public function startOfMonth(): static
    {
        return $this->setDate((int) $this->format('Y'), (int) $this->format('n'), 1)->startOfDay();
    }

    /**
     * Set the date to the end of the month
     * 
     * @return static
     */
    public function endOfMonth(): static
    {
        return $this->setDate((int) $this->format('Y'), (int) $this->format('n'), (int) $this->format('t'))->endOfDay();
    }

    /**
     * Set the date to the start of the year
     * 
     * @return static
     */
    public function startOfYear(): static
    {
        return $this->setDate((int) $this->format('Y'), 1, 1)->startOfDay();
    }

    /**
     * Set the date to the end of the year
     * 
     * @return static
     */
    public function endOfYear(): static
    {
        return $this->setDate((int) $this->format('Y'), 12, 31)->endOfDay();
    }

    /**
     * Determine if the instance is in the past
     * 
     * @return bool
     */
    public function isPast(): bool
    {
        return $this < static::now($this->getTimezone());
    }

    /**
     * Determine if the instance is in the future
     * 
     * @return bool
     */
    public function isFuture(): bool
    {
        return $this > static::now($this->getTimezone());
    }

    /**
     * Determine if the instance is today
     * 
     * @return bool
     */
    public function isToday(): bool
    {
        return $this->format('Y-m-d') === static::now($this->getTimezone())->format('Y-m-d');
    }

    /**
     * Determine if the instance is tomorrow
     * 
     * @return bool
     */
    public function isTomorrow(): bool
    {
        return $this->format('Y-m-d') === static::tomorrow($this->getTimezone())->format('Y-m-d');
    }

    /**
     * Determine if the instance is yesterday
     * 
     * @return bool
     */
    public function isYesterday(): bool
    {
        return $this->format('Y-m-d') === static::yesterday($this->getTimezone())->format('Y-m-d');
    }

    /**
     * Determine if the instance is a weekend day
     * 
     * @return bool
     */
    public function isWeekend(): bool
    {
        return in_array((int) $this->format('w'), [static::SATURDAY, static::SUNDAY]);
    }

    /**
     * Determine if the instance is a weekday
     * 
     * @return bool
     */
    public function isWeekday(): bool
    {
        return !$this->isWeekend();
    }

    /**
     * Get the difference in years
     * 
     * @param DateTimeInterface|null $dt
     * @param bool $abs
     * @return int
     */
    public function diffInYears(?DateTimeInterface $dt = null, bool $abs = true): int
    {
        $dt = $dt ?: static::now($this->getTimezone());
        return (int) $this->diff($dt, $abs)->format('%r%y');
    }

    /**
     * Get the difference in months
     * 
     * @param DateTimeInterface|null $dt
     * @param bool $abs
     * @return int
     */
    public function diffInMonths(?DateTimeInterface $dt = null, bool $abs = true): int
    {
        $dt = $dt ?: static::now($this->getTimezone());
        $diff = $this->diff($dt, $abs);
        return (int) $diff->format('%r%y') * 12 + (int) $diff->format('%r%m');
    }

    /**
     * Get the difference in days
     * 
     * @param DateTimeInterface|null $dt
     * @param bool $abs
     * @return int
     */
    public function diffInDays(?DateTimeInterface $dt = null, bool $abs = true): int
    {
        $dt = $dt ?: static::now($this->getTimezone());
        return (int) $this->diff($dt, $abs)->format('%r%a');
    }

    /**
     * Get the difference in hours
     * 
     * @param DateTimeInterface|null $dt
     * @param bool $abs
     * @return int
     */
    public function diffInHours(?DateTimeInterface $dt = null, bool $abs = true): int
    {
        return (int) ($this->diffInSeconds($dt, $abs) / 3600);
    }

    /**
     * Get the difference in minutes
     * 
     * @param DateTimeInterface|null $dt
     * @param bool $abs
     * @return int
     */
    public function diffInMinutes(?DateTimeInterface $dt = null, bool $abs = true): int
    {
        return (int) ($this->diffInSeconds($dt, $abs) / 60);
    }

    /**
     * Get the difference in seconds
     * 
     * @param DateTimeInterface|null $dt
     * @param bool $abs
     * @return int
     */
    public function diffInSeconds(?DateTimeInterface $dt = null, bool $abs = true): int
    {
        $dt = $dt ?: static::now($this->getTimezone());
        $diff = $this->getTimestamp() - $dt->getTimestamp();
        return $abs ? abs($diff) : $diff;
    }

    /**
     * Get a human readable difference
     * 
     * @param DateTimeInterface|null $other
     * @param bool $absolute
     * @return string
     */
    public function diffForHumans(?DateTimeInterface $other = null, bool $absolute = false): string
    {
        $isNow = $other === null;
        $other = $other ?: static::now($this->getTimezone());
        
        $diff = $this->diffInSeconds($other, false);
        $future = $diff > 0;
        $diff = abs($diff);

        if ($diff < 60) {
            $unit = 'second';
            $count = $diff;
        } elseif ($diff < 3600) {
            $unit = 'minute';
            $count = (int) ($diff / 60);
        } elseif ($diff < 86400) {
            $unit = 'hour';
            $count = (int) ($diff / 3600);
        } elseif ($diff < 2592000) {
            $unit = 'day';
            $count = (int) ($diff / 86400);
        } elseif ($diff < 31536000) {
            $unit = 'month';
            $count = $this->diffInMonths($other, true);
        } else {
            $unit = 'year';
            $count = $this->diffInYears($other, true);
        }

        if ($count !== 1) {
            $unit .= 's';
        }

        if ($absolute) {
            return "{$count} {$unit}";
        }

        if ($isNow) {
            return $future ? "in {$count} {$unit}" : "{$count} {$unit} ago";
        }

        return $future ? "{$count} {$unit} after" : "{$count} {$unit} before";
    }

    /**
     * Format the instance as a string
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->format(static::DEFAULT_TO_STRING_FORMAT);
    }

    /**
     * Convert the instance to an array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'year' => (int) $this->format('Y'),
            'month' => (int) $this->format('n'),
            'day' => (int) $this->format('j'),
            'dayOfWeek' => (int) $this->format('w'),
            'dayOfYear' => (int) $this->format('z'),
            'hour' => (int) $this->format('G'),
            'minute' => (int) $this->format('i'),
            'second' => (int) $this->format('s'),
            'micro' => (int) $this->format('u'),
            'timestamp' => $this->getTimestamp(),
            'formatted' => $this->format(static::DEFAULT_TO_STRING_FORMAT),
            'timezone' => $this->getTimezone()->getName(),
        ];
    }

    /**
     * Convert the instance to JSON
     * 
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->format(static::ATOM), JSON_THROW_ON_ERROR);
    }

    /**
     * Get the age in years
     * 
     * @param DateTimeInterface|null $other
     * @return int
     */
    public function age(?DateTimeInterface $other = null): int
    {
        return $this->diffInYears($other);
    }

    /**
     * Create a copy of the instance
     * 
     * @return static
     */
    public function copy(): static
    {
        return clone $this;
    }

    /**
     * Clone the instance
     * 
     * @return static
     */
    public function clone(): static
    {
        return $this->copy();
    }
}