<?php

declare(strict_types=1);

/**
 * View helper functions
 * 
 * Global helper functions for the TreeHouse View system.
 * 
 * @package LengthOfRope\TreeHouse\View
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */

use LengthOfRope\TreeHouse\View\{ViewEngine, ViewFactory, Template};
use LengthOfRope\TreeHouse\Support\Collection;

if (!function_exists('view')) {
    /**
     * Create a view instance
     */
    function view(?string $template = null, array $data = []): ViewEngine|Template
    {
        static $factory = null;
        
        if ($factory === null) {
            // Load support helpers if needed
            if (!function_exists('env')) {
                require_once __DIR__ . '/../Support/helpers.php';
            }
            
            // Find application root directory
            $appRoot = getcwd();
            
            // If we're in a vendor package, find the real app root
            if (strpos(__DIR__, 'vendor/lengthofrope/treehouse') !== false) {
                // We're installed as a vendor package
                $vendorPos = strpos(__DIR__, 'vendor/lengthofrope/treehouse');
                $appRoot = substr(__DIR__, 0, $vendorPos);
            } elseif (strpos(__DIR__, 'vendor') !== false && strpos(__DIR__, 'lengthofrope') !== false) {
                // Alternative vendor structure
                $vendorPos = strpos(__DIR__, 'vendor');
                $appRoot = substr(__DIR__, 0, $vendorPos);
            }
            
            // Load configuration from config file in application root
            $configFile = rtrim($appRoot, '/') . '/config/view.php';
            $config = [];
            
            if (file_exists($configFile)) {
                try {
                    $config = require $configFile;
                } catch (Throwable $e) {
                    // Fall back to default if config fails to load
                    $config = [];
                }
            }
            
            // Set default configuration if not loaded
            if (empty($config)) {
                $config = [
                    'paths' => [
                        $appRoot . '/resources/views',
                        $appRoot . '/templates',
                    ],
                    'cache_path' => $appRoot . '/storage/views',
                    'cache_enabled' => true,
                ];
            }
            
            $factory = new ViewFactory($config);
        }
        
        if ($template === null) {
            return $factory->engine();
        }
        
        return $factory->make($template, $data);
    }
}

if (!function_exists('render')) {
    /**
     * Render a template
     */
    function render(string $template, array $data = []): string
    {
        return view($template, $data)->render();
    }
}

if (!function_exists('partial')) {
    /**
     * Render a partial template
     */
    function partial(string $template, array $data = []): string
    {
        return view('partials.' . $template, $data)->render();
    }
}

if (!function_exists('component')) {
    /**
     * Render a component
     */
    function component(string $name, array $props = []): string
    {
        return view()->renderComponent($name, $props);
    }
}

if (!function_exists('layout')) {
    /**
     * Create a template with layout
     */
    function layout(string $layout, string $template, array $data = []): Template
    {
        return view($template, $data)->with('__layout', $layout);
    }
}

if (!function_exists('thEscape')) {
    /**
     * Escape HTML (template helper)
     */
    function thEscape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('thRaw')) {
    /**
     * Output raw HTML (template helper)
     */
    function thRaw(mixed $value): string
    {
        return (string) $value;
    }
}

if (!function_exists('thCollect')) {
    /**
     * Convert array to Collection (template helper)
     */
    function thCollect(array $items): Collection
    {
        return new Collection($items);
    }
}

if (!function_exists('thRepeatStatus')) {
    /**
     * Get repeat loop status (template helper)
     */
    function thRepeatStatus(int $index, int $count): object
    {
        return (object) [
            'index' => $index,
            'count' => $count,
            'first' => $index === 0,
            'last' => $index === $count - 1,
            'odd' => $index % 2 === 1,
            'even' => $index % 2 === 0,
        ];
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL
     */
    function url(string $path = ''): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('route')) {
    /**
     * Generate route URL
     */
    function route(string $name, array $parameters = []): string
    {
        // This would typically integrate with Router
        return url($name);
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value
     */
    function old(string $key, mixed $default = null): mixed
    {
        // This would typically get from session flash data
        return $default;
    }
}

if (!function_exists('csrfToken')) {
    /**
     * Get CSRF token
     */
    function csrfToken(): string
    {
        // This would typically get from CSRF service
        return 'csrf_token_placeholder';
    }
}

if (!function_exists('csrfField')) {
    /**
     * Generate CSRF field HTML
     */
    function csrfField(): string
    {
        // Generate a random token for testing purposes
        // In a real application, this would integrate with the CSRF protection system
        $token = bin2hex(random_bytes(32));
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('methodField')) {
    /**
     * Generate HTTP method field for HTTP method spoofing
     *
     * @param string $method HTTP method (PUT, PATCH, DELETE, OPTIONS)
     * @return string HTML input field
     */
    function methodField(string $method): string
    {
        $method = strtoupper(trim($method));
        
        // Validate method
        $allowedMethods = ['PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        if (!in_array($method, $allowedMethods)) {
            throw new \InvalidArgumentException("Invalid method '{$method}'. Allowed methods: " . implode(', ', $allowedMethods));
        }
        
        return '<input type="hidden" name="_method" value="' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    if (!function_exists('formMethod')) {
        /**
         * Generate method and CSRF fields for forms
         *
         * @param string $method HTTP method (PUT, PATCH, DELETE, OPTIONS)
         * @param bool $includeCsrf Whether to include CSRF token
         * @return string HTML input fields
         */
        function formMethod(string $method, bool $includeCsrf = true): string
        {
            $html = methodField($method);
            
            if ($includeCsrf) {
                $html .= "\n" . csrfField();
            }
            
            return $html;
        }
    }
}
