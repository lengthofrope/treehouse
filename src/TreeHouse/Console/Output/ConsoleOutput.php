<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Output;

/**
 * Console Output
 * 
 * Implementation of OutputInterface for console output with formatting support.
 * Handles ANSI color codes and verbosity levels.
 * 
 * @package LengthOfRope\TreeHouse\Console\Output
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ConsoleOutput implements OutputInterface
{
    /**
     * Verbosity levels
     */
    public const VERBOSITY_QUIET = 16;
    public const VERBOSITY_NORMAL = 32;
    public const VERBOSITY_VERBOSE = 64;
    public const VERBOSITY_VERY_VERBOSE = 128;
    public const VERBOSITY_DEBUG = 256;

    /**
     * Current verbosity level
     */
    private int $verbosity = self::VERBOSITY_NORMAL;

    /**
     * Output formatter
     */
    private OutputFormatter $formatter;

    /**
     * Create console output
     */
    public function __construct()
    {
        $this->formatter = new OutputFormatter();
    }

    /**
     * Write a message to output
     */
    public function write(string $message): void
    {
        if ($this->verbosity === self::VERBOSITY_QUIET) {
            return;
        }

        echo $this->formatter->format($message);
    }

    /**
     * Write a message to output with a newline
     */
    public function writeln(string $message): void
    {
        $this->write($message . PHP_EOL);
    }

    /**
     * Set output verbosity level
     */
    public function setVerbosity(int $level): void
    {
        $this->verbosity = $level;
    }

    /**
     * Get output verbosity level
     */
    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    /**
     * Check if output is quiet
     */
    public function isQuiet(): bool
    {
        return $this->verbosity === self::VERBOSITY_QUIET;
    }

    /**
     * Check if output is verbose
     */
    public function isVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERBOSE;
    }

    /**
     * Check if output is very verbose
     */
    public function isVeryVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERY_VERBOSE;
    }

    /**
     * Check if output is debug level
     */
    public function isDebug(): bool
    {
        return $this->verbosity >= self::VERBOSITY_DEBUG;
    }

    /**
     * Get output formatter
     */
    public function getFormatter(): OutputFormatter
    {
        return $this->formatter;
    }

    /**
     * Set output formatter
     */
    public function setFormatter(OutputFormatter $formatter): void
    {
        $this->formatter = $formatter;
    }
}

/**
 * Output Formatter
 * 
 * Handles formatting and styling of console output using ANSI escape codes.
 */
class OutputFormatter
{
    /**
     * ANSI color codes
     */
    private const COLORS = [
        'black' => 30,
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'blue' => 34,
        'magenta' => 35,
        'cyan' => 36,
        'white' => 37,
        'default' => 39,
    ];

    /**
     * ANSI background color codes
     */
    private const BACKGROUND_COLORS = [
        'black' => 40,
        'red' => 41,
        'green' => 42,
        'yellow' => 43,
        'blue' => 44,
        'magenta' => 45,
        'cyan' => 46,
        'white' => 47,
        'default' => 49,
    ];

    /**
     * ANSI style codes
     */
    private const STYLES = [
        'bold' => 1,
        'dim' => 2,
        'underline' => 4,
        'blink' => 5,
        'reverse' => 7,
        'hidden' => 8,
    ];

    /**
     * Predefined style tags
     */
    private const STYLE_TAGS = [
        'info' => ['color' => 'green'],
        'success' => ['color' => 'green', 'style' => 'bold'],
        'comment' => ['color' => 'cyan'],
        'question' => ['color' => 'yellow'],
        'error' => ['color' => 'red', 'style' => 'bold'],
        'warning' => ['color' => 'yellow', 'style' => 'bold'],
    ];

    /**
     * Whether formatting is supported
     */
    private bool $decorated;

    /**
     * Create output formatter
     */
    public function __construct()
    {
        $this->decorated = $this->supportsColor();
    }

