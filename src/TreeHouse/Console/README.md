# TreeHouse CLI Tool

## Overview

The TreeHouse CLI provides a comprehensive command-line interface for managing TreeHouse applications. It includes commands for project scaffolding, cache management, database operations, development tools, and user management with RBAC support.

## Installation

The CLI tool is included with the TreeHouse framework and accessible via the `th` command:

```bash
# Make the CLI executable
chmod +x bin/treehouse

# Create a global symlink (optional)
ln -s /path/to/treehouse/bin/treehouse /usr/local/bin/th
```

## Usage

```bash
# Show all available commands
th

# Get help for any command
th <command> --help

# Show version information
th --version
```

### Cache Commands

```bash
# Clear all caches
th cache:clear

# Show cache statistics
th cache:stats

# Warm up caches
th cache:warm
```

### Database Commands

```bash
# Run database migrations
th migrate:run

# Check migration status
th migrate:status
```

### Development Commands

```bash
# Start development server
th serve [--port=8000] [--host=localhost]

# Run tests
th test:run [--filter=pattern] [--coverage]
```

### User Management Commands

```bash
# Create a new user
th user:create <email> <name> [--password=secret] [--role=member]

# List all users
th users:list [--role=admin] [--limit=50]

# Update user information
th user:update <email> [--name="New Name"] [--role=editor]

# Delete a user
th user:delete <email> [--force]

# Manage user roles
th user:role <email> <action> <role>
# Actions: assign, remove, list
```

### JWT Management Commands (Phase 5)

```bash
# Generate JWT tokens
th jwt:generate <user-id> [--claims='{"role":"admin"}'] [--ttl=3600] [--format=json]

# Validate JWT tokens
th jwt:validate <token> [--format=table]

# Decode JWT tokens (without validation)
th jwt:decode <token> [--format=json]

# Manage key rotation
th jwt:rotate-keys [--algorithm=HS256] [--force]

# Check security status
th jwt:security [--hours=24] [--format=table]

# Validate JWT configuration
th jwt:config [--validate] [--format=table]
```

## Global Options

All commands support these global options:

- `-h, --help`: Display help information
- `-V, --version`: Display version information
- `--debug`: Enable debug mode with stack traces

```bash
# Examples
th cache:clear --help
th --version
th serve --debug
```

## Architecture

The CLI system is built with a modular architecture:

### Core Components

- **[`Application`](Application.php:36)**: Main CLI application with command registration and routing
- **[`Command`](Command.php:20)**: Abstract base class for all commands
- **[`InputParser`](Input/InputParser.php:1)**: Parses command-line arguments and options
- **[`ConsoleOutput`](Output/ConsoleOutput.php:1)**: Handles formatted console output
- **[`ConfigLoader`](Helpers/ConfigLoader.php:1)**: Loads configuration from various sources

### Command Categories

Commands are organized into logical groups:

1. **Project Management**: `new-project`
2. **Cache Operations**: `cache:clear`, `cache:stats`, `cache:warm`
3. **Database Operations**: `migrate:run`, `migrate:status`
4. **Development Tools**: `serve`, `test:run`
5. **User Management**: `user:create`, `users:list`, `user:update`, `user:delete`, `user:role`
6. **JWT Management (Phase 5)**: `jwt:generate`, `jwt:validate`, `jwt:decode`, `jwt:rotate-keys`, `jwt:security`, `jwt:config`

### Input/Output System

The CLI uses a sophisticated input/output system:

```php
// Input parsing with arguments and options
$input = new InputParser();
$parsedInput = $input->parse($argv);

// Formatted output with styling
$output = new ConsoleOutput();
$output->writeln("<info>Success!</info>");
$output->writeln("<error>Error occurred</error>");
$output->writeln("<comment>Note: This is a comment</comment>");
```

### Enhanced Input Processing

The [`EnhancedInput`](Application.php:402) class provides advanced input handling:

- **Default Value Resolution**: Automatically applies command-defined defaults
- **Shortcut Mapping**: Maps short options to long option names
- **Argument Mapping**: Maps named arguments to positional arguments
- **Type Coercion**: Converts string inputs to appropriate types

## Adding New Commands

Create custom commands by extending the base Command class:

```php
use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;

class MyCustomCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('my:command')
             ->setDescription('Description of my command')
             ->setHelp('Detailed help text for the command')
             ->addArgument('name', InputArgument::REQUIRED, 'Name argument')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force execution');
    }
    
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $force = $input->getOption('force');
        
        $this->info($output, "Processing: {$name}");
        
        if ($force) {
            $this->warn($output, 'Force mode enabled');
        }
        
        // Command logic here
        
        $this->success($output, 'Command completed successfully');
        return 0; // Success exit code
    }
}
```

