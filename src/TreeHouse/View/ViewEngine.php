<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View;

use LengthOfRope\TreeHouse\Support\{Collection, Arr, Str};
use LengthOfRope\TreeHouse\Cache\CacheInterface;
use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;
use LengthOfRope\TreeHouse\View\Concerns\{ManagesComponents, ManagesLayouts, ManagesStack};
use InvalidArgumentException;
use RuntimeException;

/**
 * Template engine coordinator
 * 
 * The main entry point for the TreeHouse template system. Handles template loading,
 * compilation, caching, and rendering with deep Support class integration.
 * 
 * @package LengthOfRope\TreeHouse\View
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ViewEngine
{
    use ManagesComponents, ManagesLayouts, ManagesStack;

    /**
     * Template file extensions in order of preference
     */
    protected array $extensions = ['.th.html', '.th.php', '.php', '.html'];

    /**
     * Template search paths
     */
    protected array $paths = [];

    /**
     * Compiled template cache
     */
    protected ?CacheInterface $cache = null;

    /**
     * Template compiler instance
     */
    protected TreeHouseCompiler $compiler;

    /**
     * Global template variables
     */
    protected array $shared = [];

    /**
     * Component registry
     */
    protected array $components = [];

    /**
     * Template aliases
     */
    protected array $aliases = [];

    /**
     * Constructor
     */
    public function __construct(array $paths = [], ?CacheInterface $cache = null)
    {
        $this->cache = $cache;
        $this->compiler = new TreeHouseCompiler();
        
        // Set paths - only use getcwd() fallback if no paths provided at all
        if (!empty($paths)) {
            $this->paths = $paths;
        } else {
            // Fallback paths when no configuration is provided
            $this->paths = [
                getcwd() . '/resources/views',
                getcwd() . '/templates'
            ];
        }
        
        // Auto-inject auth context and TreeHouse assets into all templates
        $this->shareAuthContext();
        $this->shareTreeHouseAssets();
    }

    /**
     * Add a template search path
     */
    public function addPath(string $path): self
    {
        $path = rtrim($path, '/\\');
        if (!in_array($path, $this->paths)) {
            $this->paths[] = $path;
        }
        return $this;
    }

    /**
     * Set global template variables
     */
    public function share(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->shared = array_merge($this->shared, $key);
        } else {
            $this->shared[$key] = $value;
        }
        return $this;
    }

    /**
     * Register a template alias
     */
    public function alias(string $alias, string $template): self
    {
        $this->aliases[$alias] = $template;
        return $this;
    }

    /**
     * Create a template instance
     */
    public function make(string $template, array $data = []): Template
    {
        // Resolve alias
        $template = $this->aliases[$template] ?? $template;
        
        // Find template file
        $templatePath = $this->findTemplate($template);
        if ($templatePath === null) {
            $searchedPaths = [];
            $templateFile = str_replace('.', '/', $template);
            
            foreach ($this->paths as $path) {
                foreach ($this->extensions as $extension) {
                    $searchedPaths[] = $path . '/' . $templateFile . $extension;
                }
            }
            
            $pathsList = implode("\n  - ", $searchedPaths);
            throw new InvalidArgumentException(
                "Template '{$template}' not found. Searched in:\n  - {$pathsList}"
            );
        }

        // Merge data with shared variables
        $data = array_merge($this->shared, $data);

        return new Template($this, $templatePath, $data);
    }

    /**
     * Render a template
     */
    public function render(string $template, array $data = []): string
    {
        return $this->make($template, $data)->render();
    }

    /**
     * Find template file
     */
    protected function findTemplate(string $template): ?string
    {
        // Convert dot notation to path
        $template = str_replace('.', '/', $template);
        
        foreach ($this->paths as $path) {
            foreach ($this->extensions as $extension) {
                $file = $path . '/' . $template . $extension;
                if (file_exists($file)) {
                    return $file;
                }
            }
        }
        
        return null;
    }

    /**
     * Compile template
     */
    public function compile(string $templatePath): string
    {
        $cacheKey = 'view_' . md5($templatePath);
        
        // Check cache
        if ($this->cache) {
            $cacheTime = $this->cache->get($cacheKey . '_time');
            if ($cacheTime && $cacheTime >= filemtime($templatePath)) {
                $compiled = $this->cache->get($cacheKey);
                if ($compiled !== null) {
                    return $compiled;
                }
            }
        }
        
        // Load and compile template
        $template = file_get_contents($templatePath);
        if ($template === false) {
            throw new RuntimeException("Cannot read template file: {$templatePath}");
        }
        
        $compiled = $this->compiler->compile($template);
        
        // Cache compiled template
        if ($this->cache) {
            $this->cache->put($cacheKey, $compiled, 3600); // 1 hour
            $this->cache->put($cacheKey . '_time', time(), 3600);
        }
        
        return $compiled;
    }

    /**
     * Get template compiler
     */
    public function getCompiler(): TreeHouseCompiler
    {
        return $this->compiler;
    }

    /**
     * Set template compiler
     */
    public function setCompiler(TreeHouseCompiler $compiler): self
    {
        $this->compiler = $compiler;
        return $this;
    }

    /**
     * Get cache instance
     */
    public function getCache(): ?CacheInterface
    {
        return $this->cache;
    }

    /**
     * Set cache instance
     */
    public function setCache(?CacheInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Get all template paths
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Get shared variables
     */
    public function getShared(): array
    {
        return $this->shared;
    }

    /**
     * Clear compiled template cache
     */
    public function clearCache(): self
    {
        if ($this->cache) {
            // Clear all view cache keys
            $this->cache->flush();
        }
        return $this;
    }

    /**
     * Check if template exists
     */
    public function exists(string $template): bool
    {
        return $this->findTemplate($template) !== null;
    }

    /**
     * Get template file extension
     */
    public function getExtension(string $templatePath): string
    {
        foreach ($this->extensions as $extension) {
            if (Str::endsWith($templatePath, $extension)) {
                return $extension;
            }
        }
        return '.php';
    }

    /**
     * Register a view composer
     */
    public function composer(string|array $views, callable $callback): self
    {
        $views = Arr::wrap($views);
        
        foreach ($views as $view) {
            $this->shared['__composers'][$view][] = $callback;
        }
        
        return $this;
    }

    /**
     * Apply view composers
     */
    public function applyComposers(string $template, array $data): array
    {
        $composers = $this->shared['__composers'] ?? [];
        
        foreach ($composers as $pattern => $callbacks) {
            if (Str::is($pattern, $template)) {
                foreach ($callbacks as $callback) {
                    $result = $callback($data);
                    if (is_array($result)) {
                        $data = array_merge($data, $result);
                    }
                }
            }
        }
        
        return $data;
    }

    /**
     * Share authentication context with all templates
     */
    protected function shareAuthContext(): void
    {
        // Share auth manager instance
        if (function_exists('auth')) {
            $this->share('auth', auth());
            
            // Share current user (evaluated lazily)
            $this->share('user', function() {
                return auth() ? auth()->user() : null;
            });
        }

        // Share Gate instance for permission checking
        if (class_exists('\LengthOfRope\TreeHouse\Auth\Gate')) {
            $this->share('gate', new \LengthOfRope\TreeHouse\Auth\Gate());
        }

        // Share helper functions for templates
        $this->share('can', function(string $ability, mixed $arguments = []) {
            if (function_exists('can')) {
                return can($ability, $arguments);
            }
            return false;
        });

        $this->share('cannot', function(string $ability, mixed $arguments = []) {
            if (function_exists('cannot')) {
                return cannot($ability, $arguments);
            }
            return true;
        });
    }

    /**
     * Refresh auth context (call this when user state changes)
     */
    public function refreshAuthContext(): void
    {
        $this->shareAuthContext();
    }

    /**
     * Share TreeHouse framework assets with all templates
     */
    protected function shareTreeHouseAssets(): void
    {
        // Load view helpers if not already loaded
        if (!function_exists('treehouseJs')) {
            require_once __DIR__ . '/helpers.php';
        }

        // TreeHouse JavaScript is now bundled with Vite, so we only need configuration
        // Auto-inject TreeHouse configuration (return actual HTML content)
        $this->share('__treehouse_config', treehouseConfig('/_assets/treehouse'));

        // Auto-inject Vite assets (return actual HTML content)
        $this->share('__vite_assets', viteAssets('resources/js/app.js'));

        // Auto-inject complete TreeHouse setup helper
        $this->share('__treehouse_setup', function() {
            return treehouseSetup([
                'modules' => ['csrf'],
                'css' => false,
                'config' => [
                    'csrf' => [
                        'endpoint' => '/_csrf/token',
                        'field' => '_token'
                    ]
                ]
            ]);
        });

        // Make individual helper functions available to templates
        $this->share('treehouseJs', function(array $modules = ['csrf'], ?bool $minified = null) {
            return treehouseJs($modules, $minified);
        });

        $this->share('treehouseConfig', function(?string $baseUrl = null, ?bool $debug = null) {
            return treehouseConfig($baseUrl, $debug);
        });

        $this->share('treehouseSetup', function(array $options = []) {
            return treehouseSetup($options);
        });

        $this->share('treehouseAsset', function(string $path) {
            return treehouseAsset($path);
        });

        $this->share('jsModule', function(string $module) {
            return jsModule($module);
        });

        // Make Vite helper functions available to templates
        $this->share('viteAssets', function(string $entry = 'resources/js/app.js') {
            return viteAssets($entry);
        });

        $this->share('vite', function(string $path) {
            return vite($path);
        });
    }
}
