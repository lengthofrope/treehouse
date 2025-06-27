# TreeHouse CLI Tool

The TreeHouse CLI tool (`th`) provides command-line functionality for the TreeHouse PHP framework.

## Installation

The CLI tool is automatically available after installing the TreeHouse framework. Make sure the `bin/th` file is executable:

```bash
chmod +x bin/th
```

## Usage

```bash
./bin/th <command> [options] [arguments]
```

## Available Commands

### Cache Commands

- **`cache:clear`** - Clear cached data
  - `--driver, -d` - Specific cache driver to clear
  - `--key, -k` - Specific cache key pattern to clear
  - `--force, -f` - Force clearing without confirmation

- **`cache:stats`** - Display cache statistics
  - `--verbose, -v` - Show detailed statistics

- **`cache:warm`** - Warm up the cache
  - `--views` - Warm view cache by pre-compiling templates

### Database Commands

- **`migrate:run`** - Run pending database migrations
  - `--force, -f` - Force run migrations in production
  - `--step` - Number of migrations to run

### Development Commands

- **`serve`** - Start a local development server
  - `--host` - Server host (default: 127.0.0.1)
  - `--port, -p` - Server port (default: 8000)
  - `--docroot, -d` - Document root (default: public)

## Global Options

- `--help, -h` - Display help information
- `--version, -V` - Display version information
- `--debug` - Enable debug mode

## Examples

```bash
# Clear all cache
./bin/th cache:clear --force

# Display cache statistics
./bin/th cache:stats

# Start development server
./bin/th serve --port 8080

# Run database migrations
./bin/th migrate:run --force

# Get help for a specific command
./bin/th cache:clear --help
```

## Architecture

The CLI tool is built with a custom command framework that includes:

- **Application Class**: Main CLI application handler
- **Command Base Class**: Abstract base for all commands
- **Input/Output System**: Custom input parsing and output formatting
- **Configuration Loader**: Multi-source configuration management
- **ANSI Color Support**: Terminal color formatting

## Adding New Commands

To add a new command:

1. Create a new command class extending `LengthOfRope\TreeHouse\Console\Command`
2. Implement the `configure()` and `execute()` methods
3. Register the command in `Application::registerCommands()`

Example:

```php
<?php

namespace LengthOfRope\TreeHouse\Console\Commands\MyCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;

class MyCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('my:command')
            ->setDescription('My custom command')
            ->setHelp('This is my custom command.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello from my command!');
        return 0;
    }
}
```

## Framework Integration

The CLI tool integrates deeply with TreeHouse framework components:

- **Cache System**: Direct integration with CacheManager and FileCache
- **Database**: Uses TreeHouse Connection and migration system
- **View Engine**: Integrates with template compilation and caching
- **Configuration**: Supports .env files and PHP configuration files

## Zero Dependencies

The CLI tool is built with zero external dependencies, using only native PHP functionality and the existing TreeHouse framework components.