### Command Registration

Register new commands in the [`Application`](Application.php:91) constructor:

```php
private function registerCommands(): void
{
    // Existing commands...
    
    // Register custom command
    $this->register(new MyCustomCommand());
}
```

## Framework Integration

The CLI integrates seamlessly with the TreeHouse framework:

### Database Integration

Commands can access the database through the global `db()` helper:

```php
public function execute(InputInterface $input, OutputInterface $output): int
{
    $db = db();
    $users = $db->select('SELECT * FROM users');
    
    foreach ($users as $user) {
        $output->writeln("User: {$user['name']}");
    }
    
    return 0;
}
```

### Configuration Access

Access application configuration through the ConfigLoader:

```php
public function execute(InputInterface $input, OutputInterface $output): int
{
    $config = $this->getApplication()->getConfig();
    $dbConfig = $config->get('database');
    
    // Use configuration...
    
    return 0;
}
```

### RBAC Integration

User management commands integrate with the RBAC system:

```php
// Create user with role
th user:create john@example.com "John Doe" --role=editor

// Assign additional roles
th user:role john@example.com assign admin

// List user roles
th user:role john@example.com list

// Remove role
th user:role john@example.com remove editor
```

### Cache Integration

Cache commands work with the framework's cache system:

```php
// Clear specific cache stores
th cache:clear --store=views

// Show detailed cache statistics
th cache:stats --detailed

// Warm specific cache types
th cache:warm --type=routes
```

### Development Integration

Development commands integrate with the framework's development tools:

```php
// Start server with custom configuration
th serve --port=8080 --host=0.0.0.0

// Run tests with coverage
th test:run --coverage --format=html

// Run specific test suites
th test:run --filter=UserTest
```

## Command Examples

### Project Scaffolding

```bash
# Create a new TreeHouse project
th new-project my-app
cd my-app

# The command creates:
# - Project structure
# - Configuration files
# - Sample controllers and views
# - Database migrations
# - Environment configuration
```

### Cache Management

```bash
# Clear all caches
th cache:clear
# Output: Cache cleared successfully

# Show cache statistics
th cache:stats
# Output: 
# Cache Statistics:
# - File cache: 150 items, 2.5MB
# - View cache: 45 items, 890KB
# - Route cache: 1 item, 15KB

# Warm up caches
th cache:warm
# Output: Cache warmed successfully
```

### Database Operations

```bash
# Run pending migrations
th migrate:run
# Output:
# Running migration: 001_create_users_table... OK
# Running migration: 002_create_roles_table... OK
# All migrations completed successfully

# Check migration status
th migrate:status
# Output:
# Migration Status:
# ✓ 001_create_users_table (2023-01-01 10:00:00)
# ✓ 002_create_roles_table (2023-01-01 10:01:00)
# ✗ 003_add_permissions_table (pending)
```

### User Management

```bash
# Create admin user
th user:create admin@example.com "Admin User" --password=secret --role=admin
# Output: User created successfully with ID: 1

# List users with role filter
th users:list --role=admin
# Output:
# ID | Name       | Email            | Role  | Created
# 1  | Admin User | admin@example.com| admin | 2023-01-01

# Update user role
th user:update admin@example.com --role=super-admin
# Output: User updated successfully

# Manage user roles
th user:role admin@example.com assign editor
# Output: Role 'editor' assigned to user

th user:role admin@example.com list
# Output: User roles: admin, editor

th user:role admin@example.com remove editor
# Output: Role 'editor' removed from user
```

### Development Server

```bash
# Start development server
th serve
# Output: TreeHouse development server started at http://localhost:8000

# Start with custom settings
th serve --port=3000 --host=0.0.0.0
# Output: TreeHouse development server started at http://0.0.0.0:3000
```

### Testing

```bash
# Run all tests
th test:run
# Output:
# Running TreeHouse Test Suite...
# ✓ UserTest::testUserCreation
# ✓ AuthTest::testLogin
# ✓ RoleTest::testRoleAssignment
# Tests: 45, Assertions: 150, Passed: 45

# Run with coverage
th test:run --coverage
# Output: Test coverage report generated in coverage-html/

# Run specific tests
th test:run --filter=UserTest
# Output: Running filtered tests matching 'UserTest'...
```

### JWT Management (Phase 5)

