<?php

/**
 * API Routes
 * 
 * Define your API routes here. The $router variable is available
 * and routes defined here will be automatically loaded by the application.
 */

use LengthOfRope\TreeHouse\Http\Response;

// API routes with /api prefix
// Example API endpoints
$router->group(['prefix' => 'api'], function($router) {
    
    $router->get('/status', function () {
        return new Response(json_encode(['status' => 'ok', 'timestamp' => time()]), 200, [
            'Content-Type' => 'application/json'
        ]);
    });

    $router->get('/users', function () {
        return new Response(json_encode([
            'users' => [
                ['id' => 1, 'name' => 'John Doe'],
                ['id' => 2, 'name' => 'Jane Smith']
            ]
        ]), 200, ['Content-Type' => 'application/json']);
    });

    $router->post('/users', function () {
        return new Response(json_encode(['message' => 'User created']), 201, [
            'Content-Type' => 'application/json'
        ]);
    });
    
});
