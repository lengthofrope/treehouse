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
    public function templating(Request $request): Response
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
                'role' => [
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
            ]
        ];

        
        return new Response(view('templating', $data)->render());
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
     * Layouts demonstration
     */
    public function layouts(Request $request): Response
    {
        $data = [
            'title' => 'TreeHouse Layouts Demo',
            'nav' => [
                'main' => [
                    'items' => [
                        [
                            'title' => 'Dashboard',
                            'url' => '/dashboard',
                            'active' => false,
                            'description' => 'Main dashboard overview',
                            'badge' => null
                        ],
                        [
                            'title' => 'Projects',
                            'url' => '/projects',
                            'active' => true,
                            'description' => 'Project management',
                            'badge' => [
                                'text' => '5',
                                'css' => 'bg-red-100 text-red-800'
                            ]
                        ],
                        [
                            'title' => 'Team',
                            'url' => '/team',
                            'active' => false,
                            'description' => 'Team management',
                            'badge' => null
                        ],
                        [
                            'title' => 'Settings',
                            'url' => '/settings',
                            'active' => false,
                            'description' => 'Application settings',
                            'badge' => [
                                'text' => 'New',
                                'css' => 'bg-blue-100 text-blue-800'
                            ]
                        ]
                    ]
                ]
            ],
            'breadcrumbs' => [
                'items' => [
                    [
                        'title' => 'Home',
                        'url' => '/'
                    ],
                    [
                        'title' => 'Demo',
                        'url' => '/demo'
                    ],
                    [
                        'title' => 'Layouts',
                        'url' => null
                    ]
                ]
            ],
            'content' => [
                'blocks' => [
                    [
                        'title' => 'Welcome to TreeHouse Layouts',
                        'content' => '<p>This content block demonstrates how dynamic content can be managed through the TreeHouse layout system. Each block can have its own styling, metadata, and content type.</p>',
                        'type' => [
                            'label' => 'Introduction',
                            'css' => 'bg-blue-100 text-blue-800'
                        ],
                        'border_color' => 'border-blue-500',
                        'background_color' => 'bg-blue-50',
                        'title_color' => 'text-blue-900',
                        'metadata' => [
                            'show' => true,
                            'author' => 'TreeHouse Team',
                            'updated' => '2 hours ago'
                        ]
                    ],
                    [
                        'title' => 'Template Inheritance',
                        'content' => '<p>TreeHouse supports powerful template inheritance patterns. Child templates can extend parent layouts and override specific sections while maintaining the overall structure.</p><ul><li>Section overrides with th:section</li><li>Conditional sections with th:if</li><li>Dynamic content with dot notation</li></ul>',
                        'type' => [
                            'label' => 'Feature',
                            'css' => 'bg-green-100 text-green-800'
                        ],
                        'border_color' => 'border-green-500',
                        'background_color' => 'bg-green-50',
                        'title_color' => 'text-green-900',
                        'metadata' => [
                            'show' => true,
                            'author' => 'John Doe',
                            'updated' => '1 day ago'
                        ]
                    ],
                    [
                        'title' => 'Dynamic Sections',
                        'content' => '<p>Each section can be dynamically populated with content, allowing for flexible page structures. This enables consistent layouts across different page types while maintaining content flexibility.</p>',
                        'type' => [
                            'label' => 'Documentation',
                            'css' => 'bg-purple-100 text-purple-800'
                        ],
                        'border_color' => 'border-purple-500',
                        'background_color' => 'bg-purple-50',
                        'title_color' => 'text-purple-900',
                        'metadata' => [
                            'show' => false,
                            'author' => 'Jane Smith',
                            'updated' => '3 days ago'
                        ]
                    ]
                ]
            ],
            'sidebar' => [
                'show' => true,
                'widgets' => [
                    [
                        'title' => 'Recent Posts',
                        'type' => 'recent_posts',
                        'css_class' => 'border-l-4 border-blue-500',
                        'data' => [
                            'posts' => [
                                [
                                    'title' => 'Getting Started with TreeHouse',
                                    'url' => '/posts/getting-started',
                                    'date' => '2 days ago'
                                ],
                                [
                                    'title' => 'Advanced Template Features',
                                    'url' => '/posts/advanced-templates',
                                    'date' => '1 week ago'
                                ],
                                [
                                    'title' => 'Building Modern PHP Apps',
                                    'url' => '/posts/modern-php-apps',
                                    'date' => '2 weeks ago'
                                ]
                            ]
                        ]
                    ],
                    [
                        'title' => 'Quick Stats',
                        'type' => 'stats',
                        'css_class' => 'border-l-4 border-green-500',
                        'data' => [
                            'stats' => [
                                [
                                    'label' => 'Total Views',
                                    'value' => '15,847'
                                ],
                                [
                                    'label' => 'Active Users',
                                    'value' => '234'
                                ],
                                [
                                    'label' => 'Page Load',
                                    'value' => '1.2s'
                                ]
                            ]
                        ]
                    ],
                    [
                        'title' => 'Useful Links',
                        'type' => 'links',
                        'css_class' => 'border-l-4 border-purple-500',
                        'data' => [
                            'links' => [
                                [
                                    'title' => 'TreeHouse Documentation',
                                    'url' => '/docs',
                                    'external' => false
                                ],
                                [
                                    'title' => 'GitHub Repository',
                                    'url' => 'https://github.com/lengthofrope/treehouse',
                                    'external' => true
                                ],
                                [
                                    'title' => 'Community Forum',
                                    'url' => 'https://forum.treehouse.dev',
                                    'external' => true
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return new Response(view('layouts', $data)->render());
    }
}