<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Support\Arr;
use LengthOfRope\TreeHouse\Support\Env;

if (!function_exists('dataGet')) {
    /**
     * Get an item from an array or object using "dot" notation
     *
     * @param mixed $target
     * @param string|array|int|null $key
     * @param mixed $default
     * @return mixed
     */
    function dataGet(mixed $target, string|array|int|null $key, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

            if ($segment === '*') {
                if (!is_array($target) && !$target instanceof \Traversable) {
                    return $default;
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = dataGet($item, $key);
                }

                return in_array('*', $key) ? Arr::collapse($result) : $result;
            }

            if (Arr::accessible($target) && Arr::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return $default;
            }
        }

        return $target;
    }
}

if (!function_exists('dataSet')) {
    /**
     * Set an item on an array or object using dot notation
     *
     * @param mixed $target
     * @param string|array $key
     * @param mixed $value
     * @param bool $overwrite
     * @return mixed
     */
    function dataSet(mixed &$target, string|array $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (!Arr::accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    dataSet($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (Arr::accessible($target)) {
            if ($segments) {
                if (!Arr::exists($target, $segment)) {
                    $target[$segment] = [];
                }

                dataSet($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !Arr::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                dataSet($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                dataSet($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value
     * 
     * @param mixed $value
     * @param mixed ...$args
     * @return mixed
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('with')) {
    /**
     * Return the given value, optionally passed through the given callback
     *
     * @param mixed $value
     * @param callable|null $callback
     * @return mixed
     */
    function with(mixed $value, ?callable $callback = null): mixed
    {
        return is_null($callback) ? $value : $callback($value);
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable with type conversion
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('event')) {
    /**
     * Dispatch an event and return the event instance
     *
     * @param object $event The event to dispatch
     * @return object The event instance
     */
    function event(object $event): object
    {
        if (isset($GLOBALS['app'])) {
            try {
                $dispatcher = $GLOBALS['app']->make('events');
                return $dispatcher->dispatch($event);
            } catch (\Exception $e) {
                // If event dispatcher is not available, just return the event
                return $event;
            }
        }
        
        return $event;
    }
}

if (!function_exists('listen')) {
    /**
     * Register an event listener
     *
     * @param string $event Event class name
     * @param callable|string|object $listener Event listener
     * @param int $priority Listener priority
     * @return void
     */
    function listen(string $event, callable|string|object $listener, int $priority = 0): void
    {
        if (isset($GLOBALS['app'])) {
            try {
                $dispatcher = $GLOBALS['app']->make('events');
                $dispatcher->listen($event, $listener, $priority);
            } catch (\Exception $e) {
                // If event dispatcher is not available, silently fail
            }
        }
    }
}

if (!function_exists('until')) {
    /**
     * Dispatch an event until the first non-null response is returned
     *
     * @param object $event The event to dispatch
     * @return mixed The first non-null response from a listener
     */
    function until(object $event): mixed
    {
        if (isset($GLOBALS['app'])) {
            try {
                $dispatcher = $GLOBALS['app']->make('events');
                return $dispatcher->until($event);
            } catch (\Exception $e) {
                // If event dispatcher is not available, return null
                return null;
            }
        }
        
        return null;
    }
}

if (!function_exists('app')) {
    /**
     * Get the application instance or resolve a service from the container
     *
     * @param string|null $abstract Service to resolve
     * @return mixed
     */
    function app(?string $abstract = null): mixed
    {
        if (!isset($GLOBALS['app'])) {
            throw new \RuntimeException('Application instance not available');
        }
        
        if (is_null($abstract)) {
            return $GLOBALS['app'];
        }
        
        return $GLOBALS['app']->make($abstract);
    }
}

if (!function_exists('auth')) {
    /**
     * Get the authentication manager instance
     *
     * @return mixed
     */
    function auth(): mixed
    {
        if (isset($GLOBALS['auth_manager'])) {
            return $GLOBALS['auth_manager'];
        }
        
        return app('auth');
    }
}

if (!function_exists('cache')) {
    /**
     * Get the cache manager instance
     *
     * @return mixed
     */
    function cache(): mixed
    {
        return app('cache');
    }
}

if (!function_exists('view')) {
    /**
     * Get the view factory instance or create a view
     *
     * @param string|null $view View name
     * @param array $data View data
     * @return mixed
     */
    function view(?string $view = null, array $data = []): mixed
    {
        $factory = app('view');
        
        if (is_null($view)) {
            return $factory;
        }
        
        return $factory->make($view, $data);
    }
}