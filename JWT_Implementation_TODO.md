# JWT Support Implementation TODO for TreeHouse Framework

## Overview

This document outlines the comprehensive implementation plan for adding JWT (JSON Web Token) support to the TreeHouse framework. The implementation will provide enterprise-grade stateless authentication while maintaining TreeHouse's zero-dependency philosophy.

## Current State Analysis

TreeHouse already has a solid foundation for JWT implementation:

- **Multi-guard authentication system** via [`AuthManager.php`](src/TreeHouse/Auth/AuthManager.php:32)
- **Guard interface contract** at [`Guard.php`](src/TreeHouse/Auth/Guard.php:24) 
- **Encryption capabilities** with [`Encryption.php`](src/TreeHouse/Security/Encryption.php:26) (AES-256-CBC, payload encryption)
- **Configuration framework** in [`config/auth.php`](config/auth.php:35) with placeholder for token driver
- **RBAC system** already integrated with authentication layer
- **Enterprise rate limiting** that can work with JWT user identification

## Implementation Phases

### Phase 1: JWT Foundation
**Priority: Critical**

- [ ] **JWT Library Implementation**
  - [ ] Create `src/TreeHouse/Auth/Jwt/JwtEncoder.php` - RFC 7519 compliant JWT encoding
  - [ ] Create `src/TreeHouse/Auth/Jwt/JwtDecoder.php` - Token decoding and validation
  - [ ] Create `src/TreeHouse/Auth/Jwt/JwtSigner.php` - Signature generation and verification
  - [ ] Implement algorithm support: HS256 (HMAC), RS256 (RSA), ES256 (ECDSA)

- [ ] **JWT Configuration System**
  - [ ] Add JWT configuration section to `config/auth.php`
  - [ ] Create `src/TreeHouse/Auth/Jwt/JwtConfig.php` for configuration management
  - [ ] Support for algorithm selection, secret management, token lifetimes
  - [ ] Environment variable integration for secrets

- [ ] **JWT Utility Classes**
  - [ ] Create `src/TreeHouse/Auth/Jwt/ClaimsManager.php` - JWT claims handling
  - [ ] Create `src/TreeHouse/Auth/Jwt/TokenGenerator.php` - Token creation utilities
  - [ ] Create `src/TreeHouse/Auth/Jwt/TokenValidator.php` - Token validation logic

### Phase 2: JWT Guard Implementation
**Priority: Critical**

- [ ] **JwtGuard Class**
  - [ ] Create `src/TreeHouse/Auth/JwtGuard.php` implementing `Guard` interface
  - [ ] Implement stateless authentication methods
  - [ ] Token extraction from Authorization headers
  - [ ] User resolution from JWT claims
  - [ ] Integration with existing Guard contract

- [ ] **Token Extraction**
  - [ ] Support for Bearer token in Authorization header
  - [ ] Optional cookie-based token extraction
  - [ ] Query parameter token extraction for development
  - [ ] Header parsing and validation

### Phase 3: JWT User Provider
**Priority: High**

- [ ] **JwtUserProvider Implementation**
  - [ ] Create `src/TreeHouse/Auth/JwtUserProvider.php` implementing `UserProvider` interface
  - [ ] Stateless user resolution from JWT payload
  - [ ] Support for embedded user data in JWT claims
  - [ ] Fallback to database provider for hybrid scenarios

- [ ] **Hybrid Authentication Mode**
  - [ ] Database fallback for additional user data
  - [ ] Performance optimization with JWT-embedded data
  - [ ] Configurable user data inclusion in tokens

### Phase 4: Authentication Manager Integration
**Priority: High**

- [ ] **AuthManager Extension**
  - [ ] Extend `resolve()` method in `AuthManager.php` to support JWT driver
  - [ ] Create `createJwtDriver()` method for JWT guard factory
  - [ ] Add JWT configuration parsing and validation
  - [ ] Maintain backward compatibility with existing guards

- [ ] **Guard Factory**
  - [ ] JWT guard instantiation with proper dependencies
  - [ ] Configuration injection and validation
  - [ ] Error handling for misconfigured JWT guards

### Phase 5: Middleware & Request Handling
**Priority: High**

- [ ] **JWT Authentication Middleware**
  - [ ] Create `src/TreeHouse/Router/Middleware/JwtAuthMiddleware.php`
  - [ ] Token validation and user resolution
  - [ ] Error handling for invalid/expired tokens
  - [ ] Integration with existing middleware pipeline

