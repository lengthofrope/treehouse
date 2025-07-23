# Authentication System

The TreeHouse Authentication system provides a comprehensive, flexible, and secure authentication solution with support for multiple authentication methods including traditional session-based authentication and modern JWT-based stateless authentication.

## Architecture Overview

The authentication system is built on a modular architecture with clear separation of concerns:

- **Guards**: Handle authentication logic for different contexts (web, API, etc.)
- **User Providers**: Retrieve and validate user data from various sources
- **Middleware**: Protect routes and handle authentication requirements
- **JWT Components**: Stateless token-based authentication (see `Jwt/README.md`)

## Core Components

### Guards

1. **SessionGuard** - Traditional session-based authentication
   - Cookie and session management
   - Remember me functionality
   - CSRF protection integration

2. **JwtGuard** - JWT token-based authentication
   - Stateless authentication
   - Bearer token support
   - Token extraction from headers/cookies

### User Providers

1. **DatabaseUserProvider** - Database-backed user authentication
   - Password hashing and verification
   - User model integration
   - Database query optimization

2. **JwtUserProvider** - Pure stateless JWT user resolution
   - **Phase 4 Enhancement**: Simplified stateless design
   - User data extraction from JWT claims
   - No database dependencies required

### Management

1. **AuthManager** - Central authentication coordinator
   - Guard creation and management
   - Provider configuration
   - Request handling integration

## Phase 4 Enhancements

### JwtUserProvider Cleanup
- **Removed hybrid mode**: Eliminated complex dual-mode authentication
- **Simplified constructor**: Reduced from 4 to 2 parameters
- **Pure stateless**: All user data comes from JWT claims
- **39% code reduction**: From 439 to 267 lines

## Phase 5: Advanced Security & Tools

Phase 5 completes the TreeHouse Authentication system with enterprise-grade security features and developer tools.

### Advanced Security Components

1. **Key Rotation System** - Automatic JWT signing key rotation
2. **Breach Detection** - Real-time threat monitoring and automated responses
3. **CSRF Protection** - Stateless CSRF protection using JWT tokens
4. **Security Headers** - Comprehensive security headers management
5. **Enhanced Debugging** - Advanced JWT debugging and analysis tools
6. **Testing Framework** - Comprehensive JWT testing utilities
7. **Configuration Validation** - Startup security validation
8. **CLI Management** - Complete JWT operations via command line

### Key Rotation & Security
- **Automatic key rotation** with configurable intervals (30 days default)
- **Grace period support** for seamless key transitions (7 days default)
- **Multi-algorithm support** (HS256, RS256, ES256)
- **Encrypted key storage** using the cache system
- **Key versioning** and rotation statistics

### Threat Detection & Response
- **Failed authentication monitoring** with configurable thresholds
- **Token replay attack detection** and prevention
- **IP-based threat blocking** with automatic responses
- **User behavior analysis** and anomaly detection
- **Comprehensive security reporting** and audit logs

### Enterprise Features
- **Production-ready security** with industry standards compliance
- **High availability design** with stateless architecture
- **Operational monitoring** with real-time metrics
- **Developer productivity tools** for debugging and testing

### Configuration Simplification
```php
// Old configuration (hybrid mode)
'jwt_users' => [
    'driver' => 'jwt',
    'mode' => 'stateless', // or 'hybrid'
    'fallback_provider' => 'users',
    'user_claim' => 'user_data',
    // ...
],

// New simplified configuration
'jwt_users' => [
    'driver' => 'jwt',
    'user_claim' => 'user',
    'embed_user_data' => true,
    'required_user_fields' => ['id', 'email'],
],
```

## Usage Examples

### Basic Authentication

```php
// Get the auth manager
$auth = auth();

// Check if user is authenticated
if ($auth->check()) {
    $user = $auth->user();
    echo "Welcome, " . $user['name'];
}

// Attempt login
$credentials = ['email' => 'user@example.com', 'password' => 'secret'];
if ($auth->attempt($credentials)) {
    echo "Login successful";
}

// Logout
$auth->logout();
```

### JWT Authentication

```php
// Use JWT guard specifically
$jwtGuard = auth('api');

// Check JWT authentication
if ($jwtGuard->check()) {
    $user = $jwtGuard->user();
    $token = $jwtGuard->getToken();
}

// Generate token for user
$token = $jwtGuard->generateTokenForUser($user);

// Login with JWT token
$jwtGuard->setToken($token);
```

