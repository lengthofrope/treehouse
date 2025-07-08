# TreeHouse Mail System

A comprehensive zero-dependency mailing system for the TreeHouse Framework featuring queue-based processing, multiple mail drivers, and performance tracking.

## Overview

The TreeHouse Mail System provides enterprise-grade email functionality with:

- **Multiple Mail Drivers**: SMTP, Sendmail, and Log drivers
- **Queue System**: Database-backed queue with retry logic and performance tracking
- **Cron Processing**: Automated email processing via cron jobs
- **Template Integration**: Seamless integration with TreeHouse View system
- **Zero Dependencies**: Built entirely using TreeHouse framework components

## Architecture

```
src/TreeHouse/Mail/
├── Queue/
│   └── QueuedMail.php          # ActiveRecord model for email queue
├── Mailers/                    # Mail driver implementations (planned)
├── Messages/                   # Mail message classes (planned)
├── Templates/                  # Template integration (planned)
├── Events/                     # Mail-specific events (planned)
├── Exceptions/                 # Mail-specific exceptions (planned)
└── README.md                   # This file
```

## Phase 1 Implementation Status ✅

### Completed Features

#### 1. Database Foundation
- **Migration**: `009_create_queued_mails_table.php` 
- **Enhanced Schema**: Includes performance metrics and retry tracking
- **QueuedMail Model**: Full ActiveRecord implementation with advanced features

#### 2. Queue Management
The `QueuedMail` model provides comprehensive queue functionality:

**Core Features:**
- Queue status tracking (pending, processing, sent, failed)
- Priority-based processing (1=highest, 10=lowest)
- Configurable retry logic with exponential backoff
- Automatic timestamp management

**Performance Tracking:**
- `queue_time`: Time spent waiting in queue
- `processing_time`: Time to process the email
- `delivery_time`: Actual mail delivery time
- Performance analytics and metrics

**Retry Logic:**
- Configurable maximum attempts
- Exponential backoff delay calculation
- Intelligent next retry scheduling
- Failure tracking with error messages

#### 3. Status Management
- **Real-time Status**: Dynamic status calculation based on current state
- **Availability Check**: Smart availability detection considering reservations
- **Expiration Handling**: Automatic cleanup of expired reservations
- **Statistics**: Queue health and performance metrics

#### 4. Advanced Query Methods
- `getAvailableForProcessing()`: Get emails ready for processing
- `getFailedForRetry()`: Get failed emails eligible for retry
- `getQueueStats()`: Overall queue statistics
- `getPerformanceMetrics()`: Detailed performance analytics
- `cleanupOldEmails()`: Maintenance and cleanup

### Database Schema

```sql
CREATE TABLE queued_mails (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Email Content
    to_addresses JSON NOT NULL,
    from_address JSON NOT NULL,
    cc_addresses JSON NULL,
    bcc_addresses JSON NULL,
    subject VARCHAR(998) NOT NULL,
    body_text TEXT NULL,
    body_html TEXT NULL,
    attachments JSON NULL,
    headers JSON NULL,
    
    -- Mail Configuration
    mailer VARCHAR(50) NOT NULL DEFAULT 'default',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
    
    -- Retry Logic
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at TIMESTAMP NULL,
    next_retry_at TIMESTAMP NULL,
    
    -- Queue Management
    available_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reserved_at TIMESTAMP NULL,
    reserved_until TIMESTAMP NULL,
    
    -- Status Tracking
    failed_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL,
    
    -- Performance Metrics
    queue_time DECIMAL(10,3) NULL,
    processing_time DECIMAL(8,3) NULL,
    delivery_time DECIMAL(8,3) NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Performance Indexes
    INDEX idx_available_reserved (available_at, reserved_at),
    INDEX idx_mailer_priority (mailer, priority, available_at),
    INDEX idx_status (failed_at, sent_at),
    INDEX idx_retry (next_retry_at, attempts, max_attempts),
    INDEX idx_performance (queue_time, processing_time, delivery_time),
    INDEX idx_last_attempt (last_attempt_at)
);
```

## Usage Examples

### Basic Queue Operations

