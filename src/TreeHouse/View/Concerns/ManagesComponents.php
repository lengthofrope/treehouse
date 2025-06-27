<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Concerns;

use LengthOfRope\TreeHouse\Support\Arr;

/**
 * Manages view components
 * 
 * @package LengthOfRope\TreeHouse\View\Concerns
 */
trait ManagesComponents
{
    /**
     * Component registry
     */
    protected array $components = [];

    /**
     * Component aliases
     */
    protected array $componentAliases = [];

    /**
     * Register a component
     */
    public function component(string $name, string $template): self
    {
        $this->components[$name] = $template;
        return $this;
    }

    /**
     * Register multiple components
     */
    public function components(array $components): self
    {
        $this->components = array_merge($this->components, $components);
        return $this;
    }

    /**
     * Register a component alias
     */
    public function componentAlias(string $alias, string $component): self
    {
        $this->componentAliases[$alias] = $component;
        return $this;
    }

    /**
     * Render a component
     */
    public function renderComponent(string $name, array $props = []): string
    {
        // Resolve alias
        $name = $this->componentAliases[$name] ?? $name;
        
        // Get component template
        $template = $this->components[$name] ?? $name;
        
        // Render component
        return $this->render($template, $props);
    }

    /**
     * Check if component exists
     */
    public function hasComponent(string $name): bool
    {
        $name = $this->componentAliases[$name] ?? $name;
        return isset($this->components[$name]) || $this->exists($name);
    }

    /**
     * Get all registered components
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * Get component aliases
     */
    public function getComponentAliases(): array
    {
        return $this->componentAliases;
    }
}
