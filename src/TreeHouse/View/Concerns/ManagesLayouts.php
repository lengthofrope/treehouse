<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Concerns;

/**
 * Manages view layouts and inheritance
 * 
 * @package LengthOfRope\TreeHouse\View\Concerns
 */
trait ManagesLayouts
{
    /**
     * Layout registry
     */
    protected array $layouts = [];

    /**
     * Default layout
     */
    protected ?string $defaultLayout = null;

    /**
     * Register a layout
     */
    public function layout(string $name, string $template): self
    {
        $this->layouts[$name] = $template;
        return $this;
    }

    /**
     * Register multiple layouts
     */
    public function layouts(array $layouts): self
    {
        $this->layouts = array_merge($this->layouts, $layouts);
        return $this;
    }

    /**
     * Set default layout
     */
    public function setDefaultLayout(?string $layout): self
    {
        $this->defaultLayout = $layout;
        return $this;
    }

    /**
     * Get default layout
     */
    public function getDefaultLayout(): ?string
    {
        return $this->defaultLayout;
    }

    /**
     * Resolve layout template
     */
    public function resolveLayout(string $layout): string
    {
        return $this->layouts[$layout] ?? $layout;
    }

    /**
     * Check if layout exists
     */
    public function hasLayout(string $name): bool
    {
        return isset($this->layouts[$name]) || $this->exists($name);
    }

    /**
     * Get all registered layouts
     */
    public function getLayouts(): array
    {
        return $this->layouts;
    }
}
