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
     * Layouts system demonstration
     */
    public function layouts(): Response
    {
        $data = [
            'title' => 'TreeHouse Layout System Demo',
            'showHero' => false,
            'layouts' => [
                'app' => [
                    'name' => 'App Layout',
                    'description' => 'Main application layout with navigation, hero section, and footer',
                    'file' => 'layouts/app.th.html',
                    'features' => [
                        'Navigation bar with responsive design',
                        'Optional hero section with th:if="showHero"',
                        'Content section with th:yield="content"',
                        'Footer with branding',
                        'Mobile-responsive menu'
                    ]
                ],
                'minimal' => [
                    'name' => 'Minimal Layout',
                    'description' => 'Clean, simple layout for focused content presentation',
                    'file' => 'layouts/minimal.th.html',
                    'features' => [
                        'Simple header with page title',
                        'Clean content area with th:yield="content"',
                        'Minimal footer',
                        'Lightweight and fast loading'
                    ]
                ],
                'error' => [
                    'name' => 'Error Layout',
                    'description' => 'Specialized layout for error pages',
                    'file' => 'layouts/error.th.html',
                    'features' => [
                        'Minimal design for error display',
                        'Debug information support',
                        'Clean error presentation'
                    ]
                ]
            ],
            'templateFeatures' => [
                'th:extend' => [
                    'name' => 'Layout Extension',
                    'description' => 'Extend a parent layout template',
                    'syntax' => 'th:extend="layouts/app"',
                    'example' => 'Inherits the entire structure of the parent layout'
                ],
                'th:section' => [
                    'name' => 'Content Sections',
                    'description' => 'Define content blocks that fill layout slots',
                    'syntax' => 'th:section="content"',
                    'example' => 'Provides content for th:yield="content" in the layout'
                ],
                'th:yield' => [
                    'name' => 'Content Slots',
                    'description' => 'Define slots where child content will be inserted',
                    'syntax' => 'th:yield="content"',
                    'example' => 'Creates a placeholder for child template content'
                ]
            ],
            'examples' => [
                'basic' => [
                    'title' => 'Basic Layout Usage',
                    'description' => 'How to use a layout in your templates',
                    'template' => '<!-- child-template.th.html -->
<div th:extend="layouts/app">
    <div th:section="content">
        <h1>Page Title</h1>
        <p>Your page content goes here</p>
    </div>
</div>',
                    'result' => 'The child content replaces th:yield="content" in the app layout'
                ],
                'conditional' => [
                    'title' => 'Conditional Layout Features',
                    'description' => 'Using conditional directives in layouts',
                    'template' => '<!-- In your controller -->
$data = [
    "showHero" => true,  // Controls hero section display
    "title" => "Page Title"
];

<!-- In your template -->
<div th:extend="layouts/app">
    <div th:section="content">
        <p>Content without hero section</p>
    </div>
</div>',
                    'result' => 'Hero section appears when showHero is true'
                ]
            ]
        ];

        return Response::html(view('layouts-demo', $data)->render());
    }

    /**
     * Minimal layout example
     */
    public function minimalLayoutExample(): Response
    {
        $data = [
            'title' => 'Minimal Layout Example',
            'content' => [
                'heading' => 'This page uses the minimal layout',
                'description' => 'Notice how this page has a much simpler structure compared to the main app layout. It demonstrates how different layouts can be used for different purposes.',
                'features' => [
                    'Clean, distraction-free design',
                    'Faster loading with minimal CSS/JS',
                    'Perfect for content-focused pages',
                    'Easy to customize and extend'
                ]
            ]
        ];

        return Response::html(view('minimal-example', $data)->render());
    }

    /**
     * Fragment functionality test
     */
    public function testFragment(): Response
    {
        // Use the test-fragment.html template file instead of inline template
        return Response::html(view('test-fragment')->render());
    }

    /**
     * CLI commands documentation
     */
    public function cli(): Response
    {
        $data = [
            'title' => 'TreeHouse CLI Commands',
            'commands' => [
                'cache' => [
                    'name' => 'Cache Commands',
                    'description' => 'Manage application cache and view compilation',
                    'commands' => [
                        'cache:clear' => [
                            'name' => 'cache:clear',
                            'description' => 'Clear application cache',
                            'usage' => 'treehouse cache:clear [options]',
                            'options' => [
                                ['key' => '--views', 'value' => 'Clear compiled view templates'],
                                ['key' => '--app', 'value' => 'Clear application cache'],
                                ['key' => '--all', 'value' => 'Clear all cache types']
                            ],
                            'examples' => [
                                ['key' => 'treehouse cache:clear', 'value' => 'Clear all cache'],
                                ['key' => 'treehouse cache:clear --views', 'value' => 'Clear only view cache'],
                                ['key' => 'treehouse cache:clear --app', 'value' => 'Clear only application cache']
                            ]
                        ],
                        'cache:stats' => [
                            'name' => 'cache:stats',
                            'description' => 'Display cache statistics and information',
                            'usage' => 'treehouse cache:stats',
                            'options' => [],
                            'examples' => [
                                ['key' => 'treehouse cache:stats', 'value' => 'Show cache statistics']
                            ]
                        ],
                        'cache:warm' => [
                            'name' => 'cache:warm',
                            'description' => 'Pre-compile templates and warm cache',
                            'usage' => 'treehouse cache:warm [options]',
                            'options' => [
                                ['key' => '--views', 'value' => 'Warm view cache by pre-compiling templates'],
                                ['key' => '--config', 'value' => 'Cache configuration data'],
                                ['key' => '--routes', 'value' => 'Cache route definitions']
                            ],
                            'examples' => [
                                ['key' => 'treehouse cache:warm', 'value' => 'Warm all cache types'],
                                ['key' => 'treehouse cache:warm --views', 'value' => 'Pre-compile templates only']
                            ]
                        ]
                    ]
                ],
                'cron' => [
                    'name' => 'Cron Commands',
                    'description' => 'Manage scheduled jobs and cron tasks',
                    'commands' => [
                        'cron:run' => [
                            'name' => 'cron:run',
                            'description' => 'Execute scheduled cron jobs',
                            'usage' => 'treehouse cron:run [options]',
                            'options' => [
                                ['key' => '--force', 'value' => 'Force execution even if jobs are locked'],
                                ['key' => '--job=NAME', 'value' => 'Run specific job by name'],
                                ['key' => '--dry-run', 'value' => 'Show what would be executed without running']
                            ],
                            'examples' => [
                                ['key' => 'treehouse cron:run', 'value' => 'Run all due cron jobs'],
                                ['key' => 'treehouse cron:run --job=cache:cleanup', 'value' => 'Run specific job'],
                                ['key' => 'treehouse cron:run --dry-run', 'value' => 'Preview jobs without execution']
                            ]
                        ],
                        'cron:list' => [
                            'name' => 'cron:list',
                            'description' => 'List all registered cron jobs',
                            'usage' => 'treehouse cron:list [options]',
                            'options' => [
                                ['key' => '--due', 'value' => 'Show only jobs that are due to run'],
                                ['key' => '--enabled', 'value' => 'Show only enabled jobs'],
                                ['key' => '--disabled', 'value' => 'Show only disabled jobs']
                            ],
                            'examples' => [
                                ['key' => 'treehouse cron:list', 'value' => 'List all cron jobs'],
                                ['key' => 'treehouse cron:list --due', 'value' => 'Show jobs due to run now'],
                                ['key' => 'treehouse cron:list --enabled', 'value' => 'Show only enabled jobs']
                            ]
                        ]
                    ]
                ],
                'database' => [
                    'name' => 'Database Commands',
                    'description' => 'Manage database migrations and schema',
                    'commands' => [
                        'migrate:run' => [
                            'name' => 'migrate:run',
                            'description' => 'Run database migrations',
                            'usage' => 'treehouse migrate:run [options]',
                            'options' => [
                                ['key' => '--step=N', 'value' => 'Run N migrations'],
                                ['key' => '--rollback', 'value' => 'Rollback last migration'],
                                ['key' => '--reset', 'value' => 'Reset all migrations']
                            ],
                            'examples' => [
                                ['key' => 'treehouse migrate:run', 'value' => 'Run all pending migrations'],
                                ['key' => 'treehouse migrate:run --step=1', 'value' => 'Run one migration'],
                                ['key' => 'treehouse migrate:run --rollback', 'value' => 'Rollback last migration']
                            ]
                        ]
                    ]
                ],
                'development' => [
                    'name' => 'Development Commands',
                    'description' => 'Development server and testing tools',
                    'commands' => [
                        'serve' => [
                            'name' => 'serve',
                            'description' => 'Start local development server',
                            'usage' => 'treehouse serve [options]',
                            'options' => [
                                ['key' => '--host=HOST', 'value' => 'Server host (default: 127.0.0.1)'],
                                ['key' => '-p, --port=PORT', 'value' => 'Server port (default: 8000)'],
                                ['key' => '-d, --docroot=PATH', 'value' => 'Document root (default: public)']
                            ],
                            'examples' => [
                                ['key' => 'treehouse serve', 'value' => 'Start server on 127.0.0.1:8000'],
                                ['key' => 'treehouse serve --port=3000', 'value' => 'Start server on port 3000'],
                                ['key' => 'treehouse serve --host=0.0.0.0', 'value' => 'Allow external connections']
                            ]
                        ],
                        'test:run' => [
                            'name' => 'test:run',
                            'description' => 'Run application tests',
                            'usage' => 'treehouse test:run [options]',
                            'options' => [
                                ['key' => '--filter=PATTERN', 'value' => 'Filter tests by pattern'],
                                ['key' => '--coverage', 'value' => 'Generate code coverage report'],
                                ['key' => '--verbose', 'value' => 'Verbose output']
                            ],
                            'examples' => [
                                ['key' => 'treehouse test:run', 'value' => 'Run all tests'],
                                ['key' => 'treehouse test:run --filter=UserTest', 'value' => 'Run specific test'],
                                ['key' => 'treehouse test:run --coverage', 'value' => 'Run tests with coverage']
                            ]
                        ]
                    ]
                ],
                'user' => [
                    'name' => 'User Management Commands',
                    'description' => 'Manage application users and roles',
                    'commands' => [
                        'user:create' => [
                            'name' => 'user:create',
                            'description' => 'Create a new user account',
                            'usage' => 'treehouse user:create [options]',
                            'options' => [
                                ['key' => '--name=NAME', 'value' => 'User full name'],
                                ['key' => '--email=EMAIL', 'value' => 'User email address'],
                                ['key' => '--password=PASS', 'value' => 'User password'],
                                ['key' => '--role=ROLE', 'value' => 'Assign role to user']
                            ],
                            'examples' => [
                                ['key' => 'treehouse user:create', 'value' => 'Interactive user creation'],
                                ['key' => 'treehouse user:create --name="John Doe" --email=john@example.com', 'value' => 'Create user with details']
                            ]
                        ],
                        'user:list' => [
                            'name' => 'user:list',
                            'description' => 'List all users',
                            'usage' => 'treehouse user:list [options]',
                            'options' => [
                                ['key' => '--role=ROLE', 'value' => 'Filter by role'],
                                ['key' => '--active', 'value' => 'Show only active users'],
                                ['key' => '--format=FORMAT', 'value' => 'Output format (table, json)']
                            ],
                            'examples' => [
                                ['key' => 'treehouse user:list', 'value' => 'List all users'],
                                ['key' => 'treehouse user:list --role=admin', 'value' => 'List admin users only'],
                                ['key' => 'treehouse user:list --format=json', 'value' => 'Output as JSON']
                            ]
                        ],
                        'user:update' => [
                            'name' => 'user:update',
                            'description' => 'Update user information',
                            'usage' => 'treehouse user:update [user_id] [options]',
                            'options' => [
                                ['key' => '--name=NAME', 'value' => 'Update user name'],
                                ['key' => '--email=EMAIL', 'value' => 'Update email address'],
                                ['key' => '--password=PASS', 'value' => 'Update password'],
                                ['key' => '--role=ROLE', 'value' => 'Update user role']
                            ],
                            'examples' => [
                                ['key' => 'treehouse user:update 1 --name="Jane Doe"', 'value' => 'Update user name'],
                                ['key' => 'treehouse user:update 1 --role=admin', 'value' => 'Make user admin']
                            ]
                        ],
                        'user:delete' => [
                            'name' => 'user:delete',
                            'description' => 'Delete a user account',
                            'usage' => 'treehouse user:delete [user_id] [options]',
                            'options' => [
                                ['key' => '--force', 'value' => 'Skip confirmation prompt'],
                                ['key' => '--soft', 'value' => 'Soft delete (deactivate instead of remove)']
                            ],
                            'examples' => [
                                ['key' => 'treehouse user:delete 1', 'value' => 'Delete user with confirmation'],
                                ['key' => 'treehouse user:delete 1 --force', 'value' => 'Delete without confirmation']
                            ]
                        ],
                        'user:role' => [
                            'name' => 'user:role',
                            'description' => 'Manage user roles and permissions',
                            'usage' => 'treehouse user:role [action] [options]',
                            'options' => [
                                ['key' => 'assign USER_ID ROLE', 'value' => 'Assign role to user'],
                                ['key' => 'remove USER_ID ROLE', 'value' => 'Remove role from user'],
                                ['key' => 'list', 'value' => 'List all available roles']
                            ],
                            'examples' => [
                                ['key' => 'treehouse user:role assign 1 admin', 'value' => 'Make user admin'],
                                ['key' => 'treehouse user:role remove 1 admin', 'value' => 'Remove admin role'],
                                ['key' => 'treehouse user:role list', 'value' => 'List all roles']
                            ]
                        ]
                    ]
                ],
                'project' => [
                    'name' => 'Project Commands',
                    'description' => 'Create new TreeHouse projects',
                    'commands' => [
                        'new' => [
                            'name' => 'new',
                            'description' => 'Create a new TreeHouse application',
                            'usage' => 'treehouse new [project-name] [options]',
                            'options' => [
                                ['key' => '--path=PATH', 'value' => 'Custom installation path'],
                                ['key' => '--template=TEMPLATE', 'value' => 'Use specific project template'],
                                ['key' => '--no-deps', 'value' => 'Skip dependency installation']
                            ],
                            'examples' => [
                                ['key' => 'treehouse new my-app', 'value' => 'Create new project named "my-app"'],
                                ['key' => 'treehouse new my-app --path=/var/www', 'value' => 'Create in custom path'],
                                ['key' => 'treehouse new my-app --no-deps', 'value' => 'Skip composer install']
                            ]
                        ]
                    ]
                ]
            ],
            'globalOptions' => [
                ['key' => '-h, --help', 'value' => 'Display help information'],
                ['key' => '-V, --version', 'value' => 'Display version information'],
                ['key' => '--debug', 'value' => 'Enable debug mode for detailed error output']
            ],
            'usage' => [
                'basic' => 'treehouse <command> [options] [arguments]',
                'help' => 'treehouse <command> --help',
                'version' => 'treehouse --version'
            ],
            'features' => [
                'context_aware' => [
                    'title' => 'Context-Aware CLI',
                    'description' => 'The TreeHouse CLI automatically detects whether you\'re inside a TreeHouse project or not, showing different commands accordingly.',
                    'outside_project' => 'Outside a project: Shows only the "new" command for creating projects',
                    'inside_project' => 'Inside a project: Shows all project management commands'
                ],
                'directory_traversal' => [
                    'title' => 'Works from Any Directory',
                    'description' => 'Commands can be run from any subdirectory within a TreeHouse project. The CLI automatically changes to the project root before executing commands.',
                    'example' => 'You can run "treehouse serve" from src/App/Controllers/ and it will work correctly'
                ],
                'command_grouping' => [
                    'title' => 'Command Grouping',
                    'description' => 'Commands are organized into logical groups. You can type a group name to see all related commands.',
                    'examples' => [
                        'treehouse user' => 'Shows all user management commands',
                        'treehouse cache' => 'Shows all cache-related commands',
                        'treehouse cron' => 'Shows all cron job commands'
                    ]
                ]
            ]
        ];

        return Response::html(view('cli', $data)->render());
    }
}