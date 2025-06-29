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
    return view('about', [
        'title' => 'About TreeHouse Framework',
        'version' => '1.0.0',
        'description' => 'A modern PHP framework for building web applications with elegant syntax and powerful features.',
        'features' => [
            'Modern PHP 8+ syntax and features',
            'Powerful template engine with inheritance',
            'Built-in authentication and authorization',
            'Flexible routing with middleware support',
            'Database abstraction layer',
            'Command-line interface',
            'Comprehensive testing tools',
            'Developer-friendly error handling'
        ],
        'github_url' => 'https://github.com/lengthofrope/treehouse',
        'documentation_url' => '/docs',
        'team' => [
            [
                'name' => 'TreeHouse Core Team',
                'role' => 'Framework Development',
                'description' => 'Dedicated to building a modern, efficient PHP framework'
            ],
            [
                'name' => 'Community Contributors',
                'role' => 'Features & Bug Fixes',
                'description' => 'Amazing developers contributing to the ecosystem'
            ]
        ]
    ]);
});

// Example API endpoints
$router->group(['prefix' => 'api'], function($router) {
    
    // API status endpoint
    $router->get('/status', function() {
        return response()->json([
            'status' => 'OK',
            'version' => '1.0.0',
            'timestamp' => date('c'),
            'environment' => getenv('APP_ENV') ?: 'production',
            'uptime' => '99.9%',
            'features' => [
                'templating_engine' => 'active',
                'vite_integration' => 'active',
                'tailwind_css' => 'active',
                'authentication' => 'active',
                'database' => 'active'
            ],
            'performance' => [
                'average_response_time' => '45ms',
                'requests_per_second' => '2,400',
                'memory_usage' => '12MB',
                'cpu_usage' => '15%'
            ]
        ]);
    });
    
    // Example user API
    $router->get('/users', function() {
        return response()->json([
            'users' => [
                [
                    'id' => 1,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'role' => 'Admin',
                    'active' => true,
                    'created_at' => '2024-01-15T10:30:00Z'
                ],
                [
                    'id' => 2,
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                    'role' => 'User',
                    'active' => true,
                    'created_at' => '2024-02-20T14:45:00Z'
                ],
                [
                    'id' => 3,
                    'name' => 'Mike Johnson',
                    'email' => 'mike@example.com',
                    'role' => 'Moderator',
                    'active' => false,
                    'created_at' => '2024-03-10T09:15:00Z'
                ]
            ],
            'total' => 3,
            'page' => 1,
            'per_page' => 10
        ]);
    });
    
    // Example projects API
    $router->get('/projects', function() {
        return response()->json([
            'projects' => [
                [
                    'id' => 1,
                    'name' => 'TreeHouse Framework',
                    'description' => 'Modern PHP framework with elegant syntax',
                    'status' => 'active',
                    'progress' => 85,
                    'team_size' => 5,
                    'created_at' => '2024-01-01T00:00:00Z'
                ],
                [
                    'id' => 2,
                    'name' => 'Demo Application',
                    'description' => 'Demonstration of framework features',
                    'status' => 'in_progress',
                    'progress' => 60,
                    'team_size' => 2,
                    'created_at' => '2024-06-01T00:00:00Z'
                ]
            ],
            'total' => 2,
            'active' => 1,
            'completed' => 0,
            'in_progress' => 1
        ]);
    });
    
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