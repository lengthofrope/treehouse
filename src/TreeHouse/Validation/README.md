# TreeHouse Validation System

The TreeHouse Validation System provides comprehensive input validation with built-in rules, custom rule support, and detailed error messaging. It's designed for security, flexibility, and ease of use.

## Components

### Core Classes

- **[`Validator`](Validator.php)** - Main validation engine with rule processing and error handling
- **[`ValidationException`](ValidationException.php)** - Exception thrown when validation fails
- **[`RuleInterface`](RuleInterface.php)** - Contract for validation rules

### Built-in Validation Rules

The TreeHouse Validation System includes 25 comprehensive built-in rules covering all common validation scenarios:

#### Basic Type and Presence Validation

- **[`Required`](Rules/Required.php)** - Field must be present and not empty
  - Usage: `'required'`
  - Rejects: `null`, `''`, `[]`, empty strings with only whitespace

- **[`String`](Rules/StringRule.php)** - Field must be a string
  - Usage: `'string'`
  - Accepts: Any string value, allows empty strings

- **[`Numeric`](Rules/Numeric.php)** - Field must be numeric (integer or float)
  - Usage: `'numeric'`
  - Accepts: Integers, floats, numeric strings like `'123'`, `'12.34'`

- **[`Integer`](Rules/Integer.php)** - Field must be an integer
  - Usage: `'integer'`
  - Accepts: Integer values and integer strings like `'123'`

- **[`Boolean`](Rules/Boolean.php)** - Field must be a boolean value
  - Usage: `'boolean'`
  - Accepts: `true`, `false`, `1`, `0`, `'1'`, `'0'`, `'true'`, `'false'`

- **[`Array`](Rules/ArrayRule.php)** - Field must be an array
  - Usage: `'array'`
  - Accepts: Any array value, including empty arrays

#### String Format Validation

- **[`Email`](Rules/Email.php)** - Field must be a valid email address
  - Usage: `'email'`
  - Uses PHP's `FILTER_VALIDATE_EMAIL` for comprehensive validation

- **[`Url`](Rules/Url.php)** - Field must be a valid URL
  - Usage: `'url'`
  - Uses PHP's `FILTER_VALIDATE_URL` to validate HTTP/HTTPS URLs

- **[`Alpha`](Rules/Alpha.php)** - Field must contain only letters
  - Usage: `'alpha'`
  - Accepts: `a-z`, `A-Z` (Unicode-aware using `ctype_alpha`)

- **[`AlphaNum`](Rules/AlphaNum.php)** - Field must contain only letters and numbers
  - Usage: `'alpha_num'`
  - Accepts: `a-z`, `A-Z`, `0-9` (Unicode-aware using `ctype_alnum`)

- **[`AlphaDash`](Rules/AlphaDash.php)** - Field must contain only letters, numbers, dashes, and underscores
  - Usage: `'alpha_dash'`
  - Accepts: `a-z`, `A-Z`, `0-9`, `-`, `_`

- **[`Regex`](Rules/Regex.php)** - Field must match a regular expression pattern
  - Usage: `'regex:/pattern/flags'`
  - Example: `'regex:/^[A-Z]{2,4}$/'` for 2-4 uppercase letters

#### Size and Length Validation

- **[`Min`](Rules/Min.php)** - Field must have a minimum value, length, or size
  - Usage: `'min:value'`
  - For strings: minimum character length
  - For numbers: minimum numeric value (treats numeric strings as numbers)
  - For arrays: minimum number of elements
  - For files: minimum file size in bytes

- **[`Max`](Rules/Max.php)** - Field must have a maximum value, length, or size
  - Usage: `'max:value'`
  - For strings: maximum character length
  - For numbers: maximum numeric value (treats numeric strings as numbers)
  - For arrays: maximum number of elements
  - For files: maximum file size in bytes

- **[`Between`](Rules/Between.php)** - Field must be between minimum and maximum values
  - Usage: `'between:min,max'`
  - Applies same logic as Min/Max rules for different data types

