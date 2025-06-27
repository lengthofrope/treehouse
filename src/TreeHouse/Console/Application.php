<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console;

use LengthOfRope\TreeHouse\Console\Input\InputParser;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\ConsoleOutput;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\Commands\NewProjectCommand;
use LengthOfRope\TreeHouse\Console\Commands\CacheCommands\CacheClearCommand;
use LengthOfRope\TreeHouse\Console\Commands\CacheCommands\CacheStatsCommand;
use LengthOfRope\TreeHouse\Console\Commands\CacheCommands\CacheWarmCommand;
use LengthOfRope\TreeHouse\Console\Commands\DatabaseCommands\MigrateRunCommand;
use LengthOfRope\TreeHouse\Console\Commands\DevelopmentCommands\ServeCommand;
use LengthOfRope\TreeHouse\Console\Commands\DevelopmentCommands\TestRunCommand;
use LengthOfRope\TreeHouse\Console\Helpers\ConfigLoader;
use Throwable;

/**
 * TreeHouse CLI Application
 * 
 * Main entry point for the TreeHouse command-line interface.
 * Handles command registration, routing, and execution.
 * 
 * @package LengthOfRope\TreeHouse\Console
 * @author  TreeHouse Framework Team
 * @since   1.0.0
 */
class Application
{
    /**
     * Application version
     */
    public const VERSION = '1.0.0';

    /**
     * Application name
     */
    public const NAME = 'TreeHouse CLI';

    /**
     * Registered commands
     *
     * @var array<string, Command>
     */
    private array $commands = [];

    /**
     * Input parser
     */
    private InputParser $input;

    /**
     * Output interface
     */
    private OutputInterface $output;

    /**
     * Configuration loader
     */
    private ConfigLoader $config;

    /**
     * Application working directory
     */
    private string $workingDirectory;

    /**
     * Create a new CLI application
     */
    public function __construct()
    {
        $this->input = new InputParser();
        $this->output = new ConsoleOutput();
        $this->config = new ConfigLoader();
        $this->workingDirectory = getcwd() ?: __DIR__;
        
        $this->registerCommands();
    }

    /**
     * Register all available commands
     */
    private function registerCommands(): void
    {
        // Project scaffolding
        $this->register(new NewProjectCommand());
        
        // Cache commands
        $this->register(new CacheClearCommand());
        $this->register(new CacheStatsCommand());
        $this->register(new CacheWarmCommand());
        
        // Database commands
        $this->register(new MigrateRunCommand());
        
        // Development commands
        $this->register(new ServeCommand());
        $this->register(new TestRunCommand());
    }

