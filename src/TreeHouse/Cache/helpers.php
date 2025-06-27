<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\Cache\CacheInterface;

if (!function_exists('cache')) {
    /**
     * Get the cache manager instance or a specific driver
     *
     * @param string|null $driver Driver name
     * @return CacheManager|CacheInterface
     */
    function cache(?string $driver = null): CacheManager|CacheInterface
    {
        // Get the global application instance
        global $app;
        
        if (!$app) {
            throw new RuntimeException('Application instance not available. Make sure to set global $app variable.');
        }
        
        $cacheManager = $app->make('cache');
        
        if ($driver !== null) {
            return $cacheManager->driver($driver);
        }
        
        return $cacheManager;
    }
}