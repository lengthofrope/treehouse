# TreeHouse Framework - Validation Layer

The Validation Layer provides a comprehensive input validation system with built-in rules, custom rule support, and detailed error handling. This layer ensures data integrity and provides user-friendly error messages for form validation, API input validation, and data processing workflows.

## Table of Contents

- [Overview](#overview)
- [Components](#components)
- [Basic Usage](#basic-usage)
- [Validation Rules](#validation-rules)
- [Custom Rules](#custom-rules)
- [Error Handling](#error-handling)
- [Advanced Features](#advanced-features)
- [File Validation](#file-validation)
- [Integration](#integration)

## Overview

The Validation Layer consists of four main components:

- **Validator**: Main validation engine that processes rules and data
- **RuleInterface**: Contract for all validation rules
- **ValidationException**: Exception thrown when validation fails
- **Built-in Rules**: Comprehensive set of pre-built validation rules

### Key Features

- **Comprehensive Rule Set**: 25+ built-in validation rules
- **Custom Rule Support**: Easy registration of custom validation logic
- **Dot Notation**: Validate nested arrays and objects
- **File Validation**: Specialized rules for file uploads
- **Error Customization**: Custom error messages and field labels
- **Multiple Errors**: Support for multiple errors per field
- **Data Transformation**: Extract only validated data

## Components

### Core Classes

```php
// Main validator
LengthOfRope\TreeHouse\Validation\Validator

// Rule interface
LengthOfRope\TreeHouse\Validation\RuleInterface

// Exception handling
LengthOfRope\TreeHouse\Validation\ValidationException

// Built-in rules (examples)
LengthOfRope\TreeHouse\Validation\Rules\Required
LengthOfRope\TreeHouse\Validation\Rules\Email
LengthOfRope\TreeHouse\Validation\Rules\Between
```

## Basic Usage

### Simple Validation

```php
use LengthOfRope\TreeHouse\Validation\Validator;

// Basic validation
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25
];

$rules = [
    'name' => ['required', 'string', 'min:2'],
    'email' => ['required', 'email'],
    'age' => ['required', 'integer', 'between:18,100']
];

$validator = Validator::make($data, $rules);

try {
    $validatedData = $validator->validate();
    // Validation passed - use $validatedData
} catch (ValidationException $e) {
    // Validation failed - handle errors
    $errors = $e->getErrors();
}
```

### String Rule Format

```php
// Rules can be defined as pipe-separated strings
$rules = [
    'name' => 'required|string|min:2|max:50',
    'email' => 'required|email',
    'password' => 'required|string|min:8|confirmed',
    'age' => 'required|integer|between:18,100'
];

$validator = Validator::make($data, $rules);
```

### Checking Validation Results

```php
$validator = Validator::make($data, $rules);

// Check if validation passes
if ($validator->passes()) {
    $validatedData = $validator->getValidatedData();
    // Process valid data
}

// Check if validation fails
if ($validator->fails()) {
    $errors = $validator->getErrors();
    // Handle validation errors
}
```

## Validation Rules

### Basic Rules

```php
// Required field validation
$rules = ['name' => 'required'];

// String validation
$rules = ['name' => 'string'];

// Numeric validation
$rules = ['age' => 'numeric'];
$rules = ['count' => 'integer'];

// Boolean validation
$rules = ['active' => 'boolean'];

// Array validation
$rules = ['tags' => 'array'];
```

### String Rules

```php
// String length validation
$rules = [
    'name' => 'min:2',           // Minimum 2 characters
    'title' => 'max:100',        // Maximum 100 characters
    'description' => 'between:10,500' // Between 10-500 characters
];

// String format validation
$rules = [
    'username' => 'alpha_num',    // Alphanumeric only
    'slug' => 'alpha_dash',       // Letters, numbers, dashes, underscores
    'code' => 'alpha',            // Letters only
    'pattern' => 'regex:/^[A-Z]{3}$/' // Custom regex pattern
];
```

### Email and URL Validation

```php
$rules = [
    'email' => 'email',           // Valid email address
    'website' => 'url',           // Valid URL
    'ip_address' => 'ip'          // Valid IP address
];
```

### Numeric Rules

```php
$rules = [
    'age' => 'min:18',            // Minimum value 18
    'score' => 'max:100',         // Maximum value 100
    'rating' => 'between:1,5',    // Between 1 and 5
    'quantity' => 'size:10'       // Exactly 10
];
```

### Choice Validation

```php
$rules = [
    'status' => 'in:active,inactive,pending',     // Must be one of these
    'role' => 'not_in:admin,super_admin',         // Cannot be these values
];
```

### Comparison Rules

```php
$rules = [
    'password' => 'confirmed',              // Must have password_confirmation field
    'new_email' => 'different:email',       // Must be different from email field
    'confirm_email' => 'same:email'         // Must be same as email field
];
```

### Date Validation

```php
$rules = [
    'birth_date' => 'date',                 // Valid date format
    'event_date' => 'date:Y-m-d',          // Specific date format
];
```

### Complete Rule List

The validation system includes these built-in rules:

- **required**: Field must be present and not empty
- **email**: Valid email address format
- **numeric**: Numeric value (integer or float)
- **integer**: Integer value only
- **string**: String value
- **boolean**: Boolean value (true/false, 1/0, "true"/"false")
- **array**: Array value
- **min**: Minimum value/length/size
- **max**: Maximum value/length/size
- **between**: Value/length/size between min and max
- **in**: Value must be in specified list
- **not_in**: Value must not be in specified list
- **regex**: Value must match regular expression
- **alpha**: Alphabetic characters only
- **alpha_num**: Alphanumeric characters only
- **alpha_dash**: Letters, numbers, dashes, underscores
- **url**: Valid URL format
- **ip**: Valid IP address
- **date**: Valid date format
- **confirmed**: Field must have matching confirmation field
- **same**: Field must match another field
- **different**: Field must be different from another field
- **file**: Valid uploaded file
- **image**: Valid uploaded image file
- **mimes**: File must have specified MIME types
- **size**: Exact value/length/size

## Custom Rules

### Creating Custom Rules

```php
use LengthOfRope\TreeHouse\Validation\RuleInterface;

class CustomRule implements RuleInterface
{
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        // Custom validation logic
        return $value === 'expected_value';
    }

    public function message(string $field, array $parameters = []): string
    {
        return "The {$field} field has an invalid value.";
    }
}
```

### Registering Custom Rules

```php
// Register single custom rule
Validator::extend('custom', new CustomRule());

// Register multiple custom rules
Validator::extendMany([
    'custom1' => new CustomRule1(),
    'custom2' => new CustomRule2(),
]);

// Use custom rule
$rules = ['field' => 'custom'];
```

### Advanced Custom Rule Example

```php
class StrongPasswordRule implements RuleInterface
{
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if (!is_string($value) || strlen($value) < 8) {
            return false;
        }

        // Check for uppercase, lowercase, number, and special character
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $value);
    }

    public function message(string $field, array $parameters = []): string
    {
        return "The {$field} must be at least 8 characters and contain uppercase, lowercase, number, and special character.";
    }
}

// Register and use
Validator::extend('strong_password', new StrongPasswordRule());
$rules = ['password' => 'required|strong_password'];
```

## Error Handling

### ValidationException

```php
use LengthOfRope\TreeHouse\Validation\ValidationException;

try {
    $validator->validate();
} catch (ValidationException $e) {
    // Get all errors
    $allErrors = $e->getErrors();
    
    // Get errors for specific field
    $nameErrors = $e->getFieldErrors('name');
    
    // Get first error for field
    $firstError = $e->getFirstError('name');
    
    // Check if field has errors
    $hasErrors = $e->hasError('name');
    
    // Get all error messages as flat array
    $messages = $e->getAllMessages();
    
    // Get first error message from any field
    $firstMessage = $e->getFirstMessage();
    
    // Get original data
    $originalData = $e->getData();
    
    // Get error summary
    $summary = $e->getSummary(); // "Validation failed for 3 fields"
}
```

### Custom Error Messages

```php
$customMessages = [
    'name.required' => 'Please enter your full name.',
    'email.email' => 'Please enter a valid email address.',
    'age.between' => 'Age must be between :min and :max years.',
];

$validator = Validator::make($data, $rules, $customMessages);
```

### Custom Field Labels

```php
$customLabels = [
    'name' => 'Full Name',
    'email' => 'Email Address',
    'phone_number' => 'Phone Number'
];

$validator = Validator::make($data, $rules, [], $customLabels);
```

## Advanced Features

### Nested Array Validation

```php
$data = [
    'user' => [
        'profile' => [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ],
        'settings' => [
            'theme' => 'dark',
            'notifications' => true
        ]
    ]
];

$rules = [
    'user.profile.name' => 'required|string',
    'user.profile.email' => 'required|email',
    'user.settings.theme' => 'in:light,dark',
    'user.settings.notifications' => 'boolean'
];

$validator = Validator::make($data, $rules);
```

### Array Element Validation

```php
$data = [
    'users' => [
        ['name' => 'John', 'email' => 'john@example.com'],
        ['name' => 'Jane', 'email' => 'jane@example.com']
    ]
];

$rules = [
    'users' => 'required|array',
    'users.*.name' => 'required|string',
    'users.*.email' => 'required|email'
];
```

### Conditional Validation

```php
// Custom rule that checks other fields
class ConditionalRule implements RuleInterface
{
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        // Only validate if another field has specific value
        if (($data['type'] ?? '') === 'premium') {
            return !empty($value);
        }
        
        return true; // Skip validation for non-premium
    }

    public function message(string $field, array $parameters = []): string
    {
        return "The {$field} field is required for premium accounts.";
    }
}
```

## File Validation

### Basic File Validation

```php
use LengthOfRope\TreeHouse\Http\UploadedFile;

$rules = [
    'avatar' => 'file',                    // Must be uploaded file
    'photo' => 'image',                    // Must be image file
    'document' => 'mimes:pdf,doc,docx',    // Specific MIME types
    'upload' => 'size:1024'                // Exact size in bytes
];
```

### Image Validation

```php
$rules = [
    'profile_picture' => 'required|image',
    'gallery_image' => 'image|max:2048000', // Max 2MB
];

// The image rule accepts these MIME types:
// - image/jpeg, image/jpg
// - image/png
// - image/gif
// - image/bmp
// - image/svg+xml
// - image/webp
```

### File Size Validation

```php
$rules = [
    'small_file' => 'file|max:1024',        // Max 1KB
    'medium_file' => 'file|between:1024,1048576', // 1KB to 1MB
    'large_file' => 'file|size:5242880',    // Exactly 5MB
];
```

### MIME Type Validation

```php
$rules = [
    'document' => 'mimes:pdf,doc,docx,txt',
    'image' => 'mimes:jpeg,png,gif',
    'archive' => 'mimes:zip,rar,tar'
];
```

## Integration

### HTTP Request Integration

```php
use LengthOfRope\TreeHouse\Http\Request;

class UserController
{
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed'
        ];

        try {
            $validatedData = Validator::make($request->all(), $rules)->validate();
            
            // Create user with validated data
            $user = User::create($validatedData);
            
            return response()->json(['user' => $user], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->getErrors()
            ], 422);
        }
    }
}
```

### Form Validation Helper

```php
class FormValidator
{
    public static function validateUserRegistration(array $data): array
    {
        $rules = [
            'name' => 'required|string|between:2,100',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
            'terms' => 'required|boolean'
        ];

        $messages = [
            'name.required' => 'Please enter your full name.',
            'email.email' => 'Please enter a valid email address.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'terms.required' => 'You must accept the terms and conditions.'
        ];

        return Validator::make($data, $rules, $messages)->validate();
    }
}
```

### API Validation Middleware

```php
class ValidationMiddleware
{
    public function handle(Request $request, callable $next, array $rules)
    {
        try {
            $validator = Validator::make($request->all(), $rules);
            $validatedData = $validator->validate();
            
            // Add validated data to request
            $request->setValidatedData($validatedData);
            
            return $next($request);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->getErrors()
            ], 422);
        }
    }
}
```

### Database Model Integration

```php
use LengthOfRope\TreeHouse\Database\ActiveRecord;

class User extends ActiveRecord
{
    protected static function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8'
        ];
    }

    public static function createWithValidation(array $data): static
    {
        $validatedData = Validator::make($data, static::getValidationRules())->validate();
        return static::create($validatedData);
    }
}
```

### Testing Validation

```php
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public function testEmailValidation()
    {
        $validator = Validator::make(
            ['email' => 'invalid-email'],
            ['email' => 'email']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->getErrors());
    }

    public function testCustomRule()
    {
        Validator::extend('even', new class implements RuleInterface {
            public function passes(mixed $value, array $parameters = [], array $data = []): bool
            {
                return is_numeric($value) && $value % 2 === 0;
            }

            public function message(string $field, array $parameters = []): string
            {
                return "The {$field} must be an even number.";
            }
        });

        $validator = Validator::make(['number' => 3], ['number' => 'even']);
        $this->assertTrue($validator->fails());

        $validator = Validator::make(['number' => 4], ['number' => 'even']);
        $this->assertTrue($validator->passes());
    }
}
```

## Key Methods

### Validator Class

- [`make(array $data, array $rules, array $messages = [], array $labels = []): static`](src/TreeHouse/Validation/Validator.php:141) - Create validator instance
- [`validate(): array`](src/TreeHouse/Validation/Validator.php:156) - Validate data and return validated data
- [`passes(): bool`](src/TreeHouse/Validation/Validator.php:176) - Check if validation passes
- [`fails(): bool`](src/TreeHouse/Validation/Validator.php:191) - Check if validation fails
- [`getErrors(): array`](src/TreeHouse/Validation/Validator.php:201) - Get validation errors
- [`getValidatedData(): array`](src/TreeHouse/Validation/Validator.php:215) - Get only validated data
- [`extend(string $name, RuleInterface $rule): void`](src/TreeHouse/Validation/Validator.php:235) - Register custom rule

### ValidationException Class

- [`getErrors(): array`](src/TreeHouse/Validation/ValidationException.php:61) - Get all errors
- [`getFieldErrors(string $field): array`](src/TreeHouse/Validation/ValidationException.php:72) - Get field-specific errors
- [`getFirstError(string $field): ?string`](src/TreeHouse/Validation/ValidationException.php:83) - Get first error for field
- [`hasError(string $field): bool`](src/TreeHouse/Validation/ValidationException.php:95) - Check if field has errors
- [`getAllMessages(): array`](src/TreeHouse/Validation/ValidationException.php:105) - Get all error messages
- [`getData(): array`](src/TreeHouse/Validation/ValidationException.php:137) - Get original data

### RuleInterface

- [`passes(mixed $value, array $parameters = [], array $data = []): bool`](src/TreeHouse/Validation/RuleInterface.php:27) - Validate value
- [`message(string $field, array $parameters = []): string`](src/TreeHouse/Validation/RuleInterface.php:36) - Get error message

The Validation Layer provides a robust, extensible validation system that ensures data integrity while providing clear, actionable error messages for users and developers.
