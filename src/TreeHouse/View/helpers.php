<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Support\Arr;

if (!function_exists('thEscape')) {
    /**
     * Escape HTML entities
     */
    function thEscape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
    }
}

if (!function_exists('thRaw')) {
    /**
     * Output raw content (unescaped)
     */
    function thRaw($value): string
    {
        return (string) $value;
    }
}

if (!function_exists('thCollect')) {
    /**
     * Create a collection from array
     */
    function thCollect($items = []): \LengthOfRope\TreeHouse\Support\Collection
    {
        return new \LengthOfRope\TreeHouse\Support\Collection($items);
    }
}

if (!function_exists('treehouseAsset')) {
    /**
     * Generate URL for TreeHouse framework asset
     * 
     * @param string $path Asset path
     * @return string Asset URL
     */
    function treehouseAsset(string $path): string
    {
        return "/_assets/treehouse/{$path}";
    }
}

if (!function_exists('treehouseConfig')) {
    /**
     * Generate TreeHouse configuration script
     * 
     * @param string|null $baseUrl Base URL for assets
     * @param bool|null $debug Debug mode
     * @return string Configuration script
     */
    function treehouseConfig(?string $baseUrl = null, ?bool $debug = null): string
    {
        $baseUrl = $baseUrl ?? '/_assets/treehouse';
        $debug = $debug ?? (getenv('APP_ENV') !== 'production');
        
        $config = [
            'csrf' => [
                'endpoint' => '/_csrf/token',
                'field' => '_token'
            ],
            'baseUrl' => $baseUrl,
            'debug' => $debug
        ];
        
        $jsonConfig = json_encode($config, JSON_UNESCAPED_SLASHES);
        
        return "<script>if(window.TreeHouse){window.TreeHouse.configure({$jsonConfig});}</script>";
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

if (!function_exists('isViteDevServerRunning')) {
    /**
     * Check if Vite dev server is actually running
     */
    function isViteDevServerRunning(): bool
    {
        // Try to connect to the Vite dev server
        $connection = @fsockopen('localhost', 5173, $errno, $errstr, 1);
        if ($connection) {
            fclose($connection);
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
        $manifestPath = getcwd() . '/build/.vite/manifest.json';

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
