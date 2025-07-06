<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Routing\Router;
/**
 * Web Routes
 *
 * Define your web routes here. The $router variable is available
 * and routes defined here will be automatically loaded by the application.
 */
// Home page
$router->get('/', 'App\Controllers\HomeController@index')->middleware('throttle:5,30');

// Demo pages showcasing TreeHouse features
$router->get('/templating', 'App\Controllers\DemoController@templating');
$router->get('/components', 'App\Controllers\DemoController@components');
$router->get('/layouts', 'App\Controllers\DemoController@layouts');
$router->get('/layouts/minimal-example', 'App\Controllers\DemoController@minimalLayoutExample');
$router->get('/test-fragment', 'App\Controllers\DemoController@testFragment');
$router->get('/cli', 'App\Controllers\DemoController@cli');

// About page
$router->get('/about', function() {
    return new Response(view('about', [
        'title' => 'About TreeHouse Framework',
        'description' => nl2br(file_get_contents(__DIR__ . '/../../README.md')),
    ])->render());
});

// Example form handling
$router->post('/contact', function() {
    // In a real application, you would validate and process the form data
    return new Response('Form save', 200, []);
});

$router->post('/settings', function() {
    // In a real application, you would save the settings
    return new Response('Settings save', 200, []);
});

// Rate limiting test routes
$router->get('/api/test', function() {
    return new Response(json_encode([
        'message' => 'API test successful',
        'timestamp' => time(),
        'data' => ['test' => true]
    ]), 200, ['Content-Type' => 'application/json']);
})->middleware('throttle:5,60'); // 5 requests per 60 seconds

$router->get('/api/heavy', function() {
    return new Response(json_encode([
        'message' => 'Heavy operation completed',
        'timestamp' => time(),
        'data' => ['heavy' => true]
    ]), 200, ['Content-Type' => 'application/json']);
})->middleware('throttle:2,60'); // 2 requests per 60 seconds