- **[`Size`](Rules/Size.php)** - Field must have an exact size
  - Usage: `'size:value'`
  - For strings: exact character length
  - For numbers: exact numeric value
  - For arrays: exact number of elements
  - For files: exact file size in bytes

#### List and Comparison Validation

- **[`In`](Rules/In.php)** - Field value must be in a list of acceptable values
  - Usage: `'in:value1,value2,value3'`
  - Example: `'in:admin,user,moderator'`

- **[`NotIn`](Rules/NotIn.php)** - Field value must not be in a list of forbidden values
  - Usage: `'not_in:value1,value2,value3'`
  - Example: `'not_in:root,admin,test'`

- **[`Same`](Rules/Same.php)** - Field must have the same value as another field
  - Usage: `'same:other_field'`
  - Example: `'same:password'` for password confirmation

- **[`Different`](Rules/Different.php)** - Field must have a different value from another field
  - Usage: `'different:other_field'`
  - Example: `'different:current_password'` for password changes

- **[`Confirmed`](Rules/Confirmed.php)** - Field must have a matching confirmation field
  - Usage: `'confirmed'`
  - Automatically looks for `{field}_confirmation` field
  - Example: `password` field looks for `password_confirmation`

#### Date and Network Validation

- **[`Date`](Rules/Date.php)** - Field must be a valid date
  - Usage: `'date'`
  - Accepts: Date strings, DateTime objects, Unix timestamps
  - Examples: `'2023-12-25'`, `'December 25, 2023'`, `1640476800`

- **[`Ip`](Rules/Ip.php)** - Field must be a valid IP address
  - Usage: `'ip'`
  - Accepts: Both IPv4 and IPv6 addresses
  - Examples: `'192.168.1.1'`, `'2001:db8::1'`

#### File Upload Validation

- **[`File`](Rules/FileRule.php)** - Field must be a valid uploaded file
  - Usage: `'file'`
  - Validates `UploadedFile` instances and checks for upload errors

- **[`Image`](Rules/Image.php)** - Field must be a valid image file
  - Usage: `'image'`
  - Validates MIME types: `image/jpeg`, `image/png`, `image/gif`, `image/bmp`, `image/svg+xml`, `image/webp`

- **[`Mimes`](Rules/Mimes.php)** - Field must be a file with acceptable MIME types
  - Usage: `'mimes:type1,type2,type3'`
  - Example: `'mimes:application/pdf,image/jpeg,image/png'`

## Rule Usage Examples

### Complete Registration Form Validation

```php
$registrationData = [
    'username' => 'johndoe123',
    'email' => 'john@example.com',
    'password' => 'SecurePass123!',
    'password_confirmation' => 'SecurePass123!',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'age' => 25,
    'phone' => '+1-555-123-4567',
    'website' => 'https://johndoe.com',
    'bio' => 'Software developer and tech enthusiast.',
    'interests' => ['coding', 'gaming', 'music'],
    'avatar' => $uploadedImageFile,
    'resume' => $uploadedPdfFile,
    'terms_accepted' => true,
    'newsletter_opt_in' => '1'
];

$rules = [
    // Basic identity validation
    'username' => ['required', 'string', 'min:3', 'max:20', 'alpha_dash'],
    'email' => ['required', 'email'],
    'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', 'confirmed'],
    
    // Personal information
    'first_name' => ['required', 'string', 'min:2', 'max:50', 'alpha'],
    'last_name' => ['required', 'string', 'min:2', 'max:50', 'alpha'],
    'age' => ['required', 'integer', 'between:13,120'],
    
    // Contact information
    'phone' => ['string', 'regex:/^\+[1-9][\d\-]{1,14}$/'],
    'website' => ['url'],
    
    // Profile content
    'bio' => ['string', 'max:500'],
    'interests' => ['array', 'min:1', 'max:10'],
    
    // File uploads
    'avatar' => ['file', 'image', 'max:2048'], // Max 2MB
    'resume' => ['file', 'mimes:application/pdf', 'max:5120'], // Max 5MB PDF
    
    // Agreements
    'terms_accepted' => ['required', 'boolean'],
    'newsletter_opt_in' => ['boolean']
];

$validator = Validator::make($registrationData, $rules);
```

