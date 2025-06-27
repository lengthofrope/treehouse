# {{PROJECT_NAME}}

A TreeHouse framework application.

## Installation

```bash
composer install
```

## Development

Start the development server:

```bash
./bin/th serve
```

## Available Commands

- `./bin/th serve` - Start development server
- `./bin/th cache:clear` - Clear application cache
- `./bin/th cache:stats` - Show cache statistics
- `./bin/th test:run` - Run PHPUnit tests
- `./bin/th migrate:run` - Run database migrations
- `./bin/th --help` - Show all available commands

## Directory Structure

```
src/App/          # Application code
config/           # Configuration files
public/           # Web root
resources/views/  # View templates
storage/          # Cache, logs, compiled views
tests/            # Test files
database/         # Migrations
```

## Framework Documentation

TreeHouse is a modern PHP framework built from scratch with zero external dependencies.

For more information, visit: https://github.com/lengthofrope/treehouse