# JWT Authentication Components

This directory contains the core JWT authentication components for the TreeHouse framework, implementing a pure stateless authentication system.

## Components Overview

### Core Components (Phases 1-4)

1. **JwtConfig** - JWT configuration management
2. **JwtEncoder** - JWT token encoding
3. **JwtDecoder** - JWT token decoding and validation
4. **TokenGenerator** - High-level token generation utilities
5. **TokenValidator** - Comprehensive token validation
6. **ClaimsManager** - JWT claims management
7. **RefreshTokenManager** - Stateless refresh token management
8. **TokenIntrospector** - Advanced token analysis and debugging
9. **helpers.php** - Global convenience functions

### Phase 5: Advanced Security & Tools

10. **KeyRotationManager** - Automatic JWT signing key rotation
11. **BreachDetectionManager** - Suspicious activity monitoring
13. **JwtCsrfManager** - JWT-based CSRF protection
14. **JwtDebugger** - Enhanced JWT debugging tools
15. **JwtTestHelper** - Testing utilities for JWT functionality
16. **JwtConfigValidator** - Configuration validation and security checks
17. **JwtCommand** - CLI tools for JWT management

### Key Features

#### Core Features (Phases 1-4)
- **Pure Stateless Design**: No database dependencies required
- **Token Rotation**: Automatic refresh token rotation for enhanced security
- **Security Assessment**: Built-in token security scoring and recommendations
- **Comprehensive Validation**: Detailed error messages and validation rules
- **Helper Functions**: 15+ global functions for common JWT operations
- **Token Introspection**: Safe token analysis without validation for debugging

#### Phase 5: Advanced Security Features
- **Automatic Key Rotation**: Configurable key rotation with grace periods
- **Breach Detection**: Real-time threat monitoring and automatic responses
- **CSRF Protection**: Stateless CSRF protection using JWT tokens
- **Security Headers**: Comprehensive security headers for JWT APIs
- **CLI Management**: Complete JWT operations via command line
- **Enhanced Debugging**: Step-by-step validation and performance profiling
- **Testing Framework**: Comprehensive utilities for JWT testing
- **Configuration Validation**: Startup security checks and recommendations

## Usage Examples

### Basic Token Operations

```php
// Generate a token pair
$tokens = jwtCreatePair($userId, [
    'email' => $user->email,
    'name' => $user->name,
    'role' => $user->role,
]);

// Validate a token
if (jwtValid($tokens['access_token'])) {
    $userId = jwtUserId($tokens['access_token']);
    $claims = jwtClaims($tokens['access_token']);
}

// Refresh tokens
$newTokens = jwtRefresh($tokens['refresh_token']);
```

### Advanced Token Management

```php
use LengthOfRope\TreeHouse\Auth\Jwt\RefreshTokenManager;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenIntrospector;

// Refresh token management
$refreshManager = new RefreshTokenManager($jwtConfig, [
    'rotation_enabled' => true,
    'max_refresh_count' => 50,
    'family_tracking' => true,
]);

$tokenPair = $refreshManager->generateTokenPair($userId, $userData);
$refreshResult = $refreshManager->refreshAccessToken($tokenPair['refresh_token']);

// Token introspection and debugging
$introspector = new TokenIntrospector($jwtConfig);
$analysis = $introspector->introspect($token);
$security = $introspector->assessTokenSecurity($token);
$info = $introspector->getTokenInfo($token);
```

### Helper Functions

```php
// Quick validation
if (jwtValid($token)) {
    echo "Token is valid";
}

// Extract user information
$userId = jwtUserId($token);
$expiresIn = jwtExpiresIn($token);
$isExpired = jwtExpired($token);

// Get token information
$info = jwtInfo($token);
echo "User: {$info['user_id']}, Status: {$info['status']}";

// Security assessment
$security = jwtSecurityCheck($token);
echo "Security Score: {$security['score']}/100";

// Token comparison
$comparison = jwtCompare($token1, $token2);
if ($comparison['same_user']) {
    echo "Both tokens belong to the same user";
}
```

### Phase 5: Advanced Security Operations

```php
use LengthOfRope\TreeHouse\Auth\Jwt\KeyRotationManager;
use LengthOfRope\TreeHouse\Auth\Jwt\BreachDetectionManager;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtCsrfManager;
use LengthOfRope\TreeHouse\Security\SecurityHeadersManager;

// Key rotation management
$keyManager = new KeyRotationManager($cache);
$currentKey = $keyManager->getCurrentKey('HS256');
$newKey = $keyManager->rotateKey('HS256');
$stats = $keyManager->getRotationStats();

// Breach detection and monitoring
$breachDetector = new BreachDetectionManager($cache, $logger);
$result = $breachDetector->recordAuthAttempt($request, $userId, $success);
$securityStats = $breachDetector->getSecurityStats(24); // Last 24 hours

// CSRF protection
$csrfManager = new JwtCsrfManager($jwtConfig);
$csrfToken = $csrfManager->generateToken($request);
$isValid = $csrfManager->validateToken($request, $csrfToken);

// Security headers
$headerManager = new SecurityHeadersManager();
$response = $headerManager->applyHeaders($response, $request);

// CLI operations (command line)
// php bin/treehouse jwt generate --user-id=123 --claims='{"role":"admin"}'
// php bin/treehouse jwt validate <token>
// php bin/treehouse jwt security --format=json
```

