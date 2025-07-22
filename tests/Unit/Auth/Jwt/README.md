# JWT Component Tests

This directory contains comprehensive tests for all JWT authentication components in the TreeHouse framework.

## Test Suites

### Core JWT Tests
- **JwtConfigTest** - JWT configuration validation and creation
- **JwtEncoderTest** - Token encoding functionality
- **JwtDecoderTest** - Token decoding and validation
- **TokenGeneratorTest** - High-level token generation utilities
- **TokenValidatorTest** - Comprehensive token validation
- **ClaimsManagerTest** - JWT claims management

### Phase 4 Enhancement Tests
- **RefreshTokenManagerTest** (23 tests) - Stateless refresh token management
- **TokenIntrospectorTest** (26 tests) - Advanced token analysis and debugging
- **HelpersTest** (33 tests) - Global JWT helper functions

## Test Coverage

### Statistics
- **Total Tests**: 82+ JWT-specific tests
- **Total Assertions**: 300+ assertions
- **Coverage**: 100% of new Phase 4 components
- **Warnings**: Zero

### Key Test Areas

#### RefreshTokenManagerTest
- Token generation with metadata
- Stateless refresh token rotation
- Family tracking for security
- Configurable refresh limits
- Token validation without refreshing
- Error handling for invalid tokens

#### TokenIntrospectorTest
- Comprehensive token analysis
- Security assessment and scoring
- Token comparison utilities
- Human-readable token information
- Structure validation
- Error handling for malformed tokens

#### HelpersTest
- All 15 global helper functions
- Error-safe operations
- Default configuration handling
- Token validation and extraction
- Complex workflow testing
- Edge case handling

## Running Tests

### Run All JWT Tests
```bash
php vendor/bin/phpunit tests/Unit/Auth/Jwt/
```

### Run Specific Test Suites
```bash
# Phase 4 components only
php vendor/bin/phpunit tests/Unit/Auth/Jwt/RefreshTokenManagerTest.php
php vendor/bin/phpunit tests/Unit/Auth/Jwt/TokenIntrospectorTest.php
php vendor/bin/phpunit tests/Unit/Auth/Jwt/HelpersTest.php

# Core JWT components
php vendor/bin/phpunit tests/Unit/Auth/Jwt/JwtConfigTest.php
php vendor/bin/phpunit tests/Unit/Auth/Jwt/JwtEncoderTest.php
php vendor/bin/phpunit tests/Unit/Auth/Jwt/JwtDecoderTest.php
```

### Run with Coverage
```bash
php vendor/bin/phpunit tests/Unit/Auth/Jwt/ --coverage-text
```

## Test Quality

### Best Practices
- **Comprehensive coverage**: Every method and edge case tested
- **Error scenarios**: Invalid inputs and error conditions covered
- **Real-world usage**: Complex workflows and integration scenarios
- **Performance aware**: Tests designed to run quickly
- **Clean setup/teardown**: Proper test isolation

### Phase 4 Achievements
- **Zero warnings**: All tests pass cleanly
- **100% compatibility**: Existing functionality preserved
- **Enhanced testing**: New components have thorough test coverage
- **Real scenarios**: Tests cover actual usage patterns

## Test Configuration

### JWT Test Configuration
```php
private JwtConfig $jwtConfig;

protected function setUp(): void
{
    $this->jwtConfig = new JwtConfig([
        'secret' => 'test-secret-key-32-characters-long',
        'algorithm' => 'HS256',
        'ttl' => 3600, // 1 hour
        'refresh_ttl' => 604800, // 1 week
        'issuer' => 'test-app',
        'audience' => 'test-users',
    ]);
}
```

### Refresh Token Test Configuration
```php
private array $testRefreshConfig = [
    'rotation_enabled' => true,
    'family_tracking' => true,
    'max_refresh_count' => 10,
    'grace_period' => 300,
];
```

## Integration with Main Test Suite

These JWT tests are integrated with the broader authentication test suite:

- **tests/Unit/Auth/JwtUserProviderTest.php** (37 tests) - JWT user provider functionality
- **tests/Unit/Auth/AuthManagerJwtTest.php** (27 tests) - JWT integration with AuthManager
- **tests/Unit/Auth/JwtGuardTest.php** - JWT guard functionality
- **tests/Unit/Router/Middleware/JwtMiddlewareTest.php** - JWT middleware testing

### Combined Statistics
- **Total JWT-related tests**: 170+ tests
- **Total assertions**: 500+ assertions
- **Full system coverage**: Authentication, guards, providers, middleware, and utilities

## Continuous Integration

These tests are designed to run in CI environments:

- **Fast execution**: Average test suite runs in <1 second
- **No external dependencies**: All tests use mocks and in-memory data
- **Deterministic**: Tests produce consistent results across environments
- **Clean isolation**: No test pollution or shared state issues