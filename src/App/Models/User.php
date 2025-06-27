<?php

declare(strict_types=1);

namespace App\Models;

use LengthOfRope\TreeHouse\Database\ActiveRecord;

class User extends ActiveRecord
{
    protected string $table = 'users';
    
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];
    
    protected array $hidden = [
        'password',
    ];
}