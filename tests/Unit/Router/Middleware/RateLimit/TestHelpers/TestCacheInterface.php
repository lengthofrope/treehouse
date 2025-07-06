<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware\RateLimit\TestHelpers;

use LengthOfRope\TreeHouse\Cache\CacheInterface;

/**
 * Test implementation of CacheInterface for testing
 */
class TestCacheInterface implements CacheInterface
{
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->data[$key] = $value;
        return true;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $current = (int) ($this->data[$key] ?? 0);
        $this->data[$key] = $current + $value;
        return $this->data[$key];
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        $current = (int) ($this->data[$key] ?? 0);
        $this->data[$key] = max(0, $current - $value);
        return $this->data[$key];
    }

    public function forget(string $key): bool
    {
        unset($this->data[$key]);
        return true;
    }

    public function flush(): bool
    {
        $this->data = [];
        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        
        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, null);
    }

    public function many(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    public function putMany(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $ttl);
        }
        return true;
    }
}