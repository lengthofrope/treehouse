# TreeHouse Framework - Support Layer

The Support Layer provides essential utility classes and helper functions that form the foundation for data manipulation, string processing, date/time handling, environment management, and UUID generation throughout the TreeHouse Framework. This layer offers a comprehensive set of tools for common programming tasks.

## Table of Contents

- [Overview](#overview)
- [Components](#components)
- [Array Utilities](#array-utilities)
- [Collection Class](#collection-class)
- [String Utilities](#string-utilities)
- [Date/Time Handling](#datetime-handling)
- [Environment Management](#environment-management)
- [UUID Generation](#uuid-generation)
- [Helper Functions](#helper-functions)
- [Usage Examples](#usage-examples)
- [Integration](#integration)

## Overview

The Support Layer consists of seven main components:

- **Array Utilities**: Comprehensive array manipulation with dot notation support
- **Collection Class**: Fluent interface for working with arrays of data
- **String Utilities**: Extensive string processing and manipulation functions
- **Date/Time Handling**: Enhanced DateTime functionality with Carbon-like features
- **Environment Management**: .env file loading and environment variable handling
- **UUID Generation**: Multiple UUID versions and utility functions
- **Helper Functions**: Global utility functions for common tasks

### Key Features

- **Dot Notation**: Access nested arrays and objects using dot notation
- **Fluent Interface**: Chainable methods for data manipulation
- **Type Safety**: Strong typing with comprehensive type annotations
- **Performance**: Optimized implementations with caching where appropriate
- **Extensibility**: Designed for easy extension and customization
- **Testing Support**: Built-in testing utilities and mock capabilities

## Components

### Core Classes

```php
// Array utilities
LengthOfRope\TreeHouse\Support\Arr

// Collection class
LengthOfRope\TreeHouse\Support\Collection

// String utilities
LengthOfRope\TreeHouse\Support\Str

// Date/time handling
LengthOfRope\TreeHouse\Support\Carbon

// Environment management
LengthOfRope\TreeHouse\Support\Env

// UUID generation
LengthOfRope\TreeHouse\Support\Uuid
```

## Array Utilities

The [`Arr`](src/TreeHouse/Support/Arr.php:1) class provides comprehensive array manipulation utilities with dot notation support.

### Basic Operations

```php
use LengthOfRope\TreeHouse\Support\Arr;

// Check if value is array accessible
$accessible = Arr::accessible(['key' => 'value']); // true
$accessible = Arr::accessible(new ArrayObject()); // true

// Add element if it doesn't exist
$array = ['name' => 'John'];
$result = Arr::add($array, 'age', 25);
// Result: ['name' => 'John', 'age' => 25]

// Get array subset
$array = ['name' => 'John', 'age' => 25, 'city' => 'NYC'];
$subset = Arr::only($array, ['name', 'age']);
// Result: ['name' => 'John', 'age' => 25]

// Exclude specific keys
$remaining = Arr::except($array, ['age']);
// Result: ['name' => 'John', 'city' => 'NYC']
```

### Dot Notation Access

```php
// Get nested values using dot notation
$data = [
    'user' => [
        'profile' => [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]
    ]
];

$name = Arr::get($data, 'user.profile.name'); // "John Doe"
$phone = Arr::get($data, 'user.profile.phone', 'N/A'); // "N/A" (default)

// Set nested values
Arr::set($data, 'user.profile.phone', '123-456-7890');

// Check if nested key exists
$exists = Arr::has($data, 'user.profile.name'); // true
$exists = Arr::has($data, ['user.profile.name', 'user.profile.email']); // true

// Remove nested keys
Arr::forget($data, 'user.profile.phone');
```

### Array Manipulation

```php
// Flatten multi-dimensional array
$nested = [
    'level1' => [
        'level2' => [
            'level3' => 'value'
        ]
    ]
];
$flattened = Arr::dot($nested);
// Result: ['level1.level2.level3' => 'value']

// Collapse array of arrays
$arrays = [['a', 'b'], ['c', 'd'], ['e', 'f']];
$collapsed = Arr::collapse($arrays);
// Result: ['a', 'b', 'c', 'd', 'e', 'f']

// Cross join arrays
$colors = ['red', 'blue'];
$sizes = ['small', 'large'];
$combinations = Arr::crossJoin($colors, $sizes);
// Result: [['red', 'small'], ['red', 'large'], ['blue', 'small'], ['blue', 'large']]
```

### Advanced Operations

```php
// Pluck values from array of arrays/objects
$users = [
    ['name' => 'John', 'age' => 25],
    ['name' => 'Jane', 'age' => 30],
    ['name' => 'Bob', 'age' => 35]
];

$names = Arr::pluck($users, 'name');
// Result: ['John', 'Jane', 'Bob']

$namesByAge = Arr::pluck($users, 'name', 'age');
// Result: [25 => 'John', 30 => 'Jane', 35 => 'Bob']

// Sort array
$sorted = Arr::sort($users, 'age');

// Get random elements
$random = Arr::random($users, 2); // Get 2 random users

// Convert to query string
$params = ['name' => 'John', 'age' => 25];
$query = Arr::query($params); // "name=John&age=25"
```

### Key Methods

- [`get(array $array, string $key, mixed $default = null): mixed`](src/TreeHouse/Support/Arr.php:286) - Get value using dot notation
- [`set(array &$array, string $key, mixed $value): array`](src/TreeHouse/Support/Arr.php:621) - Set value using dot notation
- [`has(array $array, string|array $keys): bool`](src/TreeHouse/Support/Arr.php:322) - Check if keys exist
- [`forget(array &$array, string|array $keys): void`](src/TreeHouse/Support/Arr.php:241) - Remove keys
- [`only(array $array, array|string $keys): array`](src/TreeHouse/Support/Arr.php:459) - Get subset
- [`except(array $array, array|string $keys): array`](src/TreeHouse/Support/Arr.php:135) - Exclude keys
- [`pluck(iterable $array, string $value, string $key = null): array`](src/TreeHouse/Support/Arr.php:472) - Extract values
- [`dot(iterable $array, string $prepend = ''): array`](src/TreeHouse/Support/Arr.php:113) - Flatten with dots
- [`collapse(iterable $array): array`](src/TreeHouse/Support/Arr.php:52) - Collapse arrays

## Collection Class

The [`Collection`](src/TreeHouse/Support/Collection.php:1) class provides a fluent interface for working with arrays of data, inspired by Laravel's Collection.

### Basic Usage

```php
use LengthOfRope\TreeHouse\Support\Collection;

// Create collection
$collection = Collection::make([1, 2, 3, 4, 5]);
$collection = new Collection(['name' => 'John', 'age' => 25]);

// Basic operations
$count = $collection->count(); // 5
$isEmpty = $collection->isEmpty(); // false
$all = $collection->all(); // Get underlying array
```

### Filtering and Transformation

```php
$numbers = Collection::make([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

// Filter even numbers
$even = $numbers->filter(fn($n) => $n % 2 === 0);
// Result: [2, 4, 6, 8, 10]

// Transform values
$squared = $numbers->map(fn($n) => $n * $n);
// Result: [1, 4, 9, 16, 25, 36, 49, 64, 81, 100]

// Chain operations
$result = $numbers
    ->filter(fn($n) => $n > 5)
    ->map(fn($n) => $n * 2)
    ->values(); // Reset keys
// Result: [12, 14, 16, 18, 20]
```

### Aggregation

```php
$products = Collection::make([
    ['name' => 'Laptop', 'price' => 1000],
    ['name' => 'Phone', 'price' => 500],
    ['name' => 'Tablet', 'price' => 300]
]);

// Sum prices
$total = $products->sum('price'); // 1800

// Average price
$average = $products->avg('price'); // 600

// Min/Max prices
$min = $products->min('price'); // 300
$max = $products->max('price'); // 1000

// Count items
$count = $products->count(); // 3
```

### Grouping and Sorting

```php
$users = Collection::make([
    ['name' => 'John', 'department' => 'IT', 'salary' => 50000],
    ['name' => 'Jane', 'department' => 'HR', 'salary' => 45000],
    ['name' => 'Bob', 'department' => 'IT', 'salary' => 55000]
]);

// Group by department
$grouped = $users->groupBy('department');
// Result: ['IT' => Collection, 'HR' => Collection]

// Sort by salary
$sorted = $users->sortBy('salary');

// Sort descending
$sortedDesc = $users->sortByDesc('salary');

// Sort by multiple criteria
$sorted = $users->sortBy(function($user) {
    return [$user['department'], $user['salary']];
});
```

### Collection Operations

```php
$collection1 = Collection::make([1, 2, 3]);
$collection2 = Collection::make([3, 4, 5]);

// Merge collections
$merged = $collection1->merge($collection2);
// Result: [1, 2, 3, 3, 4, 5]

// Get unique values
$unique = $merged->unique();
// Result: [1, 2, 3, 4, 5]

// Get intersection
$intersection = $collection1->intersect($collection2);
// Result: [3]

// Get difference
$diff = $collection1->diff($collection2);
// Result: [1, 2]
```

### Advanced Methods

```php
// Chunk into smaller collections
$chunked = Collection::make([1, 2, 3, 4, 5, 6])->chunk(2);
// Result: [[1, 2], [3, 4], [5, 6]]

// Take first/last n items
$first3 = $collection->take(3);
$last3 = $collection->take(-3);

// Skip items
$skipped = $collection->skip(2);

// Slice collection
$slice = $collection->slice(1, 3);

// Reduce to single value
$sum = Collection::make([1, 2, 3, 4])->reduce(fn($carry, $item) => $carry + $item, 0);
// Result: 10
```

### Key Methods

- [`make(array $items = []): static`](src/TreeHouse/Support/Collection.php:55) - Create collection
- [`filter(?callable $callback = null): static`](src/TreeHouse/Support/Collection.php:219) - Filter items
- [`map(callable $callback): static`](src/TreeHouse/Support/Collection.php:397) - Transform items
- [`reduce(callable $callback, mixed $initial = null): mixed`](src/TreeHouse/Support/Collection.php:529) - Reduce to value
- [`groupBy(callable|string $groupBy): static`](src/TreeHouse/Support/Collection.php:290) - Group items
- [`sortBy(callable|string $callback): static`](src/TreeHouse/Support/Collection.php:647) - Sort items
- [`pluck(string|array $value, string $key = null): static`](src/TreeHouse/Support/Collection.php:473) - Extract values
- [`where(string $key, mixed $operator, mixed $value): static`](src/TreeHouse/Support/Collection.php:794) - Filter by criteria

## String Utilities

The [`Str`](src/TreeHouse/Support/Str.php:1) class provides extensive string processing and manipulation functions.

### Case Conversion

```php
use LengthOfRope\TreeHouse\Support\Str;

// Convert to different cases
$camel = Str::camel('hello_world'); // "helloWorld"
$studly = Str::studly('hello_world'); // "HelloWorld"
$snake = Str::snake('HelloWorld'); // "hello_world"
$kebab = Str::kebab('HelloWorld'); // "hello-world"

// Title and sentence case
$title = Str::title('hello world'); // "Hello World"
$upper = Str::upper('hello'); // "HELLO"
$lower = Str::lower('HELLO'); // "hello"
```

### String Analysis

```php
// Check string properties
$contains = Str::contains('Hello World', 'World'); // true
$startsWith = Str::startsWith('Hello World', 'Hello'); // true
$endsWith = Str::endsWith('Hello World', 'World'); // true

// Pattern matching
$matches = Str::is('foo*', 'foobar'); // true
$matches = Str::isMatch('/^[a-z]+$/', 'hello'); // true

// Check string types
$isAscii = Str::isAscii('Hello'); // true
$isJson = Str::isJson('{"key": "value"}'); // true
$isUuid = Str::isUuid('550e8400-e29b-41d4-a716-446655440000'); // true
```

### String Manipulation

```php
// Extract parts of strings
$after = Str::after('Hello World', 'Hello '); // "World"
$before = Str::before('Hello World', ' World'); // "Hello"
$between = Str::between('Hello [World] Test', '[', ']'); // "World"

// Limit string length
$limited = Str::limit('This is a very long string', 10); // "This is a..."
$words = Str::words('This is a very long string', 3); // "This is a..."

// Padding
$padded = Str::padLeft('5', 3, '0'); // "005"
$padded = Str::padRight('5', 3, '0'); // "500"
$padded = Str::padBoth('5', 3, '0'); // "050"
```

### String Generation

```php
// Generate random strings
$random = Str::random(16); // Random alphanumeric string
$uuid = Str::uuid(); // UUID v4
$orderedUuid = Str::orderedUuid(); // UUID v1

// Create slugs
$slug = Str::slug('Hello World!'); // "hello-world"
$slug = Str::slug('Hello World!', '_'); // "hello_world"
```

### Advanced Operations

```php
// Replace operations
$replaced = Str::replace(['foo', 'bar'], ['hello', 'world'], 'foo and bar');
// Result: "hello and world"

$first = Str::replaceFirst('foo', 'bar', 'foo foo foo'); // "bar foo foo"
$last = Str::replaceLast('foo', 'bar', 'foo foo foo'); // "foo foo bar"

// Remove strings
$removed = Str::remove(['!', '?'], 'Hello World!?'); // "Hello World"

// Mask sensitive data
$masked = Str::mask('1234567890', '*', 4, 2); // "1234**7890"

// Parse callbacks
[$class, $method] = Str::parseCallback('Class@method'); // ["Class", "method"]
```

### Key Methods

- [`camel(string $value): string`](src/TreeHouse/Support/Str.php:171) - Convert to camelCase
- [`snake(string $value, string $delimiter = '_'): string`](src/TreeHouse/Support/Str.php:840) - Convert to snake_case
- [`studly(string $value): string`](src/TreeHouse/Support/Str.php:885) - Convert to StudlyCase
- [`slug(string $title, string $separator = '-'): string`](src/TreeHouse/Support/Str.php:812) - Create URL slug
- [`contains(string $haystack, string|iterable $needles): bool`](src/TreeHouse/Support/Str.php:188) - Check if contains
- [`startsWith(string $haystack, string|iterable $needles): bool`](src/TreeHouse/Support/Str.php:864) - Check if starts with
- [`endsWith(string $haystack, string|iterable $needles): bool`](src/TreeHouse/Support/Str.php:237) - Check if ends with
- [`limit(string $value, int $limit = 100, string $end = '...'): string`](src/TreeHouse/Support/Str.php:384) - Limit length
- [`random(int $length = 16): string`](src/TreeHouse/Support/Str.php:626) - Generate random string

## Date/Time Handling

The [`Carbon`](src/TreeHouse/Support/Carbon.php:1) class extends PHP's DateTime with enhanced functionality.

### Creating Instances

```php
use LengthOfRope\TreeHouse\Support\Carbon;

// Create instances
$now = Carbon::now();
$today = Carbon::today();
$tomorrow = Carbon::tomorrow();
$yesterday = Carbon::yesterday();

// Create from specific date
$date = Carbon::create(2023, 12, 25, 10, 30, 0);

// Parse from string
$parsed = Carbon::parse('2023-12-25 10:30:00');

// Create from timestamp
$fromTimestamp = Carbon::createFromTimestamp(1703505000);

// Create from format
$fromFormat = Carbon::createFromFormat('d/m/Y', '25/12/2023');
```

### Date Arithmetic

```php
$date = Carbon::now();

// Add time
$future = $date->addYears(1);
$future = $date->addMonths(6);
$future = $date->addDays(30);
$future = $date->addHours(12);
$future = $date->addMinutes(30);
$future = $date->addSeconds(45);

// Subtract time
$past = $date->subYears(1);
$past = $date->subMonths(6);
$past = $date->subDays(30);
$past = $date->subHours(12);
$past = $date->subMinutes(30);
$past = $date->subSeconds(45);

// Start/end of periods
$startOfDay = $date->startOfDay(); // 00:00:00
$endOfDay = $date->endOfDay(); // 23:59:59
$startOfMonth = $date->startOfMonth();
$endOfMonth = $date->endOfMonth();
$startOfYear = $date->startOfYear();
$endOfYear = $date->endOfYear();
```

### Date Comparison

```php
$date1 = Carbon::parse('2023-12-25');
$date2 = Carbon::parse('2023-12-26');

// Check relationships
$isPast = $date1->isPast(); // true/false
$isFuture = $date1->isFuture(); // true/false
$isToday = $date1->isToday(); // true/false
$isTomorrow = $date1->isTomorrow(); // true/false
$isYesterday = $date1->isYesterday(); // true/false

// Check day types
$isWeekend = $date1->isWeekend(); // true/false
$isWeekday = $date1->isWeekday(); // true/false

// Get differences
$diffInYears = $date1->diffInYears($date2);
$diffInMonths = $date1->diffInMonths($date2);
$diffInDays = $date1->diffInDays($date2);
$diffInHours = $date1->diffInHours($date2);
$diffInMinutes = $date1->diffInMinutes($date2);
$diffInSeconds = $date1->diffInSeconds($date2);
```

### Human-Readable Differences

```php
$date = Carbon::parse('2023-12-25 10:30:00');

// Human readable differences
$human = $date->diffForHumans(); // "2 days ago" or "in 3 hours"
$human = $date->diffForHumans(Carbon::now()); // "2 days before"
$human = $date->diffForHumans(null, true); // "2 days" (absolute)

// Get age
$birthDate = Carbon::parse('1990-05-15');
$age = $birthDate->age(); // Age in years
```

### Testing Support

```php
// Mock time for testing
Carbon::setTestNow('2023-12-25 10:30:00');
$now = Carbon::now(); // Always returns mocked time

// Check if mocked
$isMocked = Carbon::hasTestNow(); // true

// Clear mock
Carbon::clearTestNow();
```

### Key Methods

- [`now(DateTimeZone|string|null $timezone = null): static`](src/TreeHouse/Support/Carbon.php:99) - Current time
- [`create(int $year, int $month, int $day, ...): static`](src/TreeHouse/Support/Carbon.php:157) - Create from components
- [`parse(string $time, DateTimeZone|string|null $timezone = null): static`](src/TreeHouse/Support/Carbon.php:214) - Parse string
- [`addDays(int $value): static`](src/TreeHouse/Support/Carbon.php:344) - Add days
- [`diffInDays(DateTimeInterface $dt = null, bool $abs = true): int`](src/TreeHouse/Support/Carbon.php:670) - Difference in days
- [`diffForHumans(DateTimeInterface $other = null, bool $absolute = false): string`](src/TreeHouse/Support/Carbon.php:721) - Human readable
- [`isToday(): bool`](src/TreeHouse/Support/Carbon.php:591) - Check if today
- [`startOfDay(): static`](src/TreeHouse/Support/Carbon.php:511) - Start of day

## Environment Management

The [`Env`](src/TreeHouse/Support/Env.php:1) class provides centralized environment variable management with .env file support.

### Basic Usage

```php
use LengthOfRope\TreeHouse\Support\Env;

// Load .env file (automatic)
Env::loadIfNeeded();

// Get environment variables with type conversion
$debug = Env::get('APP_DEBUG', false); // boolean
$port = Env::get('APP_PORT', 8000); // integer
$name = Env::get('APP_NAME', 'TreeHouse'); // string

// Check if variable exists
$exists = Env::has('APP_DEBUG'); // true/false

// Set environment variable
Env::set('NEW_VAR', 'value');

// Get all environment variables
$all = Env::all();
```

### .env File Format

```env
# Application settings
APP_NAME="TreeHouse Framework"
APP_DEBUG=true
APP_PORT=8000

# Database settings
DB_HOST=localhost
DB_PORT=3306
DB_NAME=treehouse
DB_USER=root
DB_PASS=""

# Boolean values
FEATURE_ENABLED=true
FEATURE_DISABLED=false

# Null values
OPTIONAL_SETTING=null

# Numeric values
MAX_CONNECTIONS=100
TIMEOUT=30.5
```

### Type Conversion

```php
// Automatic type conversion
$debug = Env::get('APP_DEBUG'); // "true" -> true (boolean)
$port = Env::get('APP_PORT'); // "8000" -> 8000 (integer)
$timeout = Env::get('TIMEOUT'); // "30.5" -> 30.5 (float)
$optional = Env::get('OPTIONAL_SETTING'); // "null" -> null
$empty = Env::get('EMPTY_VALUE'); // "empty" -> ""

// With defaults
$maxRetries = Env::get('MAX_RETRIES', 3); // Default to 3 if not set
```

### Advanced Usage

```php
// Force reload .env file
Env::reload();

// Load from specific path
Env::load('/path/to/.env');

// Clear cache
Env::clearCache();
```

### Key Methods

- [`get(string $key, mixed $default = null): mixed`](src/TreeHouse/Support/Env.php:67) - Get with type conversion
- [`set(string $key, string $value): void`](src/TreeHouse/Support/Env.php:92) - Set variable
- [`has(string $key): bool`](src/TreeHouse/Support/Env.php:102) - Check existence
- [`load(string $path = null): void`](src/TreeHouse/Support/Env.php:47) - Load .env file
- [`all(): array`](src/TreeHouse/Support/Env.php:114) - Get all variables

## UUID Generation

The [`Uuid`](src/TreeHouse/Support/Uuid.php:1) class provides comprehensive UUID generation and manipulation utilities.

### UUID Generation

```php
use LengthOfRope\TreeHouse\Support\Uuid;

// Generate different UUID versions
$uuid4 = Uuid::uuid4(); // Random UUID (most common)
$uuid1 = Uuid::uuid1(); // Time-based UUID
$uuid3 = Uuid::uuid3(Uuid::NAMESPACE_DNS, 'example.com'); // Name-based MD5
$uuid5 = Uuid::uuid5(Uuid::NAMESPACE_URL, 'https://example.com'); // Name-based SHA1

// Special UUIDs
$nil = Uuid::nil(); // 00000000-0000-0000-0000-000000000000
$max = Uuid::max(); // ffffffff-ffff-ffff-ffff-ffffffffffff

// Short UUID (base62 encoded)
$short = Uuid::short(); // Shorter representation
```

### UUID Validation and Analysis

```php
$uuid = '550e8400-e29b-41d4-a716-446655440000';

// Validate UUID
$isValid = Uuid::isValid($uuid); // true

// Get UUID information
$version = Uuid::getVersion($uuid); // 4
$variant = Uuid::getVariant($uuid); // "RFC4122"

// Compare UUIDs
$comparison = Uuid::compare($uuid1, $uuid2); // -1, 0, or 1
$areEqual = Uuid::equals($uuid1, $uuid2); // true/false
```

### Binary Conversion

```php
// Convert to/from binary
$binary = Uuid::toBinary($uuid); // 16-byte binary string
$uuid = Uuid::fromBinary($binary); // Convert back to string

// Useful for database storage
```

### Predefined Namespaces

```php
// Use predefined namespaces
$dnsUuid = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'example.com');
$urlUuid = Uuid::uuid5(Uuid::NAMESPACE_URL, 'https://example.com');
$oidUuid = Uuid::uuid5(Uuid::NAMESPACE_OID, '1.2.3.4');
$x500Uuid = Uuid::uuid5(Uuid::NAMESPACE_X500, 'CN=John Doe');
```

### Key Methods

- [`uuid4(): string`](src/TreeHouse/Support/Uuid.php:23) - Generate random UUID
- [`uuid1(): string`](src/TreeHouse/Support/Uuid.php:40) - Generate time-based UUID
- [`uuid5(string $namespace, string $name): string`](src/TreeHouse/Support/Uuid.php:106) - Generate name-based UUID
- [`isValid(string $uuid): bool`](src/TreeHouse/Support/Uuid.php:138) - Validate UUID
- [`getVersion(string $uuid): int`](src/TreeHouse/Support/Uuid.php:215) - Get UUID version
- [`toBinary(string $uuid): string`](src/TreeHouse/Support/Uuid.php:169) - Convert to binary
- [`short(): string`](src/TreeHouse/Support/Uuid.php:283) - Generate short UUID

## Helper Functions

The [`helpers.php`](src/TreeHouse/Support/helpers.php:1) file provides global utility functions for common tasks.

### Data Access Functions

```php
// Get data using dot notation
$data = [
    'user' => [
        'profile' => [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]
    ]
];

$name = dataGet($data, 'user.profile.name'); // "John Doe"
$phone = dataGet($data, 'user.profile.phone', 'N/A'); // "N/A"

// Wildcard support
$users = [
    ['name' => 'John', 'age' => 25],
    ['name' => 'Jane', 'age' => 30]
];
$names = dataGet($users, '*.name'); // ['John', 'Jane']

// Set data using dot notation
dataSet($data, 'user.profile.phone', '123-456-7890');
dataSet($data, 'user.settings.theme', 'dark');
```

### Utility Functions

```php
// Return value or execute closure
$result = value('static value'); // "static value"
$result = value(fn() => 'dynamic value'); // "dynamic value"
$result = value(fn($x) => $x * 2, 5); // 10

// Pass value through callback
$result = with('hello', fn($str) => strtoupper($str)); // "HELLO"
$result = with('hello'); // "hello" (no callback)

// Environment variable access
$debug = env('APP_DEBUG', false);
$name = env('APP_NAME', 'Default App');
```

### Key Functions

- [`dataGet(mixed $target, string|array|int|null $key, mixed $default = null): mixed`](src/TreeHouse/Support/helpers.php:17) - Get nested data
- [`dataSet(mixed &$target, string|array $key, mixed $value, bool $overwrite = true): mixed`](src/TreeHouse/Support/helpers.php:69) - Set nested data
- [`value(mixed $value, mixed ...$args): mixed`](src/TreeHouse/Support/helpers.php:129) - Resolve value
- [`with(mixed $value, callable|null $callback = null): mixed`](src/TreeHouse/Support/helpers.php: