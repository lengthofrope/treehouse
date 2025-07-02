# Model Autocompletion in IDEs

This guide explains how ActiveRecord models provide perfect IDE autocompletion support using PHP 8.4 Property Hooks.

## How It Works

PHP 8.4 introduced Property Hooks, which allow defining real properties with custom getter and setter logic. This provides perfect IDE autocompletion, type safety, and better performance compared to magic methods.

## Property Hooks Implementation

Each model defines property hooks for all database attributes, providing:
- **Perfect IDE autocompletion** - Real properties, not magic methods
- **Type safety** - Actual PHP type declarations
- **Better performance** - No magic method overhead
- **Carbon integration** - DateTime properties return Carbon instances

### Example:

```php
<?php

namespace LengthOfRope\TreeHouse\Models;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Support\Carbon;

class User extends ActiveRecord
{
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];

    // Property hooks for perfect IDE autocompletion
    public int $id {
        get => (int) $this->getAttribute('id');
    }

    public string $name {
        get => (string) $this->getAttribute('name');
        set(string $value) {
            $this->setAttribute('name', $value);
        }
    }

    public string $email {
        get => (string) $this->getAttribute('email');
        set(string $value) {
            $this->setAttribute('email', $value);
        }
    }

    public ?Carbon $created_at {
        get => $this->getAttribute('created_at') ? Carbon::parse($this->getAttribute('created_at')) : null;
        set(?Carbon $value) {
            $this->setAttribute('created_at', $value?->format('Y-m-d H:i:s'));
        }
    }
}
```

## Property Types

Property hooks support all PHP types with perfect type safety:

- `int` - Integer values
- `string` - String values
- `float` - Decimal numbers
- `bool` - Boolean true/false
- `Carbon` - Date/time objects (using TreeHouse Carbon helper)
- `?string` - Nullable string
- `?int` - Nullable integer
- `array` - Array values (for JSON columns)

## What You Get

With property hooks, your IDE provides:

1. **Perfect autocompletion** - Real properties, not magic methods
2. **Actual type hints** - Native PHP type declarations
3. **Compile-time error detection** - PHP catches type errors
4. **Superior refactoring** - IDEs can safely rename and track usage
5. **Better debugging** - Properties show up in debuggers and var_dump
6. **Static analysis support** - Tools like PHPStan understand them perfectly

## Property Hook Patterns

### Read-only properties (like ID):
```php
public int $id {
    get => (int) $this->getAttribute('id');
}
```

### Read-write properties:
```php
public string $name {
    get => (string) $this->getAttribute('name');
    set(string $value) {
        $this->setAttribute('name', $value);
    }
}
```

### Nullable properties:
```php
public ?string $description {
    get => $this->getAttribute('description');
    set(?string $value) {
        $this->setAttribute('description', $value);
    }
}
```

### Carbon date properties:
```php
public ?Carbon $created_at {
    get => $this->getAttribute('created_at') ? Carbon::parse($this->getAttribute('created_at')) : null;
    set(?Carbon $value) {
        $this->setAttribute('created_at', $value?->format('Y-m-d H:i:s'));
    }
}
```

## Example for Different Column Types

```php
use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Support\Carbon;

class Product extends ActiveRecord
{
    protected array $fillable = [
        'name', 'slug', 'description', 'price',
        'stock_quantity', 'is_active', 'meta_data', 'published_at'
    ];

    public int $id {
        get => (int) $this->getAttribute('id');
    }

    public string $name {
        get => (string) $this->getAttribute('name');
        set(string $value) => $this->setAttribute('name', $value);
    }

    public float $price {
        get => (float) $this->getAttribute('price');
        set(float $value) => $this->setAttribute('price', $value);
    }

    public int $stock_quantity {
        get => (int) $this->getAttribute('stock_quantity');
        set(int $value) => $this->setAttribute('stock_quantity', $value);
    }

    public bool $is_active {
        get => (bool) $this->getAttribute('is_active');
        set(bool $value) => $this->setAttribute('is_active', $value);
    }

    public array $meta_data {
        get => json_decode($this->getAttribute('meta_data') ?? '[]', true);
        set(array $value) => $this->setAttribute('meta_data', json_encode($value));
    }

    public ?Carbon $published_at {
        get => $this->getAttribute('published_at') ? Carbon::parse($this->getAttribute('published_at')) : null;
        set(?Carbon $value) => $this->setAttribute('published_at', $value?->format('Y-m-d H:i:s'));
    }
}
```

## Benefits Over Magic Methods

Property hooks provide significant advantages:

- **Performance**: No `__get`/`__set` overhead
- **Type safety**: PHP enforces types at runtime
- **IDE support**: Perfect autocompletion and refactoring
- **Static analysis**: PHPStan, Psalm, etc. understand them
- **Debugging**: Properties visible in debuggers
- **Documentation**: Self-documenting with type declarations

This approach makes your models much more developer-friendly and eliminates entire classes of bugs!