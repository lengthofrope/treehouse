# JWT (JSON Web Token) Implementation for TreeHouse

This directory contains a complete, zero-dependency JWT implementation for the TreeHouse framework, providing RFC 7519 compliant JSON Web Token functionality.

## Overview

The JWT implementation provides secure, stateless authentication tokens with support for multiple algorithms, comprehensive validation, and enterprise-grade security features.

## Features

- **RFC 7519 Compliant**: Full compliance with JWT specification
- **Multiple Algorithms**: Support for HS256 (HMAC), RS256 (RSA), ES256 (ECDSA)
- **Zero Dependencies**: Pure PHP implementation using only built-in functions
- **Comprehensive Validation**: Claims validation, timing checks, signature verification
- **Security First**: Timing-safe comparisons, secure key validation, blacklist support
- **Developer Friendly**: High-level utilities, extensive documentation, comprehensive tests

## Architecture

```
src/TreeHouse/Auth/Jwt/
├── JwtConfig.php           # Configuration management
├── ClaimsManager.php       # JWT claims handling
├── JwtEncoder.php          # Token encoding
├── JwtDecoder.php          # Token decoding and validation
├── TokenGenerator.php      # High-level token generation
├── TokenValidator.php      # High-level token validation
├── Algorithms/             # Signature algorithms
│   ├── AlgorithmInterface.php
│   ├── HmacSha256.php     # HS256 implementation
│   ├── RsaSha256.php      # RS256 implementation
│   └── EcdsaSha256.php    # ES256 implementation
└── README.md              # This file
```

## Quick Start

### Basic Configuration

```php
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenValidator;

// Configure JWT
$config = new JwtConfig([
    'secret' => 'your-256-bit-secret-key-here',
    'algorithm' => 'HS256',
    'ttl' => 900,        // 15 minutes
    'refresh_ttl' => 1209600, // 2 weeks
    'issuer' => 'your-app',
    'audience' => 'your-users',
]);

// Create generator and validator
$generator = new TokenGenerator($config);
$validator = new TokenValidator($config);
```

### Generating Tokens

```php
// Generate authentication token
$authToken = $generator->generateAuthToken(
    userId: 123,
    userData: ['name' => 'John Doe', 'email' => 'john@example.com'],
    permissions: ['read', 'write'],
    roles: ['user', 'admin']
);

// Generate API token
$apiToken = $generator->generateApiToken(
    userId: 123,
    scopes: ['posts:read', 'users:write']
);

// Generate refresh token
$refreshToken = $generator->generateRefreshToken(
    userId: 123,
    tokenId: 'unique-token-id'
);
```

### Validating Tokens

```php
// Validate authentication token
try {
    $claims = $validator->validateAuthToken($authToken, expectedUserId: 123);
    
    $userId = $claims->getClaim('user_id');
    $permissions = $claims->getClaim('permissions', []);
    $roles = $claims->getClaim('roles', []);
    
    echo "Welcome, User ID: $userId\n";
} catch (InvalidArgumentException $e) {
    echo "Invalid token: " . $e->getMessage();
}

// Validate API token with scopes
try {
    $claims = $validator->validateApiToken(
        $apiToken, 
        requiredScopes: ['posts:read']
    );
    
    echo "API access granted\n";
} catch (InvalidArgumentException $e) {
    echo "Access denied: " . $e->getMessage();
}
```

### Low-Level Token Operations

```php
use LengthOfRope\TreeHouse\Auth\Jwt\JwtEncoder;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtDecoder;
use LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager;

$encoder = new JwtEncoder($config);
$decoder = new JwtDecoder($config);

// Create custom claims
$claims = new ClaimsManager([
    'sub' => 'user123',
    'iat' => time(),
    'exp' => time() + 3600,
    'custom_data' => ['role' => 'admin'],
]);

// Encode token
$token = $encoder->encode($claims);

// Decode and validate token
$decodedClaims = $decoder->decode($token);
$customData = $decodedClaims->getClaim('custom_data');
```

## Algorithm Support

### HS256 (HMAC SHA-256) - Recommended for Single Application

```php
$config = new JwtConfig([
    'secret' => 'your-256-bit-secret',
    'algorithm' => 'HS256',
]);
```

**Features:**
- Symmetric key algorithm
- Fast performance
- Shared secret between issuer and verifier
- Minimum 32-character secret required

