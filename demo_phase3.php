<?php

require_once __DIR__ . '/vendor/autoload.php';

use LengthOfRope\TreeHouse\Foundation\Application;
use LengthOfRope\TreeHouse\Mail\Queue\QueuedMail;
use LengthOfRope\TreeHouse\Database\Connection;

// Initialize application
$app = new Application(__DIR__);

// Set up database connection for demo
$connection = new Connection([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/storage/demo_queue.db',
]);

QueuedMail::setConnection($connection);

// Create the queued_mails table if it doesn't exist
try {
    $connection->statement("
        CREATE TABLE IF NOT EXISTS queued_mails (
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
} catch (Exception $e) {
    echo "Database setup error: " . $e->getMessage() . "\n";
}

echo "=== TreeHouse Mail System - Phase 3 Demo ===\n\n";

echo "📧 **PHASE 3: QUEUE SYSTEM COMPLETE** ✅\n\n";

// Load helper functions
require_once __DIR__ . '/src/TreeHouse/Mail/helpers.php';

// Get mail manager and queue
$mailManager = $app->make('mail');
$mailQueue = $app->make('mail.queue');

echo "1. **Queueing Emails** 📬\n";
echo "   Adding emails to the queue...\n";

// Queue some emails with different priorities
$emails = [
    ['to' => 'user1@example.com', 'subject' => 'Welcome!', 'message' => 'Welcome to our platform!', 'priority' => 1],
    ['to' => 'user2@example.com', 'subject' => 'Newsletter', 'message' => 'Monthly newsletter content', 'priority' => 5],
    ['to' => 'admin@example.com', 'subject' => 'Alert', 'message' => 'System alert notification', 'priority' => 2],
];

foreach ($emails as $email) {
    $message = $mailManager->compose()
        ->to($email['to'])
        ->subject($email['subject'])
        ->text($email['message'])
        ->priority($email['priority']);
    
    $queuedMail = $mailQueue->add($message->getMessage(), $email['priority']);
    echo "   ✅ Queued: {$email['subject']} (Priority: {$email['priority']})\n";
}

echo "\n2. **Queue Statistics** 📊\n";
$stats = $mailQueue->getStats();
echo "   - Pending: {$stats['pending']}\n";
echo "   - Processing: {$stats['processing']}\n";
echo "   - Sent: {$stats['sent']}\n";
echo "   - Failed: {$stats['failed']}\n";

echo "\n3. **Processing Queue** ⚙️\n";
echo "   Processing emails in priority order...\n";

$result = $mailQueue->processQueue(3);
echo "   📤 Processed: {$result['processed']} emails\n";
echo "   ✅ Sent: {$result['sent']} emails\n";
echo "   ❌ Failed: {$result['failed']} emails\n";

if (!empty($result['errors'])) {
    echo "   Errors:\n";
    foreach ($result['errors'] as $error) {
        echo "     - Email ID {$error['email_id']}: {$error['error']}\n";
    }
}

echo "\n4. **Updated Queue Statistics** 📊\n";
$newStats = $mailQueue->getStats();
echo "   - Pending: {$newStats['pending']}\n";
echo "   - Processing: {$newStats['processing']}\n";
echo "   - Sent: {$newStats['sent']}\n";
echo "   - Failed: {$newStats['failed']}\n";

echo "\n5. **Queue Management Operations** 🔧\n";

// Show available emails
$available = $mailQueue->getAvailable(5);
echo "   📋 Available emails: " . count($available) . "\n";

// Release any expired reservations
$released = $mailQueue->releaseExpiredReservations();
echo "   🔓 Released reservations: {$released}\n";

// Retry ready emails
$retried = $mailQueue->retryReady();
echo "   🔄 Retried emails: {$retried}\n";

echo "\n6. **Performance Tracking** 📈\n";
try {
    $metrics = QueuedMail::getPerformanceMetrics(24);
    echo "   📊 Performance Metrics (24h):\n";
    echo "     - Total processed: {$metrics['total_processed']}\n";
    if ($metrics['total_processed'] > 0) {
        echo "     - Avg queue time: {$metrics['avg_queue_time']}s\n";
        echo "     - Avg processing time: {$metrics['avg_processing_time']}s\n";
        echo "     - Avg delivery time: {$metrics['avg_delivery_time']}s\n";
    }
} catch (Exception $e) {
    echo "   ⚠️  Performance metrics not available: " . $e->getMessage() . "\n";
}

echo "\n7. **Queue Cleanup** 🧹\n";
// Clean up demo data
try {
    $sentCleared = $mailQueue->clearSent();
    echo "   🗑️  Cleared sent emails: {$sentCleared}\n";
    
    $failedCleared = $mailQueue->clearFailed();
    echo "   🗑️  Cleared failed emails: {$failedCleared}\n";
} catch (Exception $e) {
    echo "   ⚠️  Cleanup error: " . $e->getMessage() . "\n";
}

echo "\n🎉 **Phase 3 Queue System Features Demonstrated:**\n";
echo "   ✅ Email queueing with priority support\n";
echo "   ✅ Batch processing with configurable limits\n";
echo "   ✅ Queue statistics and monitoring\n";
echo "   ✅ Retry logic and error handling\n";
echo "   ✅ Performance metrics tracking\n";
echo "   ✅ Queue management operations\n";
echo "   ✅ Database persistence and reliability\n";

echo "\n🔮 **Next: Phase 4 - Template Integration**\n";
echo "   🚧 View system integration for email templates\n";
echo "   🚧 Mailable classes for object-oriented emails\n";
echo "   🚧 HTML and plain text template support\n";

echo "\n📁 **CLI Commands Available:**\n";
echo "   • treehouse mail:queue:work - Process queue manually\n";
echo "   • treehouse mail:queue:status - Show queue status\n";
echo "   • treehouse mail:queue:clear - Clear failed/sent emails\n";

echo "\n🤖 **Cron Job Integration:**\n";
echo "   • MailQueueProcessor runs every minute\n";
echo "   • Automatic queue processing in background\n";
echo "   • Configurable batch sizes and retry strategies\n";

echo "\n✨ **Phase 3 Complete - Production-Ready Queue System!** ✨\n";