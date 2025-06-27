<?php

/**
 * Web Routes
 * 
 * Define your web routes here. The $router variable is available
 * and routes defined here will be automatically loaded by the application.
 */

use LengthOfRope\TreeHouse\Http\Response;

// Example routes
$router->get('/', function () {
    return new Response('Welcome to TreeHouse Framework!');
});

$router->get('/hello/{name}', function ($name) {
    return new Response("Hello, {$name}!");
});

$router->get('/about', function () {
    return new Response('About page');
});