### RS256 (RSA SHA-256) - Recommended for Distributed Systems

```php
$config = new JwtConfig([
    'algorithm' => 'RS256',
    'private_key' => file_get_contents('private_key.pem'),
    'public_key' => file_get_contents('public_key.pem'),
]);
```

**Features:**
- Asymmetric key algorithm
- Private key for signing, public key for verification
- Suitable for microservices and distributed systems
- Minimum 2048-bit RSA keys required

### ES256 (ECDSA SHA-256) - Recommended for Performance

```php
$config = new JwtConfig([
    'algorithm' => 'ES256',
    'private_key' => file_get_contents('ec_private_key.pem'),
    'public_key' => file_get_contents('ec_public_key.pem'),
]);
```

**Features:**
- Elliptic Curve Digital Signature Algorithm
- Smaller signatures than RSA
- Better performance than RSA
- Uses P-256 curve

## Claims Management

### Standard Claims

```php
$claims = new ClaimsManager();

// Issuer
$claims->setIssuer('your-application');

// Subject (typically user ID)
$claims->setSubject('user123');

// Audience
$claims->setAudience(['web-app', 'mobile-app']); // Array of audiences
$claims->setAudience('single-audience');         // Single audience

// Timestamps
$claims->setIssuedAt(time());
$claims->setExpiration(time() + 3600);
$claims->setNotBefore(time());

// JWT ID
$claims->setJwtId('unique-token-id');
```

### Custom Claims

```php
// Add custom claims
$claims->setClaim('user_id', 123);
$claims->setClaim('roles', ['admin', 'user']);
$claims->setClaim('permissions', ['read', 'write', 'delete']);
$claims->setClaim('user_data', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'preferences' => ['theme' => 'dark']
]);

// Get claims
$userId = $claims->getClaim('user_id');
$roles = $claims->getClaim('roles', []); // With default value
```

### Claims Validation

```php
// Validate required claims
$claims->validateRequiredClaims(['iss', 'sub', 'exp']);

// Validate timing (expiration, not-before)
$claims->validateTiming(leeway: 30); // 30-second leeway for clock skew

// Check expiration manually
if ($claims->isExpired()) {
    throw new Exception('Token has expired');
}

// Check not-before manually
if ($claims->isNotYetValid()) {
    throw new Exception('Token is not yet valid');
}
```

## Security Features

### Key Security

- **Minimum Key Lengths**: 32 bytes for HMAC, 2048 bits for RSA
- **Algorithm Validation**: Strict algorithm checking prevents attacks
- **Key Type Validation**: Ensures correct key types for algorithms

### Timing Safety

- **Constant-Time Comparisons**: Prevents timing attacks
- **Signature Verification**: Uses `hash_equals()` for secure comparison

### Token Security

- **Expiration Enforcement**: Automatic expiration checking
- **Not-Before Validation**: Prevents premature token usage
- **Issuer/Audience Validation**: Ensures tokens are from trusted sources
- **Clock Skew Tolerance**: Configurable leeway for timing validation

## Configuration Options

```php
$config = new JwtConfig([
    // Required
    'secret' => 'your-secret-key',           // For HS256
    'private_key' => 'pem-content',          // For RS256/ES256 signing
    'public_key' => 'pem-content',           // For RS256/ES256 verification
    
    // Algorithm
    'algorithm' => 'HS256',                  // HS256, RS256, or ES256
    
    // Token Lifetimes
    'ttl' => 900,                           // Access token TTL (15 minutes)
    'refresh_ttl' => 1209600,               // Refresh token TTL (2 weeks)
    
    // Claims
    'issuer' => 'your-app',                 // iss claim
    'audience' => 'your-users',             // aud claim
    'subject' => 'default-subject',         // sub claim
    
    // Validation
    'required_claims' => ['iss', 'exp'],    // Required claims
    'leeway' => 0,                          // Clock skew tolerance (seconds)
    
    // Blacklist (future feature)
    'blacklist_enabled' => true,
    'blacklist_grace_period' => 0,
]);
```

## Error Handling

The JWT implementation uses specific error codes for different failure types:

