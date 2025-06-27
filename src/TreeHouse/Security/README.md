# TreeHouse Security System

The TreeHouse Security System provides comprehensive security utilities for web applications, including CSRF protection, encryption, password hashing, input sanitization, and file validation with seamless integration with the TreeHouse HTTP components.

## Components

### Core Classes

- **[`Csrf`](Csrf.php)** - Cross-Site Request Forgery protection with token generation and validation
- **[`Encryption`](Encryption.php)** - AES-256-CBC encryption and decryption with payload support
- **[`Hash`](Hash.php)** - Secure password hashing using PHP's password functions
- **[`Sanitizer`](Sanitizer.php)** - Input sanitization and XSS protection utilities
- **[`FileValidator`](FileValidator.php)** - File upload validation and security scanning

## Features

### CSRF Protection

```php
use LengthOfRope\TreeHouse\Security\Csrf;
use LengthOfRope\TreeHouse\Http\Session;

$session = new Session();
$csrf = new Csrf($session);

// Generate and get token
$token = $csrf->generateToken();
$currentToken = $csrf->getToken();

// Verify token
$isValid = $csrf->verifyToken($userToken);

// HTML helpers
echo $csrf->getTokenField(); // Hidden input field
echo $csrf->getTokenMeta();  // Meta tag for AJAX

// Verify request data
$isValidRequest = $csrf->verifyRequest($_POST);
```

### Encryption & Decryption

```php
use LengthOfRope\TreeHouse\Security\Encryption;

// Generate a secure key
$key = Encryption::generateKey();
$encryption = new Encryption($key);

// Basic encryption
$encrypted = $encryption->encrypt('sensitive data');
$decrypted = $encryption->decrypt($encrypted);

// Payload encryption with expiration
$payload = ['user_id' => 123, 'role' => 'admin'];
$encrypted = $encryption->encryptPayload($payload, time() + 3600);
$decrypted = $encryption->decryptPayload($encrypted);

// Hashing and verification
$hash = $encryption->hash('data', 'salt');
$isValid = $encryption->verifyHash('data', $hash, 'salt');

// Secure random bytes
$randomBytes = $encryption->secureRandomBytes(32);
```

### Password Hashing

```php
use LengthOfRope\TreeHouse\Security\Hash;

$hash = new Hash();

// Hash password
$hashedPassword = $hash->make('user_password');

// Verify password
$isValid = $hash->check('user_password', $hashedPassword);

// Check if rehashing is needed
if ($hash->needsRehash($hashedPassword)) {
    $newHash = $hash->make('user_password');
}

// Get hash information
$info = $hash->getInfo($hashedPassword);
```

### Input Sanitization

```php
use LengthOfRope\TreeHouse\Security\Sanitizer;

$sanitizer = new Sanitizer();

// Basic sanitization
$clean = $sanitizer->sanitizeString('<script>alert("xss")</script>Hello');
$email = $sanitizer->sanitizeEmail('user@example.com');
$url = $sanitizer->sanitizeUrl('https://example.com');
$int = $sanitizer->sanitizeInteger('123abc');
$float = $sanitizer->sanitizeFloat('123.45abc');
$bool = $sanitizer->sanitizeBoolean('true');

// Array sanitization with rules
$rules = [
    'name' => 'string',
    'email' => 'email',
    'age' => 'integer',
    'active' => 'boolean'
];
$sanitized = $sanitizer->sanitizeArray($_POST, $rules);

// XSS protection
$safe = $sanitizer->removeXssAttempts($userInput);
$escaped = $sanitizer->escapeHtml($userInput);
$safeAttribute = $sanitizer->escapeAttribute($userInput);

// File and path sanitization
$safeFilename = $sanitizer->sanitizeFilename($uploadedFilename);
$safePath = $sanitizer->sanitizePath($userPath);

// Comprehensive validation and sanitization
$result = $sanitizer->validateAndSanitize($_POST, [
    'name' => ['type' => 'string', 'max_length' => 100],
    'email' => ['type' => 'email'],
    'age' => ['type' => 'integer', 'min' => 18, 'max' => 120]
]);

if ($result['valid']) {
    $cleanData = $result['data'];
} else {
    $errors = $result['errors'];
}
```

### File Validation

```php
use LengthOfRope\TreeHouse\Security\FileValidator;
use LengthOfRope\TreeHouse\Http\UploadedFile;

$validator = new FileValidator();

// Basic validations
$isValidType = $validator->validateFileType($mimeType, ['image/jpeg', 'image/png']);
$isValidSize = $validator->validateFileSize($fileSize, 1024 * 1024); // 1MB
$isValidExt = $validator->validateFileExtension($filename, ['jpg', 'png', 'gif']);

// Security checks
$isExecutable = $validator->isExecutableFile($filename);
$hasValidContent = $validator->validateFileContent($fileContent);
$passesVirusScan = $validator->scanForViruses($fileContent);

// Image validation
$isValidImage = $validator->validateImageFile($content, $filename);

// Comprehensive uploaded file validation
$uploadedFile = new UploadedFile($_FILES['upload']);
$rules = [
    'allowed_types' => ['image/jpeg', 'image/png', 'image/gif'],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif'],
    'max_size' => 2 * 1024 * 1024, // 2MB
    'scan_content' => true
];

$result = $validator->validateUploadedFile($uploadedFile, $rules);
if ($result['valid']) {
    // File is safe to process
} else {
    $errors = $result['errors'];
}

// File utilities
$mimeType = $validator->getMimeTypeFromContent($fileContent);
$safeFilename = $validator->sanitizeFilename($originalFilename);
$secureFilename = $validator->generateSecureFilename($originalFilename);
```