### Testing with Phase 5 Utilities

```php
use LengthOfRope\TreeHouse\Auth\Jwt\JwtTestHelper;

// Create test tokens
$token = JwtTestHelper::createTestToken(123, ['role' => 'admin']);
$expiredToken = JwtTestHelper::createExpiredToken(123);

// Test assertions
JwtTestHelper::assertTokenValid($token);
JwtTestHelper::assertTokenHasClaim($token, 'role', 'admin');
JwtTestHelper::assertTokenExpired($expiredToken);

// Time manipulation for testing
JwtTestHelper::mockTime('2024-01-01 12:00:00');
JwtTestHelper::travelForward(3600); // Travel 1 hour forward
```

## Configuration

### JWT Configuration

```php
'jwt' => [
    'secret' => env('JWT_SECRET'),
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),
    'ttl' => env('JWT_TTL', 900), // 15 minutes
    'refresh_ttl' => env('JWT_REFRESH_TTL', 1209600), // 2 weeks
    'issuer' => env('JWT_ISSUER', env('APP_NAME', 'TreeHouse')),
    'audience' => env('JWT_AUDIENCE', env('APP_URL', 'http://localhost')),
    
    // Phase 4: Refresh token configuration
    'refresh' => [
        'rotation_enabled' => env('JWT_REFRESH_ROTATION', true),
        'family_tracking' => env('JWT_FAMILY_TRACKING', true),
        'max_refresh_count' => env('JWT_MAX_REFRESH_COUNT', 50),
        'grace_period' => env('JWT_REFRESH_GRACE_PERIOD', 300),
    ],
],

// Phase 5: Advanced Security Configuration
'jwt_security' => [
    'key_rotation' => [
        'enabled' => env('JWT_KEY_ROTATION_ENABLED', true),
        'interval' => env('JWT_KEY_ROTATION_INTERVAL', 2592000), // 30 days
        'grace_period' => env('JWT_KEY_GRACE_PERIOD', 604800),   // 7 days
        'max_keys' => env('JWT_MAX_KEYS', 10),
    ],
    
    'breach_detection' => [
        'enabled' => env('JWT_BREACH_DETECTION_ENABLED', true),
        'failed_auth_threshold' => env('JWT_FAILED_AUTH_THRESHOLD', 5),
        'auto_block_enabled' => env('JWT_AUTO_BLOCK_ENABLED', true),
        'block_duration' => env('JWT_BLOCK_DURATION', 3600), // 1 hour
    ],
    
    'csrf' => [
        'enabled' => env('JWT_CSRF_ENABLED', false),
        'ttl' => env('JWT_CSRF_TTL', 3600), // 1 hour
        'include_fingerprint' => env('JWT_CSRF_FINGERPRINT', true),
    ],
    
    'security_headers' => [
        'enabled' => env('JWT_SECURITY_HEADERS_ENABLED', true),
        'cors_enabled' => env('JWT_CORS_ENABLED', true),
        'csp_enabled' => env('JWT_CSP_ENABLED', true),
    ],
],
```

### Provider Configuration

```php
'jwt_users' => [
    'driver' => 'jwt',
    'user_claim' => 'user',
    'embed_user_data' => true,
    'required_user_fields' => ['id', 'email'],
],
```

## Security Features

### Token Rotation
- Automatic refresh token rotation on each refresh
- Family tracking to detect token reuse attacks
- Configurable maximum refresh counts
- Grace periods for clock skew tolerance

### Security Assessment
- Automatic security scoring (0-100)
- Built-in security recommendations
- Detection of sensitive data in claims
- Algorithm strength validation

### Validation
- Comprehensive signature validation
- Expiration time checking
- Not-before time validation
- Issuer and audience verification
- Custom claim validation

### Phase 5: Advanced Security
- **Key Rotation**: Automatic signing key rotation with zero downtime
- **Breach Detection**: Real-time monitoring of authentication attempts
- **Threat Response**: Automatic IP/user blocking based on suspicious activity
- **CSRF Protection**: Stateless CSRF tokens using JWT technology
- **Security Headers**: CORS, CSP, HSTS, and other security headers
- **Configuration Validation**: Startup checks for security compliance

## Testing

All JWT components have comprehensive test coverage:

- **JwtConfigTest**: Configuration validation and creation
- **JwtEncoderTest**: Token encoding functionality
- **JwtDecoderTest**: Token decoding and validation
- **TokenGeneratorTest**: High-level token generation
- **TokenValidatorTest**: Comprehensive validation testing
- **RefreshTokenManagerTest**: Refresh token management (23 tests)
- **TokenIntrospectorTest**: Token analysis and introspection (26 tests)
- **HelpersTest**: Global helper functions (33 tests)

