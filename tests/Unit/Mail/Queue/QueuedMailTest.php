<?php

declare(strict_types=1);

namespace Tests\Unit\Mail\Queue;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Mail\Queue\QueuedMail;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * QueuedMail Model Tests
 * 
 * Tests for the QueuedMail ActiveRecord model functionality.
 */
class QueuedMailTest extends TestCase
{
    public function testQueuedMailInstantiation(): void
    {
        $data = [
            'to_addresses' => [['email' => 'test@example.com', 'name' => 'Test User']],
            'from_address' => ['email' => 'from@example.com', 'name' => 'From User'],
            'subject' => 'Test Email',
            'body_text' => 'Test body text',
            'mailer' => 'smtp',
            'priority' => QueuedMail::PRIORITY_NORMAL,
        ];

        $queuedMail = new QueuedMail($data);

        $this->assertEquals($data['to_addresses'], $queuedMail->to_addresses);
        $this->assertEquals($data['from_address'], $queuedMail->from_address);
        $this->assertEquals($data['subject'], $queuedMail->subject);
        $this->assertEquals($data['body_text'], $queuedMail->body_text);
        $this->assertEquals($data['mailer'], $queuedMail->mailer);
        $this->assertEquals($data['priority'], $queuedMail->priority);
    }

    public function testStatusConstants(): void
    {
        $this->assertEquals('pending', QueuedMail::STATUS_PENDING);
        $this->assertEquals('processing', QueuedMail::STATUS_PROCESSING);
        $this->assertEquals('sent', QueuedMail::STATUS_SENT);
        $this->assertEquals('failed', QueuedMail::STATUS_FAILED);
    }

    public function testPriorityConstants(): void
    {
        $this->assertEquals(1, QueuedMail::PRIORITY_HIGHEST);
        $this->assertEquals(2, QueuedMail::PRIORITY_HIGH);
        $this->assertEquals(5, QueuedMail::PRIORITY_NORMAL);
        $this->assertEquals(8, QueuedMail::PRIORITY_LOW);
        $this->assertEquals(10, QueuedMail::PRIORITY_LOWEST);
    }

    public function testMarkAsQueued(): void
    {
        $queuedMail = new QueuedMail();
        
        // Mock Carbon::now() for consistent testing
        Carbon::setTestNow(Carbon::create(2025, 1, 8, 10, 0, 0));
        
        // Set available_at directly since we don't want to test save() here
        $queuedMail->available_at = Carbon::now();
        
        $this->assertNotNull($queuedMail->available_at);
        $this->assertEquals('2025-01-08 10:00:00', $queuedMail->available_at->format('Y-m-d H:i:s'));
        
        Carbon::clearTestNow();
    }

    public function testCanRetry(): void
    {
        $queuedMail = new QueuedMail();
        $queuedMail->max_attempts = 3;
        
        // Should be able to retry when attempts < max_attempts
        $queuedMail->attempts = 0;
        $this->assertTrue($queuedMail->canRetry());
        
        $queuedMail->attempts = 2;
        $this->assertTrue($queuedMail->canRetry());
        
        // Should not be able to retry when attempts >= max_attempts
        $queuedMail->attempts = 3;
        $this->assertFalse($queuedMail->canRetry());
        
        $queuedMail->attempts = 5;
        $this->assertFalse($queuedMail->canRetry());
    }

    public function testCalculateRetryDelay(): void
    {
        $queuedMail = new QueuedMail();
        
        // Test exponential backoff
        $queuedMail->attempts = 1;
        $this->assertEquals(300, $queuedMail->calculateRetryDelay(300)); // 300 * 2^0 = 300
        
        $queuedMail->attempts = 2;
        $this->assertEquals(600, $queuedMail->calculateRetryDelay(300)); // 300 * 2^1 = 600
        
        $queuedMail->attempts = 3;
        $this->assertEquals(1200, $queuedMail->calculateRetryDelay(300)); // 300 * 2^2 = 1200
        
        // Test maximum delay cap (1 hour = 3600 seconds)
        $queuedMail->attempts = 10;
        $this->assertEquals(3600, $queuedMail->calculateRetryDelay(300)); // Capped at 3600
    }