## Support Class Integration

The Security System leverages TreeHouse Support classes for enhanced functionality:

### Array Utilities

- Input sanitization uses [`Arr`](../Support/Arr.php) utilities for array operations
- Validation rules processing benefits from Arr methods
- Multi-dimensional data sanitization leverages Arr operations

```php
// Sanitizer uses Arr utilities internally
$sanitized = $sanitizer->sanitizeArray($input, $rules);

// File validation rules use array operations
$result = $validator->validateUploadedFile($file, $rules);
```

### Helper Functions

- Data extraction uses `dataGet()` helper for nested arrays
- Configuration merging uses helper functions
- Validation error handling leverages helper utilities

## Advanced Features

### Custom Validation Rules

```php
// Extend sanitizer for custom rules
class CustomSanitizer extends Sanitizer
{
    public function sanitizePhoneNumber(string $input): string
    {
        return preg_replace('/[^0-9+\-\(\)\s]/', '', $input);
    }
}

// Custom file validation
$validator = new FileValidator();
$customRules = [
    'allowed_types' => ['application/pdf', 'text/plain'],
    'max_size' => 5 * 1024 * 1024, // 5MB
    'custom_validation' => function($content) {
        return strpos($content, 'malicious') === false;
    }
];
```

### Security Headers Integration

```php
// Use with HTTP Response for security headers
use LengthOfRope\TreeHouse\Http\Response;

$response = new Response();
$csrf = new Csrf($session);

// Add CSRF token to response headers
$response->header('X-CSRF-Token', $csrf->getToken());

// Content Security Policy with nonce
$nonce = bin2hex(random_bytes(16));
$response->header('Content-Security-Policy', "script-src 'nonce-{$nonce}'");
```

### Session Integration

```php
// CSRF with custom session configuration
use LengthOfRope\TreeHouse\Http\Session;

$session = new Session([
    'name' => 'secure_session',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

$csrf = new Csrf($session);
```

## Error Handling

The Security System handles various security scenarios:

- **Invalid Tokens** - CSRF token validation failures
- **Encryption Errors** - Key validation and encryption/decryption failures
- **File Security** - Malicious file detection and validation errors
- **Input Validation** - Sanitization and validation rule violations
- **Payload Expiration** - Encrypted payload expiration handling

## Performance Considerations

- CSRF tokens use cryptographically secure random generation
- Encryption uses AES-256-CBC with secure IV generation
- Password hashing uses PHP's optimized password functions
- File validation includes efficient MIME type detection
- Content scanning uses optimized pattern matching
- Constant-time comparisons prevent timing attacks

## Integration Examples

### Basic Security Setup

```php
use LengthOfRope\TreeHouse\Security\{Csrf, Encryption, Hash, Sanitizer, FileValidator};
use LengthOfRope\TreeHouse\Http\{Request, Response, Session};

// Initialize security components
$session = new Session();
$csrf = new Csrf($session);
$encryption = new Encryption($_ENV['APP_KEY']);
$hash = new Hash();
$sanitizer = new Sanitizer();
$fileValidator = new FileValidator();

// Middleware-style CSRF protection
function csrfMiddleware($request, $csrf) {
    if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'])) {
        if (!$csrf->verifyRequest($request->all())) {
            throw new \Exception('CSRF token mismatch');
        }
    }
}

// Process request
$request = Request::createFromGlobals();
csrfMiddleware($request, $csrf);

// Sanitize input
$cleanData = $sanitizer->validateAndSanitize($request->all(), [
    'name' => ['type' => 'string', 'max_length' => 100],
    'email' => ['type' => 'email'],
    'message' => ['type' => 'string', 'max_length' => 1000]
]);
```

### File Upload Security

```php
// Secure file upload handling
function handleFileUpload($uploadedFile, $validator, $sanitizer) {
    // Validate file
    $rules = [
        'allowed_types' => ['image/jpeg', 'image/png', 'application/pdf'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
        'max_size' => 5 * 1024 * 1024, // 5MB
        'scan_content' => true
    ];
    
    $result = $validator->validateUploadedFile($uploadedFile, $rules);
    
    if (!$result['valid']) {
        throw new \Exception('File validation failed: ' . implode(', ', $result['errors']));
    }
    
    // Generate secure filename
    $secureFilename = $validator->generateSecureFilename($uploadedFile->getName());
    $safePath = $sanitizer->sanitizePath('uploads/' . $secureFilename);
    
    // Move file to secure location
    $uploadedFile->moveTo($safePath);
    
    return $safePath;
}
```

### API Authentication

```php
// JWT-style token with encryption
function generateApiToken($payload, $encryption) {
    $payload['expires_at'] = time() + 3600; // 1 hour
    $payload['issued_at'] = time();
    
    return $encryption->encryptPayload($payload);
}

function validateApiToken($token, $encryption) {
    try {
        $payload = $encryption->decryptPayload($token);
        
        if ($payload['expires_at'] < time()) {
            throw new \Exception('Token expired');
        }
        
        return $payload;
    } catch (\Exception $e) {
        throw new \Exception('Invalid token');
    }
}
```

The TreeHouse Security System provides a comprehensive foundation for securing web applications, with excellent performance characteristics and seamless integration with other TreeHouse components.