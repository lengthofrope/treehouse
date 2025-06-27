<?php

declare(strict_types=1);

namespace App\Models;

use LengthOfRope\TreeHouse\Database\ActiveRecord;

/**
 * User Model
 * 
 * Represents a user in the application with authentication capabilities.
 * 
 * @package App\Models
 */
class User extends ActiveRecord
{
    /**
     * The table associated with the model
     */
    protected string $table = 'users';

    /**
     * The attributes that are mass assignable
     */
    protected array $fillable = [
        'name',
        'email',
        'password',
        'email_verified',
    ];

    /**
     * The attributes that should be hidden for serialization
     */
    protected array $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast
     */
    protected array $casts = [
        'email_verified' => 'boolean',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Hash the password when setting it
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * Check if the given password matches the user's password
     */
    public function checkPassword(string $password): bool
    {
        return password_verify($password, $this->attributes['password']);
    }

    /**
     * Mark the user's email as verified
     */
    public function markEmailAsVerified(): void
    {
        $this->email_verified = true;
        $this->email_verified_at = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Determine if the user has verified their email address
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified === true;
    }
}