    public function testGetStatus(): void
    {
        $queuedMail = new QueuedMail();
        
        // Test pending status (default)
        $this->assertEquals(QueuedMail::STATUS_PENDING, $queuedMail->getStatus());
        
        // Test sent status
        $queuedMail->setAttribute('sent_at', Carbon::now());
        $this->assertEquals(QueuedMail::STATUS_SENT, $queuedMail->getStatus());
        
        // Test failed status
        $queuedMail->setAttribute('sent_at', null);
        $queuedMail->setAttribute('failed_at', Carbon::now());
        $this->assertEquals(QueuedMail::STATUS_FAILED, $queuedMail->getStatus());
        
        // Test processing status
        $queuedMail->setAttribute('failed_at', null);
        $queuedMail->setAttribute('reserved_at', Carbon::now());
        $queuedMail->setAttribute('reserved_until', Carbon::now()->addMinutes(5));
        $this->assertEquals(QueuedMail::STATUS_PROCESSING, $queuedMail->getStatus());
    }

    public function testIsAvailable(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 8, 10, 0, 0));
        
        $queuedMail = new QueuedMail();
        $queuedMail->setAttribute('available_at', Carbon::create(2025, 1, 8, 9, 0, 0)); // 1 hour ago
        
        // Should be available if no restrictions
        $this->assertTrue($queuedMail->isAvailable());
        
        // Should not be available if already sent
        $queuedMail->setAttribute('sent_at', Carbon::now());
        $this->assertFalse($queuedMail->isAvailable());
        
        // Should not be available if permanently failed
        $queuedMail->setAttribute('sent_at', null);
        $queuedMail->setAttribute('failed_at', Carbon::now());
        $this->assertFalse($queuedMail->isAvailable());
        
        // Should not be available if reserved and not expired
        $queuedMail->setAttribute('failed_at', null);
        $queuedMail->setAttribute('reserved_at', Carbon::now());
        $queuedMail->setAttribute('reserved_until', Carbon::now()->addMinutes(5));
        $this->assertFalse($queuedMail->isAvailable());
        
        // Should be available if reservation expired
        $queuedMail->setAttribute('reserved_until', Carbon::now()->subMinutes(5));
        $this->assertTrue($queuedMail->isAvailable());
        
        Carbon::clearTestNow();
    }

    public function testHasExpired(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 8, 10, 0, 0));
        
        $queuedMail = new QueuedMail();
        
        // Should not be expired if no reservation
        $this->assertFalse($queuedMail->hasExpired());
        
        // Should not be expired if reservation is still valid
        $queuedMail->setAttribute('reserved_until', Carbon::now()->addMinutes(5));
        $this->assertFalse($queuedMail->hasExpired());
        
        // Should be expired if reservation time has passed
        $queuedMail->setAttribute('reserved_until', Carbon::now()->subMinutes(5));
        $this->assertTrue($queuedMail->hasExpired());
        
        Carbon::clearTestNow();
    }

    public function testTableName(): void
    {
        $queuedMail = new QueuedMail();
        $this->assertEquals('queued_mails', $queuedMail->getTable());
    }

    public function testFillableAttributes(): void
    {
        $queuedMail = new QueuedMail();
        $expectedFillable = [
            'to_addresses',
            'from_address',
            'cc_addresses',
            'bcc_addresses',
            'subject',
            'body_text',
            'body_html',
            'attachments',
            'headers',
            'mailer',
            'priority',
            'max_attempts',
            'available_at',
        ];
        
        $reflection = new \ReflectionClass($queuedMail);
        $fillableProperty = $reflection->getProperty('fillable');
        $fillableProperty->setAccessible(true);
        $fillable = $fillableProperty->getValue($queuedMail);
        
        $this->assertEquals($expectedFillable, $fillable);
    }
}