### E-commerce Product Validation

```php
$productData = [
    'name' => 'Wireless Bluetooth Headphones',
    'sku' => 'WBH-001',
    'price' => 99.99,
    'sale_price' => 79.99,
    'category' => 'electronics',
    'tags' => ['bluetooth', 'wireless', 'audio'],
    'description' => 'High-quality wireless headphones with noise cancellation.',
    'images' => [$image1, $image2, $image3],
    'specifications' => [
        'battery_life' => '30 hours',
        'weight' => '250g',
        'color' => 'black'
    ],
    'in_stock' => true,
    'stock_quantity' => 50
];

$rules = [
    'name' => ['required', 'string', 'min:3', 'max:100'],
    'sku' => ['required', 'string', 'regex:/^[A-Z0-9\-]{3,20}$/'],
    'price' => ['required', 'numeric', 'min:0.01'],
    'sale_price' => ['numeric', 'min:0.01'],
    'category' => ['required', 'string', 'in:electronics,clothing,books,home,sports'],
    'tags' => ['array', 'min:1', 'max:10'],
    'description' => ['required', 'string', 'min:20', 'max:1000'],
    'images' => ['required', 'array', 'min:1', 'max:5'],
    'specifications' => ['array'],
    'specifications.battery_life' => ['string'],
    'specifications.weight' => ['string'],
    'specifications.color' => ['string', 'in:black,white,blue,red,gray'],
    'in_stock' => ['required', 'boolean'],
    'stock_quantity' => ['required', 'integer', 'min:0']
];
```

### API Data Validation

```php
$apiData = [
    'user_id' => 12345,
    'action' => 'update_profile',
    'timestamp' => time(),
    'ip_address' => '192.168.1.100',
    'user_agent' => 'Mozilla/5.0...',
    'data' => [
        'profile' => [
            'display_name' => 'John Doe',
            'avatar_url' => 'https://example.com/avatar.jpg',
            'location' => 'New York, NY'
        ],
        'preferences' => [
            'theme' => 'dark',
            'language' => 'en',
            'notifications' => true
        ]
    ]
];

$rules = [
    'user_id' => ['required', 'integer', 'min:1'],
    'action' => ['required', 'string', 'in:create,read,update,delete'],
    'timestamp' => ['required', 'date'],
    'ip_address' => ['required', 'ip'],
    'user_agent' => ['string', 'max:500'],
    'data' => ['required', 'array'],
    'data.profile.display_name' => ['required', 'string', 'min:2', 'max:50'],
    'data.profile.avatar_url' => ['url'],
    'data.profile.location' => ['string', 'max:100'],
    'data.preferences.theme' => ['string', 'in:light,dark,auto'],
    'data.preferences.language' => ['string', 'regex:/^[a-z]{2}$/'],
    'data.preferences.notifications' => ['boolean']
];
```

### File Upload Validation Examples

```php
// Single image upload
$rules = [
    'profile_photo' => ['required', 'file', 'image', 'max:2048'] // Max 2MB
];

// Multiple file types
$rules = [
    'document' => ['required', 'file', 'mimes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'max:10240'] // Max 10MB
];

// Multiple image uploads
$rules = [
    'gallery' => ['required', 'array', 'min:1', 'max:10'],
    'gallery.*' => ['file', 'image', 'max:5120'] // Each image max 5MB
];

// Mixed file uploads
$rules = [
    'attachments' => ['array', 'max:5'],
    'attachments.*.file' => ['required', 'file', 'max:20480'], // Max 20MB each
    'attachments.*.description' => ['string', 'max:200']
];
```

### Advanced Validation Patterns

