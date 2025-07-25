<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Support\Arr;
use LengthOfRope\TreeHouse\Support\Collection;
use LengthOfRope\TreeHouse\View\{ViewEngine, ViewFactory, Template};
use LengthOfRope\TreeHouse\Security\Csrf;
use LengthOfRope\TreeHouse\Http\Session;

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
            $appRoot = dirname(getcwd());
            
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

if (!function_exists('thGetProperty')) {
    /**
     * Enhanced property access helper for objects and arrays with caching
     */
    function thGetProperty(mixed $target, string $property, bool $useCache = true): mixed
    {
        // Use cached property access information for performance
        static $cache = [];
        
        if ($target === null) {
            return null;
        }
        
        // Generate cache key based on object class and property
        $cacheKey = null;
        if ($useCache && is_object($target)) {
            $cacheKey = get_class($target) . '::' . $property;
            if (isset($cache[$cacheKey])) {
                return $cache[$cacheKey]['accessor']($target);
            }
        }
        
        $accessor = thResolvePropertyAccess($target, $property);
        
        // Cache the accessor for future use
        if ($useCache && $cacheKey && $accessor) {
            $cache[$cacheKey] = ['accessor' => $accessor];
        }
        
        return $accessor ? $accessor($target) : null;
    }
}

if (!function_exists('thResolvePropertyAccess')) {
    /**
     * Resolve property access method for different data types
     */
    function thResolvePropertyAccess(mixed $target, string $property): ?callable
    {
        if (is_object($target)) {
            return thResolveObjectAccess($target, $property);
        }
        
        if (is_array($target)) {
            return thResolveArrayAccess($target, $property);
        }
        
        return null;
    }
}

if (!function_exists('thResolveObjectAccess')) {
    /**
     * Resolve object property access with multiple strategies
     */
    function thResolveObjectAccess(object $target, string $property): ?callable
    {
        // Check property access patterns in order of preference
        $accessors = [
            // 1. Direct property access
            fn($obj) => property_exists($obj, $property) ? $obj->$property : null,
            
            // 2. Direct method call (user.getName → $user->getName())
            fn($obj) => method_exists($obj, $property) ? $obj->$property() : null,
            
            // 3. Getter method (user.name → $user->getName())
            fn($obj) => method_exists($obj, 'get' . ucfirst($property)) ? $obj->{'get' . ucfirst($property)}() : null,
            
            // 4. Is/Has boolean methods (user.active → $user->isActive())
            fn($obj) => method_exists($obj, 'is' . ucfirst($property)) ? $obj->{'is' . ucfirst($property)}() : null,
            fn($obj) => method_exists($obj, 'has' . ucfirst($property)) ? $obj->{'has' . ucfirst($property)}() : null,
            
            // 5. Snake_case to camelCase conversion (user.first_name → $user->getFirstName())
            fn($obj) => method_exists($obj, 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $property)))) 
                        ? $obj->{'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $property)))}() : null,
        ];
        
        foreach ($accessors as $accessor) {
            try {
                $result = $accessor($target);
                if ($result !== null) {
                    return $accessor;
                }
            } catch (Throwable $e) {
                // Continue to next accessor
                continue;
            }
        }
        
        return null;
    }
}

if (!function_exists('thResolveArrayAccess')) {
    /**
     * Resolve array property access
     */
    function thResolveArrayAccess(array $target, string $property): ?callable
    {
        return fn($arr) => $arr[$property] ?? null;
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
        static $csrf = null;
        
        if ($csrf === null) {
            try {
                $session = new Session();
                $csrf = new Csrf($session);
            } catch (Exception $e) {
                // Fallback for CLI or when session cannot be started
                return bin2hex(random_bytes(32));
            }
        }
        
        try {
            return $csrf->getToken();
        } catch (Exception $e) {
            // Fallback for CLI or when session cannot be started
            return bin2hex(random_bytes(32));
        }
    }
}

