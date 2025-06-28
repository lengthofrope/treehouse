<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View;

use LengthOfRope\TreeHouse\Cache\CacheInterface;
use LengthOfRope\TreeHouse\Support\{Collection, Arr, Str};

/**
 * View factory for template instantiation and caching
 * 
 * Provides a factory interface for creating view instances with proper caching
 * and configuration management.
 * 
 * @package LengthOfRope\TreeHouse\View
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ViewFactory
{
    /**
     * View engine instances
     */
    protected array $engines = [];

    /**
     * Default engine name
     */
    protected string $defaultEngine = 'treehouse';

    /**
     * Configuration
     */
    protected array $config = [];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'paths' => [
                getcwd() . '/resources/views',
                getcwd() . '/templates',
            ],
            'cache_path' => getcwd() . '/storage/views',
            'cache_enabled' => true,
            'extensions' => ['.th.html', '.th.php', '.php', '.html'],
        ], $config);
        
        $this->createDefaultEngine();
    }

    /**
     * Create default TreeHouse engine
     */
    protected function createDefaultEngine(): void
    {
        $cache = null;
        if ($this->config['cache_enabled'] && class_exists('\LengthOfRope\TreeHouse\Cache\FileCache')) {
            $cache = new \LengthOfRope\TreeHouse\Cache\FileCache($this->config['cache_path']);
        }

        $engine = new ViewEngine($this->config['paths'], $cache);
        $this->engines[$this->defaultEngine] = $engine;
    }

    /**
     * Get view engine
     */
    public function engine(?string $name = null): ViewEngine
    {
        $name = $name ?: $this->defaultEngine;
        
        if (!isset($this->engines[$name])) {
            throw new \InvalidArgumentException("View engine '{$name}' not found");
        }
        
        return $this->engines[$name];
    }

    /**
     * Register view engine
     */
    public function extend(string $name, ViewEngine $engine): self
    {
        $this->engines[$name] = $engine;
        return $this;
    }

    /**
     * Create view
     */
    public function make(string $template, array $data = [], ?string $engine = null): Template
    {
        return $this->engine($engine)->make($template, $data);
    }

    /**
     * Render view
     */
    public function render(string $template, array $data = [], ?string $engine = null): string
    {
        return $this->engine($engine)->render($template, $data);
    }

    /**
     * Check if view exists
     */
    public function exists(string $template, ?string $engine = null): bool
    {
        return $this->engine($engine)->exists($template);
    }

    /**
     * Share data with all views
     */
    public function share(string|array $key, mixed $value = null, ?string $engine = null): self
    {
        $this->engine($engine)->share($key, $value);
        return $this;
    }

    /**
     * Register view composer
     */
    public function composer(string|array $views, callable $callback, ?string $engine = null): self
    {
        $this->engine($engine)->composer($views, $callback);
        return $this;
    }

    /**
     * Register component
     */
    public function component(string $name, string $template, ?string $engine = null): self
    {
        $this->engine($engine)->component($name, $template);
        return $this;
    }

    /**
     * Register layout
     */
    public function layout(string $name, string $template, ?string $engine = null): self
    {
        $this->engine($engine)->layout($name, $template);
        return $this;
    }

    /**
     * Add template path
     */
    public function addPath(string $path, ?string $engine = null): self
    {
        $this->engine($engine)->addPath($path);
        return $this;
    }

    /**
     * Clear view cache
     */
    public function clearCache(?string $engine = null): self
    {
        if ($engine) {
            $this->engine($engine)->clearCache();
        } else {
            foreach ($this->engines as $eng) {
                $eng->clearCache();
            }
        }
        
        return $this;
    }

    /**
     * Get configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set configuration
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Get all engines
     */
    public function getEngines(): array
    {
        return $this->engines;
    }

    /**
     * Set default engine
     */
    public function setDefaultEngine(string $engine): self
    {
        if (!isset($this->engines[$engine])) {
            throw new \InvalidArgumentException("View engine '{$engine}' not found");
        }
        
        $this->defaultEngine = $engine;
        return $this;
    }

    /**
     * Get default engine name
     */
    public function getDefaultEngine(): string
    {
        return $this->defaultEngine;
    }

    /**
     * Create view collection
     */
    public function collection(array $templates, array $data = [], ?string $engine = null): Collection
    {
        $views = [];
        foreach ($templates as $key => $template) {
            $views[$key] = $this->make($template, $data, $engine);
        }
        
        return new Collection($views);
    }

    /**
     * Render multiple views
     */
    public function renderMany(array $templates, array $data = [], ?string $engine = null): array
    {
        $rendered = [];
        foreach ($templates as $key => $template) {
            $rendered[$key] = $this->render($template, $data, $engine);
        }
        
        return $rendered;
    }

    /**
     * Check multiple templates exist
     */
    public function existsMany(array $templates, ?string $engine = null): array
    {
        $results = [];
        foreach ($templates as $template) {
            $results[$template] = $this->exists($template, $engine);
        }
        
        return $results;
    }

    /**
     * Flush all view data
     */
    public function flush(): self
    {
        foreach ($this->engines as $engine) {
            $engine->clearCache();
        }
        
        return $this;
    }
}