- [ ] **Token Refresh Middleware**
  - [ ] Create `src/TreeHouse/Router/Middleware/JwtRefreshMiddleware.php`
  - [ ] Sliding expiration support
  - [ ] Automatic token refresh for valid requests
  - [ ] Response header injection for new tokens

### Phase 6: Token Management Features
**Priority: Medium**

- [ ] **Token Refresh System**
  - [ ] Create `src/TreeHouse/Auth/Jwt/RefreshTokenManager.php`
  - [ ] Secure refresh token generation and storage
  - [ ] Token exchange endpoints
  - [ ] Refresh token rotation for security

- [ ] **Token Blacklisting**
  - [ ] Create `src/TreeHouse/Auth/Jwt/TokenBlacklist.php`
  - [ ] Integration with existing cache layer for blacklisted tokens
  - [ ] Efficient blacklist checking during validation
  - [ ] Cleanup mechanism for expired blacklist entries

- [ ] **Token Scopes & Permissions**
  - [ ] Scope-based token generation
  - [ ] Integration with existing RBAC system
  - [ ] Permission claims in JWT payload
  - [ ] Scope validation in middleware

### Phase 7: API Helpers & Integration
**Priority: Medium**

- [ ] **Global Helper Functions**
  - [ ] Add `jwt()` function to `src/TreeHouse/Auth/helpers.php`
  - [ ] Add `jwtUser()` function for current JWT user
  - [ ] Add `generateJwt()` function for token creation
  - [ ] Add `refreshJwt()` function for token refresh

- [ ] **Template Engine Integration**
  - [ ] JWT authentication context in views
  - [ ] JWT user data available in templates
  - [ ] Conditional rendering based on JWT scopes

- [ ] **API Response Helpers**
  - [ ] Standardized JWT response format
  - [ ] Token inclusion in API responses
  - [ ] Error response formatting for JWT failures

### Phase 8: CLI & Management Tools
**Priority: Low**

- [ ] **JWT CLI Commands**
  - [ ] Create `src/TreeHouse/Console/Commands/JwtCommands/JwtGenerateKeyCommand.php`
  - [ ] Create `src/TreeHouse/Console/Commands/JwtCommands/JwtInspectCommand.php`
  - [ ] Create `src/TreeHouse/Console/Commands/JwtCommands/JwtRevokeCommand.php`
  - [ ] Create `src/TreeHouse/Console/Commands/JwtCommands/JwtRefreshCommand.php`

- [ ] **Key Management**
  - [ ] JWT secret generation and rotation
  - [ ] RSA key pair generation for RS256
  - [ ] ECDSA key pair generation for ES256
  - [ ] Secure key storage recommendations

- [ ] **Debugging Tools**
  - [ ] Token inspection and claims viewing
  - [ ] Signature verification utilities
  - [ ] Token expiration checking

### Phase 9: Testing & Documentation
**Priority: Medium**

- [ ] **Comprehensive Test Suite**
  - [ ] Unit tests for JWT library components
  - [ ] Integration tests with existing auth system
  - [ ] Middleware testing with various scenarios
  - [ ] Security testing for token validation

- [ ] **Test Coverage**
  - [ ] JWT encoding/decoding tests - `tests/Unit/Auth/Jwt/`
  - [ ] Guard implementation tests - `tests/Unit/Auth/JwtGuardTest.php`
  - [ ] User provider tests - `tests/Unit/Auth/JwtUserProviderTest.php`
  - [ ] Middleware integration tests - `tests/Unit/Router/Middleware/`
  - [ ] Token management tests - blacklisting, refresh, scopes

- [ ] **Documentation**
  - [ ] Create `src/TreeHouse/Auth/Jwt/README.md` - JWT system documentation
  - [ ] Update main `src/TreeHouse/Auth/README.md` with JWT information
  - [ ] API documentation with usage examples
  - [ ] Best practices and security recommendations

### Phase 10: Advanced Features
**Priority: Low**

- [ ] **Single Sign-On (SSO) Support**
  - [ ] JWT token exchange mechanisms
  - [ ] Cross-application authentication
  - [ ] Trusted issuer validation
  - [ ] SSO configuration management

- [ ] **Mobile Authentication Flows**
  - [ ] Mobile-specific token generation
  - [ ] Biometric authentication integration
  - [ ] Device fingerprinting and management
  - [ ] Push notification integration for security events

- [ ] **Advanced Security Features**
  - [ ] JWT-based password reset flows
  - [ ] Email verification with JWT tokens
  - [ ] Two-factor authentication integration
  - [ ] Audit logging for JWT operations

