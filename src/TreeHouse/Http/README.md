# TreeHouse Http Library

The TreeHouse Http library provides a comprehensive set of classes for handling HTTP requests and responses in PHP. This library is designed to simplify web development by offering well-tested, secure utilities for request handling, response building, session management, cookie handling, and file uploads.

## Table of Contents

- [Classes Overview](#classes-overview)
  - [Request - HTTP Request Handler](#request---http-request-handler)
  - [Response - HTTP Response Builder](#response---http-response-builder)
  - [Session - Session Management](#session---session-management)
  - [Cookie - Cookie Handler](#cookie---cookie-handler)
  - [UploadedFile - File Upload Handler](#uploadedfile---file-upload-handler)
- [Usage Examples](#usage-examples)

## Classes Overview

### Request - HTTP Request Handler

The `Request` class provides comprehensive access to incoming HTTP requests, including headers, parameters, files, and other request-related information.

#### Key Features:
- **Global Request Creation**: Create from PHP superglobals ($_GET, $_POST, etc.)
- **Data Access**: Access query parameters, form data, and JSON payloads
- **File Handling**: Manage uploaded files with validation
- **Header Management**: Read and validate HTTP headers
- **Security Features**: IP detection, HTTPS detection, CSRF protection

#### Core Methods:
```php
// Create from globals
$request = Request::createFromGlobals();

// Request information
$request->method();                         // 'GET', 'POST', etc.
$request->uri();                           // '/path/to/resource'
$request->url();                           // 'https://example.com/path'
$request->path();                          // '/path/to/resource'
$request->isSecure();                      // true/false for HTTPS

// Input data
$request->input('name', 'default');        // Get input with default
$request->query('page', 1);                // Get query parameter
$request->request('email');                // Get POST/PUT data
$request->json('user.name');               // Get JSON data with dot notation

// Headers and metadata
$request->header('content-type');          // Get header value
$request->hasHeader('authorization');      // Check header exists
$request->ip();                           // Get client IP
$request->userAgent();                    // Get user agent

// File uploads
$request->file('avatar');                 // Get uploaded file
$request->hasFile('document');            // Check if file uploaded
$request->files();                        // Get all files

// Request type detection
$request->isMethod('POST');               // Check HTTP method
$request->isAjax();                       // Check if AJAX request
$request->expectsJson();                  // Check if expects JSON response
```

### Response - HTTP Response Builder

The `Response` class provides a fluent interface for building and sending HTTP responses with proper status codes, headers, and content handling.

#### Key Features:
- **Multiple Content Types**: JSON, HTML, plain text, file downloads
- **Status Code Helpers**: Pre-built methods for common HTTP status codes
- **Header Management**: Set, modify, and remove response headers
- **Cookie Support**: Attach cookies to responses
- **File Responses**: Serve files for download or inline display

#### Core Methods:
```php
// Basic responses
Response::ok('Hello World');               // 200 OK
Response::created($data);                  // 201 Created
Response::noContent();                     // 204 No Content

// Content-specific responses
Response::json(['status' => 'success']);   // JSON response
Response::html('<h1>Hello</h1>');         // HTML response
Response::text('Plain text');             // Text response

// Redirects
Response::redirect('/dashboard');          // 302 redirect
Response::redirect('/login', 301);        // 301 permanent redirect

// File responses
Response::download('/path/to/file.pdf');   // Force download
Response::file('/path/to/image.jpg');     // Inline display

// Error responses
Response::badRequest('Invalid input');     // 400 Bad Request
Response::unauthorized();                  // 401 Unauthorized
Response::forbidden();                     // 403 Forbidden
Response::notFound();                     // 404 Not Found
Response::serverError();                  // 500 Internal Server Error

// Fluent interface
$response = Response::json($data)
    ->setStatusCode(201)
    ->setHeader('X-Custom', 'value')
    ->withCookie('session', $sessionId);

// Send response
$response->send();
```

### Session - Session Management

The `Session` class provides secure session handling with flash data, CSRF protection, and session regeneration capabilities.

#### Key Features:
- **Secure Configuration**: Configurable security settings and cookie parameters
- **Flash Data**: Temporary data that persists for one request cycle
- **CSRF Protection**: Built-in CSRF token generation and validation
- **Session Regeneration**: Prevent session fixation attacks
- **Dot Notation**: Access nested session data with dot notation

#### Core Methods:
```php
// Session lifecycle
$session = new Session(['lifetime' => 3600]);
$session->start();                        // Start session
$session->regenerate();                   // Regenerate session ID
$session->destroy();                      // Destroy session

// Data management
$session->set('user.name', 'John Doe');   // Set session data
$session->get('user.name', 'Guest');      // Get with default
$session->has('user.email');             // Check if exists
$session->remove('user.temp');           // Remove data
$session->all();                         // Get all session data
$session->clear();                       // Clear all data

// Flash data (survives one request)
$session->flash('message', 'Success!');   // Set flash data
$session->getFlash('message');            // Get flash data
$session->hasFlash('error');             // Check flash exists
$session->keepFlash(['message']);        // Keep for another request
$session->reflash();                     // Keep all flash data

// Utility methods
$session->pull('temp_data');             // Get and remove
$session->increment('page_views');       // Increment counter
$session->decrement('attempts', 2);      // Decrement counter

// CSRF protection
$token = $session->token();              // Get CSRF token
$session->regenerateToken();             // Generate new token
$session->validateToken($userToken);     // Validate token
```

### Cookie - Cookie Handler

The `Cookie` class provides secure cookie management with proper encoding, security attributes, and validation.

#### Key Features:
- **Security Attributes**: Secure, HttpOnly, SameSite configuration
- **Flexible Expiration**: Session cookies, timed expiration, or permanent cookies
- **Validation**: Cookie name and attribute validation
- **Easy Creation**: Factory methods for common cookie types
- **Header Generation**: Generate proper Set-Cookie headers

#### Core Methods:
```php
// Cookie creation
$cookie = new Cookie('name', 'value', time() + 3600);
$cookie = Cookie::make('session_id', $id, 120);  // 120 minutes
$cookie = Cookie::forever('remember_token', $token);
$cookie = Cookie::forget('old_cookie');          // Deletion cookie

// Security configuration
$cookie->setSecure(true)                // HTTPS only
       ->setHttpOnly(true)              // No JavaScript access
       ->setSameSite('Strict')          // CSRF protection
       ->setDomain('.example.com')      // Domain scope
       ->setPath('/admin');             // Path scope

// Cookie properties
$cookie->getName();                     // Get cookie name
$cookie->getValue();                    // Get cookie value
$cookie->getExpires();                  // Get expiration timestamp
$cookie->isExpired();                   // Check if expired
$cookie->isSessionCookie();             // Check if session cookie
$cookie->getMaxAge();                   // Get max age in seconds

// Validation
Cookie::isValidName('my-cookie');       // Validate cookie name
Cookie::isValidSameSite('Lax');        // Validate SameSite value

// Global cookie access
Cookie::get('session_id', 'default');   // Get from $_COOKIE
Cookie::has('remember_token');          // Check if exists

// Send cookie
$cookie->send();                        // Send to browser
$headerString = $cookie->toHeaderString(); // Get header string
```

### UploadedFile - File Upload Handler

The `UploadedFile` class represents uploaded files and provides methods for secure file handling, validation, and storage.

#### Key Features:
- **Upload Validation**: Check upload errors and file integrity
- **MIME Type Detection**: Both client-reported and actual MIME type detection
- **File Operations**: Move, store, and read file contents
- **Image Handling**: Image-specific validation and dimension detection
- **Security Validation**: Extension and MIME type whitelisting

#### Core Methods:
```php
// File information
$file->getName();                       // Original filename
$file->getClientOriginalName();         // Alias for getName()
$file->getExtension();                  // File extension
$file->getSize();                       // File size in bytes
$file->getClientMimeType();             // Client-reported MIME type
$file->getMimeType();                   // Actual MIME type (detected)

// Upload validation
$file->isValid();                       // Check if upload successful
$file->getError();                      // Get upload error code
$file->getErrorMessage();               // Get human-readable error
$file->isMoved();                       // Check if already moved

// File operations
$content = $file->getContent();         // Read file contents
$file->move('/path/to/destination.jpg'); // Move to specific path
$filename = $file->store('/uploads');    // Store with generated name
$file->getTempName();                   // Get temporary file path

// Validation helpers
$file->hasAllowedExtension(['jpg', 'png', 'gif']);
$file->hasAllowedMimeType(['image/jpeg', 'image/png']);
$file->isWithinSizeLimit(1024 * 1024);  // 1MB limit

// Image-specific methods
$file->isImage();                       // Check if image file
$dimensions = $file->getImageDimensions(); // [width, height]

// Security
$hash = $file->getHash('sha256');       // Get file hash
```

## Usage Examples

### Complete Request/Response Cycle
```php
// Handle incoming request
$request = Request::createFromGlobals();

// Route based on method and path
if ($request->isMethod('POST') && $request->path() === '/api/users') {
    // Get JSON data
    $userData = $request->json();
    
    // Validate CSRF token
    $session = new Session();
    $session->start();
    
    if (!$session->validateToken($request->header('X-CSRF-Token'))) {
        return Response::forbidden('Invalid CSRF token')->send();
    }
    
    // Process user creation
    $user = createUser($userData);
    
    // Flash success message
    $session->flash('message', 'User created successfully');
    
    // Return JSON response
    return Response::json($user, 201)->send();
}
```

### File Upload Processing
```php
$request = Request::createFromGlobals();

if ($request->hasFile('avatar')) {
    $file = $request->file('avatar');
    
    // Validate file
    if (!$file->isValid()) {
        return Response::badRequest('Upload failed: ' . $file->getErrorMessage());
    }
    
    // Check file type and size
    if (!$file->isImage()) {
        return Response::badRequest('Only image files are allowed');
    }
    
    if (!$file->isWithinSizeLimit(2 * 1024 * 1024)) { // 2MB
        return Response::badRequest('File too large');
    }
    
    // Store file
    try {
        $filename = $file->store('/uploads/avatars');
        return Response::json(['filename' => $filename]);
    } catch (RuntimeException $e) {
        return Response::serverError('Failed to store file');
    }
}
```

### Session-Based Authentication
```php
$session = new Session([
    'lifetime' => 7200,
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

$session->start();

// Login process
if ($request->isMethod('POST') && $request->path() === '/login') {
    $email = $request->input('email');
    $password = $request->input('password');
    
    if (authenticateUser($email, $password)) {
        // Regenerate session ID for security
        $session->regenerate();
        
        // Store user data
        $session->set('user', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role
        ]);
        
        // Set remember me cookie if requested
        if ($request->input('remember')) {
            $rememberToken = generateRememberToken();
            $cookie = Cookie::forever('remember_token', $rememberToken)
                ->setSecure(true)
                ->setHttpOnly(true);
            
            return Response::redirect('/dashboard')
                ->withCookie($cookie->getName(), $cookie->getValue());
        }
        
        return Response::redirect('/dashboard');
    } else {
        $session->flash('error', 'Invalid credentials');
        return Response::redirect('/login');
    }
}

// Check authentication
if (!$session->has('user')) {
    return Response::redirect('/login');
}
```

### API Response Building
```php
class ApiController
{
    public function handleRequest(Request $request): Response
    {
        try {
            // Content negotiation
            if (!$request->expectsJson()) {
                return Response::badRequest('API only accepts JSON requests');
            }
            
            // Rate limiting check
            $session = new Session();
            $session->start();
            
            $attempts = $session->get('api_attempts', 0);
            if ($attempts > 100) {
                return Response::json(['error' => 'Rate limit exceeded'], 429);
            }
            
            $session->increment('api_attempts');
            
            // Process request
            $data = $this->processApiRequest($request);
            
            // Build response with custom headers
            return Response::json($data)
                ->setHeader('X-API-Version', '1.0')
                ->setHeader('X-Rate-Limit-Remaining', (string)(100 - $attempts));
                
        } catch (Exception $e) {
            return Response::json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
```

### Cookie-Based Preferences
```php
// Set user preferences
$preferences = [
    'theme' => 'dark',
    'language' => 'en',
    'timezone' => 'UTC'
];

$cookie = Cookie::make('user_prefs', json_encode($preferences), 43200) // 30 days
    ->setPath('/')
    ->setSecure($request->isSecure())
    ->setHttpOnly(false) // Allow JavaScript access for UI
    ->setSameSite('Lax');

$response = Response::ok('Preferences saved')
    ->withCookie($cookie->getName(), $cookie->getValue());

// Read preferences
$prefsJson = Cookie::get('user_prefs');
$preferences = $prefsJson ? json_decode($prefsJson, true) : [];
$theme = $preferences['theme'] ?? 'light';
```

## Performance Considerations

- **Request Parsing**: Headers and input data are parsed lazily for better performance
- **File Uploads**: Large files are handled efficiently with streaming where possible
- **Session Storage**: Configurable session storage backends for scalability
- **Cookie Security**: Automatic security attribute configuration based on request context
- **Memory Management**: Uploaded files are processed without loading entire contents into memory

## Security Features

- **CSRF Protection**: Built-in token generation and validation
- **Secure Sessions**: Configurable security settings and session regeneration
- **File Upload Security**: MIME type validation and secure file handling
- **Cookie Security**: Automatic security attributes (Secure, HttpOnly, SameSite)
- **Input Validation**: Safe access to user input with proper escaping
- **IP Detection**: Accurate client IP detection through proxy headers

## Contributing

This library follows PSR-12 coding standards and includes comprehensive test coverage. When contributing:

1. Write tests for new functionality
2. Follow existing code style and patterns
3. Update documentation for new features
4. Ensure backward compatibility
5. Consider security implications of changes

## License

This library is part of the TreeHouse framework and follows the same licensing terms.