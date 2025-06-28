# User Commands Test Suite

This directory contains comprehensive unit tests for all user management commands in the TreeHouse CLI application. The test suite ensures reliability, security, and proper functionality of user account management operations.

## Test Files Overview

### `CreateUserCommandTest.php`
Tests for the `user:create` command covering user account creation functionality.

**Test Coverage (11 tests):**
- Command configuration validation
- Email format and uniqueness validation
- Password security and minimum length requirements
- Role validation and default assignment
- Interactive mode handling
- Database connection configuration
- Password hashing verification
- Error handling scenarios

**Key Test Methods:**
- `testCommandConfiguration()` - Validates command setup
- `testEmailValidation()` - Tests email format validation
- `testRoleValidation()` - Tests role assignment validation
- `testPasswordMinimumLength()` - Tests password security requirements
- `testEmailUniquenessCheck()` - Tests duplicate email prevention
- `testHashPasswordSecurity()` - Tests password hashing functionality

### `ListUsersCommandTest.php`
Tests for the `user:list` command covering user listing and filtering functionality.

**Test Coverage (12 tests):**
- Command configuration and options
- Multiple output formats (table, JSON, CSV)
- Role-based filtering
- Email verification status filtering
- Pagination with limit option
- Combined filters
- Text truncation functionality
- Database error handling

**Key Test Methods:**
- `testUsersListWithTableFormat()` - Tests default table output
- `testUsersListWithJsonFormat()` - Tests JSON output format
- `testRoleFiltering()` - Tests role-based user filtering
- `testVerifiedUsersFiltering()` - Tests verification status filtering
- `testCombinedFilters()` - Tests multiple filter combinations
- `testTruncateMethod()` - Tests text truncation logic

### `UpdateUserCommandTest.php`
Tests for the `user:update` command covering user account modification functionality.

**Test Coverage (15 tests):**
- Command configuration validation
- User lookup by ID and email
- Field-specific updates (name, email, password, role)
- Email verification status changes
- Input validation for all fields
- Email uniqueness checking for updates
- Interactive mode handling
- Update confirmation flow
- Database error scenarios

**Key Test Methods:**
- `testSuccessfulUserUpdate()` - Tests complete update flow
- `testEmailValidation()` - Tests email format validation
- `testEmailUniquenessCheck()` - Tests email uniqueness for updates
- `testFindUserById()` - Tests user lookup by numeric ID
- `testFindUserByEmail()` - Tests user lookup by email address
- `testEmailVerificationUpdate()` - Tests verification status changes

### `DeleteUserCommandTest.php`
Tests for the `user:delete` command covering user account deletion functionality.

**Test Coverage (14 tests):**
- Command configuration validation
- User lookup functionality
- Hard deletion operations
- Soft deletion with `deleted_at` column detection
- Force mode operation
- Safety confirmation mechanisms
- Database error handling
- Edge cases (user not found, already deleted)

**Key Test Methods:**
- `testForcedHardDelete()` - Tests permanent user deletion
- `testForcedSoftDelete()` - Tests soft deletion functionality
- `testSoftDeleteWithDeletedAtColumn()` - Tests soft delete column detection
- `testInteractiveDeleteWithoutConfirmation()` - Tests cancellation flow
- `testHardDeleteSuccess()` - Tests successful permanent deletion
- `testSoftDeleteUserAlreadyDeleted()` - Tests already deleted scenario

### `UserRoleCommandTest.php`
Tests for the `user:role` command covering role management functionality.

**Test Coverage (18 tests):**
- Command configuration with multiple actions
- Role assignment to individual users
- Bulk role change operations
- User listing by role
- Role statistics generation
- Multiple output formats
- Role validation
- User lookup functionality
- Database error handling

**Key Test Methods:**
- `testAssignRoleSuccess()` - Tests individual role assignment
- `testBulkRoleChangeSuccess()` - Tests bulk role operations
- `testListUsersWithTableFormat()` - Tests role-based user listing
- `testShowRoleStats()` - Tests role distribution statistics
- `testAssignInvalidRole()` - Tests role validation
- `testBulkRoleChangeNoAffectedUsers()` - Tests empty bulk operations

## Test Infrastructure

### Base Testing Framework
All tests extend the `TestCase` base class which provides:
- PHPUnit framework integration
- Reflection utilities for private method testing
- Temporary file and directory management
- Custom assertion methods
- Time mocking capabilities

### Mocking Strategy
Tests use comprehensive mocking to isolate functionality:

**Database Mocking:**
```php
$mockConnection = $this->createMock(Connection::class);
$mockConnection->method('select')->willReturn($sampleData);
$command->method('getDatabaseConnection')->willReturn($mockConnection);
```

**Input/Output Mocking:**
```php
$input = $this->createMockInput(['argument' => 'value'], ['option' => true]);
$output = $this->createMockOutput();
```

