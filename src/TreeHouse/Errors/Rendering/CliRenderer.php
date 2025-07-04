<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Rendering;

use LengthOfRope\TreeHouse\Errors\Classification\ClassificationResult;
use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;
use LengthOfRope\TreeHouse\Http\Request;
use Throwable;

/**
 * CLI error renderer for command-line interface
 */
class CliRenderer implements RendererInterface
{
    private bool $colorSupport;
    private int $terminalWidth;

    public function __construct(?bool $colorSupport = null, int $terminalWidth = 80)
    {
        $this->colorSupport = $colorSupport ?? $this->detectColorSupport();
        $this->terminalWidth = $terminalWidth;
    }

    /**
     * Render an error response for CLI
     */
    public function render(
        Throwable $exception,
        ClassificationResult $classification,
        array $context,
        ?Request $request = null,
        bool $debug = false
    ): string {
        $output = [];

        // Header
        $output[] = $this->renderHeader($exception, $classification);
        $output[] = '';

        // Main error message
        $output[] = $this->renderMessage($exception, $classification, $debug);
        $output[] = '';

        // Exception details
        $output[] = $this->renderExceptionDetails($exception, $classification);
        $output[] = '';

        // Debug information if enabled
        if ($debug) {
            $output[] = $this->renderDebugInfo($exception, $classification, $context);
            $output[] = '';
        }

        // Suggestions
        $suggestions = $this->getErrorSuggestions($classification);
        if (!empty($suggestions)) {
            $output[] = $this->renderSuggestions($suggestions);
            $output[] = '';
        }

        // Footer
        $output[] = $this->renderFooter($classification);

        return implode("\n", $output);
    }

    /**
     * Render error header
     */
    private function renderHeader(Throwable $exception, ClassificationResult $classification): string
    {
        $icon = $this->getErrorIcon($classification);
        $title = $this->getErrorTitle($classification);
        
        $header = $icon . ' ' . $title;
        
        if ($this->colorSupport) {
            $color = $this->getSeverityColor($classification->severity);
            $header = $this->colorize($header, $color, true);
        }

        return $this->centerText($header);
    }

    /**
     * Render error message
     */
    private function renderMessage(Throwable $exception, ClassificationResult $classification, bool $debug): string
    {
        $message = $this->getUserMessage($exception, $debug);
        
        $lines = [];
        $lines[] = $this->colorize('Message:', 'white', true);
        $lines[] = $this->wrapText($message, 2);
        
        return implode("\n", $lines);
    }

    /**
     * Render exception details
     */
    private function renderExceptionDetails(Throwable $exception, ClassificationResult $classification): string
    {
        $lines = [];
        $lines[] = $this->colorize('Details:', 'white', true);
        
        $details = [
            'Type' => get_class($exception),
            'Category' => $classification->category,
            'Severity' => $classification->severity,
            'File' => $exception->getFile(),
            'Line' => $exception->getLine()
        ];

        // Add BaseException specific details
        if ($exception instanceof BaseException) {
            if ($exception->getErrorCode()) {
                $details['Error Code'] = $exception->getErrorCode();
            }
        }

        // Add security/critical flags
        if ($classification->isSecurity) {
            $details['Security'] = $this->colorize('YES', 'red', true);
        }
        
        if ($classification->isCritical) {
            $details['Critical'] = $this->colorize('YES', 'red', true);
        }

        foreach ($details as $label => $value) {
            $lines[] = '  ' . $this->colorize($label . ':', 'cyan') . ' ' . $value;
        }

        return implode("\n", $lines);
    }

