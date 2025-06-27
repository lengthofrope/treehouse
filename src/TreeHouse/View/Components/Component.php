<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Components;

use LengthOfRope\TreeHouse\Support\{Collection, Str, Arr};
use LengthOfRope\TreeHouse\View\Template;

/**
 * Base view component class
 * 
 * Provides a base class for creating reusable view components with props,
 * state management, and lifecycle hooks.
 * 
 * @package LengthOfRope\TreeHouse\View\Components
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
abstract class Component
{
    /**
     * Component properties
     */
    protected array $props = [];

    /**
     * Component state
     */
    protected array $state = [];

    /**
     * Component attributes
     */
    protected array $attributes = [];

    /**
     * Component slot content
     */
    protected string $slot = '';

    /**
     * Constructor
     */
    public function __construct(array $props = [])
    {
        $this->props = $props;
        $this->state = $this->getInitialState();
        $this->boot();
    }

    /**
     * Get component template
     */
    abstract public function template(): string;

    /**
     * Get initial component state
     */
    protected function getInitialState(): array
    {
        return [];
    }

    /**
     * Boot the component
     */
    protected function boot(): void
    {
        // Override in subclasses
    }

    /**
     * Render the component
     */
    public function render(): string
    {
        $data = array_merge($this->props, $this->state, [
            'attributes' => $this->getAttributesString(),
            'slot' => $this->slot,
        ]);

        // For now, return a simple template system
        // This would typically integrate with ViewEngine
        return "<!-- Component: {$this->template()} -->" . $this->slot;
    }

    /**
     * Set component properties
     */
    public function withProps(array $props): self
    {
        $this->props = array_merge($this->props, $props);
        return $this;
    }

    /**
     * Set component attributes
     */
    public function withAttributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    /**
     * Set slot content
     */
    public function withSlot(string $slot): self
    {
        $this->slot = $slot;
        return $this;
    }

    /**
     * Get property value
     */
    public function prop(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->props, $key, $default);
    }

    /**
     * Set state value
     */
    public function setState(string $key, mixed $value): self
    {
        Arr::set($this->state, $key, $value);
        return $this;
    }

    /**
     * Get state value
     */
    public function getState(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->state, $key, $default);
    }

    /**
     * Check if property exists
     */
    public function hasProp(string $key): bool
    {
        return Arr::has($this->props, $key);
    }

    /**
     * Get all properties
     */
    public function getProps(): array
    {
        return $this->props;
    }

    /**
     * Get all state
     */
    public function getAllState(): array
    {
        return $this->state;
    }

    /**
     * Get attributes as string
     */
    protected function getAttributesString(): string
    {
        $attributes = [];
        
        foreach ($this->attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $attributes[] = $key;
                }
            } else {
                $attributes[] = $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
            }
        }
        
        return implode(' ', $attributes);
    }

    /**
     * Get computed property
     */
    public function computed(string $key): mixed
    {
        $method = 'get' . Str::studly($key) . 'Property';
        
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        
        return null;
    }

    /**
     * Check if component should update
     */
    public function shouldUpdate(array $newProps): bool
    {
        return $newProps !== $this->props;
    }

    /**
     * Component lifecycle: before render
     */
    protected function beforeRender(): void
    {
        // Override in subclasses
    }

    /**
     * Component lifecycle: after render
     */
    protected function afterRender(string $rendered): string
    {
        // Override in subclasses
        return $rendered;
    }

    /**
     * Render component to string
     */
    public function __toString(): string
    {
        try {
            $this->beforeRender();
            $rendered = $this->render();
            return $this->afterRender($rendered);
        } catch (\Throwable $e) {
            return "<!-- Component Error: {$e->getMessage()} -->";
        }
    }

    /**
     * Create component instance
     */
    public static function make(array $props = []): static
    {
        return new static($props);
    }

    /**
     * Render component with props
     */
    public static function renderStatic(array $props = []): string
    {
        return static::make($props)->render();
    }
}
