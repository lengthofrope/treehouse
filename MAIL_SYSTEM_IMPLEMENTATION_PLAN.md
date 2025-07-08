# TreeHouse Framework - Mail System Implementation Plan

## Overview

This document outlines the complete implementation plan for adding comprehensive mailing capabilities to the TreeHouse Framework. The mail system will include multiple drivers (SMTP, Sendmail, Log), a robust queue system using ActiveRecord models, template support, and cron-based processing.

## Architecture Design

### Core Principles
- **Zero External Dependencies**: Uses only TreeHouse framework components
- **Framework Integration**: Leverages existing ActiveRecord, Events, Cron, and View systems
- **Performance Focused**: Queue system with retry logic and performance tracking
- **Laravel-Inspired API**: Familiar patterns for easy adoption
- **Extensible Design**: Driver pattern allows adding new mail transports

### Mail Layer Structure

```
src/TreeHouse/Mail/
├── MailManager.php              # Main mail service orchestrator
├── Mailers/                     # Mail driver implementations
│   ├── MailerInterface.php      # Contract for all mailers
│   ├── SmtpMailer.php          # SMTP driver (using framework's Connection pattern)
│   ├── SendmailMailer.php      # Sendmail driver
│   └── LogMailer.php           # Log-only driver for testing
├── Queue/                       # Mail queue system
│   ├── QueuedMail.php          # ActiveRecord model for queued emails
│   ├── MailQueue.php           # Queue management service
│   └── MailQueueProcessor.php  # Cron job for processing queue
├── Messages/                    # Mail message classes
│   ├── Message.php             # Base mail message class
│   ├── Mailable.php            # Abstract mailable class
│   └── MailableInterface.php   # Contract for mailable classes
├── Templates/                   # Mail template support
│   ├── MailTemplate.php        # Mail template class
│   └── TemplateRenderer.php    # Integrates with View layer
├── Attachments/                 # Attachment handling
│   ├── Attachment.php          # File attachment class
│   └── AttachmentManager.php   # Attachment processing
├── Events/                      # Mail-specific events
│   ├── MailSending.php         # Before mail is sent
│   ├── MailSent.php           # After mail is sent successfully
│   ├── MailFailed.php         # When mail sending fails
│   └── MailQueued.php         # When mail is queued
├── Exceptions/                  # Mail-specific exceptions
│   ├── MailException.php       # Base mail exception
│   ├── MailerException.php     # Driver-specific exceptions
│   ├── QueueException.php      # Queue-related exceptions
│   └── TemplateException.php   # Template-related exceptions
├── Validation/                  # Mail validation
│   ├── MailValidator.php       # Email address validation
│   └── Rules/                  # Custom validation rules
│       ├── ValidEmail.php      # Email format validation
│       └── MaxAttachments.php  # Attachment limits
├── Support/                     # Mail utilities
│   ├── Address.php             # Email address class
│   ├── AddressList.php        # Collection of addresses
│   └── MimeType.php           # MIME type detection
├── helpers.php                  # Global mail() helper function
└── README.md                    # Mail layer documentation
```

## Database Schema

### QueuedMails Table