    /**
     * Render debug information
     */
    private function renderDebugInfo(
        Throwable $exception,
        ClassificationResult $classification,
        array $context
    ): string {
        $lines = [];
        $lines[] = $this->colorize('Debug Information:', 'white', true);
        $lines[] = '';

        // Stack trace
        $lines[] = $this->colorize('Stack Trace:', 'yellow', true);
        $trace = $exception->getTraceAsString();
        $lines[] = $this->wrapText($trace, 2);
        $lines[] = '';

        // Classification details
        $lines[] = $this->colorize('Classification:', 'yellow', true);
        $classificationData = $classification->toArray();
        foreach ($classificationData as $key => $value) {
            if (is_array($value)) {
                $lines[] = '  ' . $this->colorize($key . ':', 'cyan') . ' ' . implode(', ', $value);
            } else {
                $lines[] = '  ' . $this->colorize($key . ':', 'cyan') . ' ' . $value;
            }
        }
        $lines[] = '';

        // Context summary
        if (!empty($context)) {
            $lines[] = $this->colorize('Context Summary:', 'yellow', true);
            
            if (isset($context['request'])) {
                $lines[] = '  ' . $this->colorize('Request:', 'cyan') . ' ' . 
                          ($context['request']['method'] ?? 'N/A') . ' ' . 
                          ($context['request']['uri'] ?? 'N/A');
            }
            
            if (isset($context['user']['authenticated'])) {
                $authStatus = $context['user']['authenticated'] ? 'Yes' : 'No';
                $lines[] = '  ' . $this->colorize('Authenticated:', 'cyan') . ' ' . $authStatus;
            }
            
            if (isset($context['environment']['app']['env'])) {
                $lines[] = '  ' . $this->colorize('Environment:', 'cyan') . ' ' . $context['environment']['app']['env'];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Render suggestions
     */
    private function renderSuggestions(array $suggestions): string
    {
        $lines = [];
        $lines[] = $this->colorize('Suggestions:', 'green', true);
        
        foreach ($suggestions as $suggestion) {
            $lines[] = '  • ' . $this->wrapText($suggestion, 4);
        }

        return implode("\n", $lines);
    }

    /**
     * Render footer
     */
    private function renderFooter(ClassificationResult $classification): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $footer = 'Error occurred at ' . $timestamp;
        
        if ($classification->shouldReport) {
            $footer .= ' (logged)';
        }

        return $this->colorize($footer, 'dark_gray');
    }

    /**
     * Get error icon based on classification
     */
    private function getErrorIcon(ClassificationResult $classification): string
    {
        if ($classification->isCritical) {
            return '🚨';
        }
        
        if ($classification->isSecurity) {
            return '🔒';
        }

        return match ($classification->severity) {
            'critical' => '💥',
            'high' => '❌',
            'medium' => '⚠️',
            'low' => 'ℹ️',
            default => '❌'
        };
    }

    /**
     * Get error title
     */
    private function getErrorTitle(ClassificationResult $classification): string
    {
        if ($classification->isCritical) {
            return 'CRITICAL SYSTEM ERROR';
        }
        
        if ($classification->isSecurity) {
            return 'SECURITY ERROR';
        }

        return match ($classification->severity) {
            'critical' => 'CRITICAL ERROR',
            'high' => 'ERROR',
            'medium' => 'WARNING',
            'low' => 'NOTICE',
            default => 'ERROR'
        };
    }

    /**
     * Get user-friendly message
     */
    private function getUserMessage(Throwable $exception, bool $debug): string
    {
        // Use user message from BaseException if available
        if ($exception instanceof BaseException && $exception->getUserMessage()) {
            return $exception->getUserMessage();
        }

        // Always show actual message in CLI (unlike web where we hide in production)
        return $exception->getMessage();
    }

    /**
     * Get error suggestions
     */
    private function getErrorSuggestions(ClassificationResult $classification): array
    {
        return match ($classification->category) {
            'validation' => [
                'Check the command arguments and options',
                'Use --help to see available options',
                'Verify input data format'
            ],
            'authentication' => [
                'Check your credentials',
                'Ensure you are logged in',
                'Verify API keys or tokens'
            ],
            'authorization' => [
                'Check your permissions',
                'Contact an administrator',
                'Verify you have access to this resource'
            ],
            'database' => [
                'Check database connection',
                'Verify database credentials',
                'Ensure database server is running'
            ],
            'system' => [
                'Check system resources',
                'Verify file permissions',
                'Check disk space and memory'
            ],
            default => [
                'Check the error details above',
                'Try running the command again',
                'Use --debug for more information'
            ]
        };
    }

    /**
     * Get color for severity level
     */
    private function getSeverityColor(string $severity): string
    {
        return match ($severity) {
            'critical' => 'red',
            'high' => 'red',
            'medium' => 'yellow',
            'low' => 'blue',
            default => 'red'
        };
    }

    /**
     * Colorize text for terminal output
     */
    private function colorize(string $text, string $color, bool $bold = false): string
    {
        if (!$this->colorSupport) {
            return $text;
        }

        $colors = [
            'black' => '30',
            'red' => '31',
            'green' => '32',
            'yellow' => '33',
            'blue' => '34',
            'magenta' => '35',
            'cyan' => '36',
            'white' => '37',
            'dark_gray' => '90',
            'light_red' => '91',
            'light_green' => '92',
            'light_yellow' => '93',
            'light_blue' => '94',
            'light_magenta' => '95',
            'light_cyan' => '96',
            'light_white' => '97'
        ];

        $colorCode = $colors[$color] ?? '37';
        $boldCode = $bold ? '1;' : '';

        return "\033[{$boldCode}{$colorCode}m{$text}\033[0m";
    }

    /**
     * Center text in terminal
     */
    private function centerText(string $text): string
    {
        // Remove ANSI color codes for length calculation
        $plainText = preg_replace('/\033\[[0-9;]*m/', '', $text);
        $textLength = strlen($plainText);
        
        if ($textLength >= $this->terminalWidth) {
            return $text;
        }

        $padding = floor(($this->terminalWidth - $textLength) / 2);
        return str_repeat(' ', (int) $padding) . $text;
    }

    /**
     * Wrap text to terminal width
     */
    private function wrapText(string $text, int $indent = 0): string
    {
        $maxWidth = $this->terminalWidth - $indent;
        $lines = explode("\n", $text);
        $wrappedLines = [];

        foreach ($lines as $line) {
            if (strlen($line) <= $maxWidth) {
                $wrappedLines[] = str_repeat(' ', $indent) . $line;
            } else {
                $wrapped = wordwrap($line, $maxWidth, "\n", true);
                $subLines = explode("\n", $wrapped);
                foreach ($subLines as $subLine) {
                    $wrappedLines[] = str_repeat(' ', $indent) . $subLine;
                }
            }
        }

        return implode("\n", $wrappedLines);
    }

    /**
     * Detect if terminal supports colors
     */
    private function detectColorSupport(): bool
    {
        // Check if we're in a CLI environment
        if (php_sapi_name() !== 'cli') {
            return false;
        }

        // Check environment variables
        $term = getenv('TERM');
        if ($term && str_contains($term, 'color')) {
            return true;
        }

        // Check for common color-supporting terminals
        $colorTerms = ['xterm', 'xterm-color', 'xterm-256color', 'screen', 'screen-256color', 'tmux', 'tmux-256color'];
        if ($term && in_array($term, $colorTerms, true)) {
            return true;
        }

        // Check for Windows
        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON';
        }

        return false;
    }

    /**
     * Check if this renderer can handle the given request
     */
    public function canRender(?Request $request): bool
    {
        // CLI renderer is used ONLY when there's no HTTP request (true CLI environment)
        // If there's a Request object, even in CLI mode (like tests), use other renderers
        return $request === null;
    }

    /**
     * Get the content type for this renderer
     */
    public function getContentType(): string
    {
        return 'text/plain; charset=utf-8';
    }

    /**
     * Get the priority of this renderer
     */
    public function getPriority(): int
    {
        return 90; // Highest priority for CLI environment
    }

    /**
     * Get the name/identifier of this renderer
     */
    public function getName(): string
    {
        return 'cli';
    }

    /**
     * Set color support
     */
    public function setColorSupport(bool $colorSupport): void
    {
        $this->colorSupport = $colorSupport;
    }

    /**
     * Set terminal width
     */
    public function setTerminalWidth(int $width): void
    {
        $this->terminalWidth = max(40, $width); // Minimum width of 40
    }
}