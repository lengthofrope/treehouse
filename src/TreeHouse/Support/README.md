# TreeHouse Support Library

The TreeHouse Support library provides a comprehensive set of utility classes and helper functions for common programming tasks in PHP. This library is designed to enhance developer productivity by offering well-tested, performant utilities for array manipulation, string processing, date/time handling, collections, UUID generation, and more.

## Table of Contents

- [Classes Overview](#classes-overview)
  - [Arr - Array Utilities](#arr---array-utilities)
  - [Collection - Enhanced Array Collections](#collection---enhanced-array-collections)
  - [Str - String Utilities](#str---string-utilities)
  - [Carbon - Date/Time Utilities](#carbon---datetime-utilities)
  - [Uuid - UUID Generation](#uuid---uuid-generation)
  - [Env - Environment Configuration](#env---environment-configuration)
- [Helper Functions](#helper-functions)
- [Usage Examples](#usage-examples)

## Classes Overview

### Arr - Array Utilities

The `Arr` class provides powerful utilities for working with arrays, including support for "dot" notation for nested array access.

#### Key Features:
- **Dot Notation Access**: Access nested arrays using dot notation (`user.profile.name`)
- **Array Manipulation**: Add, remove, filter, and transform array elements
- **Type Checking**: Determine if arrays are associative, lists, or accessible
- **Advanced Operations**: Collapse, flatten, cross-join, and pluck operations

#### Core Methods:
```php
// Dot notation access
Arr::get($array, 'user.profile.name', 'default');
Arr::set($array, 'user.profile.name', 'John Doe');
Arr::has($array, 'user.profile.email');

// Array manipulation
Arr::add($array, 'key', 'value');           // Add if key doesn't exist
Arr::forget($array, 'unwanted.key');        // Remove using dot notation
Arr::only($array, ['name', 'email']);       // Get subset of keys
Arr::except($array, ['password']);          // Get all except specified keys

// Advanced operations
Arr::flatten($nested);                      // Flatten multi-dimensional array
Arr::collapse($arrayOfArrays);              // Collapse array of arrays
Arr::pluck($users, 'name', 'id');          // Extract column from array of objects
```

### Collection - Enhanced Array Collections

The `Collection` class provides a fluent, object-oriented interface for working with arrays, inspired by modern functional programming concepts.

#### Key Features:
- **Fluent Interface**: Chain operations for readable, expressive code
- **Functional Programming**: Map, filter, reduce, and other functional operations
- **Laravel-Compatible**: Similar API to Laravel Collections
- **Type Safety**: Implements standard PHP interfaces (ArrayAccess, Countable, etc.)

#### Core Methods:
```php
$collection = Collection::make([1, 2, 3, 4, 5]);

// Functional operations
$collection->map(fn($x) => $x * 2)          // [2, 4, 6, 8, 10]
          ->filter(fn($x) => $x > 5)        // [6, 8, 10]
          ->values();                       // Reset keys

// Aggregation
$collection->sum();                         // Sum all values
$collection->avg();                         // Average of values
$collection->max();                         // Maximum value
$collection->min();                         // Minimum value

// Grouping and sorting
$users->groupBy('department');              // Group by field
$users->sortBy('name');                     // Sort by field
$users->unique('email');                    // Remove duplicates
```

### Str - String Utilities

The `Str` class offers comprehensive string manipulation utilities with Unicode support and performance optimizations.

#### Key Features:
- **Case Conversion**: camelCase, snake_case, StudlyCase, kebab-case
- **String Analysis**: Check for patterns, validate formats, detect encoding
- **Text Processing**: Limit text, mask sensitive data, pluralization
- **URL-Friendly**: Generate slugs, handle special characters

#### Core Methods:
```php
// Case conversion
Str::camel('hello_world');                  // 'helloWorld'
Str::snake('HelloWorld');                   // 'hello_world'
Str::studly('hello_world');                 // 'HelloWorld'
Str::kebab('HelloWorld');                   // 'hello-world'

// String analysis
Str::contains('Hello World', 'World');      // true
Str::startsWith('Hello', 'He');             // true
Str::endsWith('World', 'ld');               // true
Str::is('foo*', 'foobar');                  // true (pattern matching)

// Text processing
Str::limit('Long text here', 10);           // 'Long text...'
Str::words('One two three four', 2);        // 'One two...'
Str::mask('1234567890', '*', 4, 2);         // '1234**7890'
Str::plural('user', 2);                     // 'users'

// Utilities
Str::random(16);                            // Generate random string
Str::slug('Hello World!');                  // 'hello-world'
Str::uuid();                                // Generate UUID
```

### Carbon - Date/Time Utilities

The `Carbon` class extends PHP's DateTime with a fluent API and additional functionality for date/time manipulation.

#### Key Features:
- **Fluent API**: Chainable methods for date manipulation
- **Human-Readable Differences**: "2 hours ago", "in 3 days"
- **Timezone Support**: Full timezone handling and conversion
- **Business Logic**: Weekend/weekday detection, age calculation

#### Core Methods:
```php
// Creation
$now = Carbon::now();
$date = Carbon::create(2024, 1, 15, 10, 30);
$parsed = Carbon::parse('2024-01-15 10:30:00');

// Manipulation
$tomorrow = $now->addDay();
$nextWeek = $now->addWeeks(1);
$startOfMonth = $now->startOfMonth();
$endOfYear = $now->endOfYear();

// Comparison and differences
$date->isPast();                            // true/false
$date->isFuture();                          // true/false
$date->isToday();                           // true/false
$date->diffInDays($otherDate);              // Integer difference
$date->diffForHumans();                     // "2 hours ago"

// Formatting and conversion
$date->format('Y-m-d H:i:s');               // Custom format
$date->toArray();                           // Array representation
$date->toJson();                            // JSON string
```

### Uuid - UUID Generation

The `Uuid` class provides comprehensive UUID generation and manipulation utilities supporting all standard UUID versions.

#### Key Features:
- **Multiple Versions**: Support for UUID v1, v3, v4, and v5
- **Validation**: Validate UUID format and extract version/variant information
- **Binary Conversion**: Convert between string and binary representations
- **Short UUIDs**: Generate compact, URL-safe identifiers

#### Core Methods:
```php
// Generation
$uuid4 = Uuid::uuid4();                     // Random UUID
$uuid1 = Uuid::uuid1();                     // Time-based UUID
$uuid3 = Uuid::uuid3($namespace, $name);    // Name-based MD5
$uuid5 = Uuid::uuid5($namespace, $name);    // Name-based SHA1

// Validation and analysis
Uuid::isValid($uuid);                       // true/false
Uuid::getVersion($uuid);                    // 1, 3, 4, or 5
Uuid::getVariant($uuid);                    // 'RFC4122', 'NCS', etc.

// Utilities
Uuid::short();                              // Compact base62 UUID
Uuid::toBinary($uuid);                      // Binary representation
Uuid::fromBinary($binary);                  // Convert back to string
Uuid::compare($uuid1, $uuid2);              // Compare UUIDs
```

### Env - Environment Configuration

The `Env` class provides robust support for environment-specific configuration through `.env` files. This allows you to keep sensitive information like database passwords and API keys out of your version control while maintaining different configurations for development, staging, and production environments.

#### Key Features:
- **Automatic Loading**: Loads `.env` files from multiple possible locations
- **Type Conversion**: Automatic conversion of string values to appropriate PHP types
- **Secure Parsing**: Handles quotes, escape sequences, and comments safely
- **Caching**: Memory cache for environment variables to avoid re-parsing
- **Global Access**: Available through global `env()` helper function

#### Core Methods:
```php
// Basic usage
$dbHost = Env::get('DB_HOST', 'localhost');
$isDebug = Env::get('APP_DEBUG', false);
$apiKey = Env::get('API_KEY');

// Environment management
Env::load('/path/to/.env');                // Load specific .env file
Env::loadIfNeeded();                       // Load if not already loaded
Env::reload();                             // Force reload
Env::set('RUNTIME_VAR', 'value');          // Set programmatically

// Inspection
Env::has('API_KEY');                       // Check if variable exists
Env::all();                                // Get all environment variables
Env::clearCache();                         // Clear internal cache
```

#### Environment File Format:
```env
# Application settings
APP_NAME="My TreeHouse App"
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database configuration
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_database
DB_USERNAME=root
DB_PASSWORD=secret_password

# Type conversion examples
CACHE_ENABLED=true                         # boolean true
DEBUG_MODE=false                           # boolean false
MAX_SIZE=2048                              # integer 2048
TIMEOUT=30.5                               # float 30.5
OPTIONAL_KEY=null                          # null value
EMPTY_VALUE=empty                          # empty string ""
```

#### Quoted Values and Escaping:
```php
// .env file:
// MESSAGE="Hello\nWorld\ttab"
// PATH='/path/with spaces/file'
// URL="https://example.com?param=value"

$message = Env::get('MESSAGE');            // "Hello\nWorld\ttab" with actual newlines
$path = Env::get('PATH');                  // "/path/with spaces/file"
$url = Env::get('URL');                    // "https://example.com?param=value"
```

#### Global Helper Function:
```php
// Using the global env() helper (recommended)
$dbPassword = env('DB_PASSWORD');
$maxUpload = env('MAX_UPLOAD_SIZE', 1024);
$features = env('FEATURE_FLAGS', []);

// In configuration files
return [
    'name' => env('APP_NAME', 'TreeHouse App'),
    'debug' => env('APP_DEBUG', false),
    'database' => [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
    ],
];
```

#### Security Best Practices:
```php
// 1. Never commit .env files (automatically in .gitignore)
// 2. Use .env.example for sharing configuration structure
// 3. Generate secure keys:
//    APP_KEY=base64:dGVzdGluZy0zMi1jaGFyLXNlY3JldC1rZXktaGVyZQ==
//    ENCRYPTION_KEY=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6

// 4. Environment-specific configurations
if (env('APP_ENV') === 'production') {
    // Production-specific settings
}
```

#### Advanced Usage:
```php
// Custom loading for testing
if (defined('PHPUNIT_COMPOSER_INSTALL')) {
    Env::load(__DIR__ . '/.env.testing');
}

// Runtime configuration
Env::set('DYNAMIC_CONFIG', $computedValue);

// Bulk inspection
$allVars = Env::all();
$hasRequired = Env::has('REQUIRED_API_KEY');

// Cache management in long-running processes
Env::clearCache();  // Clear before reload
Env::reload();       // Reload from file
```

## Helper Functions

The library includes global helper functions for common operations:

### dataGet() and dataSet()
```php
// Get nested data with dot notation
$value = dataGet($array, 'user.profile.name', 'default');

// Set nested data with dot notation
dataSet($array, 'user.profile.name', 'John Doe');
```

### env()
```php
// Get environment variables with defaults
$dbHost = env('DB_HOST', 'localhost');
$isDebug = env('APP_DEBUG', false);
$apiKey = env('API_KEY');
```

### value() and with()
```php
// Execute closures or return values
$result = value($callback, $arg1, $arg2);

// Pass value through callback
$processed = with($value, fn($v) => strtoupper($v));
```

## Usage Examples

### Working with Complex Data Structures
```php
$users = [
    ['name' => 'John', 'email' => 'john@example.com', 'role' => 'admin'],
    ['name' => 'Jane', 'email' => 'jane@example.com', 'role' => 'user'],
    ['name' => 'Bob', 'email' => 'bob@example.com', 'role' => 'user'],
];

// Using Collection for data processing
$adminEmails = Collection::make($users)
    ->where('role', 'admin')
    ->pluck('email')
    ->all();

// Using Arr for data extraction
$names = Arr::pluck($users, 'name');
$usersByRole = Arr::keyBy($users, 'role');
```

### String Processing Pipeline
```php
$title = "  Hello World! This is a GREAT day  ";

$slug = Str::of($title)
    ->trim()
    ->lower()
    ->slug();  // "hello-world-this-is-a-great-day"

// Or using static methods
$processed = Str::slug(Str::lower(trim($title)));
```

### Date Range Processing
```php
$start = Carbon::parse('2024-01-01');
$end = Carbon::parse('2024-12-31');

$businessDays = 0;
$current = $start->copy();

while ($current->lte($end)) {
    if ($current->isWeekday()) {
        $businessDays++;
    }
    $current->addDay();
}

echo "Business days in 2024: {$businessDays}";
```

### UUID-Based Identifiers
```php
// Generate unique identifiers for different purposes
$sessionId = Uuid::uuid4();                 // Random session ID
$userId = Uuid::uuid1();                    // Time-ordered user ID
$apiKey = Uuid::short();                    // Compact API key

// Namespace-based UUIDs for consistent generation
$configId = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'config.example.com');
```

### Environment-Based Configuration
```php
// .env file:
// APP_ENV=production
// DB_HOST=prod-server.example.com
// CACHE_TTL=3600
// FEATURE_BETA=true

// Application configuration with environment fallbacks
$config = [
    'app' => [
        'name' => env('APP_NAME', 'TreeHouse App'),
        'env' => env('APP_ENV', 'development'),
        'debug' => env('APP_DEBUG', false),
        'url' => env('APP_URL', 'http://localhost:8000'),
    ],
    'database' => [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'treehouse'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
    ],
    'cache' => [
        'driver' => env('CACHE_DRIVER', 'file'),
        'ttl' => env('CACHE_TTL', 3600),
    ],
    'features' => [
        'beta' => env('FEATURE_BETA', false),
        'analytics' => env('FEATURE_ANALYTICS', true),
    ],
];

// Environment-specific logic
if (env('APP_ENV') === 'production') {
    // Production-specific configuration
    $config['app']['debug'] = false;
    $config['cache']['ttl'] = env('CACHE_TTL', 86400); // 24 hours
} elseif (env('APP_ENV') === 'testing') {
    // Testing-specific configuration
    $config['database']['database'] = env('DB_DATABASE', 'treehouse_test');
}

// Feature flags based on environment
$features = Collection::make($config['features'])
    ->filter(fn($enabled) => $enabled)
    ->keys()
    ->all();
```

## Performance Considerations

- **Caching**: String operations like `camel()` and `snake()` use internal caching
- **Memory Efficiency**: Collections use lazy evaluation where possible
- **Unicode Support**: All string operations are Unicode-safe
- **Type Safety**: Strict typing throughout for better performance and reliability

## Contributing

This library follows PSR-12 coding standards and includes comprehensive test coverage. When contributing:

1. Write tests for new functionality
2. Follow existing code style and patterns
3. Update documentation for new features
4. Ensure backward compatibility

## License

This library is part of the TreeHouse framework and follows the same licensing terms.