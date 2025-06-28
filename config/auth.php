<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Provider Class
    |--------------------------------------------------------------------------
    |
    | This option specifies the class used to retrieve users for authorization.
    | The class must implement the Authorizable interface and have a find() method.
    | If not specified, defaults to \LengthOfRope\TreeHouse\Models\User.
    |
    */

    'user_provider' => env('AUTH_USER_PROVIDER', '\LengthOfRope\TreeHouse\Models\User'),

    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | This option controls the default role assigned to new users.
    |
    */

    'default_role' => env('AUTH_DEFAULT_ROLE', 'member'),

];