```php
// Password strength validation
$rules = [
    'password' => [
        'required',
        'string',
        'min:8',
        'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', // Must contain: lowercase, uppercase, digit, special char
        'different:current_password',
        'confirmed'
    ]
];

// Phone number validation (international)
$rules = [
    'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{1,14}$/']
];

// Credit card validation (basic format)
$rules = [
    'card_number' => ['required', 'string', 'regex:/^\d{4}\s?\d{4}\s?\d{4}\s?\d{4}$/'],
    'expiry_date' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
    'cvv' => ['required', 'string', 'regex:/^\d{3,4}$/']
];

// Social Security Number (US format)
$rules = [
    'ssn' => ['required', 'string', 'regex:/^\d{3}-\d{2}-\d{4}$/']
];

// URL slug validation
$rules = [
    'slug' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']
];
```

## Features

### Rule-Based Validation

```php
use LengthOfRope\TreeHouse\Validation\Validator;

$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
    'password' => 'secret123',
    'password_confirmation' => 'secret123'
];

$rules = [
    'name' => ['required', 'string', 'min:2', 'max:50'],
    'email' => ['required', 'email'],
    'age' => ['required', 'integer', 'min:18', 'max:120'],
    'password' => ['required', 'string', 'min:8', 'confirmed']
];

try {
    $validator = Validator::make($data, $rules);
    $validatedData = $validator->validate();
    // Validation passed
} catch (ValidationException $e) {
    $errors = $e->getErrors();
    // Handle validation errors
}
```

### Custom Error Messages

```php
$messages = [
    'name.required' => 'Please provide your name.',
    'email.email' => 'Please provide a valid email address.',
    'age.min' => 'You must be at least 18 years old.'
];

$validator = Validator::make($data, $rules, $messages);
```

### Custom Field Labels

```php
$labels = [
    'email' => 'Email Address',
    'password' => 'Password',
    'password_confirmation' => 'Confirm Password'
];

$validator = Validator::make($data, $rules, $messages, $labels);
```

### File Upload Validation

```php
use LengthOfRope\TreeHouse\Http\UploadedFile;

$data = [
    'avatar' => $uploadedFile, // UploadedFile instance
    'document' => $documentFile
];

$rules = [
    'avatar' => ['required', 'file', 'image', 'max:2048'], // Max 2MB
    'document' => ['file', 'mimes:application/pdf,application/msword']
];

$validator = Validator::make($data, $rules);
```

### String Rule Format

Rules can be specified as strings using pipe separation:

```php
$rules = [
    'username' => 'required|string|min:3|max:20|alpha_dash',
    'email' => 'required|email',
    'age' => 'required|integer|between:18,120'
];
```

### Conditional Validation

```php
// Check if validation passes without throwing exception
if ($validator->passes()) {
    // Validation successful
    $validatedData = $validator->getValidatedData();
} else {
    // Validation failed
    $errors = $validator->getErrors();
}

// Or check if validation fails
if ($validator->fails()) {
    $errors = $validator->getErrors();
}
```

### Custom Validation Rules

Create custom rules by implementing the `RuleInterface`:

```php
use LengthOfRope\TreeHouse\Validation\RuleInterface;

class UniqueUsername implements RuleInterface
{
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        // Check if username is unique in database
        return !User::where('username', $value)->exists();
    }

    public function message(string $field, array $parameters = []): string
    {
        return "The {$field} has already been taken.";
    }
}

// Register the custom rule
Validator::extend('unique_username', new UniqueUsername());

// Use in validation
$rules = ['username' => ['required', 'unique_username']];
```

### Multiple Custom Rules

```php
$customRules = [
    'unique_email' => new UniqueEmail(),
    'strong_password' => new StrongPassword(),
    'valid_phone' => new ValidPhone()
];

Validator::extendMany($customRules);
```

## Nested Array Validation

The validator supports dot notation for validating nested arrays:

```php
$data = [
    'user' => [
        'profile' => [
            'name' => 'John',
            'email' => 'john@example.com'
        ]
    ]
];

$rules = [
    'user.profile.name' => ['required', 'string'],
    'user.profile.email' => ['required', 'email']
];
```

## Error Handling

### ValidationException Methods

The `ValidationException` provides comprehensive methods for accessing validation errors:

