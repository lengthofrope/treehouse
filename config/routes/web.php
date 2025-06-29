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

// About page
$router->get('/about', function() {
    return new Response(view('about', [])->render());
});

// Example form handling
$router->post('/contact', function() {
    // In a real application, you would validate and process the form data
    return response()->json([
        'status' => 'success',
        'message' => 'Thank you for your message! We will get back to you soon.',
        'timestamp' => date('c')
    ]);
});

$router->post('/settings', function() {
    // In a real application, you would save the settings
    return response()->json([
        'status' => 'success',
        'message' => 'Settings have been updated successfully.',
        'timestamp' => date('c')
    ]);
});