**Method Mocking:**
```php
$command = $this->getMockBuilder(CommandClass::class)
    ->onlyMethods(['specificMethod'])
    ->getMock();
```

### Environment Isolation
Each test class manages its own environment:
```php
protected function setUp(): void
{
    // Set up mock environment variables
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['DB_DATABASE'] = ':memory:';
}

protected function tearDown(): void
{
    // Clean up environment variables
    unset($_ENV['DB_CONNECTION']);
}
```

## Test Categories

### 1. Configuration Tests
Validate command setup and metadata:
- Command names and descriptions
- Argument and option definitions
- Default values and help text

### 2. Validation Tests
Test input validation logic:
- Email format validation
- Password strength requirements
- Role validation
- Identifier format checking

### 3. Database Operation Tests
Test database interactions:
- User creation, retrieval, update, deletion
- Query construction and parameter binding
- Connection configuration
- Error handling

### 4. Security Tests
Verify security measures:
- Password hashing
- Input sanitization
- SQL injection prevention
- Access control

### 5. User Interface Tests
Test command-line interaction:
- Output formatting (table, JSON, CSV)
- Interactive prompts
- Confirmation dialogs
- Help text display

### 6. Error Handling Tests
Test error scenarios:
- Database connection failures
- User not found errors
- Validation failures
- Permission errors

### 7. Edge Case Tests
Test boundary conditions:
- Empty results
- Duplicate operations
- Invalid inputs
- Null values

## Running the Tests

### Individual Test Files
```bash
# Run specific command tests
vendor/bin/phpunit tests/Unit/Console/Commands/UserCommands/CreateUserCommandTest.php
vendor/bin/phpunit tests/Unit/Console/Commands/UserCommands/ListUsersCommandTest.php
vendor/bin/phpunit tests/Unit/Console/Commands/UserCommands/UpdateUserCommandTest.php
vendor/bin/phpunit tests/Unit/Console/Commands/UserCommands/DeleteUserCommandTest.php
vendor/bin/phpunit tests/Unit/Console/Commands/UserCommands/UserRoleCommandTest.php
```

### Full User Commands Test Suite
```bash
# Run all user command tests
vendor/bin/phpunit tests/Unit/Console/Commands/UserCommands/

# Run with coverage report
vendor/bin/phpunit --coverage-html coverage-html tests/Unit/Console/Commands/UserCommands/
```

### Specific Test Methods
```bash
# Run specific test method
vendor/bin/phpunit --filter testCommandConfiguration tests/Unit/Console/Commands/UserCommands/CreateUserCommandTest.php
```

## Test Data Patterns

### Sample User Data
```php
$sampleUser = [
    'id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'role' => 'viewer',
    'email_verified' => 0,
    'email_verified_at' => null,
    'created_at' => '2024-01-01 10:00:00'
];
```

### Mock Input Patterns
```php
// Arguments and options
$input = $this->createMockInput(
    ['identifier' => 'test@example.com'], // arguments
    ['role' => 'admin', 'force' => true]  // options
);
```

### Database Query Testing
```php
$mockConnection->expects($this->once())
              ->method('select')
              ->with(
                  $this->stringContains('WHERE role = ?'),
                  $this->equalTo(['admin'])
              )
              ->willReturn($expectedData);
```

## Assertions and Validations

### Common Assertions
- `assertEquals()` - Exact value matching
- `assertStringContainsString()` - Partial string matching
- `assertArrayHasKey()` - Array structure validation
- `assertInstanceOf()` - Type checking
- `assertTrue()/assertFalse()` - Boolean validation

### Custom Assertions
- `assertIsJson()` - JSON format validation
- `assertArrayStructure()` - Nested array validation
- `assertStringContainsAll()` - Multiple substring matching

## Test Coverage Metrics

### Overall Coverage
- **Total Tests:** 70 test methods
- **Commands Tested:** 5 user management commands
- **Scenarios Covered:** Configuration, validation, operations, errors
- **Mock Objects:** Database connections, input/output, command methods

### Per-Command Coverage
- **CreateUserCommand:** 11 tests (configuration, validation, creation)
- **ListUsersCommand:** 12 tests (filtering, formatting, pagination)
- **UpdateUserCommand:** 15 tests (updates, validation, lookup)
- **DeleteUserCommand:** 14 tests (deletion types, safety, confirmations)
- **UserRoleCommand:** 18 tests (role management, statistics, bulk operations)

## Best Practices Implemented

### Test Isolation
- Each test is independent
- Clean setup and teardown
- Mock environment variables
- No shared state between tests

### Comprehensive Coverage
- Happy path scenarios
- Error conditions
- Edge cases
- Security validations

### Maintainable Code
- Clear test method names
- Descriptive assertions
- Modular helper methods
- Consistent patterns

### Performance Optimization
- In-memory database mocking
- Minimal database operations
- Efficient mock objects
- Fast test execution

The test suite provides comprehensive coverage of all user management functionality, ensuring the commands are reliable, secure, and maintainable.