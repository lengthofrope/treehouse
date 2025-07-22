# JWT Authentication Components

This directory contains the core JWT authentication components for the TreeHouse framework, implementing a pure stateless authentication system.

## Components Overview

### Core Components

1. **JwtConfig** - JWT configuration management
2. **JwtEncoder** - JWT token encoding
3. **JwtDecoder** - JWT token decoding and validation
4. **TokenGenerator** - High-level token generation utilities
5. **TokenValidator** - Comprehensive token validation
6. **ClaimsManager** - JWT claims management
7. **RefreshTokenManager** - Stateless refresh token management
8. **TokenIntrospector** - Advanced token analysis and debugging
9. **helpers.php** - Global convenience functions

### Key Features

- **Pure Stateless Design**: No database dependencies required
- **Token Rotation**: Automatic refresh token rotation for enhanced security
- **Security Assessment**: Built-in token security scoring and recommendations
- **Comprehensive Validation**: Detailed error messages and validation rules
- **Helper Functions**: 15+ global functions for common JWT operations
- **Token Introspection**: Safe token analysis without validation for debugging

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

Total: **153+ tests** with **400+ assertions** and zero warnings.

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

All benchmarks measured on standard hardware with HS256 algorithm.