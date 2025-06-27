<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Input;

/**
 * Input Parser
 * 
 * Parses command line arguments and options for the TreeHouse CLI.
 * Handles both short and long options, arguments, and flags.
 * 
 * @package LengthOfRope\TreeHouse\Console\Input
 * @author  TreeHouse Framework Team
 * @since   1.0.0
 */
class InputParser
{
    /**
     * Parse command line arguments
     *
     * @param string[] $argv Raw command line arguments
     * @return ParsedInput
     */
    public function parse(array $argv): ParsedInput
    {
        // Remove script name
        array_shift($argv);
        
        $arguments = [];
        $options = [];
        $rawArguments = $argv;
        
        $i = 0;
        $argumentIndex = 0;
        
        while ($i < count($argv)) {
            $arg = $argv[$i];
            
            if ($this->isLongOption($arg)) {
                // Handle long options (--option or --option=value)
                $this->parseLongOption($arg, $argv, $i, $options);
            } elseif ($this->isShortOption($arg)) {
                // Handle short options (-o or -o value)
                $this->parseShortOption($arg, $argv, $i, $options);
            } else {
                // Handle arguments
                if ($argumentIndex === 0) {
                    $arguments['command'] = $arg;
                } else {
                    $arguments["arg{$argumentIndex}"] = $arg;
                }
                $argumentIndex++;
            }
            
            $i++;
        }
        
        return new ParsedInput($arguments, $options, $rawArguments);
    }

    /**
     * Check if argument is a long option (starts with --)
     */
    private function isLongOption(string $arg): bool
    {
        return str_starts_with($arg, '--');
    }

    /**
     * Check if argument is a short option (starts with -)
     */
    private function isShortOption(string $arg): bool
    {
        return str_starts_with($arg, '-') && !str_starts_with($arg, '--');
    }

    /**
     * Parse long option
     *
     * @param string $arg Current argument
     * @param string[] $argv All arguments
     * @param int $i Current index (passed by reference)
     * @param array $options Options array (passed by reference)
     */
    private function parseLongOption(string $arg, array $argv, int &$i, array &$options): void
    {
        $option = substr($arg, 2); // Remove --
        
        if (str_contains($option, '=')) {
            // --option=value format
            [$name, $value] = explode('=', $option, 2);
            $options[$name] = $value;
        } else {
            // --option format (check if next arg is value)
            if (isset($argv[$i + 1]) && !$this->isOption($argv[$i + 1])) {
                $options[$option] = $argv[$i + 1];
                $i++; // Skip next argument as it's the value
            } else {
                $options[$option] = true; // Flag option
            }
        }
    }

    /**
     * Parse short option
     *
     * @param string $arg Current argument
     * @param string[] $argv All arguments
     * @param int $i Current index (passed by reference)
     * @param array $options Options array (passed by reference)
     */
    private function parseShortOption(string $arg, array $argv, int &$i, array &$options): void
    {
        $option = substr($arg, 1); // Remove -
        
        if (strlen($option) === 1) {
            // Single short option (-o)
            if (isset($argv[$i + 1]) && !$this->isOption($argv[$i + 1])) {
                $options[$option] = $argv[$i + 1];
                $i++; // Skip next argument as it's the value
            } else {
                $options[$option] = true; // Flag option
            }
        } else {
            // Multiple short options (-abc) or short option with value (-ovalue)
            if (ctype_alpha($option)) {
                // Multiple flags (-abc = -a -b -c)
                for ($j = 0; $j < strlen($option); $j++) {
                    $options[$option[$j]] = true;
                }
            } else {
                // Short option with value (-ovalue)
                $name = $option[0];
                $value = substr($option, 1);
                $options[$name] = $value;
            }
        }
    }

    /**
     * Check if argument is an option (starts with -)
     */
    private function isOption(string $arg): bool
    {
        return str_starts_with($arg, '-');
    }
}

/**
 * Parsed Input
 * 
 * Container for parsed command line input.
 */
class ParsedInput implements InputInterface
{
    /**
     * Parsed arguments
     *
     * @var array<string, mixed>
     */
    private array $arguments;

    /**
     * Parsed options
     *
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * Raw command line arguments
     *
     * @var string[]
     */
    private array $rawArguments;

    /**
     * Create parsed input
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $options
     * @param string[] $rawArguments
     */
    public function __construct(array $arguments, array $options, array $rawArguments)
    {
        $this->arguments = $arguments;
        $this->options = $options;
        $this->rawArguments = $rawArguments;
    }

    /**
     * Get an argument value by name
     */
    public function getArgument(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }

    /**
     * Check if an argument exists
     */
    public function hasArgument(string $name): bool
    {
        return isset($this->arguments[$name]);
    }

    /**
     * Get all arguments
     *
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get an option value by name
     */
    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Check if an option exists
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Get all options
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get the raw command line arguments
     *
     * @return string[]
     */
    public function getRawArguments(): array
    {
        return $this->rawArguments;
    }
}