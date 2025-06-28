<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console;

use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;

/**
 * Base Command Class
 * 
 * Abstract base class for all CLI commands in the TreeHouse framework.
 * Provides common functionality and structure for command implementation.
 * 
 * @package LengthOfRope\TreeHouse\Console
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
abstract class Command
{
    /**
     * Command name
     */
    protected string $name = '';

    /**
     * Command description
     */
    protected string $description = '';

    /**
     * Command help text
     */
    protected string $help = '';

    /**
     * Command aliases
     *
     * @var string[]
     */
    protected array $aliases = [];

    /**
     * Command arguments
     *
     * @var array<string, array>
     */
    protected array $arguments = [];

    /**
     * Command options
     *
     * @var array<string, array>
     */
    protected array $options = [];

    /**
     * Configure the command
     * 
     * This method should be overridden by subclasses to define
     * the command name, description, arguments, and options.
     */
    protected function configure(): void
    {
        // Override in subclasses
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Exit code (0 for success, non-zero for error)
     */
    abstract public function execute(InputInterface $input, OutputInterface $output): int;

    /**
     * Get command name
     */
    public function getName(): string
    {
        if (empty($this->name)) {
            $this->configure();
        }
        return $this->name;
    }

    /**
     * Set command name
     */
    protected function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get command description
     */
    public function getDescription(): string
    {
        if (empty($this->description)) {
            $this->configure();
        }
        return $this->description;
    }

    /**
     * Set command description
     */
    protected function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get command help text
     */
    public function getHelp(): string
    {
        if (empty($this->help)) {
            $this->configure();
        }
        return $this->help;
    }

    /**
     * Set command help text
     */
    protected function setHelp(string $help): self
    {
        $this->help = $help;
        return $this;
    }

    /**
     * Get command aliases
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Set command aliases
     *
     * @param string[] $aliases
     */
    protected function setAliases(array $aliases): self
    {
        $this->aliases = $aliases;
        return $this;
    }

    /**
     * Add command argument
     */
    protected function addArgument(string $name, int $mode = 0, string $description = '', mixed $default = null): self
    {
        $this->arguments[$name] = [
            'mode' => $mode,
            'description' => $description,
            'default' => $default,
        ];
        return $this;
    }

    /**
     * Add command option
     */
    protected function addOption(string $name, ?string $shortcut = null, int $mode = 0, string $description = '', mixed $default = null): self
    {
        $this->options[$name] = [
            'shortcut' => $shortcut,
            'mode' => $mode,
            'description' => $description,
            'default' => $default,
        ];
        return $this;
    }

    /**
     * Get command arguments
     *
     * @return array<string, array>
     */
    public function getArguments(): array
    {
        if (empty($this->arguments)) {
            $this->configure();
        }
        return $this->arguments;
    }

    /**
     * Get command options
     *
     * @return array<string, array>
     */
    public function getOptions(): array
    {
        if (empty($this->options)) {
            $this->configure();
        }
        return $this->options;
    }

    /**
     * Get command synopsis (usage pattern)
     */
    public function getSynopsis(): string
    {
        $synopsis = '';
        
        // Add arguments
        foreach ($this->getArguments() as $name => $config) {
            if ($config['mode'] & InputArgument::REQUIRED) {
                $synopsis .= "<{$name}> ";
            } else {
                $synopsis .= "[<{$name}>] ";
            }
        }
        
        // Add options
        if (!empty($this->getOptions())) {
            $synopsis .= '[options]';
        }
        
        return trim($synopsis);
    }

    /**
     * Validate command input
     */
    protected function validateInput(InputInterface $input, OutputInterface $output): bool
    {
        // Check required arguments
        foreach ($this->getArguments() as $name => $config) {
            if (($config['mode'] & InputArgument::REQUIRED) && !$input->hasArgument($name)) {
                $output->writeln("<error>Missing required argument: {$name}</error>");
                return false;
            }
        }
        
        return true;
    }

    /**
     * Write a line to output with formatting
     */
    protected function line(OutputInterface $output, string $message, ?string $style = null): void
    {
        if ($style) {
            $message = "<{$style}>{$message}</{$style}>";
        }
        $output->writeln($message);
    }

    /**
     * Write an info message
     */
    protected function info(OutputInterface $output, string $message): void
    {
        $this->line($output, $message, 'info');
    }

    /**
     * Write an error message
     */
    protected function error(OutputInterface $output, string $message): void
    {
        $this->line($output, $message, 'error');
    }

    /**
     * Write a warning message
     */
    protected function warn(OutputInterface $output, string $message): void
    {
        $this->line($output, $message, 'warning');
    }

    /**
     * Write a success message
     */
    protected function success(OutputInterface $output, string $message): void
    {
        $this->line($output, $message, 'success');
    }

    /**
     * Write a comment message
     */
    protected function comment(OutputInterface $output, string $message): void
    {
        $this->line($output, $message, 'comment');
    }

    /**
     * Ask user for confirmation
     */
    protected function confirm(OutputInterface $output, string $question, bool $default = false): bool
    {
        // Return default value in testing environment to avoid hanging
        if ($this->isTestingEnvironment()) {
            return $default;
        }
        
        $defaultText = $default ? 'Y/n' : 'y/N';
        $output->write("<question>{$question}</question> <comment>[{$defaultText}]</comment> ");
        
        $handle = fopen('php://stdin', 'r');
        $response = trim(fgets($handle));
        fclose($handle);
        
        if (empty($response)) {
            return $default;
        }
        
        return in_array(strtolower($response), ['y', 'yes', '1', 'true']);
    }

    /**
     * Ask user for input
     */
    protected function ask(OutputInterface $output, string $question, ?string $default = null): string
    {
        // Return default value in testing environment to avoid hanging
        if ($this->isTestingEnvironment()) {
            return $default ?? '';
        }
        
        $defaultText = $default ? " <comment>[{$default}]</comment>" : '';
        $output->write("<question>{$question}</question>{$defaultText} ");
        
        $handle = fopen('php://stdin', 'r');
        $response = trim(fgets($handle));
        fclose($handle);
        
        return empty($response) ? ($default ?? '') : $response;
    }

    /**
     * Check if we're in a testing environment
     */
    protected function isTestingEnvironment(): bool
    {
        return defined('TESTING') ||
               (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing') ||
               (php_sapi_name() === 'cli' && strpos($_SERVER['argv'][0] ?? '', 'phpunit') !== false);
    }
}

/**
 * Input argument modes
 */
class InputArgument
{
    public const REQUIRED = 1;
    public const OPTIONAL = 2;
    public const IS_ARRAY = 4;
}

/**
 * Input option modes
 */
class InputOption
{
    public const VALUE_NONE = 1;
    public const VALUE_REQUIRED = 2;
    public const VALUE_OPTIONAL = 4;
    public const VALUE_IS_ARRAY = 8;
}