```php
try {
    $validator->validate();
} catch (ValidationException $e) {
    // Get all errors organized by field
    $allErrors = $e->getErrors();
    /* Returns:
    [
        'email' => ['The email field must be a valid email address.'],
        'password' => [
            'The password field is required.',
            'The password field must be at least 8 characters.'
        ],
        'age' => ['The age field must be an integer.']
    ]
    */
    
    // Get errors for specific field
    $emailErrors = $e->getFieldErrors('email');
    // Returns: ['The email field must be a valid email address.']
    
    // Get first error for field
    $firstEmailError = $e->getFirstError('email');
    // Returns: 'The email field must be a valid email address.'
    
    // Check if field has errors
    if ($e->hasError('password')) {
        $passwordErrors = $e->getFieldErrors('password');
        // Handle password-specific errors
    }
    
    // Get all error messages as flat array (useful for displaying all errors)
    $allMessages = $e->getAllMessages();
    /* Returns:
    [
        'The email field must be a valid email address.',
        'The password field is required.',
        'The password field must be at least 8 characters.',
        'The age field must be an integer.'
    ]
    */
    
    // Get first error message from any field
    $firstMessage = $e->getFirstMessage();
    // Returns: 'The email field must be a valid email address.'
    
    // Get error summary
    $summary = $e->getSummary();
    // Returns: "Validation failed for 3 fields" (email, password, age)
    
    // Convert to array for JSON API responses
    $errorData = $e->toArray();
    /* Returns:
    [
        'message' => 'Validation failed for 3 fields',
        'errors' => [
            'email' => ['The email field must be a valid email address.'],
            'password' => [
                'The password field is required.',
                'The password field must be at least 8 characters.'
            ],
            'age' => ['The age field must be an integer.']
        ]
    ]
    */
}
```

### Error Response Patterns

#### API JSON Response

```php
class ApiController
{
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->getRules());
            $validatedData = $validator->validate();
            
            // Process valid data...
            return Response::json(['success' => true, 'data' => $result], 201);
            
        } catch (ValidationException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->getErrors(),
                'summary' => $e->getSummary()
            ], 422); // HTTP 422 Unprocessable Entity
        }
    }
}
```

#### Web Form Error Display

```php
class FormController
{
    public function processForm(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->getRules());
            $validatedData = $validator->validate();
            
            // Process form...
            return redirect('/success')->with('message', 'Form submitted successfully!');
            
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withInput($request->all())
                ->with('errors', $e->getErrors())
                ->with('error_summary', $e->getSummary());
        }
    }
}
```

#### Field-Specific Error Handling

```php
try {
    $validator->validate();
} catch (ValidationException $e) {
    // Handle specific field errors differently
    if ($e->hasError('email')) {
        $emailErrors = $e->getFieldErrors('email');
        // Maybe check if email already exists and show custom message
        if (in_array('email', array_keys($e->getErrors()))) {
            Logger::info('Email validation failed', ['email' => $data['email']]);
        }
    }
    
    if ($e->hasError('password')) {
        // Password validation failed - maybe suggest password requirements
        $passwordHelp = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
    }
    
    // Handle critical fields that must pass
    $criticalFields = ['email', 'password', 'terms_accepted'];
    $criticalErrors = array_intersect_key($e->getErrors(), array_flip($criticalFields));
    
    if (!empty($criticalErrors)) {
        // Handle critical validation failures
        Logger::warning('Critical field validation failed', $criticalErrors);
    }
}
```

### Custom Error Messages

You can provide custom error messages at multiple levels:

#### Rule-Level Custom Messages

```php
$messages = [
    'email.required' => 'We need your email address to contact you.',
    'email.email' => 'Please enter a valid email address.',
    'password.required' => 'Password is required for account security.',
    'password.min' => 'Password must be at least :min characters for security.',
    'password.confirmed' => 'Password confirmation does not match.',
    'age.integer' => 'Please enter your age as a number.',
    'age.min' => 'You must be at least :min years old to register.',
    'terms_accepted.required' => 'You must accept our terms and conditions to proceed.'
];

$validator = Validator::make($data, $rules, $messages);
```