```bash
# Generate JWT token for user
th jwt:generate 123 --claims='{"role":"admin","department":"IT"}'
# Output:
# JWT Token Generated Successfully:
# Token: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
# User ID: 123
# Claims: {"role":"admin","department":"IT"}
# Expires: 2024-01-01 13:00:00

# Validate JWT token
th jwt:validate eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
# Output:
# Token Validation Result:
# ✓ Token is valid
# ✓ Signature verified
# ✓ Not expired
# User ID: 123
# Claims: {"role":"admin"}

# Decode token structure
th jwt:decode eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9... --format=json
# Output: {"header":{"typ":"JWT","alg":"HS256"},"payload":{"user_id":123},"signature":"..."}

# Rotate JWT signing keys
th jwt:rotate-keys --algorithm=HS256
# Output:
# Key Rotation Completed:
# ✓ New key generated for HS256
# ✓ Old key marked for grace period
# ✓ Key rotation logged
# Total keys: 2 (1 active, 1 in grace period)

# Check security status
th jwt:security --hours=24 --format=table
# Output:
# JWT Security Status (Last 24 Hours):
# | Metric                | Value  |
# |-----------------------|--------|
# | Failed Auth Attempts  | 12     |
# | Blocked IPs           | 2      |
# | Token Validations     | 1,847  |
# | Key Rotations         | 0      |
# | Threat Level          | Low    |

# Validate JWT configuration
th jwt:config --validate
# Output:
# JWT Configuration Validation:
# ✓ Secret key length adequate (64 chars)
# ✓ Algorithm is secure (HS256)
# ✓ TTL is reasonable (900 seconds)
# ✓ Refresh TTL configured (1209600 seconds)
# ⚠ Key rotation not enabled
# ✓ CSRF protection configured
# Overall Status: Good (1 warning)
```

## Error Handling

The CLI provides comprehensive error handling:

```bash
# Command not found
th invalid:command
# Output: 
# Command 'invalid:command' not found.
# 
# Did you mean one of these?
#   cache:clear
#   user:create

# Invalid arguments
th user:create
# Output: Error: Missing required argument 'email'

# Debug mode
th user:create --debug
# Output: 
# Error: Database connection failed
# Stack trace:
# #0 /path/to/file.php(123): DatabaseConnection->connect()
# ...
```

## Output Formatting

The CLI supports rich output formatting:

```php
// In command execute method
$this->info($output, 'Information message');
$this->success($output, 'Success message');
$this->error($output, 'Error message');
$this->warn($output, 'Warning message');
$this->comment($output, 'Comment message');

// Custom styling
$output->writeln('<info>Blue text</info>');
$output->writeln('<error>Red text</error>');
$output->writeln('<comment>Yellow text</comment>');
$output->writeln('<question>Magenta text</question>');
```

## Interactive Features

Commands support interactive input:

```php
// Confirmation prompts
if ($this->confirm($output, 'Are you sure?', false)) {
    // User confirmed
}

// Text input
$name = $this->ask($output, 'Enter your name:', 'Default Name');

// Password input (hidden)
$password = $this->askHidden($output, 'Enter password:');
```

## Configuration

The CLI can be configured through environment variables:

```bash
# Enable debug mode
export TH_DEBUG=true

# Set custom working directory
export TH_WORKING_DIR=/path/to/project

# Database configuration
export DB_HOST=localhost
export DB_DATABASE=treehouse
export DB_USERNAME=root
export DB_PASSWORD=secret
```

## Integration with Other Layers

The Console layer integrates with all other framework layers:

- **Foundation Layer**: Uses application container and configuration system
- **Database Layer**: Provides migration and user management commands
- **Auth Layer**: Integrates with RBAC system for user management and JWT operations
- **Security Layer**: Provides security monitoring and key rotation management
- **Cache Layer**: Provides cache management commands and JWT key storage
- **Router Layer**: Can generate route lists and debugging information
- **View Layer**: Can clear view caches and compile templates

## Phase 5 Enhancements

The Console layer has been enhanced with comprehensive JWT management capabilities:

### Enterprise JWT Operations
- **Token Lifecycle Management**: Generate, validate, and decode tokens
- **Security Operations**: Key rotation, threat monitoring, configuration validation
- **Operational Monitoring**: Real-time security status and performance metrics
- **Developer Tools**: Debug-friendly token analysis and testing utilities

### Production-Ready Features
- **Multiple Output Formats**: JSON, table, and plain text for automation
- **Comprehensive Validation**: Configuration security checks and recommendations
- **Real-time Monitoring**: Security alerts and threat level assessment
- **Operational Excellence**: Automated key rotation and security compliance