<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * Data collection utilities
 *
 * Provides a fluent interface for working with arrays of data.
 * Inspired by Laravel's Collection but built from scratch for TreeHouse.
 * Now includes model-awareness to preserve object types through transformations.
 *
 * @package LengthOfRope\TreeHouse\Support
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 *
 * @template TKey of array-key
 * @template TValue
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The items contained in the collection
     *
     * @var array<TKey, TValue>
     */
    protected array $items = [];

    /**
     * The model class this collection contains (for model-aware collections)
     *
     * @var string|null
     */
    protected ?string $modelClass = null;

    /**
     * Create a new collection
     *
     * @param array<TKey, TValue> $items Initial items
     * @param string|null $modelClass Model class for model-aware collections
     */
    public function __construct(array $items = [], ?string $modelClass = null)
    {
        $this->items = $items;
        $this->modelClass = $modelClass;
    }

    /**
     * Create a new collection instance
     *
     * @template TMakeKey of array-key
     * @template TMakeValue
     * @param array<TMakeKey, TMakeValue> $items
     * @param string|null $modelClass Model class for model-aware collections
     * @return static<TMakeKey, TMakeValue>
     */
    public static function make(array $items = [], ?string $modelClass = null): static
    {
        return new static($items, $modelClass);
    }

    /**
     * Get the model class for this collection
     *
     * @return string|null
     */
    public function getModelClass(): ?string
    {
        return $this->modelClass;
    }

    /**
     * Check if this is a model-aware collection
     *
     * @return bool
     */
    public function isModelCollection(): bool
    {
        return $this->modelClass !== null;
    }

    /**
     * Get all items in the collection
     * 
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the average value of a given key
     * 
     * @param callable|string|null $callback
     * @return float|int|null
     */
    public function avg(callable|string|null $callback = null): float|int|null
    {
        $callback = $this->valueRetriever($callback);
        
        $items = $this->map($callback)->filter(fn($value) => !is_null($value));
        
        if ($count = $items->count()) {
            return $items->sum() / $count;
        }
        
        return null;
    }

    /**
     * Chunk the collection into chunks of the given size
     *
     * @param int $size
     * @return static<int, static<TKey, TValue>>
     */
    public function chunk(int $size): static
    {
        if ($size <= 0) {
            return static::make([], $this->modelClass);
        }

        $chunks = [];
        
        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = static::make($chunk, $this->modelClass);
        }

        return static::make($chunks);
    }

    /**
     * Collapse the collection of arrays into a single, flat collection
     *
     * @return static<array-key, mixed>
     */
    public function collapse(): static
    {
        $results = [];

        foreach ($this->items as $values) {
            if ($values instanceof Collection) {
                $values = $values->all();
            } elseif (!is_array($values)) {
                continue;
            }

            $results[] = $values;
        }

        // Collapse operation loses model class since we're merging different types
        return static::make(array_merge([], ...$results));
    }

    /**
     * Determine if an item exists in the collection
     * 
     * @param mixed $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if ($this->useAsCallable($key)) {
                $placeholder = new \stdClass();
                
                return $this->first($key, $placeholder) !== $placeholder;
            }

            return in_array($key, $this->items, true);
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Count the number of items in the collection
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get the items that are not present in the given items
     *
     * @param mixed $items
     * @return static<TKey, TValue>
     */
    public function diff(mixed $items): static
    {
        return static::make(array_diff($this->items, $this->getArrayableItems($items)), $this->modelClass);
    }

    /**
     * Execute a callback over each item
     * 
     * @param callable $callback
     * @return $this
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Determine if the collection is empty or not
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Determine if the collection is not empty
     * 
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Filter the collection using the given callback
     *
     * @param callable|null $callback
     * @return static<TKey, TValue>
     */
    public function filter(?callable $callback = null): static
    {
        if ($callback) {
            return static::make(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH), $this->modelClass);
        }

        return static::make(array_filter($this->items), $this->modelClass);
    }

    /**
     * Get the first item from the collection
     * 
     * @param callable|null $callback
     * @param mixed $default
     * @return TValue|mixed
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if (is_null($callback)) {
            if (empty($this->items)) {
                return $default;
            }

            foreach ($this->items as $item) {
                return $item;
            }
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get a flattened array of the items
     *
     * @param int $depth
     * @return static<array-key, mixed>
     */
    public function flatten(float $depth = INF): static
    {
        // Flatten operation loses model class since we're flattening nested structures
        return static::make(Arr::flatten($this->items, $depth));
    }

    /**
     * Get an item from the collection by key
     * 
     * @param TKey $key
     * @param mixed $default
     * @return TValue|mixed
     */
    public function get(mixed $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        return $default;
    }

    /**
     * Group an associative array by a field or using a callback
     *
     * @param callable|string $groupBy
     * @param bool $preserveKeys
     * @return static<array-key, static<TKey, TValue>>
     */
    public function groupBy(callable|string $groupBy, bool $preserveKeys = false): static
    {
        $groupBy = $this->valueRetriever($groupBy);
        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (!is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                $groupKey = is_bool($groupKey) ? (int) $groupKey : $groupKey;

                if (!array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = static::make([], $this->modelClass);
                }

                $results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
            }
        }

        return static::make($results);
    }

    /**
     * Determine if an item exists in the collection by key
     * 
     * @param TKey $key
     * @return bool
     */
    public function has(mixed $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if (!array_key_exists($value, $this->items)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Concatenate values of a given key as a string
     * 
     * @param string $value
     * @param string|null $glue
     * @return string
     */
    public function implode(string $value, ?string $glue = null): string
    {
        $first = $this->first();

        if (is_array($first) || (is_object($first) && !$first instanceof \Stringable)) {
            return implode($glue ?? '', $this->pluck($value)->all());
        }

        return implode($value, $this->items);
    }

    /**
     * Intersect the collection with the given items
     *
     * @param mixed $items
     * @return static<TKey, TValue>
     */
    public function intersect(mixed $items): static
    {
        return static::make(array_intersect($this->items, $this->getArrayableItems($items)), $this->modelClass);
    }

    /**
     * Get the keys of the collection items
     *
     * @return static<int, TKey>
     */
    public function keys(): static
    {
        // Keys operation returns a different type (keys instead of values), so no model class
        return static::make(array_keys($this->items));
    }

    /**
     * Get the last item from the collection
     * 
     * @param callable|null $callback
     * @param mixed $default
     * @return TValue|mixed
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        if (is_null($callback)) {
            return empty($this->items) ? $default : end($this->items);
        }

        return $this->reverse()->first($callback, $default);
    }

    /**
     * Run a map over each of the items
     *
     * @template TMapValue
     * @param callable(TValue, TKey): TMapValue $callback
     * @return static<TKey, TMapValue>
     */
    public function map(callable $callback): static
    {
        $keys = array_keys($this->items);
        
        $items = array_map($callback, $this->items, $keys);
        $result = array_combine($keys, $items);

        // Preserve model class only if all results are still instances of the same model class
        $preserveModelClass = $this->shouldPreserveModelClass($result);

        return static::make($result, $preserveModelClass ? $this->modelClass : null);
    }

    /**
     * Get the max value of a given key
     * 
     * @param callable|string|null $callback
     * @return mixed
     */
    public function max(callable|string|null $callback = null): mixed
    {
        $callback = $this->valueRetriever($callback);

        return $this->filter()->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);

            return is_null($result) || $value > $result ? $value : $result;
        });
    }

    /**
     * Merge the collection with the given items
     *
     * @param mixed $items
     * @return static<TKey|array-key, TValue|mixed>
     */
    public function merge(mixed $items): static
    {
        // Merge operation loses model class since we're merging with potentially different types
        return static::make(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the min value of a given key
     * 
     * @param callable|string|null $callback
     * @return mixed
     */
    public function min(callable|string|null $callback = null): mixed
    {
        $callback = $this->valueRetriever($callback);

        return $this->map($callback)->filter()->reduce(function ($result, $value) {
            return is_null($result) || $value < $result ? $value : $result;
        });
    }

    /**
     * Get the items with the specified keys
     *
     * @param mixed $keys
     * @return static<TKey, TValue>
     */
    public function only(mixed $keys): static
    {
        if (is_null($keys)) {
            return static::make([], $this->modelClass);
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        return static::make(Arr::only($this->items, $keys), $this->modelClass);
    }

    /**
     * Get the values of a given key
     *
     * @param string|array $value
     * @param string|null $key
     * @return static<array-key, mixed>
     */
    public function pluck(string|array $value, ?string $key = null): static
    {
        // Pluck operation returns attribute values, not models, so no model class
        return static::make(Arr::pluck($this->items, $value, $key));
    }

    /**
     * Push an item onto the end of the collection
     * 
     * @param TValue $value
     * @return $this
     */
    public function push(mixed $value): static
    {
        $this->offsetSet(null, $value);

        return $this;
    }

    /**
     * Put an item in the collection by key
     * 
     * @param TKey $key
     * @param TValue $value
     * @return $this
     */
    public function put(mixed $key, mixed $value): static
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * Get one or a specified number of items randomly from the collection
     *
     * @param int|null $number
     * @return static<TKey, TValue>|TValue
     */
    public function random(?int $number = null): mixed
    {
        if (is_null($number)) {
            return Arr::random($this->items);
        }

        return static::make(Arr::random($this->items, $number), $this->modelClass);
    }

    /**
     * Reduce the collection to a single value
     * 
     * @template TReduceInitial
     * @template TReduceReturnType
     * @param callable(TReduceInitial|TReduceReturnType, TValue, TKey): TReduceReturnType $callback
     * @param TReduceInitial $initial
     * @return TReduceInitial|TReduceReturnType
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $result = $initial;

        foreach ($this->items as $key => $value) {
            $result = $callback($result, $value, $key);
        }

        return $result;
    }

    /**
     * Create a collection of all elements that do not pass a given truth test
     *
     * @param callable|mixed $callback
     * @return static<TKey, TValue>
     */
    public function reject(mixed $callback = true): static
    {
        $useAsCallable = $this->useAsCallable($callback);

        // reject() calls filter() which already preserves model class
        return $this->filter(function ($value, $key) use ($callback, $useAsCallable) {
            return $useAsCallable
                ? !$callback($value, $key)
                : $value !== $callback;
        });
    }

    /**
     * Reverse items order
     *
     * @return static<TKey, TValue>
     */
    public function reverse(): static
    {
        return static::make(array_reverse($this->items, true), $this->modelClass);
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful
     * 
     * @param mixed $value
     * @param bool $strict
     * @return TKey|false
     */
    public function search(mixed $value, bool $strict = false): mixed
    {
        if (!$this->useAsCallable($value)) {
            return array_search($value, $this->items, $strict);
        }

        foreach ($this->items as $key => $item) {
            if ($value($item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Shuffle the items in the collection
     *
     * @return static<TKey, TValue>
     */
    public function shuffle(): static
    {
        return static::make(Arr::shuffle($this->items), $this->modelClass);
    }

    /**
     * Skip the first {$count} items
     * 
     * @param int $count
     * @return static<TKey, TValue>
     */
    public function skip(int $count): static
    {
        return $this->slice($count);
    }

    /**
     * Slice the underlying collection array
     *
     * @param int $offset
     * @param int|null $length
     * @return static<TKey, TValue>
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return static::make(array_slice($this->items, $offset, $length, true), $this->modelClass);
    }

    /**
     * Sort through each item with a callback
     *
     * @param callable|null $callback
     * @return static<TKey, TValue>
     */
    public function sort(?callable $callback = null): static
    {
        $items = $this->items;

        $callback && is_callable($callback)
            ? uasort($items, $callback)
            : asort($items, SORT_REGULAR);

        return static::make($items, $this->modelClass);
    }

    /**
     * Sort the collection using the given callback
     *
     * @param callable|string $callback
     * @param int $options
     * @param bool $descending
     * @return static<TKey, TValue>
     */
    public function sortBy(callable|string $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $results = [];

        $callback = $this->valueRetriever($callback);

        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // grab all the corresponding values for the sorted keys from this array.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return static::make($results, $this->modelClass);
    }

    /**
     * Sort the collection in descending order using the given callback
     * 
     * @param callable|string $callback
     * @param int $options
     * @return static<TKey, TValue>
     */
    public function sortByDesc(callable|string $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Sort the collection keys
     *
     * @param int $options
     * @param bool $descending
     * @return static<TKey, TValue>
     */
    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false): static
    {
        $items = $this->items;

        $descending ? krsort($items, $options) : ksort($items, $options);

        return static::make($items, $this->modelClass);
    }

    /**
     * Sort the collection keys in descending order
     *
     * @param int $options
     * @return static<TKey, TValue>
     */
    public function sortKeysDesc(int $options = SORT_REGULAR): static
    {
        return $this->sortKeys($options, true);
    }

    /**
     * Get the sum of the given values
     * 
     * @param callable|string|null $callback
     * @return mixed
     */
    public function sum(callable|string|null $callback = null): mixed
    {
        $callback = is_null($callback) ? $this->identity() : $this->valueRetriever($callback);

        return $this->reduce(function ($result, $item) use ($callback) {
            return $result + $callback($item);
        }, 0);
    }

    /**
     * Take the first or last {$limit} items
     * 
     * @param int $limit
     * @return static<TKey, TValue>
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Transform each item in the collection using a callback
     * 
     * @param callable $callback
     * @return $this
     */
    public function transform(callable $callback): static
    {
        $this->items = $this->map($callback)->all();

        return $this;
    }

    /**
     * Return only unique items from the collection array
     *
     * @param string|callable|null $key
     * @param bool $strict
     * @return static<TKey, TValue>
     */
    public function unique(string|callable|null $key = null, bool $strict = false): static
    {
        $callback = $this->valueRetriever($key);

        $exists = [];

        // unique() calls reject() which calls filter() which already preserves model class
        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /**
     * Reset the keys on the underlying array
     *
     * @return static<int, TValue>
     */
    public function values(): static
    {
        return static::make(array_values($this->items), $this->modelClass);
    }

    /**
     * Filter items by the given key value pair
     *
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return static<TKey, TValue>
     */
    public function where(string $key, mixed $operator = null, mixed $value = null): static
    {
        // where() calls filter() which already preserves model class
        return $this->filter($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Filter items where the given key is not null
     *
     * @param string|null $key
     * @return static<TKey, TValue>
     */
    public function whereNotNull(?string $key = null): static
    {
        return $this->whereNot($key, null);
    }

    /**
     * Filter items by the given key value pair using strict comparison
     *
     * @param string $key
     * @param mixed $value
     * @return static<TKey, TValue>
     */
    public function whereStrict(string $key, mixed $value): static
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Filter items where the value for the given key is null
     *
     * @param string|null $key
     * @return static<TKey, TValue>
     */
    public function whereNull(?string $key = null): static
    {
        return $this->where($key, '=', null);
    }

    /**
     * Filter items where the given key value pair does not match
     *
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return static<TKey, TValue>
     */
    public function whereNot(string $key, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '!=';
        }

        return $this->where($key, $operator, $value);
    }

    /**
     * Filter items where the given key is in the given array
     *
     * @param string $key
     * @param mixed $values
     * @param bool $strict
     * @return static<TKey, TValue>
     */
    public function whereIn(string $key, mixed $values, bool $strict = false): static
    {
        $values = $this->getArrayableItems($values);

        // whereIn() calls filter() which already preserves model class
        return $this->filter(function ($item) use ($key, $values, $strict) {
            return in_array(dataGet($item, $key), $values, $strict);
        });
    }

    /**
     * Filter items where the given key is not in the given array
     *
     * @param string $key
     * @param mixed $values
     * @param bool $strict
     * @return static<TKey, TValue>
     */
    public function whereNotIn(string $key, mixed $values, bool $strict = false): static
    {
        $values = $this->getArrayableItems($values);

        // whereNotIn() calls reject() which calls filter() which preserves model class
        return $this->reject(function ($item) use ($key, $values, $strict) {
            return in_array(dataGet($item, $key), $values, $strict);
        });
    }

    /**
     * Zip the collection together with one or more arrays
     *
     * @param mixed ...$items
     * @return static<int, static<int, mixed>>
     */
    public function zip(mixed ...$items): static
    {
        $arrayableItems = array_map([$this, 'getArrayableItems'], func_get_args());

        $params = array_merge([function () {
            return static::make(func_get_args());
        }, $this->items], $arrayableItems);

        // Zip operation creates new structure, loses model class
        return static::make(array_map(...$params));
    }

    /**
     * Get an iterator for the items
     * 
     * @return ArrayIterator<TKey, TValue>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }


    /**
     * Add an item to the collection
     * 
     * @param TKey|null $key
     * @param TValue $value
     * @return void
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Determine if an item exists at an offset
     * 
     * @param TKey $key
     * @return bool
     */
    public function offsetExists(mixed $key): bool
    {
        return isset($this->items[$key]);
    }

    /**
     * Remove an item at a given offset
     * 
     * @param TKey $key
     * @return void
     */
    public function offsetUnset(mixed $key): void
    {
        unset($this->items[$key]);
    }

    /**
     * Get an item at a given offset
     * 
     * @param TKey $key
     * @return TValue
     */
    public function offsetGet(mixed $key): mixed
    {
        return $this->items[$key];
    }


    /**
     * Convert the object to its JSON representation
     *
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the collection to its string representation
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Specify data which should be serialized to JSON
     * 
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): array
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif ($value instanceof \JsonSerializable) {
                return $value->jsonSerialize();
            }

            return $value;
        }, $this->items);
    }

    /**
     * Get a value retrieving callback
     * 
     * @param callable|string|null $value
     * @return callable
     */
    protected function valueRetriever(callable|string|null $value): callable
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            return dataGet($item, $value);
        };
    }

    /**
     * Determine if the given value is callable, but not a string
     * 
     * @param mixed $value
     * @return bool
     */
    protected function useAsCallable(mixed $value): bool
    {
        return !is_string($value) && is_callable($value);
    }

    /**
     * Get an operator checker callback
     * 
     * @param string $key
     * @param string|null $operator
     * @param mixed $value
     * @return callable
     */
    protected function operatorForWhere(string $key, ?string $operator = null, mixed $value = null): callable
    {
        if (func_num_args() === 1) {
            $value = true;
            $operator = '=';
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $retrieved = dataGet($item, $key);

            $strings = array_filter([$retrieved, $value], function ($value) {
                return is_string($value) || (is_object($value) && method_exists($value, '__toString'));
            });

            if (count($strings) < 2 && count(array_filter([$retrieved, $value], 'is_object')) == 1) {
                return in_array($operator, ['!=', '<>', '!==']);
            }

            switch ($operator) {
                default:
                case '=':
                case '==':
                    return $retrieved == $value;
                case '!=':
                case '<>':
                    return $retrieved != $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
            }
        };
    }

    /**
     * Results array of items from Collection or Arrayable
     *
     * @param mixed $items
     * @return array
     */
    protected function getArrayableItems(mixed $items): array
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof self) {
            return $items->all();
        } elseif ($items instanceof \JsonSerializable) {
            return (array) $items->jsonSerialize();
        } elseif ($items instanceof \Traversable) {
            return iterator_to_array($items);
        }

        return (array) $items;
    }

    /**
     * Get a callback that returns the identity value
     *
     * @return callable
     */
    protected function identity(): callable
    {
        return function ($value) {
            return $value;
        };
    }

    /**
     * Find a model by a specific attribute value
     *
     * @param string $attribute
     * @param mixed $value
     * @return mixed|null
     */
    public function findBy(string $attribute, mixed $value): mixed
    {
        return $this->first(function ($item) use ($attribute, $value) {
            if (is_object($item) && method_exists($item, 'getAttribute')) {
                return $item->getAttribute($attribute) === $value;
            }
            return dataGet($item, $attribute) === $value;
        });
    }

    /**
     * Reload all models from the database (only works with model collections)
     *
     * @return static
     */
    public function fresh(): static
    {
        if (!$this->isModelCollection()) {
            return $this;
        }

        $freshItems = [];
        foreach ($this->items as $key => $item) {
            if (is_object($item) && method_exists($item, 'fresh')) {
                $fresh = $item->fresh();
                if ($fresh) {
                    $freshItems[$key] = $fresh;
                }
            } else {
                $freshItems[$key] = $item;
            }
        }

        return static::make($freshItems, $this->modelClass);
    }

    /**
     * Save all models in the collection (only works with model collections)
     *
     * @return bool
     */
    public function saveAll(): bool
    {
        if (!$this->isModelCollection()) {
            return false;
        }

        $success = true;
        foreach ($this->items as $item) {
            if (is_object($item) && method_exists($item, 'save')) {
                if (!$item->save()) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Delete all models in the collection (only works with model collections)
     *
     * @return bool
     */
    public function deleteAll(): bool
    {
        if (!$this->isModelCollection()) {
            return false;
        }

        $success = true;
        foreach ($this->items as $item) {
            if (is_object($item) && method_exists($item, 'delete')) {
                if (!$item->delete()) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Get an array of the primary key values
     *
     * @return array
     */
    public function modelKeys(): array
    {
        if (!$this->isModelCollection()) {
            return [];
        }

        return $this->map(function ($item) {
            if (is_object($item) && method_exists($item, 'getKey')) {
                return $item->getKey();
            }
            return null;
        })->filter()->values()->all();
    }

    /**
     * Determine if the model class should be preserved based on result items
     *
     * @param array $result
     * @return bool
     */
    protected function shouldPreserveModelClass(array $result): bool
    {
        if (!$this->modelClass) {
            return false;
        }

        // If result is empty, preserve model class
        if (empty($result)) {
            return true;
        }

        // Check if all items are still instances of the model class
        foreach ($result as $item) {
            if (!($item instanceof $this->modelClass)) {
                return false;
            }
        }

        return true;
    }
}