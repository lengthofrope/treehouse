# TreeHouse Framework - Cache Layer

The Cache layer provides a flexible and powerful caching system for the TreeHouse Framework. It offers multiple cache drivers, a unified interface, and advanced features like prefixed caching, pattern matching, and automatic cleanup.

## Table of Contents

- [Overview](#overview)
- [Core Components](#core-components)
- [Cache Interface](#cache-interface)
- [Cache Manager](#cache-manager)
- [File Cache Driver](#file-cache-driver)
- [Prefixed Cache](#prefixed-cache)
- [Helper Functions](#helper-functions)
- [Configuration](#configuration)
- [Usage Examples](#usage-examples)
- [Advanced Features](#advanced-features)
- [Best Practices](#best-practices)

## Overview

The Cache layer implements a driver-based caching system that supports:

- **Multiple Drivers**: File-based caching with extensible driver architecture
- **Unified Interface**: Consistent API across all cache drivers
- **TTL Support**: Time-to-live expiration for cache entries
- **Bulk Operations**: Store and retrieve multiple items efficiently
- **Prefixed Caching**: Namespace cache keys with automatic prefixing
- **Pattern Matching**: Find cache entries by pattern
- **Statistics & Cleanup**: Monitor cache usage and clean expired entries

## Core Components

### CacheInterface

The [`CacheInterface`](CacheInterface.php:17) defines the contract for all cache implementations:

```php
interface CacheInterface
{
    public function put(string $key, mixed $value, ?int $ttl = null): bool;
    public function get(string $key, mixed $default = null): mixed;
    public function has(string $key): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
    public function remember(string $key, ?int $ttl, callable $callback): mixed;
    public function rememberForever(string $key, callable $callback): mixed;
    public function increment(string $key, int $value = 1): int|false;
    public function decrement(string $key, int $value = 1): int|false;
    public function forever(string $key, mixed $value): bool;
    public function many(array $keys): array;
    public function putMany(array $values, ?int $ttl = null): bool;
}
```

### CacheManager

The [`CacheManager`](CacheManager.php:21) manages multiple cache drivers and provides a unified interface:

```php
class CacheManager
{
    public function __construct(array $config = [], string $defaultDriver = 'file');
    public function driver(?string $driver = null): CacheInterface;
    public function extend(string $name, CacheInterface $driver): void;
    public function setDefaultDriver(string $driver): void;
    public function prefix(string $prefix): PrefixedCache;
}
```

### FileCache

The [`FileCache`](FileCache.php:21) provides file-based caching with metadata support:

```php
class FileCache implements CacheInterface
{
    public function __construct(string $directory, int $defaultTtl = 3600);
    public function cleanup(): int;
    public function getStats(): array;
    public function getByPattern(string $pattern): array;
    public function getDirectoryInfo(): array;
}
```

## Cache Interface

### Basic Operations

#### Store Data
```php
// Store with default TTL
$cache->put('user:123', $userData);

// Store with custom TTL (1 hour)
$cache->put('session:abc', $sessionData, 3600);

// Store forever
$cache->forever('config:app', $appConfig);
```

#### Retrieve Data
```php
// Get with default value
$user = $cache->get('user:123', null);

// Check existence
if ($cache->has('user:123')) {
    $user = $cache->get('user:123');
}
```

#### Remove Data
```php
// Remove single item
$cache->forget('user:123');

// Clear all cache
$cache->flush();
```

### Advanced Operations

#### Remember Pattern
```php
// Get from cache or execute callback and store result
$expensiveData = $cache->remember('expensive:calculation', 3600, function() {
    return performExpensiveCalculation();
});

// Remember forever
$staticData = $cache->rememberForever('static:config', function() {
    return loadStaticConfiguration();
});
```

#### Increment/Decrement
```php
// Increment counter
$newValue = $cache->increment('page:views', 1);

// Decrement with custom value
$remaining = $cache->decrement('api:rate_limit', 5);
```

#### Bulk Operations
```php
// Store multiple items
$cache->putMany([
    'user:123' => $user1,
    'user:456' => $user2,
    'user:789' => $user3
], 3600);

// Get multiple items
$users = $cache->many(['user:123', 'user:456', 'user:789']);
```

## Cache Manager

### Driver Management

#### Get Driver Instance
```php
$cacheManager = new CacheManager($config);

// Get default driver
$cache = $cacheManager->driver();

// Get specific driver
$fileCache = $cacheManager->driver('file');
```

#### Register Custom Driver
```php
$customCache = new CustomCacheDriver();
$cacheManager->extend('custom', $customCache);

// Use custom driver
$cache = $cacheManager->driver('custom');
```

#### Configuration Management
```php
// Set configuration
$cacheManager->setConfig([
    'file' => [
        'path' => '/tmp/cache',
        'default_ttl' => 7200
    ]
]);

// Get configuration value
$ttl = $cacheManager->getConfigValue('file.default_ttl', 3600);

// Set configuration value
$cacheManager->setConfigValue('file.path', '/var/cache');
```

### Proxy Methods

The CacheManager proxies all CacheInterface methods to the default driver:

```php
// These calls are proxied to the default driver
$cacheManager->put('key', 'value', 3600);
$value = $cacheManager->get('key');
$exists = $cacheManager->has('key');
```

### Key Generation

```php
// Generate normalized cache key
$key = $cacheManager->generateKey('User Profile Data', 'users');
// Result: "users:user_profile_data"

// Bulk operations with prefix
$cacheManager->putManyWithPrefix('users', [
    'profile:123' => $profile1,
    'settings:123' => $settings1
], 3600);

$data = $cacheManager->getManyWithPrefix('users', [
    'profile:123',
    'settings:123'
]);
```

## File Cache Driver

### Initialization

```php
$fileCache = new FileCache('/path/to/cache', 3600);
```

### File Storage

The FileCache stores data as serialized files with metadata:

```php
// Stored data structure
[
    'value' => $actualValue,
    'expiration' => $timestamp,
    'created_at' => $timestamp,
    'original_key' => $originalKey
]
```

### Maintenance Operations

#### Cleanup Expired Files
```php
$cleanedCount = $fileCache->cleanup();
echo "Cleaned up {$cleanedCount} expired files";
```

#### Get Statistics
```php
$stats = $fileCache->getStats();
/*
[
    'total_files' => 150,
    'total_size' => 2048576,
    'expired_files' => 12,
    'directory' => '/path/to/cache',
    'default_ttl' => 3600
]
*/
```

#### Directory Information
```php
$info = $fileCache->getDirectoryInfo();
/*
[
    'path' => '/path/to/cache',
    'exists' => true,
    'writable' => true,
    'size' => 2048576
]
*/
```

### Pattern Matching

```php
// Find cache entries by pattern
$userCaches = $fileCache->getByPattern('user:*');
$sessionCaches = $fileCache->getByPattern('session:*');
```

## Prefixed Cache

The [`PrefixedCache`](CacheManager.php:429) wrapper automatically prefixes all cache keys:

### Creation

```php
$prefixedCache = $cacheManager->prefix('users');
// or
$prefixedCache = new PrefixedCache($cache, 'users');
```

### Usage

```php
// All operations are automatically prefixed
$prefixedCache->put('profile:123', $profile);
// Actual key: "users:profile:123"

$profile = $prefixedCache->get('profile:123');
// Looks for key: "users:profile:123"

// Bulk operations maintain prefixing
$prefixedCache->putMany([
    'profile:123' => $profile1,
    'settings:123' => $settings1
]);
// Keys: "users:profile:123", "users:settings:123"
```

### Prefix Management

```php
$prefix = $prefixedCache->getPrefix(); // "users"
$hasPrefix = $prefixedCache->hasPrefix('users:profile:123'); // true
$cleanKey = $prefixedCache->removePrefix('users:profile:123'); // "profile:123"
```

## Helper Functions

### Cache Helper

The [`cache()`](helpers.php:15) helper provides convenient access to the cache system:

```php
// Get cache manager
$cacheManager = cache();

// Get specific driver
$fileCache = cache('file');

// Direct usage
cache()->put('key', 'value', 3600);
$value = cache()->get('key');
```

## Configuration

### Basic Configuration

```php
$config = [
    'file' => [
        'path' => '/var/cache/app',
        'default_ttl' => 3600
    ]
];

$cacheManager = new CacheManager($config, 'file');
```

### Environment-Based Configuration

```php
$config = [
    'file' => [
        'path' => $_ENV['CACHE_PATH'] ?? sys_get_temp_dir() . '/cache',
        'default_ttl' => (int)($_ENV['CACHE_TTL'] ?? 3600)
    ]
];
```

## Usage Examples

### User Profile Caching

```php
class UserService
{
    private CacheInterface $cache;
    
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }
    
    public function getUserProfile(int $userId): array
    {
        return $this->cache->remember(
            "user:profile:{$userId}",
            3600,
            fn() => $this->loadUserProfileFromDatabase($userId)
        );
    }
    
    public function updateUserProfile(int $userId, array $profile): void
    {
        $this->saveUserProfileToDatabase($userId, $profile);
        $this->cache->forget("user:profile:{$userId}");
    }
}
```

### Session Management

```php
class SessionManager
{
    private PrefixedCache $cache;
    
    public function __construct(CacheManager $cacheManager)
    {
        $this->cache = $cacheManager->prefix('sessions');
    }
    
    public function store(string $sessionId, array $data): void
    {
        $this->cache->put($sessionId, $data, 1800); // 30 minutes
    }
    
    public function retrieve(string $sessionId): ?array
    {
        return $this->cache->get($sessionId);
    }
    
    public function destroy(string $sessionId): void
    {
        $this->cache->forget($sessionId);
    }
}
```

### Rate Limiting

```php
class RateLimiter
{
    private CacheInterface $cache;
    
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }
    
    public function attempt(string $key, int $maxAttempts, int $window): bool
    {
        $attempts = $this->cache->get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return false;
        }
        
        $this->cache->put($key, $attempts + 1, $window);
        return true;
    }
    
    public function clear(string $key): void
    {
        $this->cache->forget($key);
    }
}
```

### Configuration Caching

```php
class ConfigCache
{
    private CacheInterface $cache;
    
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }
    
    public function getConfig(string $file): array
    {
        return $this->cache->rememberForever(
            "config:{$file}",
            fn() => $this->loadConfigFile($file)
        );
    }
    
    public function clearConfig(?string $file = null): void
    {
        if ($file) {
            $this->cache->forget("config:{$file}");
        } else {
            // Clear all config cache
            $this->cache->flush();
        }
    }
}
```

## Advanced Features

### Cache Warming

```php
class CacheWarmer
{
    private CacheInterface $cache;
    
    public function warmUserProfiles(array $userIds): void
    {
        $profiles = [];
        foreach ($userIds as $userId) {
            $profiles["user:profile:{$userId}"] = $this->loadUserProfile($userId);
        }
        
        $this->cache->putMany($profiles, 3600);
    }
}
```

### Cache Invalidation

```php
class CacheInvalidator
{
    private FileCache $cache;
    
    public function invalidateUserData(int $userId): void
    {
        $patterns = [
            "user:profile:{$userId}",
            "user:settings:{$userId}",
            "user:permissions:{$userId}"
        ];
        
        foreach ($patterns as $pattern) {
            $this->cache->forget($pattern);
        }
    }
    
    public function invalidateByPattern(string $pattern): void
    {
        $matches = $this->cache->getByPattern($pattern);
        foreach (array_keys($matches) as $key) {
            $this->cache->forget($key);
        }
    }
}
```

### Cache Monitoring

```php
class CacheMonitor
{
    private FileCache $cache;
    
    public function getHealthStatus(): array
    {
        $stats = $this->cache->getStats();
        $dirInfo = $this->cache->getDirectoryInfo();
        
        return [
            'status' => $dirInfo['writable'] ? 'healthy' : 'error',
            'total_entries' => $stats['total_files'],
            'expired_entries' => $stats['expired_files'],
            'size_mb' => round($stats['total_size'] / 1024 / 1024, 2),
            'directory' => $stats['directory']
        ];
    }
    
    public function performMaintenance(): array
    {
        $cleaned = $this->cache->cleanup();
        $stats = $this->cache->getStats();
        
        return [
            'cleaned_files' => $cleaned,
            'remaining_files' => $stats['total_files'],
            'size_after_cleanup' => $stats['total_size']
        ];
    }
}
```

## Best Practices

### Key Naming

```php
// Use consistent, hierarchical naming
'user:profile:123'
'user:settings:123'
'session:abc123'
'config:database'

// Use prefixes for namespacing
$userCache = $cacheManager->prefix('users');
$sessionCache = $cacheManager->prefix('sessions');
```

### TTL Management

```php
// Short TTL for frequently changing data
$cache->put('user:online_status:123', $status, 60); // 1 minute

// Medium TTL for semi-static data
$cache->put('user:profile:123', $profile, 3600); // 1 hour

// Long TTL for static data
$cache->put('config:app', $config, 86400); // 1 day

// Forever for truly static data
$cache->forever('system:constants', $constants);
```

### Error Handling

```php
try {
    $value = $cache->get('key');
} catch (Exception $e) {
    // Log error and fallback to source
    error_log("Cache error: " . $e->getMessage());
    $value = $this->loadFromSource();
}
```

### Performance Optimization

```php
// Use bulk operations when possible
$cache->putMany($bulkData, 3600);
$results = $cache->many($keys);

// Implement cache warming for critical data
$this->warmCriticalData();

// Regular cleanup for file-based cache
if (rand(1, 100) === 1) {
    $cache->cleanup();
}
```

The Cache layer provides a robust, flexible caching system that can significantly improve application performance through efficient data storage and retrieval mechanisms.