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
        // Set default configuration with proper fallbacks
        $defaultConfig = [
            'cache_enabled' => true,
            'extensions' => ['.th.html', '.th.php', '.php', '.html'],
        ];
        
        // Only set paths and cache_path as fallbacks if not provided
        if (!isset($config['paths'])) {
            $defaultConfig['paths'] = $this->getDefaultViewPaths();
        }
        
        if (!isset($config['cache_path'])) {
            $defaultConfig['cache_path'] = $this->getDefaultCachePath();
        }
        
        $this->config = array_merge($defaultConfig, $config);
        
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

    /**
     * Get default view paths with intelligent vendor detection
     */
    protected function getDefaultViewPaths(): array
    {
        // Check if we're running as a vendor dependency
        $reflector = new \ReflectionClass(static::class);
        $frameworkDir = dirname($reflector->getFileName(), 4); // Go up from src/TreeHouse/View/ViewFactory.php
        
        // If we're in vendor/, look for the consuming project's views
        if (str_contains($frameworkDir, '/vendor/')) {
            $projectRoot = $this->findProjectRoot($frameworkDir);
            return [
                $projectRoot . '/resources/views',
                $projectRoot . '/templates',
                $projectRoot . '/views',
            ];
        }
        
        // If we're running standalone, use current working directory
        return [
            getcwd() . '/resources/views',
            getcwd() . '/templates',
        ];
    }

    /**
     * Get default cache path with intelligent vendor detection
     */
    protected function getDefaultCachePath(): string
    {
        // Check if we're running as a vendor dependency
        $reflector = new \ReflectionClass(static::class);
        $frameworkDir = dirname($reflector->getFileName(), 4); // Go up from src/TreeHouse/View/ViewFactory.php
        
        // If we're in vendor/, look for the consuming project's storage
        if (str_contains($frameworkDir, '/vendor/')) {
            $projectRoot = $this->findProjectRoot($frameworkDir);
            return $projectRoot . '/storage/views';
        }
        
        // If we're running standalone, use current working directory
        return getcwd() . '/storage/views';
    }

    /**
     * Find the project root directory when running as vendor dependency
     */
    protected function findProjectRoot(string $vendorPath): string
    {
        // Extract project root from vendor path
        // vendor path typically looks like: /path/to/project/vendor/lengthofrope/treehouse-framework
        $parts = explode('/vendor/', $vendorPath);
        if (count($parts) >= 2) {
            return $parts[0];
        }
        
        // Fallback to current working directory
        return getcwd();
    }
}
