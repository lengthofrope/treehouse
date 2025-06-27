# TreeHouse Cache System

The TreeHouse Cache System provides a flexible and extensible caching solution with multiple drivers and a unified interface.

## Features

- **Multiple Drivers**: File-based caching with easy extensibility for other drivers
- **Unified Interface**: Consistent API across all cache drivers
- **TTL Support**: Time-to-live functionality for automatic expiration
- **Key Prefixing**: Namespace your cache keys with prefixes
- **Batch Operations**: Store and retrieve multiple items at once
- **Increment/Decrement**: Atomic counter operations
- **Remember Pattern**: Cache-or-execute pattern for expensive operations

## Helper Function

TreeHouse provides a convenient global helper function for accessing the cache:

### Using the cache() Helper

```php
// Get the cache manager instance
$cacheManager = cache();

// Get a specific driver
$fileCache = cache('file');

// Use cache operations directly
cache()->put('key', 'value', 3600);
$value = cache()->get('key', 'default');

if (cache()->has('key')) {
    echo "Key exists!";
}

// Remember pattern with helper
$expensiveData = cache()->remember('expensive_key', 300, function() {
    return performExpensiveOperation();
});

// Flush all cache
cache()->flush();
```

### Helper Usage Examples

```php
// Simple caching
cache()->put('user:123', $userData, 3600);
$user = cache()->get('user:123');

// Cache with specific driver
cache('file')->put('config', $settings);

// Remember pattern
$posts = cache()->remember('latest_posts', 1800, function() {
    return Post::latest()->limit(10)->get();
});

// Batch operations
cache()->putMany([
    'key1' => 'value1',
    'key2' => 'value2'
], 3600);

$values = cache()->many(['key1', 'key2']);
```

## Basic Usage

### Setting Up the Cache Manager

```php
use LengthOfRope\TreeHouse\Cache\CacheManager;

// Basic setup with default file driver
$cache = new CacheManager();

// With custom configuration
$config = [
    'file' => [
        'path' => '/path/to/cache/directory',
        'default_ttl' => 3600 // 1 hour
    ]
];
$cache = new CacheManager($config, 'file');
```

### Basic Operations

```php
// Store an item
$cache->put('user:123', $userData, 3600); // Cache for 1 hour

// Retrieve an item
$user = $cache->get('user:123');

// Check if item exists
if ($cache->has('user:123')) {
    // Item exists and is not expired
}

// Remove an item
$cache->forget('user:123');

// Clear all cache
$cache->flush();
```

### Remember Pattern

The remember pattern is useful for caching expensive operations:

```php
// Cache database query result
$users = $cache->remember('all_users', 1800, function() {
    return User::all();
});

// Cache forever (until manually removed)
$settings = $cache->rememberForever('app_settings', function() {
    return loadApplicationSettings();
});
```

### Batch Operations

```php
// Store multiple items
$cache->putMany([
    'user:1' => $user1,
    'user:2' => $user2,
    'user:3' => $user3
], 3600);

// Retrieve multiple items
$users = $cache->many(['user:1', 'user:2', 'user:3']);
```

### Counters

```php
// Initialize counter
$cache->put('page_views', 0);

// Increment
$views = $cache->increment('page_views'); // Returns 1
$views = $cache->increment('page_views', 5); // Returns 6

// Decrement
$views = $cache->decrement('page_views'); // Returns 5
$views = $cache->decrement('page_views', 2); // Returns 3
```

### Key Prefixing

Use prefixes to namespace your cache keys:

```php
// Create a prefixed cache instance
$userCache = $cache->prefix('users');

// These operations use prefixed keys
$userCache->put('123', $userData); // Actually stores 'users:123'
$user = $userCache->get('123'); // Actually retrieves 'users:123'
```

## File Cache Driver

The file cache driver stores cached data as serialized files on the filesystem.

### Configuration

```php
$config = [
    'file' => [
        'path' => '/var/cache/app', // Cache directory
        'default_ttl' => 3600 // Default TTL in seconds
    ]
];
```

### Features

- **Automatic Directory Creation**: Creates cache directory if it doesn't exist
- **Expiration Handling**: Automatically removes expired cache files
- **File Locking**: Uses `LOCK_EX` for atomic writes
- **SHA256 Hashing**: Cache keys are hashed for safe filesystem storage

### Maintenance

