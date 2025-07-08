<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Database\Migration;

/**
 * Create Queued Mails Table Migration
 * 
 * Creates the queued_mails table for the mail queue system.
 * This table stores email queue entries with performance metrics,
 * retry tracking, and comprehensive status management.
 */
class CreateQueuedMailsTable extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->createTable('queued_mails', function ($table) {
            // Primary Key
            $table->id();
            
            // Email Content
            $table->json('to_addresses');
            $table->json('from_address');
            $table->json('cc_addresses')->nullable();
            $table->json('bcc_addresses')->nullable();
            $table->string('subject', 998);
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();
            $table->json('attachments')->nullable();
            $table->json('headers')->nullable();
            
            // Mail Configuration
            $table->string('mailer', 50)->default('default');
            $table->tinyInteger('priority')->unsigned()->default(5);
            
            // Retry Logic
            $table->tinyInteger('max_attempts')->unsigned()->default(3);
            $table->tinyInteger('attempts')->unsigned()->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            
            // Queue Management
            $table->timestamp('available_at')->default('CURRENT_TIMESTAMP');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('reserved_until')->nullable();
            
            // Status Tracking
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            
            // Performance Metrics
            $table->decimal('queue_time', 10, 3)->nullable()->comment('Time spent in queue (seconds)');
            $table->decimal('processing_time', 8, 3)->nullable()->comment('Time to process email (seconds)');
            $table->decimal('delivery_time', 8, 3)->nullable()->comment('Time for actual delivery (seconds)');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for Performance
            $table->index(['available_at', 'reserved_at'], 'idx_available_reserved');
            $table->index(['mailer', 'priority', 'available_at'], 'idx_mailer_priority');
            $table->index(['failed_at', 'sent_at'], 'idx_status');
            $table->index(['next_retry_at', 'attempts', 'max_attempts'], 'idx_retry');
            $table->index(['queue_time', 'processing_time', 'delivery_time'], 'idx_performance');
            $table->index(['last_attempt_at'], 'idx_last_attempt');
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->dropTable('queued_mails');
    }
}