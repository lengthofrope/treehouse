# TreeHouse Framework - Http Layer

The Http layer provides comprehensive HTTP request and response handling for the TreeHouse Framework. It includes secure session management, cookie handling, file uploads, and a rich set of utilities for building web applications.

## Table of Contents

- [Overview](#overview)
- [Core Components](#core-components)
- [Request Handling](#request-handling)
- [Response Building](#response-building)
- [Session Management](#session-management)
- [Cookie Management](#cookie-management)
- [File Uploads](#file-uploads)
- [Helper Functions](#helper-functions)
- [Usage Examples](#usage-examples)
- [Security Features](#security-features)
- [Best Practices](#best-practices)

## Overview

The Http layer provides:

- **Request Processing**: Parse and access HTTP request data, headers, and files
- **Response Building**: Create various types of HTTP responses with proper headers
- **Session Management**: Secure session handling with flash data and CSRF protection
- **Cookie Management**: Secure cookie creation and management
- **File Uploads**: Robust file upload handling with validation
- **Security Features**: CSRF protection, secure headers, and input validation

## Core Components

### Request

The [`Request`](Request.php:20) class handles incoming HTTP requests:

```php
class Request
{
    public static function createFromGlobals(): static;
    public function method(): string;
    public function uri(): string;
    public function url(): string;
    public function path(): string;
    public function input(?string $key = null, mixed $default = null): mixed;
    public function query(?string $key = null, mixed $default = null): mixed;
    public function request(?string $key = null, mixed $default = null): mixed;
    public function file(string $key): ?UploadedFile;
    public function header(string $key, ?string $default = null): ?string;
    public function cookie(string $key, ?string $default = null): ?string;
    public function json(?string $key = null, mixed $default = null): mixed;
}
```

### Response

The [`Response`](Response.php:17) class builds HTTP responses:

```php
class Response
{
    public static function ok(string $content = '', array $headers = []): static;
    public static function json(mixed $data, int $statusCode = 200, array $headers = [], int $options = 0): static;
    public static function redirect(string $url, int $statusCode = 302, array $headers = []): static;
    public static function download(string $filePath, ?string $filename = null, array $headers = []): static;
    public static function notFound(string $message = 'Not Found', array $headers = []): static;
    public function send(): void;
}
```

### Session

The [`Session`](Session.php:20) class provides secure session management:

```php
class Session
{
    public function start(): bool;
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function flash(string $key, mixed $value): void;
    public function token(): string;
    public function regenerate(bool $deleteOldSession = true): bool;
}
```

### Cookie

The [`Cookie`](Cookie.php:17) class handles secure cookie management:

```php
class Cookie
{
    public static function make(string $name, string $value, int $minutes = 0, ...): static;
    public static function forever(string $name, string $value, ...): static;
    public static function forget(string $name, string $path = '/', string $domain = ''): static;
    public function send(): bool;
}
```

### UploadedFile

The [`UploadedFile`](UploadedFile.php:19) class handles file uploads:

```php
class UploadedFile
{
    public function isValid(): bool;
    public function move(string $targetPath): bool;
    public function store(string $directory, ?string $filename = null): string;
    public function getExtension(): string;
    public function getMimeType(): ?string;
    public function isImage(): bool;
}
```

## Request Handling

### Creating Requests

```php
// Create from PHP globals
$request = Request::createFromGlobals();

// Create manually
$request = new Request($_GET, $_POST, $_FILES, $_COOKIE, $_SERVER);
```

### Accessing Request Data

#### Basic Information
```php
$method = $request->method(); // GET, POST, PUT, etc.
$uri = $request->uri(); // /path/to/resource
$url = $request->url(); // https://example.com/path/to/resource
$path = $request->path(); // /path/to/resource (without query string)
$host = $request->getHost(); // example.com
$ip = $request->ip(); // Client IP address
```

#### Input Data
```php
// Get all input (query + request data)
$allInput = $request->input();

// Get specific input with default
$name = $request->input('name', 'Anonymous');

// Get query parameters
$page = $request->query('page', 1);

// Get request data (POST/PUT/PATCH)
$email = $request->request('email');
```

#### Headers
```php
// Get specific header
$contentType = $request->header('content-type');
$userAgent = $request->userAgent();

// Get all headers
$headers = $request->headers();

// Check if header exists
if ($request->hasHeader('authorization')) {
    $auth = $request->header('authorization');
}
```

#### JSON Data
```php
// Get all JSON data
$jsonData = $request->json();

// Get specific JSON field
$userId = $request->json('user.id');

// Check if request expects JSON
if ($request->expectsJson()) {
    return Response::json($data);
}
```

#### Request Type Detection
```php
// Check request method
if ($request->isMethod('POST')) {
    // Handle POST request
}

// Check if AJAX request
if ($request->isAjax()) {
    return Response::json($response);
}

// Check if secure (HTTPS)
if ($request->isSecure()) {
    // Handle secure request
}
```

## Response Building

### Basic Responses

```php
// Simple text response
return Response::ok('Hello World');

// HTML response
return Response::html('<h1>Welcome</h1>');

// Plain text response
return Response::text('Plain text content');

// No content response
return Response::noContent();
```

### JSON Responses

```php
// Simple JSON response
return Response::json(['message' => 'Success']);

// JSON with custom status code
return Response::json(['error' => 'Not found'], 404);

// JSON with custom headers
return Response::json($data, 200, ['X-Custom-Header' => 'value']);

// JSON with encoding options
return Response::json($data, 200, [], JSON_PRETTY_PRINT);
```

### Redirects

```php
// Simple redirect
return Response::redirect('/dashboard');

// Permanent redirect
return Response::redirect('/new-url', 301);

// Redirect with custom headers
return Response::redirect('/login', 302, ['X-Reason' => 'Authentication required']);
```

### File Responses

```php
// File download
return Response::download('/path/to/file.pdf', 'document.pdf');

// Inline file display
return Response::file('/path/to/image.jpg', 'photo.jpg');

// File with custom headers
return Response::download($filePath, $filename, [
    'X-Custom-Header' => 'value'
]);
```

### Error Responses

```php
// Bad request
return Response::badRequest('Invalid input data');

// Unauthorized
return Response::unauthorized('Authentication required');

// Forbidden
return Response::forbidden('Access denied');

// Not found
return Response::notFound('Resource not found');

// Method not allowed
return Response::methodNotAllowed('POST method not allowed');

// Unprocessable entity
return Response::unprocessableEntity('Validation failed');

// Server error
return Response::serverError('Internal server error');
```

### Response Manipulation

```php
$response = Response::ok('Content');

// Set headers
$response->setHeader('Cache-Control', 'no-cache');
$response->withHeaders([
    'X-Frame-Options' => 'DENY',
    'X-Content-Type-Options' => 'nosniff'
]);

// Set cookies
$response->withCookie('user_preference', 'dark_mode', time() + 3600);

// Modify content
$response->setContent('New content');

// Change status code
$response->setStatusCode(201);
```

## Session Management

### Starting Sessions

```php
$session = new Session([
    'name' => 'app_session',
    'lifetime' => 7200, // 2 hours
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

$session->start();
```

### Basic Session Operations

```php
// Set session data
$session->set('user_id', 123);
$session->set('user.profile.name', 'John Doe');

// Get session data
$userId = $session->get('user_id');
$name = $session->get('user.profile.name', 'Guest');

// Check if key exists
if ($session->has('user_id')) {
    // User is logged in
}

// Remove session data
$session->remove('temp_data');

// Get and remove (pull)
$message = $session->pull('status_message', 'No message');

// Clear all session data
$session->clear();
```

### Flash Data

```php
// Set flash data for next request
$session->flash('success', 'Profile updated successfully');
$session->flash('errors', ['Email is required', 'Password too short']);

// Get flash data (available only once)
$success = $session->getFlash('success');
$errors = $session->getFlash('errors', []);

// Check if flash data exists
if ($session->hasFlash('success')) {
    echo $session->getFlash('success');
}

// Keep flash data for another request
$session->keepFlash(['success', 'errors']);

// Reflash all data
$session->reflash();
```

### CSRF Protection

```php
// Get CSRF token
$token = $session->token();

// Regenerate token
$newToken = $session->regenerateToken();

// Validate token
$isValid = $session->validateToken($submittedToken);

// In forms
echo '<input type="hidden" name="_token" value="' . $session->token() . '">';
```

### Session Security

```php
// Regenerate session ID (prevent session fixation)
$session->regenerate();

// Increment/decrement values
$loginAttempts = $session->increment('login_attempts');
$remaining = $session->decrement('api_calls_remaining');

// Session information
$sessionId = $session->getId();
$sessionName = $session->getName();

// Destroy session
$session->destroy();
```

## Cookie Management

### Creating Cookies

```php
// Simple cookie (session cookie)
$cookie = Cookie::make('user_preference', 'dark_mode');

// Cookie with expiration (in minutes)
$cookie = Cookie::make('remember_token', $token, 1440); // 24 hours

// Permanent cookie (5 years)
$cookie = Cookie::forever('user_settings', json_encode($settings));

// Secure cookie with all options
$cookie = Cookie::make(
    'secure_data',
    $encryptedValue,
    60, // 1 hour
    '/', // path
    '.example.com', // domain
    true, // secure
    true, // httpOnly
    'Strict' // sameSite
);
```

### Cookie Operations

```php
// Send cookie to browser
$cookie->send();

// Get cookie properties
$name = $cookie->getName();
$value = $cookie->getValue();
$expires = $cookie->getExpires();
$maxAge = $cookie->getMaxAge();

// Check cookie status
$isExpired = $cookie->isExpired();
$isSession = $cookie->isSessionCookie();
$isSecure = $cookie->isSecure();
$isHttpOnly = $cookie->isHttpOnly();

// Modify cookie
$cookie->setValue('new_value')
       ->expiresIn(120) // 2 hours
       ->setSecure(true)
       ->setHttpOnly(true);

// Delete cookie
$deleteCookie = Cookie::forget('user_preference');
$deleteCookie->send();
```

### Reading Cookies

```php
// Get cookie from request
$preference = Cookie::get('user_preference', 'light_mode');

// Check if cookie exists
if (Cookie::has('remember_token')) {
    $token = Cookie::get('remember_token');
}

// Get cookie header string
$headerString = $cookie->toHeaderString();
```

## File Uploads

### Basic File Handling

```php
// Get uploaded file
$file = $request->file('avatar');

if ($file && $file->isValid()) {
    // File was uploaded successfully
    $filename = $file->getName();
    $size = $file->getSize();
    $extension = $file->getExtension();
    $mimeType = $file->getMimeType();
}
```

### File Validation

```php
// Check if file is valid
if (!$file->isValid()) {
    $error = $file->getErrorMessage();
    return Response::badRequest("Upload error: $error");
}

// Validate file extension
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
if (!$file->hasAllowedExtension($allowedExtensions)) {
    return Response::badRequest('Invalid file type');
}

// Validate MIME type
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
if (!$file->hasAllowedMimeType($allowedMimeTypes)) {
    return Response::badRequest('Invalid file format');
}

// Validate file size (5MB limit)
if (!$file->isWithinSizeLimit(5 * 1024 * 1024)) {
    return Response::badRequest('File too large');
}

// Check if file is an image
if ($file->isImage()) {
    $dimensions = $file->getImageDimensions();
    [$width, $height] = $dimensions;
}
```

### Storing Files

```php
// Move file to specific location
$targetPath = '/uploads/avatars/' . $file->getName();
$file->move($targetPath);

// Store with generated filename
$filename = $file->store('/uploads/documents');
// Returns: "a1b2c3d4e5f6g7h8.pdf"

// Store with custom filename
$filename = $file->store('/uploads/avatars', 'user_123_avatar');
// Returns: "user_123_avatar.jpg"
```

### File Information

```php
// Get file hash
$hash = $file->getHash('sha256');

// Get file content
$content = $file->getContent();

// Get original filename
$originalName = $file->getClientOriginalName();

// Get client MIME type (from browser)
$clientMimeType = $file->getClientMimeType();

// Get actual MIME type (detected from content)
$actualMimeType = $file->getMimeType();
```

## Helper Functions

### Session Helper

The [`session()`](helpers.php:13) helper provides convenient access to the session:

```php
// Get session instance
$session = session();

// Direct usage examples
session()->set('user_id', 123);
$userId = session()->get('user_id');
session()->flash('message', 'Success!');
$token = session()->token();
```

## Usage Examples

### User Authentication

```php
class AuthController
{
    public function login(Request $request): Response
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $remember = $request->input('remember', false);
        
        // Validate CSRF token
        if (!session()->validateToken($request->input('_token'))) {
            return Response::forbidden('Invalid CSRF token');
        }
        
        if ($this->attemptLogin($email, $password)) {
            // Regenerate session ID for security
            session()->regenerate();
            
            // Set user session
            session()->set('user_id', $user->id);
            session()->flash('success', 'Welcome back!');
            
            // Set remember cookie if requested
            if ($remember) {
                $rememberToken = $this->generateRememberToken();
                $cookie = Cookie::make('remember_token', $rememberToken, 43200); // 30 days
                
                return Response::redirect('/dashboard')->withCookie(
                    $cookie->getName(),
                    $cookie->getValue(),
                    $cookie->getExpires()
                );
            }
            
            return Response::redirect('/dashboard');
        }
        
        session()->flash('error', 'Invalid credentials');
        return Response::redirect('/login');
    }
}
```

### File Upload Handler

```php
class FileUploadController
{
    public function uploadAvatar(Request $request): Response
    {
        $file = $request->file('avatar');
        
        if (!$file || !$file->isValid()) {
            return Response::json([
                'error' => 'No valid file uploaded'
            ], 400);
        }
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!$file->hasAllowedMimeType($allowedTypes)) {
            return Response::json([
                'error' => 'Only JPEG, PNG, and GIF images are allowed'
            ], 400);
        }
        
        if (!$file->isWithinSizeLimit($maxSize)) {
            return Response::json([
                'error' => 'File size must be less than 2MB'
            ], 400);
        }
        
        // Validate image dimensions
        if ($file->isImage()) {
            $dimensions = $file->getImageDimensions();
            if ($dimensions && ($dimensions[0] > 1000 || $dimensions[1] > 1000)) {
                return Response::json([
                    'error' => 'Image dimensions must be less than 1000x1000'
                ], 400);
            }
        }
        
        try {
            // Store file
            $filename = $file->store('/uploads/avatars', 'user_' . session()->get('user_id'));
            
            // Update user avatar in database
            $this->updateUserAvatar(session()->get('user_id'), $filename);
            
            return Response::json([
                'success' => true,
                'filename' => $filename,
                'url' => '/uploads/avatars/' . $filename
            ]);
            
        } catch (Exception $e) {
            return Response::json([
                'error' => 'Failed to upload file'
            ], 500);
        }
    }
}
```

### API Response Handler

```php
class ApiController
{
    public function handleRequest(Request $request): Response
    {
        // Check if request expects JSON
        if (!$request->expectsJson()) {
            return Response::badRequest('API endpoints require JSON requests');
        }
        
        // Get JSON data
        $data = $request->json();
        
        if (empty($data)) {
            return Response::json([
                'error' => 'Invalid JSON data'
            ], 400);
        }
        
        try {
            $result = $this->processData($data);
            
            return Response::json([
                'success' => true,
                'data' => $result
            ])->withHeaders([
                'X-API-Version' => '1.0',
                'X-Rate-Limit-Remaining' => '99'
            ]);
            
        } catch (ValidationException $e) {
            return Response::json([
                'error' => 'Validation failed',
                'details' => $e->getErrors()
            ], 422);
            
        } catch (Exception $e) {
            return Response::json([
                'error' => 'Internal server error'
            ], 500);
        }
    }
}
```

### Session-based Shopping Cart

```php
class CartController
{
    public function addItem(Request $request): Response
    {
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity', 1);
        
        // Get current cart from session
        $cart = session()->get('cart', []);
        
        // Add or update item
        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
        } else {
            $cart[$productId] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'added_at' => time()
            ];
        }
        
        // Save cart back to session
        session()->set('cart', $cart);
        session()->flash('success', 'Item added to cart');
        
        return Response::json([
            'success' => true,
            'cart_count' => count($cart)
        ]);
    }
    
    public function getCart(): Response
    {
        $cart = session()->get('cart', []);
        $cartData = $this->enrichCartData($cart);
        
        return Response::json([
            'cart' => $cartData,
            'total' => $this->calculateTotal($cartData)
        ]);
    }
}
```

## Security Features

### CSRF Protection

```php
// Generate and validate CSRF tokens
$token = session()->token();

// In forms
echo '<input type="hidden" name="_token" value="' . htmlspecialchars($token) . '">';

// Validation
if (!session()->validateToken($request->input('_token'))) {
    return Response::forbidden('CSRF token mismatch');
}
```

### Secure Headers

```php
$response = Response::ok($content);

// Add security headers
$response->withHeaders([
    'X-Frame-Options' => 'DENY',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'Content-Security-Policy' => "default-src 'self'"
]);
```

### Secure Cookies

```php
// Create secure cookie
$cookie = Cookie::make('session_data', $encryptedData, 60)
    ->setSecure(true)
    ->setHttpOnly(true)
    ->setSameSite('Strict');
```

### Input Validation

```php
// Validate and sanitize input
$email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);
if (!$email) {
    return Response::badRequest('Invalid email address');
}

// Check for required fields
$requiredFields = ['name', 'email', 'password'];
foreach ($requiredFields as $field) {
    if (!$request->input($field)) {
        return Response::badRequest("Field '$field' is required");
    }
}
```

## Best Practices

### Request Handling

```php
// Always validate input
$input = $request->input();
$validated = $this->validateInput($input);

// Use appropriate response types
if ($request->expectsJson()) {
    return Response::json($data);
} else {
    return Response::html($view);
}

// Handle different HTTP methods appropriately
switch ($request->method()) {
    case 'GET':
        return $this->show($request);
    case 'POST':
        return $this->store($request);
    case 'PUT':
        return $this->update($request);
    case 'DELETE':
        return $this->destroy($request);
}
```

### Session Management

```php
// Regenerate session ID on privilege changes
if ($userLoggedIn) {
    session()->regenerate();
}

// Use flash data for one-time messages
session()->flash('success', 'Operation completed');

// Clear sensitive data after use
$sensitiveData = session()->pull('sensitive_data');
```

### File Upload Security

```php
// Always validate uploaded files
if ($file->isValid()) {
    // Check file type
    if (!$file->hasAllowedExtension(['jpg', 'png'])) {
        throw new InvalidFileException();
    }
    
    // Check file size
    if (!$file->isWithinSizeLimit(5 * 1024 * 1024)) {
        throw new FileTooLargeException();
    }
    
    // Generate secure filename
    $filename = $file->store('/secure/uploads');
}
```

### Response Security

```php
// Always set appropriate headers
$response->withHeaders([
    'Cache-Control' => 'no-cache, no-store, must-revalidate',
    'Pragma' => 'no-cache',
    'Expires' => '0'
]);

// Escape output in HTML responses
$safeContent = htmlspecialchars($userContent, ENT_QUOTES, 'UTF-8');
return Response::html($safeContent);
```

The Http layer provides a comprehensive foundation for handling HTTP requests and responses securely and efficiently in web applications.