#### Field-Level Custom Labels

```php
$labels = [
    'email' => 'Email Address',
    'password' => 'Password',
    'password_confirmation' => 'Confirm Password',
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'phone_number' => 'Phone Number',
    'date_of_birth' => 'Date of Birth'
];

$validator = Validator::make($data, $rules, $messages, $labels);
```

### Conditional Error Handling

```php
class SmartErrorHandler
{
    public function handleValidationErrors(ValidationException $e, array $context = []): array
    {
        $errors = $e->getErrors();
        $response = ['errors' => []];
        
        foreach ($errors as $field => $fieldErrors) {
            switch ($field) {
                case 'email':
                    // Check if it's a format issue or already exists
                    if (strpos($fieldErrors[0], 'valid email') !== false) {
                        $response['errors'][$field] = [
                            'message' => $fieldErrors[0],
                            'suggestion' => 'Please check the format (e.g., user@example.com)'
                        ];
                    }
                    break;
                    
                case 'password':
                    $response['errors'][$field] = [
                        'messages' => $fieldErrors,
                        'requirements' => [
                            'At least 8 characters',
                            'One uppercase letter',
                            'One lowercase letter',
                            'One number',
                            'One special character (@$!%*?&)'
                        ]
                    ];
                    break;
                    
                case 'file':
                case 'avatar':
                case 'image':
                    // File upload errors with helpful info
                    $response['errors'][$field] = [
                        'message' => $fieldErrors[0],
                        'allowed_types' => $context['allowed_file_types'] ?? 'jpeg, png, gif',
                        'max_size' => $context['max_file_size'] ?? '2MB'
                    ];
                    break;
                    
                default:
                    $response['errors'][$field] = $fieldErrors;
            }
        }
        
        $response['summary'] = $e->getSummary();
        $response['field_count'] = count($errors);
        
        return $response;
    }
}
```

## Performance Considerations

- **Lazy Evaluation**: Rules are processed only when validation runs
- **Early Termination**: Field validation stops on first rule failure
- **Efficient Rule Loading**: Built-in rules are instantiated on demand
- **Memory Management**: Large file validation uses streaming where possible

## Security Features

- **Input Sanitization**: All input is safely handled and validated
- **File Upload Security**: Comprehensive file validation with MIME type checking
- **XSS Prevention**: String validation prevents malicious content
- **Type Safety**: Strict type checking prevents type confusion attacks

## Integration Examples

### HTTP Request Validation

```php
use LengthOfRope\TreeHouse\Http\Request;

class UserController
{
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed'
        ];
        
        try {
            $validator = Validator::make($request->all(), $rules);
            $validatedData = $validator->validate();
            
            // Create user with validated data
            $user = User::create($validatedData);
            
            return Response::json($user, 201);
        } catch (ValidationException $e) {
            return Response::json([
                'message' => 'Validation failed',
                'errors' => $e->getErrors()
            ], 422);
        }
    }
}
```

### API Validation

```php
class ApiValidator
{
    public static function validateUserData(array $data): array
    {
        $rules = [
            'name' => 'required|string|min:2|max:50',
            'email' => 'required|email',
            'role' => 'required|in:admin,user,moderator',
            'preferences' => 'array',
            'preferences.theme' => 'string|in:light,dark',
            'preferences.notifications' => 'boolean'
        ];
        
        $validator = Validator::make($data, $rules);
        return $validator->validate();
    }
}
```

### Form Validation

```php
class ContactForm
{
    public function validate(array $data): bool
    {
        $rules = [
            'name' => 'required|string|max:100',
            'email' => 'required|email',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|min:10|max:1000',
            'attachment' => 'file|mimes:application/pdf,image/jpeg,image/png|max:5120'
        ];
        
        $validator = Validator::make($data, $rules);
        return $validator->passes();
    }
}
```

The TreeHouse Validation System provides a robust foundation for input validation with excellent security characteristics and seamless integration with other TreeHouse components.
