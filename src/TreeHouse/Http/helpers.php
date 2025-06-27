<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Http\Session;

if (!function_exists('session')) {
    /**
     * Get the session instance
     * 
     * @return Session
     */
    function session(): Session
    {
        // Get the global application instance
        global $app;
        
        if (!$app) {
            throw new RuntimeException('Application instance not available. Make sure to set global $app variable.');
        }
        
        return $app->make('session');
    }
}