```php
try {
    $claims = $validator->validate($token);
} catch (InvalidArgumentException $e) {
    switch ($e->getCode()) {
        case 'JWT_TOKEN_EXPIRED':
            // Handle expired token
            break;
        case 'JWT_SIGNATURE_INVALID':
            // Handle invalid signature
            break;
        case 'JWT_MISSING_REQUIRED_CLAIMS':
            // Handle missing claims
            break;
        case 'JWT_INVALID_AUDIENCE':
            // Handle audience mismatch
            break;
        // ... other error codes
    }
}
```

## Testing

The JWT implementation includes comprehensive test coverage:

```bash
# Run JWT tests
./vendor/bin/phpunit tests/Unit/Auth/Jwt/

# Test coverage includes:
# - Configuration validation
# - Claims management
# - Algorithm implementations
# - Token encoding/decoding
# - Security validations
# - Error conditions
```

## Performance Considerations

### Algorithm Performance

1. **HS256**: Fastest, suitable for single applications
2. **ES256**: Good performance with smaller signatures
3. **RS256**: Slower but better for distributed systems

### Token Size

- **Minimal Claims**: Keep custom claims small
- **Base64URL Encoding**: Adds ~33% overhead
- **Signature Size**: HS256 (43 chars), RS256 (512 chars), ES256 (86 chars)

### Caching Strategies

```php
// Cache decoded claims for repeated validations
$cacheKey = 'jwt_claims_' . hash('sha256', $token);
if ($cachedClaims = $cache->get($cacheKey)) {
    return $cachedClaims;
}

$claims = $validator->validate($token);
$cache->set($cacheKey, $claims, 300); // Cache for 5 minutes
```

## Integration Examples

### Authentication Middleware

```php
class JwtAuthMiddleware
{
    private TokenValidator $validator;
    
    public function handle($request, $next)
    {
        $token = $this->extractToken($request);
        
        try {
            $claims = $this->validator->validateAuthToken($token);
            $request->setUser($claims->getClaim('user_id'));
            return $next($request);
        } catch (InvalidArgumentException $e) {
            return response('Unauthorized', 401);
        }
    }
    
    private function extractToken($request): string
    {
        $header = $request->header('Authorization');
        if (!str_starts_with($header, 'Bearer ')) {
            throw new InvalidArgumentException('Missing Bearer token');
        }
        return substr($header, 7);
    }
}
```

### API Authentication

```php
class ApiController
{
    private TokenValidator $validator;
    
    public function secureEndpoint($request)
    {
        $token = $request->bearerToken();
        
        try {
            $claims = $this->validator->validateApiToken(
                $token,
                requiredScopes: ['api:read', 'posts:write']
            );
            
            $userId = $claims->getClaim('user_id');
            // Process request...
            
        } catch (InvalidArgumentException $e) {
            return response(['error' => 'Invalid token'], 403);
        }
    }
}
```

## Best Practices

1. **Use HTTPS**: Always transmit tokens over HTTPS
2. **Short Expiration**: Keep access tokens short-lived (15 minutes)
3. **Refresh Tokens**: Use longer-lived refresh tokens for renewal
4. **Secure Storage**: Store refresh tokens securely on client
5. **Validate Everything**: Always validate all claims, not just signature
6. **Monitor Usage**: Log authentication events for security monitoring
7. **Rotate Secrets**: Regularly rotate signing keys
8. **Minimal Claims**: Include only necessary data in tokens

## Troubleshooting

### Common Issues

**Invalid Secret Length**
```
Error: JWT secret must be at least 32 characters long
Solution: Use a longer secret key (256 bits minimum)
```

**Algorithm Mismatch**
```
Error: Unsupported algorithm: HS512
Solution: Use supported algorithms (HS256, RS256, ES256)
```

**Expired Token**
```
Error: Token has expired
Solution: Check system clocks, use refresh tokens, adjust TTL
```

**Missing Keys**
```
Error: Private key required for RS256 algorithm
Solution: Provide private_key in configuration for asymmetric algorithms
```

### Debug Mode

```php
// Decode without verification for debugging
$debugInfo = $decoder->decodeWithoutVerification($token);
print_r($debugInfo['header']);
print_r($debugInfo['payload']);

// Extract claims without validation
$debugClaims = $validator->extractClaims($token);
```

This JWT implementation provides a solid foundation for stateless authentication in the TreeHouse framework while maintaining security, performance, and ease of use.