    /**
     * Run the CLI application
     *
     * @param array $argv Command line arguments
     * @return int Exit code
     */
    public function run(array $argv): int
    {
        try {
            // Parse input arguments
            $input = $this->input->parse($argv);
            
            // Handle global options
            if ($input->hasOption('help') || $input->hasOption('h')) {
                $this->showHelp($input->getArgument('command'));
                return 0;
            }
            
            if ($input->hasOption('version') || $input->hasOption('V')) {
                $this->showVersion();
                return 0;
            }
            
            // Get command name
            $commandName = $input->getArgument('command');
            
            if (!$commandName) {
                $this->showHelp();
                return 0;
            }
            
            // Find and execute command
            $command = $this->findCommand($commandName);
            
            if (!$command) {
                $this->output->writeln("<error>Command '{$commandName}' not found.</error>");
                $this->suggestSimilarCommands($commandName);
                return 1;
            }
            
            // Execute the command with enhanced input that includes defaults
            $enhancedInput = $this->createEnhancedInput($input, $command);
            return $command->execute($enhancedInput, $this->output);
            
        } catch (Throwable $e) {
            $this->output->writeln("<error>Error: {$e->getMessage()}</error>");
            
            if ($this->isDebugMode()) {
                $this->output->writeln("<comment>Stack trace:</comment>");
                $this->output->writeln($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Register a command
     */
    public function register(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
        
        // Register aliases
        foreach ($command->getAliases() as $alias) {
            $this->commands[$alias] = $command;
        }
    }

    /**
     * Find a command by name
     */
    private function findCommand(string $name): ?Command
    {
        return $this->commands[$name] ?? null;
    }

    /**
     * Show application help
     */
    private function showHelp(?string $commandName = null): void
    {
        if ($commandName && isset($this->commands[$commandName])) {
            $this->showCommandHelp($this->commands[$commandName]);
            return;
        }
        
        $this->output->writeln("<info>" . self::NAME . "</info> <comment>version " . self::VERSION . "</comment>");
        $this->output->writeln("");
        $this->output->writeln("<comment>Usage:</comment>");
        $this->output->writeln("  th <command> [options] [arguments]");
        $this->output->writeln("");
        $this->output->writeln("<comment>Available commands:</comment>");
        
        $groups = $this->groupCommands();
        
        foreach ($groups as $group => $commands) {
            $this->output->writeln(" <comment>{$group}</comment>");
            foreach ($commands as $command) {
                $this->output->writeln(sprintf("  <info>%-20s</info> %s", $command->getName(), $command->getDescription()));
            }
            $this->output->writeln("");
        }
        
        $this->output->writeln("<comment>Global options:</comment>");
        $this->output->writeln("  <info>-h, --help</info>     Display help information");
        $this->output->writeln("  <info>-V, --version</info>  Display version information");
        $this->output->writeln("  <info>--debug</info>        Enable debug mode");
    }

    /**
     * Show command-specific help
     */
    private function showCommandHelp(Command $command): void
    {
        $this->output->writeln("<comment>Description:</comment>");
        $this->output->writeln("  " . $command->getDescription());
        $this->output->writeln("");
        
        $this->output->writeln("<comment>Usage:</comment>");
        $this->output->writeln("  th " . $command->getName() . " " . $command->getSynopsis());
        $this->output->writeln("");
        
        // Show options if any exist
        $options = $command->getOptions();
        if (!empty($options)) {
            $this->output->writeln("<comment>Options:</comment>");
            foreach ($options as $name => $config) {
                $optionLine = "  ";
                
                // Add short option if available
                if (!empty($config['shortcut'])) {
                    $optionLine .= "<info>-{$config['shortcut']}, </info>";
                }
                
                // Add long option
                $optionLine .= "<info>--{$name}</info>";
                
                // Add value indicator based on mode
                if (isset($config['mode'])) {
                    if ($config['mode'] & InputOption::VALUE_REQUIRED) {
                        $optionLine .= "=VALUE";
                    } elseif ($config['mode'] & InputOption::VALUE_OPTIONAL) {
                        $optionLine .= "[=VALUE]";
                    }
                }
                
                // Add description
                if (!empty($config['description'])) {
                    $optionLine = sprintf("%-30s %s", $optionLine, $config['description']);
                }
                
                // Add default value if available
                if (isset($config['default']) && $config['default'] !== null) {
                    $optionLine .= " <comment>[default: {$config['default']}]</comment>";
                }
                
                $this->output->writeln($optionLine);
            }
            $this->output->writeln("");
        }
        
        if ($command->getHelp()) {
            $this->output->writeln("<comment>Help:</comment>");
            $this->output->writeln("  " . $command->getHelp());
            $this->output->writeln("");
        }
    }

    /**
     * Show version information
     */
    private function showVersion(): void
    {
        $this->output->writeln(self::NAME . " " . self::VERSION);
    }

    /**
     * Group commands by category
     *
     * @return array<string, Command[]>
     */
    private function groupCommands(): array
    {
        $groups = [];
        $processed = [];
        
        foreach ($this->commands as $command) {
            // Skip aliases
            if (in_array($command->getName(), $processed)) {
                continue;
            }
            
            $processed[] = $command->getName();
            
            // Determine group from command name
            $parts = explode(':', $command->getName());
            $group = count($parts) > 1 ? $parts[0] : 'general';
            
            if (!isset($groups[$group])) {
                $groups[$group] = [];
            }
            
            $groups[$group][] = $command;
        }
        
        // Sort groups and commands
        ksort($groups);
        foreach ($groups as &$commands) {
            usort($commands, fn($a, $b) => strcmp($a->getName(), $b->getName()));
        }
        
        return $groups;
    }

    /**
     * Suggest similar commands
     */
    private function suggestSimilarCommands(string $name): void
    {
        $suggestions = [];
        
        foreach (array_keys($this->commands) as $commandName) {
            $distance = levenshtein($name, $commandName);
            if ($distance <= 3) {
                $suggestions[] = $commandName;
            }
        }
        
        if (!empty($suggestions)) {
            $this->output->writeln("");
            $this->output->writeln("Did you mean one of these?");
            foreach (array_unique($suggestions) as $suggestion) {
                $this->output->writeln("  <info>{$suggestion}</info>");
            }
        }
    }

    /**
     * Check if debug mode is enabled
     */
    private function isDebugMode(): bool
    {
        return isset($_ENV['TH_DEBUG']) && $_ENV['TH_DEBUG'] === 'true';
    }

    /**
     * Get working directory
     */
    public function getWorkingDirectory(): string
    {
        return $this->workingDirectory;
    }

    /**
     * Get configuration loader
     */
    public function getConfig(): ConfigLoader
    {
        return $this->config;
    }

    /**
     * Get all registered commands
     *
     * @return array<string, Command>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Create enhanced input with command defaults applied
     */
    private function createEnhancedInput(InputInterface $input, Command $command): InputInterface
    {
        return new EnhancedInput($input, $command);
    }
}

/**
 * Enhanced Input
 *
 * Wraps the parsed input and applies command defaults for options.
 */
class EnhancedInput implements InputInterface
{
    private InputInterface $input;
    private Command $command;
    private array $optionDefaults = [];

    public function __construct(InputInterface $input, Command $command)
    {
        $this->input = $input;
        $this->command = $command;
        
        // Extract default values from command options
        foreach ($command->getOptions() as $name => $config) {
            if (isset($config['default'])) {
                $this->optionDefaults[$name] = $config['default'];
            }
        }
    }

    public function getArgument(string $name): mixed
    {
        return $this->input->getArgument($name);
    }

    public function hasArgument(string $name): bool
    {
        return $this->input->hasArgument($name);
    }

    public function getArguments(): array
    {
        return $this->input->getArguments();
    }

    public function getOption(string $name): mixed
    {
        $value = $this->input->getOption($name);
        
        // If option is not set but has a default, return the default
        if ($value === null && isset($this->optionDefaults[$name])) {
            return $this->optionDefaults[$name];
        }
        
        return $value;
    }

    public function hasOption(string $name): bool
    {
        return $this->input->hasOption($name) || isset($this->optionDefaults[$name]);
    }

    public function getOptions(): array
    {
        $options = $this->input->getOptions();
        
        // Merge in defaults for options that weren't provided
        foreach ($this->optionDefaults as $name => $default) {
            if (!isset($options[$name])) {
                $options[$name] = $default;
            }
        }
        
        return $options;
    }

    public function getRawArguments(): array
    {
        return $this->input->getRawArguments();
    }
}