```php
use LengthOfRope\TreeHouse\Mail\Queue\QueuedMail;

// Create a new queued email
$email = QueuedMail::create([
    'to_addresses' => [['email' => 'user@example.com', 'name' => 'User']],
    'from_address' => ['email' => 'system@example.com', 'name' => 'System'],
    'subject' => 'Welcome Email',
    'body_html' => '<h1>Welcome!</h1>',
    'body_text' => 'Welcome!',
    'mailer' => 'smtp',
    'priority' => QueuedMail::PRIORITY_HIGH,
]);

// Get emails ready for processing
$availableEmails = QueuedMail::getAvailableForProcessing('smtp', 10);

// Process an email
foreach ($availableEmails as $email) {
    $email->startProcessing();
    
    try {
        // Send email logic here...
        $deliveryTime = 0.5; // seconds
        $email->markAsProcessed($deliveryTime);
    } catch (Exception $e) {
        $email->markAsFailed($e->getMessage());
    }
}
```

### Performance Monitoring

```php
// Get queue statistics
$stats = QueuedMail::getQueueStats();
// Returns: ['total', 'pending', 'processing', 'sent', 'failed']

// Get performance metrics for last 24 hours
$metrics = QueuedMail::getPerformanceMetrics(24);
// Returns: avg/max times for queue, processing, delivery

// Queue health monitoring
$pendingCount = $stats['pending'];
$failureRate = $stats['failed'] / max(1, $stats['total']);

if ($failureRate > 0.1) {
    // Alert: High failure rate (>10%)
}
```

### Status and Priority Management

```php
// Priority constants
QueuedMail::PRIORITY_HIGHEST; // 1
QueuedMail::PRIORITY_HIGH;    // 2
QueuedMail::PRIORITY_NORMAL;  // 5 (default)
QueuedMail::PRIORITY_LOW;     // 8
QueuedMail::PRIORITY_LOWEST;  // 10

// Status constants
QueuedMail::STATUS_PENDING;    // 'pending'
QueuedMail::STATUS_PROCESSING; // 'processing'
QueuedMail::STATUS_SENT;       // 'sent'
QueuedMail::STATUS_FAILED;     // 'failed'

// Check email status
$status = $email->getStatus();
$isAvailable = $email->isAvailable();
$canRetry = $email->canRetry();
$hasExpired = $email->hasExpired();
```

## Testing

Phase 1 includes comprehensive unit tests:

```bash
# Run mail queue tests
./vendor/bin/phpunit tests/Unit/Mail/Queue/QueuedMailTest.php

# Test coverage includes:
# - Model instantiation and casting
# - Status management and transitions
# - Priority and retry logic
# - Performance tracking methods
# - Queue availability logic
```

## Next Phases

### Phase 2: Core Mail System (Planned)
- Mail drivers (SMTP, Sendmail, Log)
- MailManager service orchestrator
- Message and Address classes
- Service container registration

### Phase 3: Queue Processing (Planned)
- MailQueueProcessor cron job
- Batch processing with configurable limits
- CLI commands for queue management
- Integration with existing cron system

### Phase 4: Template Integration (Planned)
- ViewFactory integration
- Mailable classes
- HTML and plain text templates
- Email template examples

### Phase 5: Advanced Features (Planned)
- Attachment support
- Mail events integration
- Validation system
- Exception hierarchy

## Framework Integration

The Mail system follows TreeHouse's architectural patterns:

- **Zero Dependencies**: Uses only framework components
- **ActiveRecord Pattern**: Database models extend TreeHouse ActiveRecord
- **Laravel-Inspired API**: Familiar method signatures and patterns
- **Service Container**: Dependency injection integration
- **Events Integration**: Mail lifecycle events
- **Cron Integration**: Scheduled queue processing

## Performance Considerations

- **Indexed Queries**: All queue queries use optimized database indexes
- **Batch Processing**: Configurable batch sizes prevent memory issues
- **Exponential Backoff**: Intelligent retry delays reduce system load
- **Performance Metrics**: Built-in monitoring for optimization
- **Cleanup Operations**: Automatic cleanup of old processed emails

---

*Part of the TreeHouse Framework - A zero-dependency PHP web framework*