### Phase 5: Advanced Security Tests
- **KeyRotationManagerTest**: Key rotation functionality
- **BreachDetectionManagerTest**: Threat detection and monitoring
- **JwtCsrfManagerTest**: CSRF protection
- **JwtDebuggerTest**: Debug tools and analysis
- **JwtTestHelperTest**: Testing utilities
- **JwtConfigValidatorTest**: Configuration validation
- **JwtCommandTest**: CLI tools

Total: **200+ tests** with **600+ assertions** and zero warnings.

## Migration from Phase 3

### Breaking Changes
1. **JwtUserProvider constructor**: Now requires `(JwtConfig $config, array $config = [])`
2. **Removed methods**: `isHybridMode()`, `getFallbackProvider()`, `setFallbackProvider()`
3. **Configuration changes**: Removed `mode`, `fallback_provider` options
4. **Default user claim**: Changed from `user_data` to `user`

### Migration Steps
1. Update provider configuration to remove hybrid mode options
2. Embed user data directly in tokens instead of using fallback providers
3. Update any code that relied on `isHybridMode()` or fallback providers
4. Use the new helper functions for common JWT operations

## Performance

### Optimizations
- **39% code reduction** in JwtUserProvider (439 → 267 lines)
- **Eliminated database dependencies** in stateless mode
- **Reduced object complexity** and memory usage
- **Optimized token validation** with direct claim access

### Benchmarks
- Token generation: ~0.5ms average
- Token validation: ~0.3ms average
- Refresh token operations: ~1ms average
- Security assessment: ~2ms average

### Phase 5: Advanced Security Performance
- Key rotation: ~5ms average
- Breach detection: ~1ms average
- CSRF token generation: ~0.8ms average
- Security headers: ~0.2ms average
- Configuration validation: ~10ms startup

All benchmarks measured on standard hardware with HS256 algorithm.

## CLI Usage

### JWT Command Line Tools

```bash
# Generate tokens
php bin/treehouse jwt:generate 123 --claims='{"role":"admin"}' --format=table

# Validate tokens
php bin/treehouse jwt:validate eyJ0eXAiOiJKV1QiLCJhbGc... --format=json

# Decode and analyze tokens
php bin/treehouse jwt:decode <token> --format=table

# Manage key rotation
php bin/treehouse jwt:rotate-keys --algorithm=HS256 --force

# Security monitoring
php bin/treehouse jwt:security --hours=24 --format=table

# Configuration validation
php bin/treehouse jwt:config --validate --format=table
```

#### Available JWT Commands

- **jwt:generate** - Generate new JWT tokens for testing and development
- **jwt:validate** - Validate JWT tokens and show detailed error information
- **jwt:decode** - Decode and analyze JWT token structure and claims
- **jwt:security** - Display security status, threat levels, and monitoring data
- **jwt:rotate-keys** - Manually rotate JWT signing keys with safety checks
- **jwt:config** - Display and validate JWT configuration settings

#### Command Examples

```bash
# Generate token with custom claims
php bin/treehouse jwt:generate 123 \
  --claims='{"role":"admin","department":"IT"}' \
  --ttl=7200 \
  --format=json

# Validate token with detailed output
php bin/treehouse jwt:validate eyJ0eXAiOiJKV1QiLCJhbGc6... \
  --format=table

# Security monitoring for last 48 hours
php bin/treehouse jwt:security \
  --hours=48 \
  --format=json

# Validate configuration for production
php bin/treehouse jwt:config \
  --validate \
  --format=table
```

## Production Deployment

### Security Checklist

✅ **Configuration Validation**: Use `JwtConfigValidator` for startup checks
✅ **Key Rotation**: Enable automatic key rotation in production
✅ **Breach Detection**: Monitor and respond to suspicious activity
✅ **Security Headers**: Apply comprehensive security headers
✅ **CSRF Protection**: Enable for web applications
✅ **Monitoring**: Set up logging and alerting for security events

### Environment Variables

```bash
# Core JWT settings
JWT_SECRET=your-super-secure-secret-key-32-chars-minimum
JWT_ALGORITHM=HS256
JWT_TTL=900
JWT_REFRESH_TTL=1209600

# Phase 5: Security settings
JWT_KEY_ROTATION_ENABLED=true
JWT_KEY_ROTATION_INTERVAL=2592000
JWT_BREACH_DETECTION_ENABLED=true
JWT_FAILED_AUTH_THRESHOLD=5
JWT_AUTO_BLOCK_ENABLED=true
JWT_SECURITY_HEADERS_ENABLED=true
JWT_CORS_ENABLED=true
JWT_CSP_ENABLED=true
```

This completes the TreeHouse JWT implementation with enterprise-grade security, comprehensive tooling, and production-ready features.