## Technical Implementation Details

### JWT Configuration Extension

Update `config/auth.php` to include JWT configuration:

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
        'provider' => 'jwt_users', // Stateless provider
    ],
],

'jwt' => [
    'secret' => env('JWT_SECRET'),
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),
    'ttl' => env('JWT_TTL', 900), // 15 minutes
    'refresh_ttl' => env('JWT_REFRESH_TTL', 1209600), // 2 weeks
    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),
    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),
],
```

### JWT Library Architecture

```
src/TreeHouse/Auth/Jwt/
├── JwtEncoder.php          # Token encoding
├── JwtDecoder.php          # Token decoding
├── JwtSigner.php           # Signature handling
├── ClaimsManager.php       # Claims management
├── TokenGenerator.php      # Token creation
├── TokenValidator.php      # Token validation
├── RefreshTokenManager.php # Refresh tokens
├── TokenBlacklist.php      # Token revocation
├── JwtConfig.php           # Configuration
├── Algorithms/             # Signature algorithms
│   ├── HmacSha256.php     # HS256 implementation
│   ├── RsaSha256.php      # RS256 implementation
│   └── EcdsaSha256.php    # ES256 implementation
└── README.md              # JWT documentation
```

### Integration Points

#### AuthManager Extension
```php
// Add to AuthManager::resolve()
case 'jwt':
    return $this->createJwtDriver($name, $config);

protected function createJwtDriver(string $name, array $config): JwtGuard
{
    $provider = $this->createUserProvider($config['provider'] ?? null);
    $jwtConfig = new JwtConfig($this->config['jwt'] ?? []);
    
    return new JwtGuard($provider, $jwtConfig);
}
```

#### Middleware Usage
```php
// API routes with JWT authentication
Route::middleware('auth:jwt')->group(function () {
    Route::get('/api/user', [UserController::class, 'profile']);
    Route::post('/api/logout', [AuthController::class, 'logout']);
});

// Mixed authentication (session + JWT)
Route::middleware('auth:web,jwt')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

#### Helper Function Usage
```php
// Generate JWT token
$token = generateJwt([
    'user_id' => $user->id,
    'roles' => $user->roles->pluck('name'),
    'permissions' => $user->permissions->pluck('name'),
]);

// Get current JWT user
$user = jwtUser();

// Check JWT authentication
if (jwt()->check()) {
    $claims = jwt()->claims();
}
```

## Security Considerations

### Token Security
- **Short-lived Access Tokens**: 15-minute default expiration
- **Secure Refresh Tokens**: 2-week expiration with rotation
- **Token Blacklisting**: Immediate revocation support
- **Algorithm Security**: Prefer RS256 for production, HS256 for development

### Key Management
- **Secret Rotation**: CLI tools for key rotation
- **Environment Variables**: Secure storage of JWT secrets
- **RSA Key Pairs**: For RS256 signature algorithm
- **ECDSA Support**: For ES256 signature algorithm

### Integration Security
- **Rate Limiting**: JWT tokens work with existing rate limiting
- **RBAC Integration**: Roles and permissions in JWT claims
- **Audit Logging**: Track JWT operations and security events
- **CSRF Protection**: JWT provides natural CSRF protection

## Performance Optimization

### Stateless Benefits
- **No Database Queries**: User data embedded in JWT claims
- **Horizontal Scaling**: No session storage requirements
- **CDN Compatibility**: Stateless API responses
- **Mobile Performance**: Reduced server round-trips

### Caching Strategy
- **Claims Caching**: Cache decoded JWT user data
- **Blacklist Optimization**: Efficient blacklist checking
- **Signature Verification**: Cache public keys for RSA/ECDSA
- **Token Validation**: Cache validation results briefly

## Migration and Compatibility

### Backward Compatibility
- **Existing Authentication**: Session-based auth continues unchanged
- **Progressive Migration**: Gradual move from sessions to JWT
- **Hybrid Support**: Mix session and JWT authentication
- **API Versioning**: Support both auth methods during transition

### Migration Strategy
1. **Phase 1**: Implement JWT alongside existing session auth
2. **Phase 2**: Migrate API routes to JWT authentication
3. **Phase 3**: Add mobile app JWT support
4. **Phase 4**: Implement SSO with JWT tokens
5. **Phase 5**: Full JWT feature utilization

This comprehensive implementation plan ensures TreeHouse gains enterprise-grade JWT support while maintaining its core principles of zero dependencies, clean architecture, and backward compatibility.