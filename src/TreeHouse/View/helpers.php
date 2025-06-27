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
            $factory = new ViewFactory();
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
        return '<input type="hidden" name="_token" value="' . csrfToken() . '">';
    }
}

if (!function_exists('methodField')) {
    /**
     * Generate HTTP method field
     */
    function methodField(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}
