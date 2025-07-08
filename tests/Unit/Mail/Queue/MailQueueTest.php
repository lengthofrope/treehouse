<?php

declare(strict_types=1);

namespace Tests\Unit\Mail\Queue;

use LengthOfRope\TreeHouse\Mail\Queue\MailQueue;
use LengthOfRope\TreeHouse\Mail\Queue\QueuedMail;
use LengthOfRope\TreeHouse\Mail\Messages\Message;
use LengthOfRope\TreeHouse\Mail\MailManager;
use LengthOfRope\TreeHouse\Foundation\Application;
use LengthOfRope\TreeHouse\Database\Connection;
use Tests\TestCase;
use DateTime;

/**
 * MailQueue Test
 * 
 * Tests for the mail queue service
 */
class MailQueueTest extends TestCase
{
    protected MailQueue $mailQueue;
    protected Application $app;
    protected MailManager $mailManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up database connection for testing
        $connection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        // Set the global connection for ActiveRecord
        QueuedMail::setConnection($connection);
        
        // Create the queued_mails table
        $connection->statement("
            CREATE TABLE queued_mails (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                to_addresses TEXT NOT NULL,
                from_address TEXT NOT NULL,
                cc_addresses TEXT NULL,
                bcc_addresses TEXT NULL,
                subject TEXT NOT NULL,
                body_text TEXT NULL,
                body_html TEXT NULL,
                attachments TEXT NULL,
                headers TEXT NULL,
                mailer TEXT NOT NULL DEFAULT 'default',
                priority INTEGER NOT NULL DEFAULT 5,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                attempts INTEGER NOT NULL DEFAULT 0,
                last_attempt_at TEXT NULL,
                next_retry_at TEXT NULL,
                available_at TEXT NOT NULL,
                reserved_at TEXT NULL,
                reserved_until TEXT NULL,
                failed_at TEXT NULL,
                sent_at TEXT NULL,
                error_message TEXT NULL,
                queue_time REAL NULL,
                processing_time REAL NULL,
                delivery_time REAL NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->app = new Application(__DIR__ . '/../../../../..');
        
        $config = [
            'batch_size' => 5,
            'max_attempts' => 3,
            'retry_strategy' => 'exponential',
            'base_retry_delay' => 60,
            'max_retry_delay' => 3600,
            'retry_multiplier' => 2,
        ];
        
        $this->mailQueue = new MailQueue($config, $this->app);
        
        // Create a mock mail manager
        $mailConfig = [
            'default' => 'log',
            'mailers' => [
                'log' => [
                    'transport' => 'log',
                    'path' => 'storage/logs/test-mail.log',
                ],
            ],
        ];
        
        $this->mailManager = new MailManager($mailConfig, $this->app);
    }

    public function testAddMessageToQueue(): void
    {
        $message = new Message($this->mailManager);
        $message->to('test@example.com')
                ->subject('Test Subject')
                ->text('Test body');

        $queuedMail = $this->mailQueue->add($message, 5);

        $this->assertInstanceOf(QueuedMail::class, $queuedMail);
        $this->assertEquals('Test Subject', $queuedMail->subject);
        $this->assertEquals(5, $queuedMail->priority);
        $this->assertEquals(3, $queuedMail->max_attempts);
        $this->assertNotNull($queuedMail->available_at);
    }

    public function testAddMessageWithCustomPriority(): void
    {
        $message = new Message($this->mailManager);
        $message->to('test@example.com')
                ->subject('High Priority')
                ->text('Urgent message')
                ->priority(1);

        $queuedMail = $this->mailQueue->add($message, 2);

        // Custom priority should override message priority
        $this->assertEquals(2, $queuedMail->priority);
    }

    public function testAddMessageWithDelayedAvailability(): void
    {
        $message = new Message($this->mailManager);
        $message->to('test@example.com')
                ->subject('Delayed Message')
                ->text('This message should be delayed');

        $availableAt = new DateTime('+1 hour');
        $queuedMail = $this->mailQueue->add($message, null, $availableAt);

        $this->assertInstanceOf(QueuedMail::class, $queuedMail);
        // Should be approximately equal (within a few seconds)
        $this->assertEqualsWithDelta(
            $availableAt->getTimestamp(),
            $queuedMail->available_at->getTimestamp(),
            5
        );
    }

    public function testGetAvailableEmails(): void
    {
        // Create some test messages
        for ($i = 1; $i <= 3; $i++) {
            $message = new Message($this->mailManager);
            $message->to("test{$i}@example.com")
                    ->subject("Test Subject {$i}")
                    ->text("Test body {$i}");
            
            $this->mailQueue->add($message, $i);
        }

        $availableEmails = $this->mailQueue->getAvailable(2);

        $this->assertCount(2, $availableEmails);
        // Should be ordered by priority (ascending)
        $this->assertEquals(1, $availableEmails[0]->priority);
        $this->assertEquals(2, $availableEmails[1]->priority);
    }

    public function testReserveEmails(): void
    {
        // Create test message
        $message = new Message($this->mailManager);
        $message->to('test@example.com')
                ->subject('Test Subject')
                ->text('Test body');
        
        $queuedMail = $this->mailQueue->add($message);
        
        $emails = [$queuedMail];
        $reserved = $this->mailQueue->reserve($emails, 300);

        $this->assertCount(1, $reserved);
        $this->assertNotNull($reserved[0]->reserved_at);
        $this->assertNotNull($reserved[0]->reserved_until);
    }

    public function testProcessSingleEmail(): void
    {
        // Create test message
        $message = new Message($this->mailManager);
        $message->to('test@example.com')
                ->subject('Test Subject')
                ->text('Test body');
        
        $queuedMail = $this->mailQueue->add($message);
        
        // Reserve the email
        $reserved = $this->mailQueue->reserve([$queuedMail]);
        
        // Mock the application to return the mail manager
        $mockApp = $this->createMock(Application::class);
        $mockApp->method('make')->with('mail')->willReturn($this->mailManager);
        
        $mailQueue = new MailQueue(['max_attempts' => 3], $mockApp);
        
        $result = $mailQueue->process($reserved[0]);

        $this->assertTrue($result);
    }

    public function testProcessQueue(): void
    {
        // Create multiple test messages
        for ($i = 1; $i <= 3; $i++) {
            $message = new Message($this->mailManager);
            $message->to("test{$i}@example.com")
                    ->subject("Test Subject {$i}")
                    ->text("Test body {$i}");
            
            $this->mailQueue->add($message);
        }

        // Mock the application to return the mail manager
        $mockApp = $this->createMock(Application::class);
        $mockApp->method('make')->with('mail')->willReturn($this->mailManager);
        
        $mailQueue = new MailQueue(['batch_size' => 2, 'max_attempts' => 3], $mockApp);
        
        $result = $mailQueue->processQueue(2);

        $this->assertEquals(2, $result['processed']);
        $this->assertEquals(2, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);
    }

    public function testGetStats(): void
    {
        // Create some test emails in different states
        $message1 = new Message($this->mailManager);
        $message1->to('test1@example.com')->subject('Test 1')->text('Body 1');
        $this->mailQueue->add($message1);

        $message2 = new Message($this->mailManager);
        $message2->to('test2@example.com')->subject('Test 2')->text('Body 2');
        $queuedMail2 = $this->mailQueue->add($message2);
        
        // Mark one as sent
        $queuedMail2->markAsProcessed(1.5);

        $stats = $this->mailQueue->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('sent', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['pending']);
        $this->assertGreaterThanOrEqual(1, $stats['sent']);
    }

    public function testClearFailed(): void
    {
        // Create test message
        $message = new Message($this->mailManager);
        $message->to('test@example.com')->subject('Test')->text('Body');
        $queuedMail = $this->mailQueue->add($message);
        
        // Mark as failed
        $queuedMail->markAsFailed('Test failure');

        $cleared = $this->mailQueue->clearFailed();

        $this->assertEquals(1, $cleared);
    }

    public function testClearSent(): void
    {
        // Create test message
        $message = new Message($this->mailManager);
        $message->to('test@example.com')->subject('Test')->text('Body');
        $queuedMail = $this->mailQueue->add($message);
        
        // Mark as sent
        $queuedMail->markAsProcessed(1.0);

        $cleared = $this->mailQueue->clearSent();

        $this->assertEquals(1, $cleared);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        try {
            $emails = QueuedMail::query()->get();
            foreach ($emails as $email) {
                $email->delete();
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        
        parent::tearDown();
    }
}