    /**
     * Format a message with style tags
     */
    public function format(string $message): string
    {
        if (!$this->decorated) {
            return $this->stripTags($message);
        }

        return preg_replace_callback(
            '/<(\/?)([\w]+)(?:\s+([^>]+))?>/',
            [$this, 'replaceStyle'],
            $message
        );
    }

    /**
     * Replace style tags with ANSI codes
     */
    private function replaceStyle(array $matches): string
    {
        $isClosing = $matches[1] === '/';
        $tag = $matches[2];
        $attributes = $matches[3] ?? '';

        if ($isClosing) {
            return "\033[0m"; // Reset all formatting
        }

        if (isset(self::STYLE_TAGS[$tag])) {
            return $this->buildAnsiCode(self::STYLE_TAGS[$tag]);
        }

        // Parse custom attributes
        $styles = $this->parseAttributes($attributes);
        if (!empty($styles)) {
            return $this->buildAnsiCode($styles);
        }

        return '';
    }

    /**
     * Parse style attributes
     */
    private function parseAttributes(string $attributes): array
    {
        $styles = [];
        
        if (preg_match('/color=([a-z]+)/', $attributes, $matches)) {
            $styles['color'] = $matches[1];
        }
        
        if (preg_match('/bg=([a-z]+)/', $attributes, $matches)) {
            $styles['background'] = $matches[1];
        }
        
        if (preg_match('/style=([a-z]+)/', $attributes, $matches)) {
            $styles['style'] = $matches[1];
        }

        return $styles;
    }

    /**
     * Build ANSI escape code from style definition
     */
    private function buildAnsiCode(array $styles): string
    {
        $codes = [];

        if (isset($styles['color']) && isset(self::COLORS[$styles['color']])) {
            $codes[] = self::COLORS[$styles['color']];
        }

        if (isset($styles['background']) && isset(self::BACKGROUND_COLORS[$styles['background']])) {
            $codes[] = self::BACKGROUND_COLORS[$styles['background']];
        }

        if (isset($styles['style']) && isset(self::STYLES[$styles['style']])) {
            $codes[] = self::STYLES[$styles['style']];
        }

        return empty($codes) ? '' : "\033[" . implode(';', $codes) . 'm';
    }

    /**
     * Strip style tags from message
     */
    private function stripTags(string $message): string
    {
        return preg_replace('/<\/?[\w\s="]+>/', '', $message);
    }

    /**
     * Check if the terminal supports color output
     */
    private function supportsColor(): bool
    {
        // Force enable colors if explicitly requested
        if (isset($_ENV['FORCE_COLOR']) || isset($_ENV['CLICOLOR_FORCE'])) {
            return true;
        }

        // Disable colors if explicitly requested
        if (isset($_ENV['NO_COLOR']) || (isset($_ENV['CLICOLOR']) && $_ENV['CLICOLOR'] === '0')) {
            return false;
        }

        // Check if we're in a terminal (but don't fail if posix_isatty is not available)
        if (function_exists('posix_isatty') && !posix_isatty(STDOUT)) {
            return false;
        }

        // Check environment variables
        $term = $_ENV['TERM'] ?? $_SERVER['TERM'] ?? '';
        if (str_contains($term, 'color') || str_contains($term, '256')) {
            return true;
        }

        // Check for common terminals that support color
        $colorTerms = ['xterm', 'screen', 'linux', 'cygwin', 'ansi', 'vt100', 'vt220'];
        foreach ($colorTerms as $colorTerm) {
            if (str_contains($term, $colorTerm)) {
                return true;
            }
        }

        // Default to true for most modern terminals
        return !empty($term);
    }

    /**
     * Enable or disable formatting
     */
    public function setDecorated(bool $decorated): void
    {
        $this->decorated = $decorated;
    }

    /**
     * Check if formatting is enabled
     */
    public function isDecorated(): bool
    {
        return $this->decorated;
    }
}