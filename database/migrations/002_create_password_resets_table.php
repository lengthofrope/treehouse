<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Database\Migration;

/**
 * Create Password Resets Table Migration
 * 
 * Creates the password_resets table required for password reset functionality.
 * This table stores temporary tokens used for resetting user passwords,
 * including expiration timestamps for security.
 */
class CreatePasswordResetsTable extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->createTable('password_resets', function ($table) {
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at');
            
            // Add indexes for faster lookups
            $table->index('email');
            $table->index(['email', 'token']);
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->dropTable('password_resets');
    }
}