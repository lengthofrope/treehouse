<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View;

use LengthOfRope\TreeHouse\Support\{Collection, Arr, Str, Carbon, Uuid};
use LengthOfRope\TreeHouse\Http\Response;
use RuntimeException;

/**
 * Individual template handler
 * 
 * Represents a single template with its data and provides methods for rendering.
 * Includes helper methods for common template operations and Support class integration.
 * 
 * @package LengthOfRope\TreeHouse\View
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Template
{
    /**
     * The view engine instance
     */
    protected ViewEngine $engine;

    /**
     * Template file path
     */
    protected string $path;

    /**
     * Template data
     */
    protected array $data;

    /**
     * Template sections
     */
    protected array $sections = [];

    /**
     * Current section being captured
     */
    protected ?string $currentSection = null;

    /**
     * Rendering depth to prevent infinite recursion
     */
    protected static int $renderingDepth = 0;

    /**
     * Maximum rendering depth
     */
    protected static int $maxRenderingDepth = 10;

    /**
     * Constructor
     */
    public function __construct(ViewEngine $engine, string $path, array $data = [])
    {
        $this->engine = $engine;
        $this->path = $path;
        $this->data = $data;
    }

    /**
     * Render the template
     */
    public function render(): string
    {
        // Prevent infinite recursion
        self::$renderingDepth++;
        
        if (self::$renderingDepth > self::$maxRenderingDepth) {
            self::$renderingDepth--;
            throw new RuntimeException("Maximum rendering depth exceeded (" . self::$maxRenderingDepth . "). Possible infinite recursion in template: {$this->path}");
        }

        try {
            // Apply view composers
            $this->data = $this->engine->applyComposers($this->getTemplateName(), $this->data);

            try {
                // Start output buffering
                ob_start();
                
                // Extract data to make variables available in template
                extract($this->data, EXTR_SKIP);
                
                // Make template helpers available
                $th = $this->getTemplateHelpers();
                
                // Check if template needs compilation
                $extension = $this->engine->getExtension($this->path);
                if (in_array($extension, ['.th.html', '.th.php'])) {
                    // Compile and evaluate template
                    $compiled = $this->engine->compile($this->path);
                    eval('?>' . $compiled);
                } else {
                    // Include raw PHP template
                    include $this->path;
                }
                
                $output = ob_get_clean();
                
                // If we have sections, this might be a child template
                if (!empty($this->sections)) {
                    $result = $this->renderWithLayout($output);
                    self::$renderingDepth--;
                    return $result;
                }
                
                self::$renderingDepth--;
                return $output;
                
            } catch (\Throwable $e) {
                ob_end_clean();
                throw new RuntimeException("Error rendering template '{$this->path}': " . $e->getMessage(), 0, $e);
            }
        } catch (\Throwable $e) {
            self::$renderingDepth--;
            throw $e;
        }
    }

    /**
     * Render template with layout
     */
    protected function renderWithLayout(string $content): string
    {
        // If no layout is specified, return content as-is
        if (!isset($this->sections['__layout'])) {
            return $content;
        }

        // Create layout template
        $layout = $this->engine->make($this->sections['__layout'], $this->data);
        
        // Pass sections to layout, but exclude the __layout section to prevent recursion
        $sectionsForLayout = $this->sections;
        unset($sectionsForLayout['__layout']); // Remove __layout to prevent infinite recursion
        $layout->setSections($sectionsForLayout);
        
        return $layout->render();
    }

    /**
     * Set template sections
     */
    public function setSections(array $sections): self
    {
        $this->sections = $sections;
        return $this;
    }

    /**
     * Start capturing a section
     */
    public function startSection(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End capturing a section
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException('No section started');
        }

        $content = ob_get_clean();
        $this->sections[$this->currentSection] = $content;
        $this->currentSection = null;
    }

    /**
     * Yield a section
     */
    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Extend a layout
     */
    public function extend(string $layout): void
    {
        $this->sections['__layout'] = $layout;
    }

    /**
     * Include a partial template
     */
    public function include(string $template, array $data = []): string
    {
        $data = array_merge($this->data, $data);
        return $this->engine->render($template, $data);
    }

    /**
     * Render a component
     */
    public function component(string $name, array $props = []): string
    {
        return $this->engine->renderComponent($name, $props);
    }

    /**
     * Get template name from path
     */
    public function getTemplateName(): string
    {
        $paths = $this->engine->getPaths();
        
        foreach ($paths as $path) {
            if (str_starts_with($this->path, $path)) {
                $relative = substr($this->path, strlen($path) + 1);
                // Remove extension
                $name = preg_replace('/\.(th\.html|th\.php|php|html)$/', '', $relative);
                return str_replace('/', '.', $name);
            }
        }
        
        return basename($this->path);
    }

    /**
     * Get template helpers
     */
    protected function getTemplateHelpers(): object
    {
        return new class {
            /**
             * Escape HTML
             */
            public function e(mixed $value): string
            {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }

            /**
             * Convert to collection
             */
            public function collect(array $items): Collection
            {
                return new Collection($items);
            }

            /**
             * Format money
             */
            public function money(float $amount, string $currency = '€'): string
            {
                return $currency . number_format($amount, 2, ',', '.');
            }

            /**
             * Format number
             */
            public function number(float $number, int $decimals = 0): string
            {
                return number_format($number, $decimals, ',', '.');
            }

            /**
             * Format date
             */
            public function date(mixed $date, string $format = 'j F Y'): string
            {
                if (is_string($date)) {
                    $date = Carbon::parse($date);
                }
                return $date instanceof Carbon ? $date->format($format) : '';
            }

            /**
             * Get array value with dot notation
             */
            public function get(array $array, string $key, mixed $default = null): mixed
            {
                return Arr::get($array, $key, $default);
            }

            /**
             * Check if value is empty
             */
            public function isEmpty(mixed $value): bool
            {
                if ($value instanceof Collection) {
                    return $value->isEmpty();
                }
                return empty($value);
            }

            /**
             * Get old input value
             */
            public function old(string $key, mixed $default = null): mixed
            {
                // This would typically get old input from session
                return $default;
            }

            /**
             * Generate URL
             */
            public function url(string $path = ''): string
            {
                return '/' . ltrim($path, '/');
            }

            /**
             * Generate asset URL
             */
            public function asset(string $path): string
            {
                return '/assets/' . ltrim($path, '/');
            }

            /**
             * Generate route URL
             */
            public function route(string $name, array $parameters = []): string
            {
                // This would typically generate route URLs
                return $this->url($name);
            }

            /**
             * Check if current route matches
             */
            public function routeIs(string $pattern): bool
            {
                // This would typically check current route
                return false;
            }

            /**
             * Get CSRF token
             */
            public function csrfToken(): string
            {
                // This would typically get CSRF token from session
                return 'csrf_token_placeholder';
            }

            /**
             * Render CSRF field
             */
            public function csrfField(): string
            {
                return '<input type="hidden" name="_token" value="' . $this->csrfToken() . '">';
            }

            /**
             * Limit string length
             */
            public function limit(string $value, int $limit = 100, string $end = '...'): string
            {
                return Str::limit($value, $limit, $end);
            }

            /**
             * Convert string to title case
             */
            public function title(string $value): string
            {
                return Str::title($value);
            }
        };
    }

    /**
     * Convert to HTTP response
     */
    public function toResponse(): Response
    {
        return new Response($this->render());
    }

    /**
     * Get template data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set template data
     */
    public function with(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
        return $this;
    }

    /**
     * Get template path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get view engine
     */
    public function getEngine(): ViewEngine
    {
        return $this->engine;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