**File**: `database/migrations/009_create_queued_mails_table.php`

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
    queue_time DECIMAL(10,3) NULL COMMENT 'Time spent in queue (seconds)',
    processing_time DECIMAL(8,3) NULL COMMENT 'Time to process email (seconds)',
    delivery_time DECIMAL(8,3) NULL COMMENT 'Time for actual delivery (seconds)',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for Performance
    INDEX idx_available_reserved (available_at, reserved_at),
    INDEX idx_mailer_priority (mailer, priority, available_at),
    INDEX idx_status (failed_at, sent_at),
    INDEX idx_retry (next_retry_at, attempts, max_attempts),
    INDEX idx_performance (queue_time, processing_time, delivery_time),
    INDEX idx_last_attempt (last_attempt_at)
);
```

## Configuration

### config/mail.php

```php
return [
    'default' => env('MAIL_MAILER', 'smtp'),
    
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'localhost'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 60,
        ],
        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs'),
        ],
        'log' => [
            'transport' => 'log',
            'channel' => 'mail',
        ],
    ],
    
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],
    
    'queue' => [
        'enabled' => env('MAIL_QUEUE_ENABLED', true),
        'batch_size' => env('MAIL_QUEUE_BATCH_SIZE', 10),
        'max_attempts' => env('MAIL_QUEUE_MAX_ATTEMPTS', 3),
        
        // Enhanced Retry Configuration
        'retry_strategy' => env('MAIL_QUEUE_RETRY_STRATEGY', 'exponential'), // linear, exponential
        'base_retry_delay' => env('MAIL_QUEUE_BASE_RETRY_DELAY', 300), // 5 minutes
        'max_retry_delay' => env('MAIL_QUEUE_MAX_RETRY_DELAY', 3600), // 1 hour
        'retry_multiplier' => env('MAIL_QUEUE_RETRY_MULTIPLIER', 2),
        
        // Performance Monitoring
        'performance_tracking' => env('MAIL_QUEUE_PERFORMANCE_TRACKING', true),
        'slow_query_threshold' => env('MAIL_QUEUE_SLOW_THRESHOLD', 5.0), // seconds
        'enable_metrics_logging' => env('MAIL_QUEUE_METRICS_LOGGING', true),
    ],
    
    'templates' => [
        'path' => 'emails',
        'cache_enabled' => true,
    ],
    
    'monitoring' => [
        'queue_health_check' => true,
        'alert_on_slow_processing' => true,
        'alert_on_high_failure_rate' => true,
        'failure_rate_threshold' => 0.1, // 10%
    ],
];
```

## Implementation Phases

### Phase 1: Database Foundation ✅ COMPLETED
**Goal**: Create the database foundation for the mail queue system

**Tasks**:
1. ✅ Create migration file: `009_create_queued_mails_table.php`
2. ✅ Create `QueuedMail` ActiveRecord model with performance tracking methods
3. ✅ Test migration and model integration
4. ✅ Add model to framework autoloading
5. ✅ **Fix TreeHouse Framework Bugs** (discovered during implementation)

**Files Created**:
- ✅ `database/migrations/009_create_queued_mails_table.php`
- ✅ `src/TreeHouse/Mail/Queue/QueuedMail.php`
- ✅ `tests/Unit/Mail/Queue/QueuedMailTest.php`
- ✅ `src/TreeHouse/Mail/README.md`

**Framework Enhancements Made**:
- ✅ **Enhanced ActiveRecord**: Added universal JSON casting for all models
- ✅ **Fixed Connection.statement()**: Removed silent exception handling that was masking errors
- ✅ **Fixed Migration Runner**: Added `IF NOT EXISTS` to migrations table creation
- ✅ **Fixed Blueprint Column**: Fixed SQL function defaults (CURRENT_TIMESTAMP) being quoted
- ✅ **Fixed SQLite ALTER Operations**: Added proper database-specific handling for ALTER TABLE with indexes

**Deliverables**:
- ✅ Working database table with all required fields and indexes (27 columns)
- ✅ ActiveRecord model with automatic JSON casting and fillable properties (360+ lines)
- ✅ Performance tracking methods (markAsQueued, startProcessing, etc.)
- ✅ **Comprehensive test suite** (29 tests, 68 assertions, 100% passing)
- ✅ **Enhanced TreeHouse Framework** with multiple critical bug fixes
- ✅ **Cross-database compatibility** (MySQL, SQLite, PostgreSQL)

**Critical Bugs Fixed in TreeHouse Framework**:
1. **Connection.statement() Silent Failures** - Method was catching all exceptions and returning false, preventing proper error reporting
2. **Migration Table Creation** - Using `CREATE TABLE` instead of `CREATE TABLE IF NOT EXISTS` causing failures
3. **SQL Function Defaults** - CURRENT_TIMESTAMP being wrapped in quotes making it invalid
4. **SQLite ALTER TABLE Operations** - Using MySQL syntax for SQLite causing "syntax error near `column`" issues
5. **Universal JSON Casting** - Added automatic JSON encoding/decoding for all ActiveRecord models

**Technical Validation**:
- ✅ All 1809 framework tests passing (5083 assertions)
- ✅ All 29 mail system tests passing (68 assertions)
- ✅ All 108 database tests passing (281 assertions)
- ✅ Migration system working reliably across all databases
- ✅ Framework stability maintained while adding new functionality

### Phase 2: Core Mail System
**Goal**: Implement the foundational mail system with multiple drivers

**Tasks**:
1. Create Mail layer directory structure
2. Implement `MailManager` as central service orchestrator
3. Create mail driver implementations (SMTP, Sendmail, Log)
4. Create basic message and address classes
5. Add service registration to `Application.php`
6. Create global helper functions

**Files to Create**:
- `src/TreeHouse/Mail/MailManager.php`
- `src/TreeHouse/Mail/Mailers/MailerInterface.php`
- `src/TreeHouse/Mail/Mailers/SmtpMailer.php`
- `src/TreeHouse/Mail/Mailers/SendmailMailer.php`
- `src/TreeHouse/Mail/Mailers/LogMailer.php`
- `src/TreeHouse/Mail/Messages/Message.php`
- `src/TreeHouse/Mail/Support/Address.php`
- `src/TreeHouse/Mail/Support/AddressList.php`
- `src/TreeHouse/Mail/helpers.php`
- `config/mail.php`

**Deliverables**:
- Working mail system with immediate sending capability
- Multiple driver support with configuration-based selection
- Basic email composition and sending functionality

### Phase 3: Queue System
**Goal**: Implement comprehensive queue system with cron processing

**Tasks**:
1. Implement `MailQueue` service for queue management
2. Create `MailQueueProcessor` cron job
3. Add queue configuration to mail config
4. Create queue management CLI commands
5. Implement retry logic with exponential backoff
6. Add performance metrics tracking

**Files to Create**:
- `src/TreeHouse/Mail/Queue/MailQueue.php`
- `src/TreeHouse/Mail/Queue/MailQueueProcessor.php`
- `src/TreeHouse/Console/Commands/MailCommands/MailQueueWorkCommand.php`
- `src/TreeHouse/Console/Commands/MailCommands/MailQueueStatusCommand.php`
- `src/TreeHouse/Console/Commands/MailCommands/MailQueueClearCommand.php`
- `src/TreeHouse/Console/Commands/MailCommands/MailQueueRetryCommand.php`

**Deliverables**:
- Functional queue system with database persistence
- Cron job processing queued emails every minute
- Retry logic with configurable strategies
- CLI commands for queue management
- Performance metrics collection

### Phase 4: Template Integration
**Goal**: Integrate with TreeHouse View system for email templates

**Tasks**:
1. Create `MailTemplate` class integrating with ViewFactory
2. Implement `TemplateRenderer` for email-specific rendering
3. Create `Mailable` abstract class for email objects
4. Add template rendering support to mail system
5. Create example email templates
6. Support both HTML and plain text templates

**Files to Create**:
- `src/TreeHouse/Mail/Templates/MailTemplate.php`
- `src/TreeHouse/Mail/Templates/TemplateRenderer.php`
- `src/TreeHouse/Mail/Messages/Mailable.php`
- `src/TreeHouse/Mail/Messages/MailableInterface.php`
- `resources/views/emails/layouts/app.php`
- `resources/views/emails/welcome.php`
- `resources/views/emails/test.php`

**Deliverables**:
- Template system integration with existing View layer
- HTML and plain text email support
- Mailable classes for object-oriented email composition
- Example templates and layouts

### Phase 5: Advanced Features & Testing
**Goal**: Complete the mail system with advanced features and comprehensive testing

**Tasks**:
1. Add attachment support with file validation
2. Implement mail events integration
3. Create validation system for email addresses and content
4. Add exception hierarchy for proper error handling
5. Create comprehensive test suite
6. Add documentation and usage examples
7. Performance optimization and benchmarking

**Files to Create**:
- `src/TreeHouse/Mail/Attachments/Attachment.php`
- `src/TreeHouse/Mail/Attachments/AttachmentManager.php`
- `src/TreeHouse/Mail/Events/MailSending.php`
- `src/TreeHouse/Mail/Events/MailSent.php`
- `src/TreeHouse/Mail/Events/MailFailed.php`
- `src/TreeHouse/Mail/Events/MailQueued.php`
- `src/TreeHouse/Mail/Validation/MailValidator.php`
- `src/TreeHouse/Mail/Validation/Rules/ValidEmail.php`
- `src/TreeHouse/Mail/Validation/Rules/MaxAttachments.php`
- `src/TreeHouse/Mail/Exceptions/MailException.php`
- `src/TreeHouse/Mail/Exceptions/MailerException.php`
- `src/TreeHouse/Mail/Exceptions/QueueException.php`
- `src/TreeHouse/Mail/Exceptions/TemplateException.php`
- `src/TreeHouse/Mail/Support/MimeType.php`
- `tests/Unit/Mail/` (complete test suite)
- `src/TreeHouse/Mail/README.md`

**Deliverables**:
- Complete mail system with all advanced features
- Comprehensive test coverage
- Full documentation with examples
- Performance benchmarks and optimization

## API Design Examples

### Basic Usage
```php
// Send immediate email
mail()->to('user@example.com')
    ->subject('Welcome!')
    ->view('emails.welcome', ['user' => $user])
    ->send();

