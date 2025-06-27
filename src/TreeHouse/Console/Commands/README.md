# TreeHouse Console Commands

This directory contains all CLI commands for the TreeHouse framework, organized by functional groups.

## Command Structure

Commands are organized into logical groups based on their functionality:

### CacheCommands/
Cache management commands for optimizing application performance.

- **CacheClearCommand** (`cache:clear`) - Clear all cached data
- **CacheStatsCommand** (`cache:stats`) - Display cache statistics and usage information
- **CacheWarmCommand** (`cache:warm`) - Pre-populate cache with frequently accessed data

### DatabaseCommands/
Database-related operations and migrations.

- **MigrateRunCommand** (`migrate:run`) - Execute pending database migrations

### DevelopmentCommands/
Development tools and utilities for local development.

- **ServeCommand** (`serve`) - Start a local development server
- **TestRunCommand** (`test:run`) - Run PHPUnit tests with optional filtering and coverage

### ViewCommands/
Template and view management commands.

- **ViewClearCommand** (`view:clear`) - Clear compiled view cache
- **ViewCompileCommand** (`view:compile`) - Pre-compile all view templates
- **ViewCacheCommand** (`view:cache`) - Display view cache information and statistics

## Creating New Commands

To create a new command:

1. **Choose the appropriate group directory** or create a new one if needed
2. **Extend the base Command class** from `src/TreeHouse/Console/Command.php`
3. **Implement required methods**:
   - `configure()` - Set command name, description, and options
   - `execute()` - Main command logic
4. **Register the command** in `src/TreeHouse/Console/Application.php`

### Example Command Structure

```php
<?php

namespace TreeHouse\Console\Commands\YourGroup;

use TreeHouse\Console\Command;
use TreeHouse\Console\Input\InputInterface;
use TreeHouse\Console\Output\OutputInterface;

class YourCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('group:action')
             ->setDescription('Description of what this command does')
             ->addOption('option', 'o', 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Command implementation
        $output->writeln('Command executed successfully');
        return 0;
    }
}
```

## Command Registration

All commands must be registered in the main Application class:

```php
// In src/TreeHouse/Console/Application.php
$this->addCommand(new YourGroup\YourCommand());
```

## Command Naming Convention

Commands follow a consistent naming pattern:
- **Namespace**: `group:action` (e.g., `cache:clear`, `view:compile`)
- **Class Name**: `{Action}{Group}Command` (e.g., `ClearCacheCommand`, `CompileViewCommand`)
- **File Name**: Match the class name with `.php` extension

## Available Input/Output Features

### Input Options
- **Boolean flags**: `--flag` or `-f`
- **Value options**: `--option=value` or `-o value`
- **Arguments**: Positional parameters

### Output Formatting
- **Colors**: Success (green), error (red), warning (yellow), info (blue)
- **Styles**: Bold, underline, italic
- **Progress indicators**: For long-running operations
- **Tables**: For structured data display

## Best Practices

1. **Keep commands focused** - Each command should do one thing well
2. **Provide helpful descriptions** - Both for the command and its options
3. **Handle errors gracefully** - Return appropriate exit codes
4. **Use consistent output formatting** - Follow the established color scheme
5. **Add confirmation prompts** - For destructive operations
6. **Support dry-run mode** - Where applicable, allow preview of changes
7. **Provide progress feedback** - For long-running operations

## Exit Codes

Commands should return appropriate exit codes:
- **0**: Success
- **1**: General error
- **2**: Invalid arguments or options
- **3**: Operation cancelled by user