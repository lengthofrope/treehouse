<?php

declare(strict_types=1);

namespace App\Controllers;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\View\ViewFactory;

/**
 * Demo Controller
 * 
 * Handles demonstration pages showing TreeHouse framework features
 */
class DemoController
{
    /**
     * Templating engine demonstration
     */
    public function templating(): Response
    {
        $data = [
            'title' => 'TreeHouse Templating Engine Demo',
            'page' => [
                'title' => 'Templating Engine Demo',
                'date' => date('F j, Y'),
                'meta' => [
                    'description' => 'Explore the powerful TreeHouse templating engine with dot notation and dynamic attributes'
                ]
            ],
            'app' => [
                'version' => '1.0.0'
            ],
            'user' => [
                'id' => 123,
                'profile' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150&h=150&fit=crop&crop=face',
                    'url' => '/users/123'
                ],
                'role' => 'admin',
                'roleDetails' => [
                    'name' => 'Admin',
                    'permissions' => ['read', 'write', 'delete']
                ],
                'status' => [
                    'name' => 'Active',
                    'css_class' => 'bg-green-100 text-green-800'
                ],
                'badge' => [
                    'text' => 'VIP',
                    'css_class' => 'bg-purple-100 text-purple-800'
                ],
                'is_premium' => true,
                'is_active' => true,
                'firstName' => 'John',
                'lastName' => 'Doe',
                'experience' => 'Senior',
                'title' => 'Senior Developer',
                'department' => 'Engineering',
                'email' => 'john@example.com',
                'name' => 'John Doe',
                'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150&h=150&fit=crop&crop=face',
                'statusClass' => 'bg-green-100 text-green-800',
                'preferences' => [
                    ['name' => 'Dark Theme'],
                    ['name' => 'Email Notifications'],
                    ['name' => 'Desktop Alerts']
                ],
                'activity' => [
                    'last_login' => '2024-06-29 15:30:00'
                ],
                'activities' => [
                    [
                        'type' => 'Login',
                        'description' => 'Logged in from Chrome browser',
                        'date' => '2 minutes ago'
                    ],
                    [
                        'type' => 'Profile Update',
                        'description' => 'Updated profile picture',
                        'date' => '1 hour ago'
                    ],
                    [
                        'type' => 'Password Change',
                        'description' => 'Changed account password',
                        'date' => '2 days ago'
                    ]
                ]
            ],
            'config' => [
                'app' => [
                    'name' => 'TreeHouse Demo',
                    'env' => 'development',
                    'debug' => true
                ],
                'database' => [
                    'host' => 'localhost'
                ]
            ],
            'sampleUsers' => [
                'john' => [
                    'id' => 1,
                    'name' => 'John Doe',
                    'title' => 'Senior Developer',
                    'email' => 'john@example.com',
                    'department' => 'Engineering',
                    'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150&h=150&fit=crop&crop=face',
                    'status' => 'Online',
                    'statusClass' => 'bg-green-100 text-green-800'
                ],
                'jane' => [
                    'id' => 2,
                    'name' => 'Jane Smith',
                    'title' => 'Product Manager',
                    'email' => 'jane@example.com',
                    'department' => 'Product',
                    'avatar' => 'https://images.unsplash.com/photo-1494790108755-2616b612b786?w=150&h=150&fit=crop&crop=face',
                    'status' => 'Away',
                    'statusClass' => 'bg-yellow-100 text-yellow-800'
                ]
            ],
            'product' => [
                'price' => 99.99,
                'quantity' => 2
            ],
            'form' => [
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com'
                ],
                'api' => [
                    'data' => '{"test": "data"}'
                ],
                'settings' => [
                    'apiMethod' => 'POST'
                ]
            ]
        ];

        return Response::html(view('templating', $data)->render());
    }

    /**
     * Components demonstration
     */
    public function components(Request $request): Response
    {
        $data = [
            'title' => 'TreeHouse Components Demo',
            'users' => [
                'john' => [
                    'id' => 1,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'role' => 'Senior Developer',
                    'department' => 'Engineering',
                    'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150&h=150&fit=crop&crop=face',
                    'status' => [
                        'label' => 'Online',
                        'css' => 'bg-green-100 text-green-800'
                    ]
                ],
                'jane' => [
                    'id' => 2,
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                    'role' => 'Product Manager',
                    'department' => 'Product',
                    'avatar' => 'https://images.unsplash.com/photo-1494790108755-2616b612b786?w=150&h=150&fit=crop&crop=face',
                    'status' => [
                        'label' => 'Away',
                        'css' => 'bg-yellow-100 text-yellow-800'
                    ]
                ],
                'mike' => [
                    'id' => 3,
                    'name' => 'Mike Johnson',
                    'email' => 'mike@example.com',
                    'role' => 'UX Designer',
                    'department' => 'Design',
                    'avatar' => 'https://images.unsplash.com/photo-1519244703995-f4e0f30006d5?w=150&h=150&fit=crop&crop=face',
                    'status' => [
                        'label' => 'Offline',
                        'css' => 'bg-gray-100 text-gray-800'
                    ]
                ]
            ],
            'stats' => [
                'users' => [
                    'count' => 1247,
                    'change' => '+12%',
                    'color' => 'bg-blue-500'
                ],
                'projects' => [
                    'count' => 89,
                    'change' => '+3%',
                    'color' => 'bg-green-500'
                ],
                'revenue' => [
                    'amount' => '$52,430',
                    'change' => '+8%',
                    'color' => 'bg-purple-500'
                ],
                'performance' => [
                    'score' => 94,
                    'change' => '+2%',
                    'color' => 'bg-orange-500'
                ]
            ],
            'form' => [
                'contact' => [
                    'action' => '/contact',
                    'method' => 'POST',
                    'fields' => [
                        'name' => [
                            'id' => 'contact_name',
                            'name' => 'name',
                            'label' => 'Full Name',
                            'placeholder' => 'Enter your full name'
                        ],
                        'email' => [
                            'id' => 'contact_email',
                            'name' => 'email',
                            'label' => 'Email Address',
                            'placeholder' => 'Enter your email'
                        ],
                        'subject' => [
                            'id' => 'contact_subject',
                            'name' => 'subject',
                            'label' => 'Subject',
                            'options' => [
                                ['value' => 'general', 'label' => 'General Inquiry'],
                                ['value' => 'support', 'label' => 'Technical Support'],
                                ['value' => 'sales', 'label' => 'Sales Question'],
                                ['value' => 'partnership', 'label' => 'Partnership']
                            ]
                        ],
                        'message' => [
                            'id' => 'contact_message',
                            'name' => 'message',
                            'label' => 'Message',
                            'placeholder' => 'Enter your message'
                        ]
                    ],
                    'submit' => [
                        'text' => 'Send Message',
                        'css' => 'bg-treehouse-600 hover:bg-treehouse-700'
                    ]
                ],
                'settings' => [
                    'action' => '/settings',
                    'method' => 'POST',
                    'fields' => [
                        'notifications' => [
                            'id' => 'settings_notifications',
                            'name' => 'notifications',
                            'label' => 'Email Notifications'
                        ],
                        'marketing' => [
                            'id' => 'settings_marketing',
                            'name' => 'marketing',
                            'label' => 'Marketing Emails'
                        ],
                        'theme' => [
                            'id' => 'settings_theme',
                            'name' => 'theme',
                            'label' => 'Interface Theme',
                            'options' => [
                                ['value' => 'light', 'label' => 'Light Theme'],
                                ['value' => 'dark', 'label' => 'Dark Theme'],
                                ['value' => 'auto', 'label' => 'Auto (System)']
                            ]
                        ]
                    ],
                    'submit' => [
                        'text' => 'Save Settings',
                        'css' => 'bg-blue-600 hover:bg-blue-700'
                    ]
                ]
            ]
        ];

        return new Response(view('components', $data)->render());
    }

    /**
     * Fragment functionality test
     */
    public function testFragment(): Response
    {
        // Use the test-fragment.html template file instead of inline template
        return Response::html(view('test-fragment')->render());
    }
}