```php
// Get file cache instance
$fileCache = $cache->driver('file');

// Clean up expired files
$cleaned = $fileCache->cleanup();
echo "Cleaned up {$cleaned} expired files";

// Get cache statistics
$stats = $fileCache->getStats();
echo "Total files: {$stats['total_files']}";
echo "Total size: {$stats['total_size']} bytes";
echo "Expired files: {$stats['expired_files']}";
```

## Extending with Custom Drivers

You can add custom cache drivers by implementing the `CacheInterface`:

```php
use LengthOfRope\TreeHouse\Cache\CacheInterface;

class RedisCache implements CacheInterface
{
    // Implement all interface methods
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        // Redis implementation
    }
    
    // ... other methods
}

// Register the custom driver
$cache->extend('redis', new RedisCache($redisConnection));

// Use the custom driver
$redisCache = $cache->driver('redis');
```

## Driver Management

```php
// Set default driver
$cache->setDefaultDriver('file');

// Get current default driver
$defaultDriver = $cache->getDefaultDriver();

// Get specific driver instance
$fileCache = $cache->driver('file');

// Clear all driver instances (forces recreation)
$cache->clearDrivers();
```

## Best Practices

### 1. Use Meaningful Keys

```php
// Good
$cache->put('user:profile:123', $userProfile);
$cache->put('posts:category:tech:page:1', $posts);

// Avoid
$cache->put('data', $someData);
$cache->put('temp', $temporaryValue);
```

### 2. Set Appropriate TTL

```php
// Short-lived data
$cache->put('csrf_token', $token, 300); // 5 minutes

// Medium-lived data
$cache->put('user_session', $session, 3600); // 1 hour

// Long-lived data
$cache->put('app_config', $config, 86400); // 24 hours
```

### 3. Use Remember Pattern for Expensive Operations

```php
// Database queries
$users = $cache->remember('active_users', 1800, function() {
    return User::where('active', true)->get();
});

// API calls
$weather = $cache->remember('weather:london', 600, function() {
    return $weatherApi->getCurrentWeather('London');
});
```

### 4. Use Prefixes for Organization

```php
$userCache = $cache->prefix('users');
$sessionCache = $cache->prefix('sessions');
$apiCache = $cache->prefix('api');
```

### 5. Handle Cache Failures Gracefully

```php
$userData = $cache->get('user:123');
if ($userData === null) {
    // Cache miss - fetch from database
    $userData = User::find(123);
    if ($userData) {
        $cache->put('user:123', $userData, 3600);
    }
}
```

## Error Handling

The cache system is designed to fail gracefully:

- Failed cache operations return `false` or `null` instead of throwing exceptions
- Invalid cache files are automatically cleaned up
- Missing cache directories are created automatically

## Performance Considerations

### File Cache

- **Pros**: No external dependencies, simple setup, persistent across restarts
- **Cons**: Slower than memory-based caches, file I/O overhead
- **Best for**: Small to medium applications, development environments

### Optimization Tips

1. **Regular Cleanup**: Run `cleanup()` periodically to remove expired files
2. **Monitor Size**: Use `getStats()` to monitor cache size and performance
3. **Appropriate TTL**: Don't cache data longer than necessary
4. **Key Design**: Use consistent, hierarchical key naming

## Integration Examples

### With Database Models

```php
class User extends ActiveRecord
{
    private static CacheManager $cache;
    
    public static function setCache(CacheManager $cache): void
    {
        self::$cache = $cache;
    }
    
    public static function findCached(int $id): ?self
    {
        return self::$cache->remember("user:{$id}", 3600, function() use ($id) {
            return self::find($id);
        });
    }
    
    public function save(): bool
    {
        $result = parent::save();
        if ($result) {
            // Invalidate cache
            self::$cache->forget("user:{$this->id}");
        }
        return $result;
    }
}
```

### With HTTP Responses

```php
use LengthOfRope\TreeHouse\Http\Response;

class ApiController
{
    private CacheManager $cache;
    
    public function __construct(CacheManager $cache)
    {
        $this->cache = $cache;
    }
    
    public function getUsers(): Response
    {
        $users = $this->cache->remember('api:users', 600, function() {
            return User::all()->toArray();
        });
        
        return Response::json($users);
    }
}
```

This cache system provides a solid foundation for application caching needs while remaining simple and extensible.