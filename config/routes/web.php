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
$router->get('/', 'App\Controllers\HomeController@index');

// Demo pages showcasing TreeHouse features
$router->get('/templating', 'App\Controllers\DemoController@templating');
$router->get('/components', 'App\Controllers\DemoController@components');
$router->get('/layouts', 'App\Controllers\DemoController@layouts');
$router->get('/test-fragment', 'App\Controllers\DemoController@testFragment');

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