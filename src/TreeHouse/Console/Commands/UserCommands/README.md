# User Management Commands

This directory contains comprehensive user management commands for the TreeHouse CLI application. These commands provide a complete interface for managing user accounts, roles, and permissions.

## Available Commands

### `user:create` - Create User Command
Creates new user accounts with validation and security features.

**Usage:**
```bash
th user:create [name] [email] [options]
th user:create --interactive
th user:create "John Doe" john@example.com --role=admin --verified
```

**Options:**
- `--role, -r` - User role (admin, editor, viewer) [default: viewer]
- `--password, -p` - User password (will prompt if not provided)
- `--interactive, -i` - Use interactive mode
- `--verified` - Mark email as verified

**Features:**
- Interactive prompts for missing information
- Email validation and uniqueness checking
- Secure password input with confirmation
- Role validation with available options
- Email verification status setting

### `user:list` - List Users Command
Lists all user accounts with filtering and formatting options.

**Usage:**
```bash
th user:list
th user:list --role=admin
th user:list --verified --format=json
th user:list --limit=10 --format=csv
```

**Options:**
- `--role, -r` - Filter by role (admin, editor, viewer)
- `--verified` - Show only verified users
- `--unverified` - Show only unverified users
- `--format, -f` - Output format (table, json, csv) [default: table]
- `--limit, -l` - Maximum number of users to show [default: 50]

**Features:**
- Multiple output formats (table, JSON, CSV)
- Role-based filtering
- Email verification status filtering
- Pagination support
- Grouped table display by role

### `user:update` - Update User Command
Updates existing user account information with validation.

**Usage:**
```bash
th user:update [user_id|email] [options]
th user:update john@example.com --role=editor
th user:update 123 --interactive
th user:update john@example.com --verify --name="John Smith"
```

**Options:**
- `--name` - Update user name
- `--email` - Update email address
- `--password, -p` - Update password
- `--role, -r` - Update user role (admin, editor, viewer)
- `--verify` - Mark email as verified
- `--unverify` - Mark email as unverified
- `--interactive, -i` - Use interactive mode

**Features:**
- Find users by ID or email
- Selective field updates
- Email uniqueness validation
- Current vs new value confirmation
- Interactive mode for guided updates
- Secure password handling

### `user:delete` - Delete User Command
Deletes user accounts with safety confirmations.

**Usage:**
```bash
th user:delete [user_id|email] [options]
th user:delete john@example.com
th user:delete 123 --force
th user:delete john@example.com --soft
```

**Options:**
- `--force, -f` - Skip confirmation prompts
- `--soft` - Soft delete (mark as deleted instead of removing)

**Features:**
- Multiple safety confirmations
- User information display before deletion
- Soft delete support (if deleted_at column exists)
- Force mode for batch operations
- Final confirmation for permanent deletion

### `user:role` - User Role Management Command
Manages user roles with individual and bulk operations.

**Usage:**
```bash
# Assign role to user
th user:role assign [user_id|email] [role]
th user:role assign john@example.com admin

# List users by role
th user:role list
th user:role list --format=json

# Bulk role changes
th user:role bulk --from-role=viewer --to-role=editor

# Role statistics
th user:role stats
```

**Actions:**
- `assign` - Assign role to specific user
- `list` - List users grouped by role
- `bulk` - Bulk role change operations
- `stats` - Show role statistics

**Options:**
- `--from-role` - Source role for bulk operations
- `--to-role` - Target role for bulk operations
- `--force, -f` - Skip confirmation prompts
- `--format` - Output format (table, json, csv) [default: table]

**Features:**
- Individual role assignment
- Bulk role changes with preview
- Role statistics with counts and percentages
- Multiple output formats
- Confirmation prompts for safety

## Available Roles

The system supports three built-in roles:

- **admin** - Full system access
- **editor** - Content editing capabilities
- **viewer** - Read-only access (default)

## Database Configuration

All user commands automatically load database configuration from environment variables:

- `DB_CONNECTION` or `DB_DRIVER` - Database driver (mysql, sqlite, pgsql)
- `DB_HOST` - Database host
- `DB_PORT` - Database port
- `DB_DATABASE` - Database name
- `DB_USERNAME` - Database username
- `DB_PASSWORD` - Database password
- `DB_CHARSET` - Character set (default: utf8mb4)

## Security Features

### Password Security
- Minimum 6 character requirement
- Hidden input during entry
- Password confirmation required
- Secure hashing using PHP's password_hash()

### Email Validation
- Format validation using filter_var()
- Uniqueness checking across all users
- Email verification status tracking

### Role Validation
- Restricted to predefined roles
- Default role assignment (viewer)
- Role change confirmations

### Safety Confirmations
- Multiple confirmation prompts for destructive operations
- User information display before changes
- Force flags for batch operations
- Final confirmation for permanent deletions

## Examples

### Create a new admin user interactively
```bash
th user:create --interactive
```

### List all admin users in JSON format
```bash
th user:list --role=admin --format=json
```

### Update user role and verify email
```bash
th user:update john@example.com --role=editor --verify
```

### Bulk change all viewers to editors
```bash
th user:role bulk --from-role=viewer --to-role=editor
```

### Show role distribution statistics
```bash
th user:role stats
```

### Soft delete a user account
```bash
th user:delete john@example.com --soft
```

## Error Handling

All commands include comprehensive error handling:

- Database connection errors
- Validation failures
- User not found errors
- Duplicate email errors
- Invalid role errors
- Permission errors

Commands return appropriate exit codes:
- `0` - Success
- `1` - Error or failure

## Integration

These commands are automatically registered in the TreeHouse Console Application and appear in the `user` command group when running `th --help`.