<?php

declare(strict_types=1);

namespace App\Models;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Auth\AuthorizableUser;

class User extends ActiveRecord implements Authorizable
{
    use AuthorizableUser;
    
    protected string $table = 'users';
    
    protected array $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];
    
    protected array $hidden = [
        'password',
    ];
}