if (!function_exists('csrfField')) {
    /**
     * Generate CSRF field HTML using the Security\Csrf class
     *
     * @param bool $dynamic Whether to use dynamic JavaScript injection (cache-friendly)
     * @return string HTML input field with CSRF token or placeholder
     */
    function csrfField(bool $dynamic = false): string
    {
        if ($dynamic) {
            // Return placeholder that will be populated by JavaScript
            return '<input type="hidden" name="_token" value="" data-csrf-placeholder="true">';
        }
        
        static $csrf = null;
        
        if ($csrf === null) {
            try {
                $session = new Session();
                $csrf = new Csrf($session);
            } catch (Exception $e) {
                // Fallback for CLI or when session cannot be started
                $token = bin2hex(random_bytes(32));
                return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
            }
        }
        
        try {
            return $csrf->getTokenField('_token');
        } catch (Exception $e) {
            // Fallback for CLI or when session cannot be started
            $token = bin2hex(random_bytes(32));
            return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        }
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
}

if (!function_exists('formMethod')) {
    /**
     * Generate method and CSRF fields for forms
     *
     * @param string $method HTTP method (PUT, PATCH, DELETE, OPTIONS)
     * @param bool $includeCsrf Whether to include CSRF token
     * @param bool $dynamic Whether to use dynamic CSRF injection
     * @return string HTML input fields
     */
    function formMethod(string $method, bool $includeCsrf = true, bool $dynamic = false): string
    {
        $html = methodField($method);
        
        if ($includeCsrf) {
            $html .= "\n" . csrfField($dynamic);
        }
        
        return $html;
    }
}

if (!function_exists('csrfScript')) {
    /**
     * Generate script tag for CSRF JavaScript helper
     *
     * @param string|null $src Custom source path for the CSRF script
     * @return string HTML script tag
     */
    function csrfScript(?string $src = null): string
    {
        $src = $src ?? asset('js/csrf.js');
        return '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" defer></script>';
    }
}

if (!function_exists('csrfMeta')) {
    /**
     * Generate meta tag for CSRF token (for AJAX requests)
     *
     * @param bool $dynamic Whether to use dynamic token injection
     * @return string HTML meta tag
     */
    function csrfMeta(bool $dynamic = false): string
    {
        if ($dynamic) {
            // Return placeholder that will be populated by JavaScript
            return '<meta name="csrf-token" content="" data-csrf-meta="true">';
        }
        
        $token = csrfToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrfSetup')) {
    /**
     * Generate complete CSRF setup for cache-friendly pages
     *
     * @param array $options Configuration options
     * @return string HTML for CSRF setup
     */
    function csrfSetup(array $options = []): string
    {
        $includeMeta = $options['meta'] ?? true;
        $includeScript = $options['script'] ?? true;
        $scriptSrc = $options['script_src'] ?? null;
        
        $html = '';
        
        if ($includeMeta) {
            $html .= csrfMeta(true) . "\n";
        }
        
        if ($includeScript) {
            $html .= csrfScript($scriptSrc);
        }
        
        return $html;
    }
}

if (!function_exists('treehouseAsset')) {
    /**
     * Generate URL for TreeHouse framework assets
     *
     * @param string $path Asset path relative to TreeHouse assets directory
     * @return string Asset URL
     */
    function treehouseAsset(string $path): string
    {
        // Check if override exists in application public directory
        $publicPath = getcwd() . "/public/assets/treehouse/{$path}";
        if (file_exists($publicPath)) {
            return asset("assets/treehouse/{$path}");
        }
        
        // Use vendor asset route
        return url("_assets/treehouse/{$path}");
    }
}

if (!function_exists('treehouseConfig')) {
    /**
     * Generate configuration script for TreeHouse JavaScript library
     *
     * @param array|string|null $config Configuration options array, or legacy baseUrl string
     * @param bool|null $debug Debug mode (legacy parameter)
     * @return string HTML script tag with configuration
     */
    function treehouseConfig(array|string|null $config = [], ?bool $debug = null): string
    {
        // Handle legacy function signature: treehouseConfig($baseUrl, $debug)
        if (is_string($config) || $config === null) {
            $baseUrl = $config ?? '/_assets/treehouse';
            $debug = $debug ?? (function_exists('env') ? env('APP_DEBUG', false) : false);
            
            $finalConfig = [
                'csrf' => [
                    'endpoint' => '/_csrf/token',
                    'field' => '_token'
                ],
                'baseUrl' => $baseUrl,
                'debug' => $debug
            ];
        } else {
            // New array-based configuration
            $defaultConfig = [
                'csrf' => [
                    'endpoint' => '/_csrf/token',
                    'field' => '_token'
                ],
                'baseUrl' => url('_assets/treehouse'),
                'debug' => function_exists('env') ? env('APP_DEBUG', false) : false
            ];
            
            // Merge with provided configuration
            $finalConfig = array_merge_recursive($defaultConfig, $config);
        }
        
        // Convert to JSON
        $configJson = json_encode($finalConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        
        return '<script>if(window.TreeHouse){window.TreeHouse.configure(' . $configJson . ');}</script>';
    }
}

if (!function_exists('treehouseJs')) {
    /**
     * Generate TreeHouse JavaScript includes
     * 
     * @param array $modules Modules to load
     * @param bool|null $minified Use minified version
     * @return string Script tags
     */
    function treehouseJs(array $modules = ['csrf'], ?bool $minified = null): string
    {
        $minified = $minified ?? (getenv('APP_ENV') === 'production');
        $suffix = $minified ? '.min' : '';
        
        $scripts = [];
        
        // Core TreeHouse library (no defer to ensure it loads first)
        $scripts[] = '<script src="' . htmlspecialchars(treehouseAsset("js/treehouse{$suffix}.js"), ENT_QUOTES, 'UTF-8') . '"></script>';
        
        // Load requested modules (with defer to load after core)
        foreach ($modules as $module) {
            $modulePath = "js/modules/{$module}.js";
            $scripts[] = '<script src="' . htmlspecialchars(treehouseAsset($modulePath), ENT_QUOTES, 'UTF-8') . '" defer></script>';
        }
        
        return implode("\n", $scripts);
    }
}

if (!function_exists('treehouseLogo')) {
    /**
     * Get TreeHouse logo with application override support
     * 
     * @param string $alt Alt text for the logo
     * @param array $attributes Additional HTML attributes
     * @return string Logo HTML tag
     */
    function treehouseLogo(string $alt = 'TreeHouse Framework', array $attributes = []): string
    {
        // Check for application override first
        $publicLogoPath = 'assets/treehouse/img/treehouse-logo.svg';
        $logoUrl = '/_assets/treehouse/img/treehouse-logo.svg';
        
        // Build attributes string
        $attributeString = '';
        foreach ($attributes as $key => $value) {
            $attributeString .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }
        
        return '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' . $attributeString . '>';
    }
}

if (!function_exists('treehouseFavicons')) {
    /**
     * Get TreeHouse favicon links with application override support
     * 
     * @return string Complete favicon HTML tags
     */
    function treehouseFavicons(): string
    {
        $baseUrl = '/_assets/treehouse/img/favicon';
        
        $favicons = [
            '<link rel="icon" type="image/x-icon" href="' . $baseUrl . '/favicon.ico">',
            '<link rel="icon" type="image/svg+xml" href="' . $baseUrl . '/favicon.svg">',
            '<link rel="apple-touch-icon" sizes="180x180" href="' . $baseUrl . '/apple-touch-icon.png">',
            '<link rel="icon" type="image/png" sizes="96x96" href="' . $baseUrl . '/favicon-96x96.png">',
            '<link rel="manifest" href="' . $baseUrl . '/site.webmanifest">'
        ];
        
        return implode("\n", $favicons);
    }
}

if (!function_exists('treehouseManifest')) {
    /**
     * Get TreeHouse web app manifest link with application override support
     * 
     * @return string Web app manifest link tag
     */
    function treehouseManifest(): string
    {
        $manifestUrl = '/_assets/treehouse/img/favicon/site.webmanifest';
        return '<link rel="manifest" href="' . htmlspecialchars($manifestUrl, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('treehouseBranding')) {
    /**
     * Get complete TreeHouse branding package (logo + favicons)
     * 
     * @param string $logoAlt Alt text for the logo
     * @param array $logoAttributes Additional logo attributes
     * @return array Array with 'logo' and 'favicons' keys
     */
    function treehouseBranding(string $logoAlt = 'TreeHouse Framework', array $logoAttributes = []): array
    {
        return [
            'logo' => treehouseLogo($logoAlt, $logoAttributes),
            'favicons' => treehouseFavicons(),
            'manifest' => treehouseManifest()
        ];
    }
}

if (!function_exists('treehouseCss')) {
    /**
     * Generate CSS link tag for TreeHouse framework styles
     *
     * @param bool|null $minified Whether to use minified version
     * @return string HTML link tag
     */
    function treehouseCss(?bool $minified = null): string
    {
        if ($minified === null) {
            $minified = (function_exists('env') && env('APP_ENV') === 'production') ||
                       (!function_exists('env') && !ini_get('display_errors'));
        }
        
        $suffix = $minified ? '.min' : '';
        return '<link rel="stylesheet" href="' . htmlspecialchars(treehouseAsset("css/treehouse{$suffix}.css"), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('jsModule')) {
    /**
     * Generate script tag for a specific TreeHouse module
     *
     * @param string $module Module name
     * @return string HTML script tag
     */
    function jsModule(string $module): string
    {
        $modulePath = "js/modules/{$module}.js";
        return '<script src="' . htmlspecialchars(treehouseAsset($modulePath), ENT_QUOTES, 'UTF-8') . '" defer></script>';
    }
}

if (!function_exists('treehouseSetup')) {
    /**
     * Generate complete TreeHouse framework setup
     *
     * @param array $options Setup options
     * @return string HTML for complete TreeHouse setup
     */
    function treehouseSetup(array $options = []): string
    {
        $modules = $options['modules'] ?? ['csrf'];
        $includeCss = $options['css'] ?? false;
        $config = $options['config'] ?? [];
        $minified = $options['minified'] ?? null;
        
        $html = '';
        
        // CSS if requested
        if ($includeCss) {
            $html .= treehouseCss($minified) . "\n";
        }
        
        // CSRF meta tag for dynamic injection
        if (in_array('csrf', $modules)) {
            $html .= csrfMeta(true) . "\n";
        }
        
        // Configuration
        $html .= treehouseConfig($config) . "\n";
        
        // JavaScript files
        $html .= treehouseJs($modules, $minified);
        
        return $html;
    }
}

if (!function_exists('isViteDevServerRunning')) {
    /**
     * Check if Vite dev server is running using multiple methods
     */
    function isViteDevServerRunning(): bool
    {
        // Check if Vite dev server is running by checking the environment variable
        if (file_exists(dirname(getcwd()) . '/VITE_RUNNING')) {
            return true;
        }
        return false;
    }
}

if (!function_exists('vite')) {
    /**
     * Generate Vite asset URLs
     * 
     * @param string $path Asset path
     * @return string Asset URL
     */
    function vite(string $path): string
    {
        static $manifest = null;
        static $isDev = null;
        
        if ($isDev === null) {
            // Check if we're in development mode AND Vite dev server is running
            $isDev = getenv('APP_ENV') !== 'production' && isViteDevServerRunning();
        }
        
        if ($isDev) {
            // Development mode - return Vite dev server URL
            return "http://localhost:5173/{$path}";
        }
        
        // Production mode - use manifest
        if ($manifest === null) {
            $manifestPath = getcwd() . '/public/build/.vite/manifest.json';
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
            } else {
                $manifest = [];
            }
        }
        
        if (isset($manifest[$path])) {
            return "/build/" . $manifest[$path]['file'];
        }
        
        // Fallback
        return "/build/{$path}";
    }
}

if (!function_exists('viteAssets')) {
    /**
     * Generate Vite script and CSS tags
     * 
     * @param string $entry Entry point (default: resources/js/app.js)
     * @return string HTML tags
     */
    function viteAssets(string $entry = 'resources/js/app.js'): string
    {
        // Check if we're in development mode AND Vite dev server is actually running
        $isDev = getenv('APP_ENV') !== 'production' && isViteDevServerRunning();
        
        if ($isDev) {
            // Development mode - return Vite dev server assets
            return 
                '<script type="module" src="http://localhost:5173/@vite/client"></script>' . "\n" .
                '<script type="module" src="http://localhost:5173/' . htmlspecialchars($entry, ENT_QUOTES, 'UTF-8') . '"></script>';
        }
        
        // Production mode - use manifest to generate asset tags
        $manifestPath = dirname(getcwd()) . '/public/build/.vite/manifest.json';

        if (!file_exists($manifestPath)) {
            // If no manifest exists, return empty (no assets)
            return '<!-- Vite assets: No manifest found, run "npm run build" -->';
        }
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!$manifest || !isset($manifest[$entry])) {
            // If manifest is invalid or entry not found
            return '<!-- Vite assets: Entry not found in manifest -->';
        }
        
        $entryData = $manifest[$entry];
        $tags = [];
        
        // Add CSS files first
        if (isset($entryData['css'])) {
            foreach ($entryData['css'] as $css) {
                $tags[] = '<link rel="stylesheet" href="/build/' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . '">';
            }
        }
        
        // Add JS file
        if (isset($entryData['file'])) {
            $tags[] = '<script type="module" src="/build/' . htmlspecialchars($entryData['file'], ENT_QUOTES, 'UTF-8') . '"></script>';
        }
        
        return implode("\n", $tags);
    }
}
