<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Authentication Guard
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application.
    |
    */

    'default' => env('AUTH_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Here you may define every authentication guard for your application.
    | A great default configuration has been defined for you here which uses
    | session storage and the database user provider.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'database',
            'table' => env('AUTH_TABLE', 'users'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TABLE', 'password_resets'),
            'expire' => env('AUTH_PASSWORD_RESET_EXPIRE', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Definitions
    |--------------------------------------------------------------------------
    |
    | Here you may define all the roles for your application and their
    | corresponding permissions. Roles with ['*'] have all permissions.
    |
    */

    'roles' => [
        'admin' => ['*'], // All permissions
        'editor' => ['edit-posts', 'delete-posts', 'view-posts', 'view-users'],
        'auditor' => ['view-posts', 'view-users', 'view-analytics'],
        'viewer' => ['view-posts'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Definitions
    |--------------------------------------------------------------------------
    |
    | Here you may define all the permissions for your application and which
    | roles are allowed to perform them.
    |
    */

    'permissions' => [
        'manage-users' => ['admin'],
        'edit-posts' => ['admin', 'editor'],
        'delete-posts' => ['admin', 'editor'],
        'view-posts' => ['admin', 'editor', 'auditor', 'viewer'],
        'view-users' => ['admin', 'auditor'],
        'view-analytics' => ['admin', 'auditor'],
        'manage-settings' => ['admin'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | This option controls the default role assigned to new users.
    |
    */

    'default_role' => env('AUTH_DEFAULT_ROLE', 'viewer'),

    /*
    |--------------------------------------------------------------------------
    | Role Hierarchy (Optional)
    |--------------------------------------------------------------------------
    |
    | Define role inheritance. Higher roles inherit permissions from lower roles.
    |
    */

    'role_hierarchy' => [
        'admin' => ['editor', 'auditor', 'viewer'],
        'editor' => ['viewer'],
        'auditor' => ['viewer'],
    ],

];