// Queue email for later processing
mail()->to('user@example.com')
    ->subject('Newsletter')
    ->view('emails.newsletter')
    ->queue();

// Send with attachments
mail()->to('user@example.com')
    ->subject('Invoice')
    ->view('emails.invoice', ['invoice' => $invoice])
    ->attach('/path/to/invoice.pdf')
    ->send();
```

### Mailable Classes
```php
class WelcomeEmail extends Mailable
{
    public function __construct(private User $user) {}
    
    public function build(): void
    {
        $this->to($this->user->email)
            ->subject('Welcome to TreeHouse!')
            ->view('emails.welcome', ['user' => $this->user]);
    }
}

// Usage
(new WelcomeEmail($user))->send();
```

### Queue Management
```php
// Queue with priority and delay
mail()->to('user@example.com')
    ->subject('Important Notice')
    ->view('emails.notice')
    ->priority(1)              // High priority
    ->delay(Carbon::now()->addMinutes(30))  // Send in 30 minutes
    ->queue();

// Queue with custom retry settings
mail()->to('user@example.com')
    ->subject('Critical Alert')
    ->view('emails.alert')
    ->maxAttempts(5)           // Try up to 5 times
    ->retryDelay(600)          // Wait 10 minutes between retries
    ->queue();
```

## CLI Commands

### Queue Management
```bash
# Process queue manually
treehouse mail:queue:work