### Multi-Guard Authentication

```php
// Web authentication (sessions)
$webAuth = auth('web');
if ($webAuth->attempt($credentials, $remember = true)) {
    // Session-based login successful
}

// API authentication (JWT)
$apiAuth = auth('api');
if ($apiAuth->check()) {
    // JWT token is valid
}

// Different providers for different contexts
$mobileAuth = auth('mobile'); // Uses jwt_users provider
$adminAuth = auth('admin');   // Uses admin_users provider
```

## Configuration

### Guards Configuration

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
    'mobile' => [
        'driver' => 'jwt',
        'provider' => 'jwt_users',
    ],
],
```

### Providers Configuration

```php
'providers' => [
    'users' => [
        'driver' => 'database',
        'model' => 'User',
        'table' => 'users',
    ],
    'jwt_users' => [
        'driver' => 'jwt',
        'user_claim' => 'user',
        'embed_user_data' => true,
        'required_user_fields' => ['id', 'email'],
    ],
],
```

## Security Features

### Session Security
- CSRF token integration
- Session fixation protection
- Secure cookie configuration
- Remember me token management

### JWT Security
- Token signature validation
- Expiration checking
- Algorithm verification
- **Phase 4**: Security assessment and scoring
- **Phase 4**: Token rotation and family tracking

### Password Security
- Configurable hashing algorithms
- Salt generation
- Password rehashing detection
- Timing attack protection

## Middleware Integration

### JWT Middleware
```php
// Protect routes with JWT authentication
Route::middleware('jwt')->group(function () {
    Route::get('/profile', 'ProfileController@show');
    Route::post('/posts', 'PostController@store');
});

// Optional JWT authentication
Route::middleware('jwt:optional')->group(function () {
    Route::get('/posts', 'PostController@index');
});
```

### Session Middleware
```php
// Traditional web routes with session authentication
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', 'DashboardController@index');
});
```

## Testing

The authentication system has comprehensive test coverage:

### Core Authentication Tests
- **AuthManagerTest**: Central manager functionality
- **AuthManagerJwtTest**: JWT integration (27 tests)
- **SessionGuardTest**: Session-based authentication
- **JwtGuardTest**: JWT-based authentication
- **DatabaseUserProviderTest**: Database user operations
- **JwtUserProviderTest**: JWT user resolution (37 tests)

### JWT Component Tests
- See `Jwt/README.md` for detailed JWT testing information
- **153+ JWT-specific tests** with comprehensive coverage

### Middleware Tests
- **JwtMiddlewareTest**: Route protection and token extraction

Total: **200+ tests** across all authentication components.

## Migration Guide

### From Phase 3 to Phase 4

1. **Update JWT provider configuration**:
   ```php
   // Remove these options:
   // 'mode' => 'stateless',
   // 'fallback_provider' => 'users',
   
   // Update user claim:
   'user_claim' => 'user', // was 'user_data'
   ```

2. **Embed user data in tokens**:
   ```php
   $tokenGenerator->generateAuthToken($userId, [
       'id' => $user->id,
       'email' => $user->email,
       'name' => $user->name,
       'role' => $user->role,
   ]);
   ```

3. **Use new helper functions**:
   ```php
   // Instead of complex provider calls
   if (jwt_valid($token)) {
       $userId = jwt_user_id($token);
       $claims = jwt_claims($token);
   }
   ```

## Performance

### Optimizations
- **Lazy loading**: Guards and providers created on demand
- **Caching**: Provider instances cached for reuse
- **Stateless JWT**: No database queries for JWT authentication
- **Optimized validation**: Direct claim access in JWT providers

### Benchmarks
- Session authentication: ~2ms average
- JWT authentication: ~0.5ms average
- User provider operations: ~1ms average
- Guard creation: ~0.1ms average

## Best Practices

### Security
1. Use HTTPS for all authentication operations
2. Implement proper CSRF protection for session-based auth
3. Use secure JWT algorithms (HS256, RS256, ES256)
4. Set appropriate token expiration times
5. Implement token rotation for refresh tokens

### Performance
1. Use JWT for stateless API authentication
2. Cache user providers when possible
3. Minimize database queries in authentication paths
4. Use appropriate session storage for web authentication

### Maintenance
1. Regularly rotate JWT secrets
2. Monitor authentication logs
3. Implement rate limiting for login attempts
4. Keep authentication dependencies updated