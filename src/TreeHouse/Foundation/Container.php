<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Foundation;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionParameter;

/**
 * TreeHouse Service Container
 * 
 * A simple dependency injection container for the TreeHouse framework.
 * Supports binding, singleton registration, and automatic dependency resolution.
 * 
 * @package LengthOfRope\TreeHouse\Foundation
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Container
{
    /**
     * Service bindings
     *
     * @var array<string, mixed>
     */
    private array $bindings = [];

    /**
     * Singleton instances
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Service aliases
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Services being resolved (for circular dependency detection)
     *
     * @var array<string, bool>
     */
    private array $building = [];

    /**
     * Bind a service to the container
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation (closure, class name, or instance)
     * @param bool $shared Whether the service should be a singleton
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        $concrete = $concrete ?? $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];

        // Remove existing singleton instance if re-binding
        if (isset($this->instances[$abstract])) {
            unset($this->instances[$abstract]);
        }
    }

    /**
     * Bind a singleton service to the container
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as a singleton
     *
     * @param string $abstract Service identifier
     * @param mixed $instance Service instance
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Register an alias for a service
     *
     * @param string $alias The alias
     * @param string $abstract The service identifier
     */
    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Resolve a service from the container
     *
     * @param string $abstract Service identifier
     * @return mixed Resolved service
     * @throws InvalidArgumentException If the service cannot be resolved
     */
    public function make(string $abstract): mixed
    {
        $abstract = $this->getAlias($abstract);

        // Return existing singleton instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check for circular dependencies
        if (isset($this->building[$abstract])) {
            throw new InvalidArgumentException("Circular dependency detected while resolving [{$abstract}]");
        }

        $this->building[$abstract] = true;

        try {
            $concrete = $this->getConcrete($abstract);
            $object = $this->build($concrete);

            // Store singleton instances
            if ($this->isShared($abstract)) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        } finally {
            unset($this->building[$abstract]);
        }
    }

    /**
     * Check if a service is bound in the container
     *
     * @param string $abstract Service identifier
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || 
               isset($this->instances[$abstract]) || 
               $this->isAlias($abstract);
    }

    /**
     * Check if a service is a singleton
     *
     * @param string $abstract Service identifier
     * @return bool
     */
    public function isShared(string $abstract): bool
    {
        return isset($this->instances[$abstract]) ||
               (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared']);
    }

    /**
     * Get all bindings
     *
     * @return array<string, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Flush all bindings and instances
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->building = [];
    }

    /**
     * Get the alias for a service
     *
     * @param string $abstract Service identifier
     * @return string Resolved service identifier
     */
    private function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    /**
     * Check if a name is an alias
     *
     * @param string $name Service name
     * @return bool
     */
    private function isAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Get the concrete implementation for a service
     *
     * @param string $abstract Service identifier
     * @return mixed Concrete implementation
     */
    private function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Build a concrete implementation
     *
     * @param mixed $concrete Concrete implementation
     * @return mixed Built instance
     * @throws InvalidArgumentException If the concrete cannot be built
     */
    private function build(mixed $concrete): mixed
    {
        // If concrete is a closure, execute it
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        // If concrete is not a class name, return as-is
        if (!is_string($concrete) || !class_exists($concrete)) {
            return $concrete;
        }

        // Build the class using reflection
        return $this->buildClass($concrete);
    }

    /**
     * Build a class using reflection and dependency injection
     *
     * @param string $className Class name to build
     * @return object Built class instance
     * @throws InvalidArgumentException If the class cannot be built
     */
    private function buildClass(string $className): object
    {
        $reflector = new ReflectionClass($className);

        if (!$reflector->isInstantiable()) {
            throw new InvalidArgumentException("Target [{$className}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        // If no constructor, create instance without dependencies
        if (is_null($constructor)) {
            return new $className();
        }

        // Resolve constructor dependencies
        $dependencies = $this->resolveDependencies($constructor->getParameters());

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve method dependencies
     *
     * @param ReflectionParameter[] $parameters Method parameters
     * @return array<mixed> Resolved dependencies
     * @throws InvalidArgumentException If a dependency cannot be resolved
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveDependency($parameter);
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Resolve a single parameter dependency
     *
     * @param ReflectionParameter $parameter Parameter to resolve
     * @return mixed Resolved dependency
     * @throws InvalidArgumentException If the dependency cannot be resolved
     */
    private function resolveDependency(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        // Handle union types and other complex types
        if (!$type || $type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new InvalidArgumentException(
                "Cannot resolve dependency [{$parameter->getName()}] in [{$parameter->getDeclaringClass()?->getName()}]"
            );
        }

        $typeName = $type->getName();

        // Try to resolve from container
        if ($this->bound($typeName)) {
            return $this->make($typeName);
        }

        // Try to auto-resolve
        if (class_exists($typeName)) {
            return $this->make($typeName);
        }

        // Check for default value
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new InvalidArgumentException(
            "Cannot resolve dependency [{$typeName}] for parameter [{$parameter->getName()}] in [{$parameter->getDeclaringClass()?->getName()}]"
        );
    }

    /**
     * Call a method and inject its dependencies
     *
     * @param callable $callback Callback to call
     * @param array<mixed> $parameters Additional parameters
     * @return mixed Method result
     */
    public function call(callable $callback, array $parameters = []): mixed
    {
        // For now, just call the callback with provided parameters
        // This could be enhanced to inject dependencies into the callback
        return $callback(...$parameters);
    }
}