# Show queue status with performance metrics
treehouse mail:queue:status --metrics

# Show performance analytics
treehouse mail:queue:analytics --period=1h

# Show slow performing emails
treehouse mail:queue:slow --threshold=5

# Clear failed jobs
treehouse mail:queue:clear-failed

# Retry failed jobs
treehouse mail:queue:retry

# Export performance data
treehouse mail:queue:export --format=csv --period=24h

# Send test email
treehouse mail:test user@example.com

# Generate mailable class
treehouse make:mail WelcomeEmail
```

## Framework Integration Points

### Service Registration (Application.php)
```php
private function registerMailServices(): void
{
    $this->container->singleton('mail', function () {
        $config = $this->config['mail'] ?? [];
        return new \LengthOfRope\TreeHouse\Mail\MailManager($config, $this->container);
    });
    
    $this->container->singleton('mail.queue', function () {
        $config = $this->config['mail']['queue'] ?? [];
        return new \LengthOfRope\TreeHouse\Mail\Queue\MailQueue($config);
    });
}
```

### Cron Job Registration (config/cron.php)
```php
'jobs' => [
    \LengthOfRope\TreeHouse\Mail\Queue\MailQueueProcessor::class,
],
```

### Event Integration
- Mail events integrate with TreeHouse's Events system
- Model events fire during queue operations
- Listeners can be registered for mail lifecycle events

## Key Benefits

1. **Zero Dependencies**: Uses only TreeHouse framework components
2. **Familiar Patterns**: Follows Laravel-inspired API for easy adoption
3. **Framework Integration**: Leverages existing ActiveRecord, Events, Cron, and View systems
4. **Extensible**: Driver pattern allows adding new mail transports
5. **Reliable**: Queue system with retry logic and failure handling
6. **Performance**: Batch processing with configurable limits and performance tracking
7. **Testable**: Log driver for development and testing
8. **Secure**: Uses framework's validation and security features
9. **Observable**: Comprehensive performance metrics and monitoring
10. **Maintainable**: Clean architecture with separation of concerns

## Success Criteria

**Phase 1 Completed** ✅:
- [x] **Database foundation with queue persistence** - QueuedMail ActiveRecord model with 27-column schema
- [x] **Performance tracking and retry logic** - Built-in timing, metrics, and exponential backoff
- [x] **Full test coverage for Phase 1** - 29 tests with 68 assertions, 100% passing
- [x] **Complete documentation** - Comprehensive README with examples
- [x] **Zero external dependencies maintained** - Uses only TreeHouse framework components
- [x] **Framework integration following TreeHouse patterns** - Leverages existing ActiveRecord system
- [x] **Enhanced framework reliability** - Fixed 5 critical bugs in TreeHouse core

**Remaining Phases**:
- [ ] Complete mail system with SMTP, Sendmail, and Log drivers
- [ ] Cron-based processing with configurable batch sizes
- [ ] Template integration with existing View system
- [ ] Comprehensive CLI commands for queue management

## Timeline Estimate

- **Phase 1**: 1-2 days (Database Foundation)
- **Phase 2**: 3-4 days (Core Mail System)
- **Phase 3**: 3-4 days (Queue System)
- **Phase 4**: 2-3 days (Template Integration)
- **Phase 5**: 4-5 days (Advanced Features & Testing)

**Total Estimated Time**: 13-18 days

---

*This implementation plan maintains TreeHouse's philosophy of being self-contained while providing enterprise-grade mailing capabilities that integrate seamlessly with the existing architecture.*