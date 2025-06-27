<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Support;

/**
 * String utilities
 * 
 * Provides utility functions for working with strings.
 * 
 * @package LengthOfRope\TreeHouse\Support
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class Str
{
    /**
     * The cache of snake-cased words
     * 
     * @var array<string, string>
     */
    protected static array $snakeCache = [];

    /**
     * The cache of camel-cased words
     * 
     * @var array<string, string>
     */
    protected static array $camelCache = [];

    /**
     * The cache of studly-cased words
     * 
     * @var array<string, string>
     */
    protected static array $studlyCache = [];

    /**
     * Return the remainder of a string after the first occurrence of a given value
     * 
     * @param string $subject
     * @param string $search
     * @return string
     */
    public static function after(string $subject, string $search): string
    {
        return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    /**
     * Return the remainder of a string after the last occurrence of a given value
     * 
     * @param string $subject
     * @param string $search
     * @return string
     */
    public static function afterLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, $position + strlen($search));
    }

    /**
     * Transliterate a UTF-8 value to ASCII
     * 
     * @param string $value
     * @param string $language
     * @return string
     */
    public static function ascii(string $value, string $language = 'en'): string
    {
        $languageSpecific = static::languageSpecificCharsArray($language);

        if (!is_null($languageSpecific)) {
            $value = str_replace($languageSpecific[0], $languageSpecific[1], $value);
        }

        return preg_replace('/[^\x20-\x7E]/u', '', $value);
    }

    /**
     * Get the portion of a string before the first occurrence of a given value
     * 
     * @param string $subject
     * @param string $search
     * @return string
     */
    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $result = strstr($subject, $search, true);

        return $result === false ? $subject : $result;
    }

    /**
     * Get the portion of a string before the last occurrence of a given value
     * 
     * @param string $subject
     * @param string $search
     * @return string
     */
    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strrpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return static::substr($subject, 0, $pos);
    }

    /**
     * Get the portion of a string between two given values
     * 
     * @param string $subject
     * @param string $from
     * @param string $to
     * @return string
     */
    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return static::beforeLast(static::after($subject, $from), $to);
    }

    /**
     * Get the smallest possible portion of a string between two given values
     * 
     * @param string $subject
     * @param string $from
     * @param string $to
     * @return string
     */
    public static function betweenFirst(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return static::before(static::after($subject, $from), $to);
    }

    /**
     * Convert a value to camel case
     * 
     * @param string $value
     * @return string
     */
    public static function camel(string $value): string
    {
        if (isset(static::$camelCache[$value])) {
            return static::$camelCache[$value];
        }

        return static::$camelCache[$value] = lcfirst(static::studly($value));
    }

    /**
     * Determine if a given string contains a given substring
     * 
     * @param string $haystack
     * @param string|iterable<string> $needles
     * @param bool $ignoreCase
     * @return bool
     */
    public static function contains(string $haystack, string|iterable $needles, bool $ignoreCase = false): bool
    {
        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }

        if (!is_iterable($needles)) {
            $needles = [$needles];
        }

        foreach ($needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string contains all array values
     * 
     * @param string $haystack
     * @param iterable<string> $needles
     * @param bool $ignoreCase
     * @return bool
     */
    public static function containsAll(string $haystack, iterable $needles, bool $ignoreCase = false): bool
    {
        foreach ($needles as $needle) {
            if (!static::contains($haystack, $needle, $ignoreCase)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if a given string ends with a given substring
     * 
     * @param string $haystack
     * @param string|iterable<string> $needles
     * @return bool
     */
    public static function endsWith(string $haystack, string|iterable $needles): bool
    {
        if (!is_iterable($needles)) {
            $needles = [$needles];
        }

        foreach ($needles as $needle) {
            if ((string) $needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cap a string with a single instance of a given value
     * 
     * @param string $value
     * @param string $cap
     * @return string
     */
    public static function finish(string $value, string $cap): string
    {
        $quoted = preg_quote($cap, '/');

        return preg_replace('/(?:' . $quoted . ')+$/u', '', $value) . $cap;
    }

    /**
     * Determine if a given string matches a given pattern
     * 
     * @param string|iterable<string> $pattern
     * @param string $value
     * @return bool
     */
    public static function is(string|iterable $pattern, string $value): bool
    {
        $patterns = is_iterable($pattern) ? $pattern : [$pattern];

        if (empty($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $pattern = (string) $pattern;

            // If the given value is an exact match we can of course return true right
            // from the beginning. Otherwise, we will translate asterisks and do an
            // actual pattern match against the two strings to see if they match.
            if ($pattern === $value) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');

            // Asterisks are translated into zero-or-more regular expression wildcards
            // to make it convenient to check if the strings starts with the given
            // pattern such as "library/*", making any string check convenient.
            $pattern = str_replace('\*', '.*', $pattern);

            if (preg_match('#^' . $pattern . '\z#u', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string is 7 bit ASCII
     * 
     * @param string $value
     * @return bool
     */
    public static function isAscii(string $value): bool
    {
        return mb_check_encoding($value, 'ASCII');
    }

    /**
     * Determine if a given string is valid JSON
     * 
     * @param string $value
     * @return bool
     */
    public static function isJson(string $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        try {
            json_decode($value, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a given string is a valid UUID
     * 
     * @param string $value
     * @return bool
     */
    public static function isUuid(string $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/iD', $value) > 0;
    }

    /**
     * Convert a string to kebab case
     * 
     * @param string $value
     * @return string
     */
    public static function kebab(string $value): string
    {
        return static::snake($value, '-');
    }

    /**
     * Return the length of the given string
     * 
     * @param string $value
     * @param string|null $encoding
     * @return int
     */
    public static function length(string $value, ?string $encoding = null): int
    {
        return mb_strlen($value, $encoding);
    }

    /**
     * Limit the number of characters in a string
     * 
     * @param string $value
     * @param int $limit
     * @param string $end
     * @return string
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    /**
     * Convert the given string to lower-case
     * 
     * @param string $value
     * @return string
     */
    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Limit the number of words in a string
     * 
     * @param string $value
     * @param int $words
     * @param string $end
     * @return string
     */
    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || static::length($value) === static::length($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Masks a portion of a string with a repeated character
     * 
     * @param string $string
     * @param string $character
     * @param int $index
     * @param int|null $length
     * @param string $encoding
     * @return string
     */
    public static function mask(string $string, string $character, int $index, ?int $length = null, string $encoding = 'UTF-8'): string
    {
        if ($character === '') {
            return $string;
        }

        $segment = mb_substr($string, $index, $length, $encoding);

        if ($segment === '') {
            return $string;
        }

        $strlen = mb_strlen($string, $encoding);
        $startIndex = $index;

        if ($index < 0) {
            $startIndex = $index < -$strlen ? 0 : $strlen + $index;
        }

        $start = mb_substr($string, 0, $startIndex, $encoding);
        $segmentLen = mb_strlen($segment, $encoding);
        $end = mb_substr($string, $startIndex + $segmentLen);

        return $start . str_repeat(mb_substr($character, 0, 1, $encoding), $segmentLen) . $end;
    }

    /**
     * Get the string matching the given pattern
     * 
     * @param string $pattern
     * @param string $subject
     * @return string
     */
    public static function match(string $pattern, string $subject): string
    {
        preg_match($pattern, $subject, $matches);

        if (!$matches) {
            return '';
        }

        return $matches[1] ?? $matches[0];
    }

    /**
     * Get the string matching the given pattern
     * 
     * @param string $pattern
     * @param string $subject
     * @return Collection<int, string>
     */
    public static function matchAll(string $pattern, string $subject): Collection
    {
        preg_match_all($pattern, $subject, $matches);

        if (empty($matches[0])) {
            return Collection::make();
        }

        return Collection::make($matches[1] ?? $matches[0]);
    }

    /**
     * Determine if a string matches a given pattern
     * 
     * @param string|iterable<string> $pattern
     * @param string $value
     * @return bool
     */
    public static function isMatch(string|iterable $pattern, string $value): bool
    {
        $value = (string) $value;

        if (!is_iterable($pattern)) {
            $pattern = [$pattern];
        }

        foreach ($pattern as $p) {
            $p = (string) $p;

            if (preg_match($p, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pad both sides of a string with another
     * 
     * @param string $value
     * @param int $length
     * @param string $pad
     * @return string
     */
    public static function padBoth(string $value, int $length, string $pad = ' '): string
    {
        $short = max(0, $length - mb_strlen($value));
        $shortLeft = intval(floor($short / 2));
        $shortRight = intval(ceil($short / 2));

        return mb_substr(str_repeat($pad, $shortLeft), 0, $shortLeft) .
               $value .
               mb_substr(str_repeat($pad, $shortRight), 0, $shortRight);
    }

    /**
     * Pad the left side of a string with another
     * 
     * @param string $value
     * @param int $length
     * @param string $pad
     * @return string
     */
    public static function padLeft(string $value, int $length, string $pad = ' '): string
    {
        $short = max(0, $length - mb_strlen($value));

        return mb_substr(str_repeat($pad, $short), 0, $short) . $value;
    }

    /**
     * Pad the right side of a string with another
     * 
     * @param string $value
     * @param int $length
     * @param string $pad
     * @return string
     */
    public static function padRight(string $value, int $length, string $pad = ' '): string
    {
        $short = max(0, $length - mb_strlen($value));

        return $value . mb_substr(str_repeat($pad, $short), 0, $short);
    }

    /**
     * Parse a Class[@]method style callback into class and method
     * 
     * @param string $callback
     * @param string|null $default
     * @return array<int, string|null>
     */
    public static function parseCallback(string $callback, ?string $default = null): array
    {
        return static::contains($callback, '@') ? explode('@', $callback, 2) : [$callback, $default];
    }

    /**
     * Get the plural form of an English word
     * 
     * @param string $value
     * @param int|array|\Countable $count
     * @return string
     */
    public static function plural(string $value, int|array|\Countable $count = 2): string
    {
        if (is_countable($count)) {
            $count = count($count);
        }

        if ((int) abs($count) === 1 || preg_match('/^(.*)[A-Za-z0-9\x{0080}-\x{FFFF}]$/u', $value) == 0) {
            return $value;
        }

        $plural = static::pluralize($value);

        return static::matchCase($plural, $value);
    }

    /**
     * Pluralize the last word of an English, studly caps case string
     * 
     * @param string $value
     * @param int|array|\Countable $count
     * @return string
     */
    public static function pluralStudly(string $value, int|array|\Countable $count = 2): string
    {
        $parts = preg_split('/(.)(?=[A-Z])/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        $lastWord = array_pop($parts);

        return implode('', $parts) . static::plural($lastWord, $count);
    }

    /**
     * Generate a more truly "random" alpha-numeric string
     * 
     * @param int $length
     * @return string
     */
    public static function random(int $length = 16): string
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;

            $bytes = random_bytes($size);

            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    /**
     * Repeat the given string
     * 
     * @param string $string
     * @param int $times
     * @return string
     */
    public static function repeat(string $string, int $times): string
    {
        return str_repeat($string, $times);
    }

    /**
     * Replace a given value in the string sequentially with an array
     * 
     * @param string $search
     * @param iterable<string> $replace
     * @param string $subject
     * @return string
     */
    public static function replaceArray(string $search, iterable $replace, string $subject): string
    {
        $segments = explode($search, $subject);

        $result = array_shift($segments);

        foreach ($segments as $segment) {
            $result .= (current($replace) ?: $search) . $segment;
            next($replace);
        }

        return $result;
    }

    /**
     * Replace the first occurrence of a given value in the string
     * 
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     */
    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Replace the last occurrence of a given value in the string
     * 
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     */
    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Remove any occurrence of the given string in the subject
     * 
     * @param string|iterable<string> $search
     * @param string $subject
     * @param bool $caseSensitive
     * @return string
     */
    public static function remove(string|iterable $search, string $subject, bool $caseSensitive = true): string
    {
        if (!is_iterable($search)) {
            $search = [$search];
        }

        foreach ($search as $s) {
            $subject = $caseSensitive
                ? str_replace($s, '', $subject)
                : str_ireplace($s, '', $subject);
        }

        return $subject;
    }

    /**
     * Reverse the given string
     * 
     * @param string $value
     * @return string
     */
    public static function reverse(string $value): string
    {
        return implode(array_reverse(mb_str_split($value)));
    }

    /**
     * Begin a string with a single instance of a given value
     * 
     * @param string $value
     * @param string $prefix
     * @return string
     */
    public static function start(string $value, string $prefix): string
    {
        $quoted = preg_quote($prefix, '/');

        return $prefix . preg_replace('/^(?:' . $quoted . ')+/u', '', $value);
    }

    /**
     * Convert the given string to upper-case
     * 
     * @param string $value
     * @return string
     */
    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Convert the given string to title case
     * 
     * @param string $value
     * @return string
     */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Get the singular form of an English word
     * 
     * @param string $value
     * @return string
     */
    public static function singular(string $value): string
    {
        $singular = static::singularize($value);

        return static::matchCase($singular, $value);
    }

    /**
     * Generate a URL friendly "slug" from a given string
     * 
     * @param string $title
     * @param string $separator
     * @param string|null $language
     * @return string
     */
    public static function slug(string $title, string $separator = '-', ?string $language = 'en'): string
    {
        $title = $language ? static::ascii($title, $language) : $title;

        // Convert all dashes/underscores into separator
        $flip = $separator === '-' ? '_' : '-';

        $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);

        // Replace @ with the word 'at'
        $title = str_replace('@', $separator . 'at' . $separator, $title);

        // Remove all characters that are not the separator, letters, numbers, or whitespace
        $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', static::lower($title));

        // Replace all separator characters and whitespace by a single separator
        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

        return trim($title, $separator);
    }

    /**
     * Convert a string to snake case
     * 
     * @param string $value
     * @param string $delimiter
     * @return string
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $key = $value;

        if (isset(static::$snakeCache[$key][$delimiter])) {
            return static::$snakeCache[$key][$delimiter];
        }

        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));

            $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }

        return static::$snakeCache[$key][$delimiter] = $value;
    }

    /**
     * Determine if a given string starts with a given substring
     * 
     * @param string $haystack
     * @param string|iterable<string> $needles
     * @return bool
     */
    public static function startsWith(string $haystack, string|iterable $needles): bool
    {
        if (!is_iterable($needles)) {
            $needles = [$needles];
        }

        foreach ($needles as $needle) {
            if ((string) $needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a value to studly caps case
     * 
     * @param string $value
     * @return string
     */
    public static function studly(string $value): string
    {
        $key = $value;

        if (isset(static::$studlyCache[$key])) {
            return static::$studlyCache[$key];
        }

        $words = explode(' ', static::replace(['-', '_'], ' ', $value));

        $studlyWords = array_map(fn ($word) => static::ucfirst($word), $words);

        return static::$studlyCache[$key] = implode($studlyWords);
    }

    /**
     * Returns the portion of the string specified by the start and length parameters
     * 
     * @param string $string
     * @param int $start
     * @param int|null $length
     * @param string $encoding
     * @return string
     */
    public static function substr(string $string, int $start, ?int $length = null, string $encoding = 'UTF-8'): string
    {
        return mb_substr($string, $start, $length, $encoding);
    }

    /**
     * Returns the number of substring occurrences
     * 
     * @param string $haystack
     * @param string $needle
     * @param int $offset
     * @param int|null $length
     * @return int
     */
    public static function substrCount(string $haystack, string $needle, int $offset = 0, ?int $length = null): int
    {
        if (!is_null($length)) {
            return substr_count($haystack, $needle, $offset, $length);
        }

        return substr_count($haystack, $needle, $offset);
    }

    /**
     * Replace text within a portion of a string
     * 
     * @param string|array<string> $string
     * @param string|array<string> $replace
     * @param array<int>|int $offset
     * @param array<int>|int|null $length
     * @return string|array<string>
     */
    public static function substrReplace(string|array $string, string|array $replace, array|int $offset = 0, array|int|null $length = null): string|array
    {
        if ($length === null) {
            $length = strlen($string);
        }

        return substr_replace($string, $replace, $offset, $length);
    }

    /**
     * Swap multiple keywords in a string with other keywords
     * 
     * @param array<string, string> $map
     * @param string $subject
     * @return string
     */
    public static function swap(array $map, string $subject): string
    {
        return strtr($subject, $map);
    }

    /**
     * Make a string's first character uppercase
     * 
     * @param string $string
     * @return string
     */
    public static function ucfirst(string $string): string
    {
        return static::upper(static::substr($string, 0, 1)) . static::substr($string, 1);
    }

    /**
     * Split a string by uppercase characters
     * 
     * @param string $string
     * @return array<int, string>
     */
    public static function ucsplit(string $string): array
    {
        return preg_split('/(?=\p{Lu})/u', $string, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Execute a callback over each word in a string
     * 
     * @param string $value
     * @param callable $callback
     * @return string
     */
    public static function wordWrap(string $value, callable $callback): string
    {
        return preg_replace_callback('/\S+/', $callback, $value);
    }

    /**
     * Generate a UUID (version 4)
     * 
     * @return string
     */
    public static function uuid(): string
    {
        return Uuid::uuid4();
    }

    /**
     * Generate a time-ordered UUID (version 1)
     * 
     * @return string
     */
    public static function orderedUuid(): string
    {
        return Uuid::uuid1();
    }

    /**
     * Replace the given value in the given string
     * 
     * @param string|iterable<string> $search
     * @param string|iterable<string> $replace
     * @param string|iterable<string> $subject
     * @return string
     */
    public static function replace(string|iterable $search, string|iterable $replace, string|iterable $subject): string
    {
        if ($subject instanceof \Traversable) {
            $subject = iterator_to_array($subject);
        }

        return str_replace($search, $replace, $subject);
    }

    /**
     * Attempt to match the case on two strings
     * 
     * @param string $value
     * @param string $comparison
     * @return string
     */
    protected static function matchCase(string $value, string $comparison): string
    {
        $functions = ['mb_strtolower', 'mb_strtoupper', 'ucfirst', 'ucwords'];

        foreach ($functions as $function) {
            if ($function($comparison) === $comparison) {
                return $function($value);
            }
        }

        return $value;
    }

    /**
     * Get the language specific replacements for the ascii method
     *
     * @param string $language
     * @return array<int, array<string>>|null
     */
    protected static function languageSpecificCharsArray(string $language): ?array
    {
        static $languageSpecific;

        if (!isset($languageSpecific)) {
            $languageSpecific = [
                'bg' => [
                    ['х', 'Х', 'щ', 'Щ', 'ъ', 'Ъ', 'ь', 'Ь'],
                    ['h', 'H', 'sht', 'SHT', 'a', 'А', 'y', 'Y'],
                ],
            ];
        }

        return $languageSpecific[$language] ?? null;
    }

    /**
     * Get the pluralized version of an English word
     *
     * @param string $value
     * @return string
     */
    protected static function pluralize(string $value): string
    {
        // Simple pluralization rules - can be extended
        if (static::endsWith($value, ['s', 'sh', 'ch', 'x', 'z'])) {
            return $value . 'es';
        }

        if (static::endsWith($value, 'y') && !static::endsWith($value, ['ay', 'ey', 'iy', 'oy', 'uy'])) {
            return static::replaceLast('y', 'ies', $value);
        }

        if (static::endsWith($value, 'f')) {
            return static::replaceLast('f', 'ves', $value);
        }

        if (static::endsWith($value, 'fe')) {
            return static::replaceLast('fe', 'ves', $value);
        }

        return $value . 's';
    }

    /**
     * Get the singular version of an English word
     *
     * @param string $value
     * @return string
     */
    protected static function singularize(string $value): string
    {
        // Simple singularization rules - can be extended
        if (static::endsWith($value, 'ies')) {
            return static::replaceLast('ies', 'y', $value);
        }

        if (static::endsWith($value, 'ves')) {
            if (static::endsWith($value, 'ives')) {
                return static::replaceLast('ves', 'fe', $value);
            }
            return static::replaceLast('ves', 'f', $value);
        }

        if (static::endsWith($value, 'es') && static::length($value) > 3) {
            $singular = static::replaceLast('es', '', $value);
            if (static::endsWith($singular, ['s', 'sh', 'ch', 'x', 'z'])) {
                return $singular;
            }
        }

        if (static::endsWith($value, 's') && static::length($value) > 1) {
            return static::replaceLast('s', '', $value);
        }

        return $value;
    }
}