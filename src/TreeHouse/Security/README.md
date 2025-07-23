# TreeHouse Framework - Security Layer

The Security Layer provides comprehensive security utilities for the TreeHouse Framework, including CSRF protection, encryption/decryption, password hashing, and input sanitization. This layer focuses on protecting applications from common security vulnerabilities and providing secure data handling capabilities.

## Table of Contents

- [Overview](#overview)
- [Components](#components)
- [CSRF Protection](#csrf-protection)
- [Encryption](#encryption)
- [Password Hashing](#password-hashing)
- [Input Sanitization](#input-sanitization)
- [Usage Examples](#usage-examples)
- [Security Best Practices](#security-best-practices)
- [Integration](#integration)

## Overview

The Security Layer consists of five main components:

- **CSRF Protection**: Token-based Cross-Site Request Forgery protection
- **Encryption**: AES-256-CBC encryption with secure payload handling
- **Password Hashing**: Secure password hashing using PHP's built-in functions
- **Input Sanitization**: XSS prevention and input cleaning utilities
- **Security Headers Management**: Comprehensive HTTP security headers (Phase 5)

### Key Features

- **CSRF Token Management**: Generation, validation, and session-based storage
- **Symmetric Encryption**: AES-256-CBC with HMAC-SHA256 authentication
- **Password Security**: bcrypt hashing with rehashing detection
- **XSS Prevention**: Comprehensive input sanitization and HTML escaping
- **Type-Safe Sanitization**: Type-specific input cleaning (string, email, URL, numeric)
- **File Security**: Filename and path sanitization
- **Collection Integration**: Fluent array sanitization using Collections

## Components

### Core Classes

```php
// CSRF Protection
LengthOfRope\TreeHouse\Security\Csrf

// Encryption/Decryption
LengthOfRope\TreeHouse\Security\Encryption

// Password Hashing
LengthOfRope\TreeHouse\Security\Hash

// Input Sanitization
LengthOfRope\TreeHouse\Security\Sanitizer

// Security Headers Management (Phase 5)
LengthOfRope\TreeHouse\Security\SecurityHeadersManager
```

## Security Headers Management (Phase 5)

The [`SecurityHeadersManager`](src/TreeHouse/Security/SecurityHeadersManager.php:1) class provides comprehensive HTTP security headers management for enhanced application security.

### Features

- **CORS Headers**: Cross-Origin Resource Sharing configuration
- **Content Security Policy (CSP)**: XSS and injection attack prevention
- **Security Headers**: HSTS, X-Frame-Options, X-Content-Type-Options, etc.
- **Rate Limiting Headers**: API rate limiting information
- **Custom Headers**: Flexible custom header injection
- **Environment-aware Configuration**: Development vs. production settings

### Basic Usage

```php
use LengthOfRope\TreeHouse\Security\SecurityHeadersManager;
use LengthOfRope\TreeHouse\Http\{Request, Response};

$headerManager = new SecurityHeadersManager();
$response = new Response('Content');
$request = new Request();

// Apply security headers to response
$secureResponse = $headerManager->applyHeaders($response, $request);
```

### Configuration

```php
$config = [
    'cors' => [
        'enabled' => true,
        'allowed_origins' => ['https://example.com', '*.trusted.com'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'allowed_headers' => ['Authorization', 'Content-Type'],
        'allow_credentials' => false,
        'max_age' => 86400
    ],
    'csp' => [
        'enabled' => true,
        'default_src' => ["'self'"],
        'script_src' => ["'self'", "'unsafe-inline'"],
        'style_src' => ["'self'", "'unsafe-inline'"]
    ],
    'security' => [
        'hsts' => [
            'enabled' => true,
            'max_age' => 31536000,
            'include_subdomains' => true
        ],
        'content_type_options' => 'nosniff',
        'frame_options' => 'DENY'
    ]
];

$headerManager = new SecurityHeadersManager($config);
```

### CORS Support

```php
// Handle preflight requests
if ($request->getMethod() === 'OPTIONS') {
    $response = $headerManager->createPreflightResponse($request);
    return $response;
}

// Apply CORS headers with origin validation
$response = $headerManager->applyHeaders($response, $request);
```

### Key Methods

- [`applyHeaders(Response $response, ?Request $request, array $context): Response`] - Apply security headers
- [`createPreflightResponse(Request $request): Response`] - Create CORS preflight response
- [`updateConfig(array $config): self`] - Update configuration
- [`getHeadersSummary(): array`] - Get security headers status

### Phase 5 Improvements

- **Fixed Configuration Merging**: Resolved `array_merge_recursive` issues that created invalid configurations
- **Enhanced CORS Validation**: Improved wildcard subdomain matching for origin validation
- **Proper Header Management**: Fixed header replacement logic to prevent stale values
- **Type-safe Configuration**: Ensures boolean and scalar values are properly handled

## CSRF Protection

The [`Csrf`](src/TreeHouse/Security/Csrf.php:1) class provides Cross-Site Request Forgery protection through token generation and validation.

### Basic Usage

```php
use LengthOfRope\TreeHouse\Security\Csrf;

$csrf = new Csrf();

// Generate a new CSRF token
$token = $csrf->generateToken();

// Validate a token
if ($csrf->validateToken($token)) {
    // Token is valid, process request
    echo "Request validated";
} else {
    // Invalid token, reject request
    throw new SecurityException('Invalid CSRF token');
}
```

### Token Management

```php
// Generate token with custom length
$token = $csrf->generateToken(64); // 64-byte token

// Get current token from session
$currentToken = $csrf->getToken();

// Regenerate token (invalidates previous)
$newToken = $csrf->regenerateToken();

// Clear token from session
$csrf->clearToken();
```

### Form Integration

```php
// In your form template
echo '<input type="hidden" name="_token" value="' . $csrf->getToken() . '">';

// In your request handler
if (!$csrf->validateToken($_POST['_token'] ?? '')) {
    throw new SecurityException('CSRF token mismatch');
}
```

### Key Methods

- [`generateToken(int $length = 32): string`](src/TreeHouse/Security/Csrf.php:40) - Generate new CSRF token
- [`validateToken(string $token): bool`](src/TreeHouse/Security/Csrf.php:56) - Validate provided token
- [`getToken(): string`](src/TreeHouse/Security/Csrf.php:72) - Get current session token
- [`regenerateToken(): string`](src/TreeHouse/Security/Csrf.php:85) - Generate and store new token
- [`clearToken(): void`](src/TreeHouse/Security/Csrf.php:96) - Remove token from session

## Encryption

The [`Encryption`](src/TreeHouse/Security/Encryption.php:1) class provides AES-256-CBC encryption with HMAC-SHA256 authentication for secure data handling.

### Basic Usage

```php
use LengthOfRope\TreeHouse\Security\Encryption;

$encryption = new Encryption('your-secret-key-here');

// Encrypt data
$encrypted = $encryption->encrypt('sensitive data');

// Decrypt data
$decrypted = $encryption->decrypt($encrypted);
echo $decrypted; // "sensitive data"
```

### Advanced Encryption

```php
// Encrypt with custom cipher
$encrypted = $encryption->encrypt('data', 'aes-128-cbc');

// Encrypt arrays and objects
$data = ['user_id' => 123, 'role' => 'admin'];
$encrypted = $encryption->encrypt(serialize($data));
$decrypted = unserialize($encryption->decrypt($encrypted));

// Handle encryption errors
try {
    $result = $encryption->decrypt($tamperedData);
} catch (InvalidArgumentException $e) {
    echo "Decryption failed: " . $e->getMessage();
}
```

### Payload Structure

The encrypted payload includes:
- **IV**: Initialization vector for encryption
- **Value**: Base64-encoded encrypted data
- **MAC**: HMAC-SHA256 authentication code

```php
// Example payload structure
$payload = [
    'iv' => base64_encode($iv),
    'value' => base64_encode($encrypted),
    'mac' => hash_hmac('sha256', $data, $key)
];
```

### Key Methods

- [`encrypt(string $data, string $cipher = 'aes-256-cbc'): string`](src/TreeHouse/Security/Encryption.php:45) - Encrypt data
- [`decrypt(string $payload): string`](src/TreeHouse/Security/Encryption.php:75) - Decrypt payload
- [`generateKey(): string`](src/TreeHouse/Security/Encryption.php:108) - Generate random encryption key

## Password Hashing

The [`Hash`](src/TreeHouse/Security/Hash.php:1) class provides secure password hashing using PHP's built-in password functions.

### Basic Usage

```php
use LengthOfRope\TreeHouse\Security\Hash;

$hash = new Hash();

// Hash a password
$hashedPassword = $hash->make('user-password');

// Verify a password
if ($hash->check('user-password', $hashedPassword)) {
    echo "Password is correct";
} else {
    echo "Invalid password";
}
```

### Advanced Hashing

```php
// Hash with custom cost (higher = more secure, slower)
$hashedPassword = $hash->make('password', ['cost' => 12]);

// Check if hash needs rehashing (security upgrade)
if ($hash->needsRehash($existingHash, ['cost' => 12])) {
    $newHash = $hash->make($plainPassword, ['cost' => 12]);
    // Update database with new hash
}

// Get hash information
$info = $hash->getInfo($hashedPassword);
echo "Algorithm: " . $info['algoName'];
echo "Cost: " . $info['options']['cost'];
```

### Password Security Workflow

```php
// Registration
$password = $_POST['password'];
$hashedPassword = $hash->make($password);
// Store $hashedPassword in database

// Login
$inputPassword = $_POST['password'];
$storedHash = getUserHashFromDatabase($userId);

if ($hash->check($inputPassword, $storedHash)) {
    // Check if hash needs upgrade
    if ($hash->needsRehash($storedHash)) {
        $newHash = $hash->make($inputPassword);
        updateUserHashInDatabase($userId, $newHash);
    }
    
    // Login successful
    authenticateUser($userId);
} else {
    // Invalid credentials
    throw new AuthenticationException('Invalid password');
}
```

### Key Methods

- [`make(string $password, array $options = []): string`](src/TreeHouse/Security/Hash.php:40) - Hash password
- [`check(string $password, string $hash): bool`](src/TreeHouse/Security/Hash.php:61) - Verify password
- [`needsRehash(string $hash, array $options = []): bool`](src/TreeHouse/Security/Hash.php:81) - Check if rehashing needed
- [`getInfo(string $hash): array`](src/TreeHouse/Security/Hash.php:96) - Get hash information

## Input Sanitization

The [`Sanitizer`](src/TreeHouse/Security/Sanitizer.php:1) class provides comprehensive input sanitization to prevent XSS attacks and clean user input.

### Basic Sanitization

```php
use LengthOfRope\TreeHouse\Security\Sanitizer;

$sanitizer = new Sanitizer();

// Sanitize different data types
$cleanString = $sanitizer->sanitizeString('<script>alert("xss")</script>Hello');
// Result: "Hello"

$cleanEmail = $sanitizer->sanitizeEmail('user@example.com<script>');
// Result: "user@example.com"

$cleanUrl = $sanitizer->sanitizeUrl('javascript:alert("xss")');
// Result: "" (dangerous URL removed)

$cleanInt = $sanitizer->sanitizeInteger('123abc');
// Result: 123

$cleanFloat = $sanitizer->sanitizeFloat('123.45abc');
// Result: 123.45

$cleanBool = $sanitizer->sanitizeBoolean('true');
// Result: true
```

### Array Sanitization

```php
// Define sanitization rules
$rules = [
    'name' => 'string',
    'email' => 'email',
    'website' => 'url',
    'age' => 'integer',
    'salary' => 'float',
    'active' => 'boolean'
];

// Sanitize entire array
$input = [
    'name' => '<script>alert("xss")</script>John Doe',
    'email' => 'john@example.com<script>',
    'website' => 'https://example.com',
    'age' => '25abc',
    'salary' => '50000.50abc',
    'active' => 'true'
];

$sanitized = $sanitizer->sanitizeArray($input, $rules);
// Result: Clean, type-appropriate values
```

### XSS Protection

```php
// Remove XSS attempts
$input = '<script>alert("xss")</script><p onclick="alert()">Content</p>';
$clean = $sanitizer->removeXssAttempts($input);
// Result: "<p>Content</p>"

// Escape HTML for output
$userInput = '<script>alert("xss")</script>Hello & "World"';
$escaped = $sanitizer->escapeHtml($userInput);
// Result: "&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;Hello &amp; &quot;World&quot;"

// Escape for HTML attributes
$attrValue = 'value"onclick="alert()"';
$escaped = $sanitizer->escapeAttribute($attrValue);
// Result: "value&quot;onclick=&quot;alert()&quot;"
```

### File Security

```php
// Sanitize filenames
$filename = $sanitizer->sanitizeFilename('../../../etc/passwd');
// Result: "passwd"

$filename = $sanitizer->sanitizeFilename('<script>evil.php');
// Result: "evil.php"

// Sanitize file paths
$path = $sanitizer->sanitizePath('../../../var/www/uploads/file.txt');
// Result: "var/www/uploads/file.txt"
```

### Key Methods

- [`sanitizeString(?string $input): string`](src/TreeHouse/Security/Sanitizer.php:36) - Clean string input
- [`sanitizeEmail(string $input): string`](src/TreeHouse/Security/Sanitizer.php:51) - Sanitize email addresses
- [`sanitizeUrl(string $input): string`](src/TreeHouse/Security/Sanitizer.php:68) - Clean and validate URLs
- [`sanitizeInteger(mixed $input): int`](src/TreeHouse/Security/Sanitizer.php:95) - Convert to integer
- [`sanitizeFloat(mixed $input): float`](src/TreeHouse/Security/Sanitizer.php:106) - Convert to float
- [`sanitizeBoolean(mixed $input): bool`](src/TreeHouse/Security/Sanitizer.php:122) - Convert to boolean
- [`sanitizeArray(array $input, array $rules): array`](src/TreeHouse/Security/Sanitizer.php:140) - Bulk sanitization
- [`removeXssAttempts(string $input): string`](src/TreeHouse/Security/Sanitizer.php:170) - Remove XSS patterns
- [`escapeHtml(string $input): string`](src/TreeHouse/Security/Sanitizer.php:192) - HTML entity escaping
- [`escapeAttribute(string $input): string`](src/TreeHouse/Security/Sanitizer.php:203) - Attribute-safe escaping
- [`sanitizeFilename(string $input): string`](src/TreeHouse/Security/Sanitizer.php:217) - Clean filenames
- [`sanitizePath(string $input): string`](src/TreeHouse/Security/Sanitizer.php:242) - Sanitize file paths

## Usage Examples

### Complete Form Processing

```php
use LengthOfRope\TreeHouse\Security\{Csrf, Sanitizer, Hash};

// Initialize security components
$csrf = new Csrf();
$sanitizer = new Sanitizer();
$hash = new Hash();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!$csrf->validateToken($_POST['_token'] ?? '')) {
        throw new SecurityException('CSRF token mismatch');
    }
    
    // Sanitize input data
    $rules = [
        'name' => 'string',
        'email' => 'email',
        'website' => 'url',
        'age' => 'integer'
    ];
    
    $sanitized = $sanitizer->sanitizeArray($_POST, $rules);
    
    // Hash password if provided
    if (!empty($_POST['password'])) {
        $sanitized['password'] = $hash->make($_POST['password']);
    }
    
    // Process sanitized data
    saveUserData($sanitized);
}

// Generate token for form
$token = $csrf->getToken();
```

### Secure Data Storage

```php
use LengthOfRope\TreeHouse\Security\Encryption;

$encryption = new Encryption($_ENV['APP_KEY']);

// Encrypt sensitive data before storage
$sensitiveData = [
    'ssn' => '123-45-6789',
    'credit_card' => '4111-1111-1111-1111'
];

$encrypted = $encryption->encrypt(serialize($sensitiveData));
// Store $encrypted in database

// Decrypt when needed
$decrypted = unserialize($encryption->decrypt($encrypted));
```

### User Authentication System

```php
use LengthOfRope\TreeHouse\Security\{Hash, Sanitizer};

class UserAuth
{
    private Hash $hash;
    private Sanitizer $sanitizer;
    
    public function __construct()
    {
        $this->hash = new Hash();
        $this->sanitizer = new Sanitizer();
    }
    
    public function register(array $data): bool
    {
        // Sanitize input
        $rules = [
            'username' => 'string',
            'email' => 'email',
            'password' => 'string'
        ];
        
        $clean = $this->sanitizer->sanitizeArray($data, $rules);
        
        // Hash password
        $clean['password'] = $this->hash->make($clean['password']);
        
        // Save user
        return $this->saveUser($clean);
    }
    
    public function login(string $email, string $password): bool
    {
        // Sanitize input
        $email = $this->sanitizer->sanitizeEmail($email);
        $password = $this->sanitizer->sanitizeString($password);
        
        // Get user from database
        $user = $this->getUserByEmail($email);
        
        if (!$user || !$this->hash->check($password, $user['password'])) {
            return false;
        }
        
        // Check if password needs rehashing
        if ($this->hash->needsRehash($user['password'])) {
            $newHash = $this->hash->make($password);
            $this->updateUserPassword($user['id'], $newHash);
        }
        
        return true;
    }
}
```

## Security Best Practices

### CSRF Protection

1. **Always validate tokens** for state-changing operations
2. **Regenerate tokens** after successful authentication
3. **Use HTTPS** to prevent token interception
4. **Set appropriate token expiration** times

### Encryption

1. **Use strong keys** (32+ bytes for AES-256)
2. **Store keys securely** (environment variables, key management)
3. **Rotate keys regularly** for long-term security
4. **Validate MAC** before decryption to prevent tampering

### Password Security

1. **Use appropriate cost factors** (10-12 for bcrypt)
2. **Implement rehashing** when security parameters change
3. **Never store plain text** passwords
4. **Use timing-safe comparison** (provided by password_verify)

### Input Sanitization

1. **Sanitize all user input** before processing
2. **Use type-specific sanitization** for different data types
3. **Escape output** when displaying user content
4. **Validate after sanitization** for business logic
5. **Use prepared statements** for database queries

### General Security

1. **Layer security measures** (defense in depth)
2. **Log security events** for monitoring
3. **Keep dependencies updated** for security patches
4. **Use HTTPS** for all sensitive operations
5. **Implement rate limiting** for authentication attempts

## Integration

### With TreeHouse Framework

The Security Layer integrates seamlessly with other TreeHouse components:

```php
// With HTTP Layer
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Security\{Csrf, Sanitizer};

$request = new Request();
$csrf = new Csrf();
$sanitizer = new Sanitizer();

// Validate CSRF in middleware
if (!$csrf->validateToken($request->input('_token'))) {
    throw new SecurityException('CSRF token mismatch');
}

// Sanitize request data
$rules = ['name' => 'string', 'email' => 'email'];
$clean = $sanitizer->sanitizeArray($request->all(), $rules);
```

```php
// With Auth Layer
use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Security\Hash;

$auth = new AuthManager();
$hash = new Hash();

// Custom user provider with secure hashing
class SecureUserProvider extends DatabaseUserProvider
{
    private Hash $hash;
    
    public function validateCredentials($user, array $credentials): bool
    {
        return $this->hash->check($credentials['password'], $user->password);
    }
}
```

### Environment Configuration

```php
// .env configuration
APP_KEY=base64:your-32-byte-encryption-key-here
CSRF_TOKEN_LENGTH=32
PASSWORD_COST=12
```

### Service Registration

```php
// Register security services
$container->singleton(Csrf::class, function() {
    return new Csrf();
});

$container->singleton(Encryption::class, function() {
    return new Encryption($_ENV['APP_KEY']);
});

$container->singleton(Hash::class, function() {
    return new Hash();
});

$container->singleton(Sanitizer::class, function() {
    return new Sanitizer();
});
```

The Security Layer provides essential security utilities that work together to create a comprehensive security framework for web applications, ensuring data protection, user authentication security, and protection against common web vulnerabilities.