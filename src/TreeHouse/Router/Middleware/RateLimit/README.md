# TreeHouse Rate Limiting Middleware

## 🎯 Overview

Enterprise-grade rate limiting middleware for the TreeHouse Framework with multiple strategies, key resolvers, and advanced features.

## 📋 Features

### ✅ **Rate Limiting Strategies**
- **Fixed Window** - Simple time-based windows (default)
- **Sliding Window** - Precise rate limiting without boundary bursts
- **Token Bucket** - Burst-friendly limiting with average rate control

### ✅ **Key Resolvers**
- **IP-based** - Rate limiting by client IP address (default)
- **User-based** - Rate limiting by authenticated user ID
- **Header-based** - Rate limiting by API keys or custom headers
- **Composite** - Combined rate limiting (e.g., IP + User)

### ✅ **Advanced Features**
- Multiple strategies per application
- Configurable fallbacks (IP when user not authenticated)
- Rate limit headers in responses
- Beautiful error pages with debugging info
- Zero external dependencies
- Comprehensive test coverage

## 🚀 Quick Start

### Basic Usage

```php
// Simple rate limiting - 10 requests per 60 seconds
$router->get('/api/data', 'Controller@method')->middleware('throttle:10,60');

// Different limits for different endpoints
$router->get('/api/search', 'Controller@search')->middleware('throttle:100,60');
$router->post('/api/upload', 'Controller@upload')->middleware('throttle:5,300');
```

### Advanced Usage

```php
// Sliding window strategy - more precise
$router->get('/api/precise', 'Controller@method')
    ->middleware('throttle:50,60,sliding');

// Token bucket strategy - burst-friendly
$router->get('/api/burst', 'Controller@method')
    ->middleware('throttle:100,60,token_bucket');

// User-based rate limiting
$router->get('/api/user-data', 'Controller@method')
    ->middleware('throttle:200,60,fixed,user');

// API key-based rate limiting
$router->get('/api/premium', 'Controller@method')
    ->middleware('throttle:1000,60,fixed,header');
```

## 📖 Strategy Details

### 1. Fixed Window Strategy (Default)

```php
// 100 requests per 60-second fixed windows
->middleware('throttle:100,60,fixed')
```

**How it works:**
- Divides time into fixed windows (e.g., 00:00-01:00, 01:00-02:00)
- Counts requests within each window
- Resets counter at window boundaries
- Simple and memory efficient

**Best for:** General API rate limiting, simple use cases

### 2. Sliding Window Strategy

```php
// 100 requests per 60-second sliding window
->middleware('throttle:100,60,sliding')
```

**How it works:**
- Tracks individual request timestamps
- Only counts requests within the sliding time window
- More precise than fixed windows
- Prevents boundary burst issues

**Best for:** Precise rate limiting, preventing abuse

### 3. Token Bucket Strategy

```php
// 100 token capacity, refill over 60 seconds
->middleware('throttle:100,60,token_bucket')
```

**How it works:**
- Bucket starts empty (configurable)
- Tokens added at steady rate
- Each request consumes one token
- Allows bursts when tokens available

**Best for:** APIs that need burst capacity, file uploads

## 🔑 Key Resolver Details

### 1. IP-based (Default)

```php
->middleware('throttle:100,60,fixed,ip')
```

**Features:**
- Automatic IP detection
- Proxy header support
- IPv6 normalization
- Subnet masking support

### 2. User-based

```php
->middleware('throttle:500,60,fixed,user')
```

**Features:**
- Uses authenticated user ID
- Falls back to IP if not authenticated
- Works with any authentication system
- Session-based fallback

### 3. Header-based

```php
->middleware('throttle:1000,60,fixed,header')
```

**Features:**
- API key rate limiting
- Multiple header fallbacks
- Bearer token extraction
- Privacy-friendly hashing

### 4. Composite

```php
->middleware('throttle:100,60,fixed,composite')
```

**Features:**
- Combines multiple resolvers
- IP + User combined limiting
- Flexible configuration
- Fallback strategies

## 🛠️ Configuration Examples

### Environment-based Limits

```php
// Development - generous limits
if (app()->environment('development')) {
    $router->get('/api/test', 'Controller@test')
        ->middleware('throttle:1000,60');
}

// Production - strict limits
if (app()->environment('production')) {
    $router->get('/api/test', 'Controller@test')
        ->middleware('throttle:100,60');
}
```

### Tiered API Limits

```php
// Free tier
$router->group(['prefix' => 'api/free'], function($router) {
    $router->get('/data', 'Controller@data')
        ->middleware('throttle:100,3600,fixed,user'); // 100/hour
});

// Premium tier  
$router->group(['prefix' => 'api/premium'], function($router) {
    $router->get('/data', 'Controller@data')
        ->middleware('throttle:10000,3600,sliding,header'); // 10k/hour
});
```

## 📊 Response Headers

All rate-limited responses include headers:

```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1751825890
```

## 🚫 Rate Limit Exceeded

When limits are exceeded, clients receive:

```http
HTTP/1.1 429 Too Many Requests
Content-Type: text/html

<!DOCTYPE html>
<html>
  <head><title>Too Many Requests</title></head>
  <body>
    <h1>429 - Too Many Requests</h1>
    <p>Rate limit exceeded: requests per window</p>
    <!-- Beautiful error page with debugging info -->
  </body>
</html>
```

## 🧪 Testing Rate Limits

```bash
# Test basic rate limiting
curl -i http://localhost:8000/api/test

# Test with API key
curl -i -H "X-API-Key: your-key" http://localhost:8000/api/premium

# Test sliding window
curl -i http://localhost:8000/api/sliding

# Test token bucket
curl -i http://localhost:8000/api/token-bucket
```

## 🔧 Advanced Configuration

### Custom Strategies

You can register custom rate limiting strategies:

```php
$rateLimitManager->registerStrategy('custom', CustomStrategy::class);
```

### Custom Key Resolvers

Register custom key resolvers:

```php
$rateLimitManager->registerKeyResolver('tenant', TenantKeyResolver::class);
```

## 📈 Performance Characteristics

| Strategy | Memory Usage | CPU Usage | Precision | Burst Handling |
|----------|-------------|-----------|-----------|----------------|
| Fixed Window | Low | Low | Medium | Poor |
| Sliding Window | Medium | Medium | High | Good |
| Token Bucket | Low | Low | High | Excellent |

## 🎯 Best Practices

1. **Start Conservative** - Begin with lower limits and increase based on usage
2. **Use Appropriate Strategy** - Fixed for simple, Sliding for precision, Token Bucket for bursts
3. **Monitor Usage** - Track rate limit headers and 429 responses
4. **Provide Clear Messages** - Help users understand limits
5. **Consider User Tiers** - Different limits for different user types
6. **Test Thoroughly** - Verify limits work as expected

## 🏗️ Architecture

### Components

- **RateLimitMiddleware** - Main middleware class
- **RateLimitManager** - Orchestrates strategies and resolvers
- **RateLimitConfig** - Configuration parsing and validation
- **RateLimitResult** - Results and status tracking
- **RateLimitHeaders** - HTTP header management

### Strategies

- **FixedWindowStrategy** - Time-based fixed windows
- **SlidingWindowStrategy** - Precise sliding windows
- **TokenBucketStrategy** - Token bucket algorithm

### Key Resolvers

- **IpKeyResolver** - IP-based identification
- **UserKeyResolver** - User-based identification
- **HeaderKeyResolver** - Header/API key-based identification
- **CompositeKeyResolver** - Combined identification

---

**TreeHouse Framework - Modern PHP Development Made Simple**