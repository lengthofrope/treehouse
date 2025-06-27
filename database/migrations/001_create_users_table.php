<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Database\Migration;

/**
 * Create Users Table Migration
 * 
 * Creates the users table required for authentication functionality.
 * This table stores user account information including credentials,
 * email verification status, and remember tokens.
 */
class CreateUsersTable extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->createTable('users', function ($table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->string('name');
            $table->boolean('email_verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->dropTable('users');
    }
}