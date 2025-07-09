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

// Mail System Demo Routes
$router->get('/mail', 'App\Controllers\MailController@index');
$router->get('/mail/send-test', 'App\Controllers\MailController@sendTest');
$router->post('/mail/send-test', 'App\Controllers\MailController@sendTest');
$router->get('/mail/send-templated', 'App\Controllers\MailController@sendTemplated');
$router->post('/mail/send-templated', 'App\Controllers\MailController@sendTemplated');
$router->get('/mail/queue', 'App\Controllers\MailController@queue');
$router->post('/mail/queue', 'App\Controllers\MailController@queue');
$router->get('/mail/attachments', 'App\Controllers\MailController@attachments');
$router->post('/mail/attachments', 'App\Controllers\MailController@attachments');
$router->get('/mail/queue-status', 'App\Controllers\MailController@queueStatus');

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

// Test different rate limiting strategies
$router->get('/api/sliding', function() {
    return new Response(json_encode([
        'message' => 'Sliding window test',
        'timestamp' => time(),
        'strategy' => 'sliding'
    ]), 200, ['Content-Type' => 'application/json']);
})->middleware('throttle:3,30,sliding'); // 3 requests per 30 seconds, sliding window

$router->get('/api/token-bucket', function() {
    return new Response(json_encode([
        'message' => 'Token bucket test',
        'timestamp' => time(),
        'strategy' => 'token_bucket'
    ]), 200, ['Content-Type' => 'application/json']);
})->middleware('throttle:5,60,token_bucket'); // 5 tokens, refill every 60 seconds

$router->get('/api/user-based', function() {
    return new Response(json_encode([
        'message' => 'User-based rate limiting test',
        'timestamp' => time(),
        'strategy' => 'fixed',
        'key_resolver' => 'user'
    ]), 200, ['Content-Type' => 'application/json']);
})->middleware('throttle:10,60,fixed,user'); // 10 requests per 60 seconds per user

$router->get('/api/header-based', function() {
    return new Response(json_encode([
        'message' => 'Header-based rate limiting test',
        'timestamp' => time(),
        'strategy' => 'fixed',
        'key_resolver' => 'header'
    ]), 200, ['Content-Type' => 'application/json']);
})->middleware('throttle:20,60,fixed,header'); // 20 requests per 60 seconds per API key