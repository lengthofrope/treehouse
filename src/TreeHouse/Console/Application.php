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
use LengthOfRope\TreeHouse\Console\Commands\CronCommands\CronRunCommand;
use LengthOfRope\TreeHouse\Console\Commands\CronCommands\CronListCommand;
use LengthOfRope\TreeHouse\Console\Commands\DatabaseCommands\MigrateRunCommand;
use LengthOfRope\TreeHouse\Console\Commands\DevelopmentCommands\ServeCommand;
use LengthOfRope\TreeHouse\Console\Commands\DevelopmentCommands\TestRunCommand;
use LengthOfRope\TreeHouse\Console\Commands\UserCommands\CreateUserCommand;
use LengthOfRope\TreeHouse\Console\Commands\UserCommands\ListUsersCommand;
use LengthOfRope\TreeHouse\Console\Commands\UserCommands\UpdateUserCommand;
use LengthOfRope\TreeHouse\Console\Commands\UserCommands\DeleteUserCommand;
use LengthOfRope\TreeHouse\Console\Commands\UserCommands\UserRoleCommand;
use LengthOfRope\TreeHouse\Console\Helpers\ConfigLoader;
use Throwable;

/**
 * TreeHouse CLI Application
 * 
 * Main entry point for the TreeHouse command-line interface.
 * Handles command registration, routing, and execution.
 * 
 * @package LengthOfRope\TreeHouse\Console
 * @author  Bas de Kort <bdekort@proton.me>
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
     * Project creation tool name
     */
    public const PROJECT_CREATOR_NAME = 'TreeHouse Project Creator';
    
    /**
     * Project management tool name
     */
    public const PROJECT_MANAGER_NAME = 'TreeHouse Project Manager';

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
     * Script name (treehouse or th)
     */
    private string $scriptName;

    /**
     * Create a new CLI application
     */
    public function __construct()
    {
        $this->input = new InputParser();
        $this->output = new ConsoleOutput();
        $this->config = new ConfigLoader();
        $this->workingDirectory = getcwd() ?: __DIR__;
        $this->scriptName = 'th'; // Default to 'th'
    }

    /**
     * Register all available commands
     */
    public function registerCommands(): void
    {
        if ($this->isInTreeHouseProject()) {
            // Inside a TreeHouse project - register project management commands
            
            // Cache commands
            $this->register(new CacheClearCommand());
            $this->register(new CacheStatsCommand());
            $this->register(new CacheWarmCommand());
            
            // Cron commands
            $this->register(new CronRunCommand());
            $this->register(new CronListCommand());
            
            // Database commands
            $this->register(new MigrateRunCommand());
            
            // Development commands
            $this->register(new ServeCommand());
            $this->register(new TestRunCommand());
            
            // User management commands
            $this->register(new CreateUserCommand());
            $this->register(new ListUsersCommand());
            $this->register(new UpdateUserCommand());
            $this->register(new DeleteUserCommand());
            $this->register(new UserRoleCommand());
        } else {
            // Outside a TreeHouse project - only register the new project command
            $this->register(new NewProjectCommand());
        }
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
            // Detect script name from argv[0]
            $this->scriptName = $this->detectScriptName($argv[0] ?? 'treehouse');
            
            // Register commands based on project context
            $this->registerCommands();
            
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
                // Check if this is a group prefix (e.g., "user", "cron", "cache")
                if ($this->showGroupCommands($commandName)) {
                    return 0;
                }
                
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
        
        // Show appropriate name based on project context
        $appName = $this->isInTreeHouseProject() ? self::PROJECT_MANAGER_NAME : self::PROJECT_CREATOR_NAME;
        $this->output->writeln("<info>" . $appName . "</info> <comment>version " . self::VERSION . "</comment>");
        $this->output->writeln("");
        $this->output->writeln("<comment>Usage:</comment>");
        $this->output->writeln("  treehouse <command> [options] [arguments]");
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
        $this->output->writeln("  treehouse " . $command->getName() . " " . $command->getSynopsis());
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
        // Show appropriate name based on project context
        $appName = $this->isInTreeHouseProject() ? self::PROJECT_MANAGER_NAME : self::PROJECT_CREATOR_NAME;
        $this->output->writeln($appName . " " . self::VERSION);
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
     * Show commands for a specific group
     */
    private function showGroupCommands(string $groupName): bool
    {
        $groups = $this->groupCommands();
        
        // Check if the group exists
        if (!isset($groups[$groupName])) {
            return false;
        }
        
        $this->output->writeln("<info>" . self::NAME . "</info> <comment>version " . self::VERSION . "</comment>");
        $this->output->writeln("");
        $this->output->writeln("<comment>Available {$groupName} commands:</comment>");
        $this->output->writeln("");
        
        foreach ($groups[$groupName] as $command) {
            $this->output->writeln(sprintf("  <info>%-20s</info> %s", $command->getName(), $command->getDescription()));
        }
        
        $this->output->writeln("");
        $this->output->writeln("<comment>Usage:</comment>");
        $this->output->writeln("  treehouse <command> [options] [arguments]");
        $this->output->writeln("");
        $this->output->writeln("Run 'treehouse <command> --help' for more information on a specific command.");
        
        return true;
    }

    /**
     * Detect script name from argv[0]
     */
    private function detectScriptName(string $scriptPath): string
    {
        $basename = basename($scriptPath);
        
        // Remove file extension if present
        $name = pathinfo($basename, PATHINFO_FILENAME);
        
        // Return 'treehouse' if the script name contains 'treehouse', otherwise 'th'
        return str_contains($name, 'treehouse') ? 'treehouse' : 'th';
    }

    /**
     * Check if we're in a TreeHouse project directory or any subdirectory within one
     */
    private function isInTreeHouseProject(): bool
    {
        $currentDir = $this->workingDirectory;
        
        // Traverse up the directory tree to find a TreeHouse project root
        while ($currentDir !== '/' && $currentDir !== '' && strlen($currentDir) > 1) {
            // Check for composer.json with treehouse dependency
            $composerPath = $currentDir . '/composer.json';
            if (file_exists($composerPath)) {
                $composerContent = file_get_contents($composerPath);
                if ($composerContent !== false) {
                    $composer = json_decode($composerContent, true);
                    if (is_array($composer)) {
                        // Check if TreeHouse is in require or require-dev
                        $hasTreeHouse = isset($composer['require']['lengthofrope/treehouse']) ||
                                       isset($composer['require-dev']['lengthofrope/treehouse']);
                        
                        if ($hasTreeHouse) {
                            return true;
                        }
                    }
                }
            }
            
            // Check for TreeHouse config directory
            if (is_dir($currentDir . '/config') &&
                file_exists($currentDir . '/config/app.php')) {
                return true;
            }
            
            // Check for TreeHouse source directory structure
            if (is_dir($currentDir . '/src/TreeHouse')) {
                return true;
            }
            
            // Move up one directory level
            $parentDir = dirname($currentDir);
            
            // Prevent infinite loop if dirname returns the same directory
            if ($parentDir === $currentDir) {
                break;
            }
            
            $currentDir = $parentDir;
        }
        
        return false;
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
    private array $argumentMapping = [];
    private array $optionShortcuts = [];

    public function __construct(InputInterface $input, Command $command)
    {
        $this->input = $input;
        $this->command = $command;
        
        // Extract default values and shortcuts from command options
        foreach ($command->getOptions() as $name => $config) {
            if (isset($config['default'])) {
                $this->optionDefaults[$name] = $config['default'];
            }
            if (!empty($config['shortcut'])) {
                $this->optionShortcuts[$config['shortcut']] = $name;
            }
        }
        
        // Map command arguments to parsed arguments
        $argumentNames = array_keys($command->getArguments());
        foreach ($argumentNames as $index => $argumentName) {
            if ($index === 0) {
                // First argument after command
                $this->argumentMapping[$argumentName] = 'arg1';
            } else {
                $this->argumentMapping[$argumentName] = "arg" . ($index + 1);
            }
        }
    }

    public function getArgument(string $name): mixed
    {
        // Check if this is a mapped argument
        if (isset($this->argumentMapping[$name])) {
            $parsedName = $this->argumentMapping[$name];
            $value = $this->input->getArgument($parsedName);
            
            // If not found, try to get default value
            if ($value === null) {
                $arguments = $this->command->getArguments();
                if (isset($arguments[$name]['default'])) {
                    return $arguments[$name]['default'];
                }
            }
            
            return $value;
        }
        
        return $this->input->getArgument($name);
    }

    public function hasArgument(string $name): bool
    {
        // Check if this is a mapped argument
        if (isset($this->argumentMapping[$name])) {
            $parsedName = $this->argumentMapping[$name];
            return $this->input->hasArgument($parsedName);
        }
        
        return $this->input->hasArgument($name);
    }

    public function getArguments(): array
    {
        return $this->input->getArguments();
    }

    public function getOption(string $name): mixed
    {
        // First try to get the option directly
        $value = $this->input->getOption($name);
        
        // If not found, try to get it via shortcut
        if ($value === null) {
            $shortcuts = array_keys($this->optionShortcuts, $name);
            foreach ($shortcuts as $shortcut) {
                $value = $this->input->getOption($shortcut);
                if ($value !== null) {
                    break;
                }
            }
        }
        
        // Only use default if the option is completely absent
        if ($value === null && isset($this->optionDefaults[$name])) {
            return $this->optionDefaults[$name];
        }
        
        return $value;
    }

    public function hasOption(string $name): bool
    {
        // Check if option exists directly
        if ($this->input->hasOption($name)) {
            return true;
        }
        
        // Check if it exists via shortcut
        $shortcuts = array_keys($this->optionShortcuts, $name);
        foreach ($shortcuts as $shortcut) {
            if ($this->input->hasOption($shortcut)) {
                return true;
            }
        }
        
        // Check if it has a default value
        return array_key_exists($name, $this->optionDefaults);
    }

    public function getOptions(): array
    {
        $options = $this->input->getOptions();
        
        // Merge in defaults for options that weren't provided
        foreach ($this->optionDefaults as $name => $default) {
            if (!array_key_exists($name, $options)) {
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