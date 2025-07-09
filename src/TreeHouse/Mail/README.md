# TreeHouse Mail System

A comprehensive email system for the TreeHouse Framework with multiple drivers, queue support, automated processing, and performance tracking.

## 🚀 Status: Phase 5 Complete ✅

**Completed Features:**
- ✅ **Phase 1**: Database Foundation with QueuedMail model and framework enhancements
- ✅ **Phase 2**: Core Mail System with multiple drivers and fluent interface
- ✅ **Phase 3**: Complete Queue System with CLI tools, automated processing, and retry logic
- ✅ **Phase 4**: Template Integration with Mailable classes and email-specific templates
- ✅ **Phase 5**: Advanced Features with attachments, events, validation, and production polish

## 📋 Current Features (Phase 1, 2, 3 & 4 Complete)

### Phase 1: Database Foundation
- **QueuedMail ActiveRecord Model**: 27-column schema with performance tracking
- **Database Migration**: Production-ready migration with indexes
- **Enhanced ActiveRecord**: Universal JSON casting for all models
- **Framework Bug Fixes**: Fixed 5 critical TreeHouse framework bugs
- **Comprehensive Tests**: 29 tests covering all queue functionality

### Phase 2: Core Mail System
- **Multiple Mail Drivers**: SMTP (SSL/TLS), Sendmail, Log drivers
- **MailManager**: Central orchestrator with driver management
- **Fluent Interface**: Laravel-inspired API for email composition
- **Address Classes**: RFC-compliant email validation and parsing
- **Message System**: Full validation and multipart support
- **Helper Functions**: Simple `sendMail()`, `queueMail()`, `mailer()` functions
- **Framework Integration**: Registered in Application container
- **Production-Ready SMTP**: Full authentication and encryption support

### Phase 3: Complete Queue System
- **Queue Management Commands**: `mail:queue:status`, `mail:queue:work`, `mail:queue:clear`, `mail:queue:retry`
- **Automated Processing**: Built-in cron job (`mail:queue:process`) runs every minute
- **Retry Logic**: Exponential backoff with configurable attempts and delays
- **Performance Monitoring**: Real-time queue metrics, warnings, and health checks
- **Centralized Cron Management**: Single source of truth for built-in framework jobs
- **Comprehensive CLI**: Full help documentation, dry-run modes, and option validation
- **Production Queue**: Database persistence, reservation system, batch processing

### Phase 4: Template Integration
- **Mailable Base Class**: Laravel-style mailable classes for organized email logic
- **Email Templates**: TreeHouse template engine integration with email-safe layouts
- **EmailRenderer**: Specialized renderer for email templates with context injection
- **Template Generation**: `make:mailable` command for generating mailable classes
- **Email Layouts**: Professional email layouts with responsive design
- **Auto Text Generation**: Automatic plain text generation from HTML templates
- **Template Helpers**: Email-specific helper functions for rendering
- **Framework Integration**: Seamless integration with existing TreeHouse View system

### Phase 5: Advanced Features
- **File Attachments**: Support for file attachments with MIME type detection and size validation
- **Data Attachments**: Attach raw data as files without writing to disk
- **Event System**: Full integration with TreeHouse events (MailSending, MailSent, MailFailed, MailQueued)
- **Email Validation**: Comprehensive validation with spam detection and security checks
- **Event Context**: Rich event data with performance metrics and error details
- **Attachment Security**: File type validation and size limits for security
- **Production Polish**: Enhanced error handling, graceful fallbacks, and robust testing
- **Event Propagation**: Support for cancelling emails and stopping event propagation

## 🎯 Quick Start

### Basic Usage

```php
// Simple email (using helper function)
sendMail('user@example.com', 'Welcome!', 'Welcome to our application!');

// HTML email with helper
sendMail(
    'user@example.com',
    'Newsletter',
    '<h1>Monthly Newsletter</h1><p>Content here...</p>',
    ['X-Priority' => '1']
);

// Fluent interface
mailer()
    ->to('user@example.com')
    ->subject('Welcome!')
    ->html('<h1>Welcome!</h1>')
    ->text('Welcome to our application!')
    ->priority(2)
    ->send();

// Multiple recipients
mailer()
    ->to(['user1@example.com', 'user2@example.com'])
    ->cc('manager@example.com')
    ->subject('Team Update')
    ->html('<p>Update content</p>')
    ->send();

// Queue for later processing
queueMail('user@example.com', 'Queued Email', 'This will be processed by the queue.');
```

### Template-Based Emails (Phase 4)

```php
// Using Mailable classes
use App\Mail\WelcomeEmail;

// Send welcome email immediately
$welcomeEmail = new WelcomeEmail($user);
$welcomeEmail->send($user->email);

// Queue welcome email
$welcomeEmail = new WelcomeEmail($user);
$welcomeEmail->queue($user->email, 1); // High priority

// Using email templates directly
$html = renderEmail('emails.welcome', [
    'user' => $user,
    'dashboard_url' => url('/dashboard')
]);

// Send templated email with helper
sendMail($user->email, 'Welcome!', renderEmail('emails.welcome', ['user' => $user]));
```

### CLI Commands (Phase 4)

```bash
# Generate a new Mailable class
treehouse make:mailable WelcomeEmail

# Generate with custom template
treehouse make:mailable OrderConfirmation --template=emails.orders.confirmation

# Force overwrite existing file
treehouse make:mailable NewsletterEmail --force
```

### Advanced Features (Phase 5)

```php
// File attachments
$message = mailer()->compose()
    ->to('user@example.com')
    ->subject('Invoice with attachments')
    ->html('<p>Please find your invoice attached.</p>')
    ->attach('/path/to/invoice.pdf', ['as' => 'Invoice-2024.pdf'])
    ->attach('/path/to/receipt.jpg')
    ->send();

// Data attachments (no file required)
$csvData = "Name,Email\nJohn,john@example.com\nJane,jane@example.com";
$message = mailer()->compose()
    ->to('admin@example.com')
    ->subject('User Export')
    ->attachData($csvData, 'users.csv', ['mime' => 'text/csv'])
    ->send();

// Event listeners
app('events')->listen(MailSending::class, function(MailSending $event) {
    // Log all outgoing emails
    log('Sending email: ' . $event->getSubject());
    
    // Cancel emails to blocked domains
    foreach ($event->getRecipients() as $email) {
        if (str_ends_with($email, '@blocked-domain.com')) {
            $event->cancel();
            break;
        }
    }
});

app('events')->listen(MailSent::class, function(MailSent $event) {
    // Track email performance
    analytics()->track('email_sent', [
        'subject' => $event->getSubject(),
        'mailer' => $event->getMailerUsed(),
        'send_time' => $event->getSendTime(),
        'has_attachments' => $event->hasAttachments(),
    ]);
});

// Email validation
use LengthOfRope\TreeHouse\Mail\Validation\EmailValidator;

$validator = new EmailValidator([
    'max_attachment_size' => 5 * 1024 * 1024, // 5MB
    'max_recipients' => 50,
    'blocked_domains' => ['spam-domain.com'],
    'check_disposable_emails' => true,
]);

if (!$validator->validate($message)) {
    foreach ($validator->getErrors() as $error) {
        echo "Validation error: {$error}\n";
    }
}
```

## 🛠️ CLI Commands Overview

The TreeHouse Mail System provides comprehensive CLI tools for email management:

### Mail Generation Commands
```bash
# Generate a new Mailable class
treehouse make:mailable WelcomeEmail

# Generate with custom template
treehouse make:mailable OrderConfirmation --template=emails.orders.confirmation

# Force overwrite existing file
treehouse make:mailable NewsletterEmail --force
```

### Queue Management Commands
```bash
# Check queue status and statistics
treehouse mail:queue:status

# Show detailed queue information with metrics
treehouse mail:queue:status --details --metrics

# Process emails from the queue (manual)
treehouse mail:queue:work --limit=50

# Run queue worker continuously (development only)
treehouse mail:queue:work --continuous --timeout=300

# Retry failed emails with filtering
treehouse mail:queue:retry --limit=20 --older-than=60

# Retry specific emails by ID
treehouse mail:queue:retry 1,2,3 --force

# Dry run to see what would be retried
treehouse mail:queue:retry --dry-run --max-attempts=2

# Clear failed emails from queue
treehouse mail:queue:clear --failed

# Clear sent emails
treehouse mail:queue:clear --sent

# Clear all processed emails (with confirmation)
treehouse mail:queue:clear --all

# Force clear without confirmation
treehouse mail:queue:clear --all --force
```

### Built-in Cron Integration
```bash
# The following cron job runs automatically every minute:
# mail:queue:process - Processes pending emails in the background

# View all cron jobs (includes mail processing)
treehouse cron:list

# Run cron manually (processes mail queue among other jobs)
treehouse cron:run

# Test what cron would do
treehouse cron:run --dry-run
```

### Command Features
- **Generation Commands**: Scaffold Mailable classes with template validation
- **Queue Commands**: Full queue lifecycle management with safety checks
- **Status Commands**: Real-time metrics, health warnings, performance stats
- **Processing Commands**: Manual and automated queue processing
- **Retry Commands**: Smart retry logic with exponential backoff
- **Clear Commands**: Selective cleanup with confirmation prompts

### Queue Management (Phase 3)

```bash
# Check queue status
php bin/treehouse mail:queue:status

# Show detailed queue information with metrics
php bin/treehouse mail:queue:status --details --metrics

# Process emails from the queue (manual)
php bin/treehouse mail:queue:work --limit=50

# Run queue worker continuously (development only)
php bin/treehouse mail:queue:work --continuous --timeout=300

# Retry failed emails
php bin/treehouse mail:queue:retry --limit=20 --older-than=60

# Retry specific emails by ID
php bin/treehouse mail:queue:retry 1,2,3 --force

# Dry run to see what would be retried
php bin/treehouse mail:queue:retry --dry-run --max-attempts=2

# Clear failed emails
php bin/treehouse mail:queue:clear --failed

# Clear sent emails
php bin/treehouse mail:queue:clear --sent

# Clear all processed emails (with confirmation)
php bin/treehouse mail:queue:clear --all

# Force clear without confirmation
php bin/treehouse mail:queue:clear --all --force
```

### Automated Processing

The mail queue is automatically processed every minute by the built-in cron job:

```bash
# View cron jobs (includes mail:queue:process)
php bin/treehouse cron:list

# Run cron manually (processes due jobs including mail queue)
php bin/treehouse cron:run

# Test what cron would do
php bin/treehouse cron:run --dry-run
```

### Configuration

The system uses `config/mail.php`:

```php
return [
    'default' => env('MAIL_MAILER', 'log'), // smtp, sendmail, log
    
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'localhost'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'), // tls, ssl
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
            'path' => 'storage/logs/mail.log',
        ],
    ],
    
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],
    
    // Queue Configuration (Phase 3)
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
        'performance_tracking' => true,
        'queue_health_check' => true,
        'alert_on_slow_processing' => true,
        'alert_on_high_failure_rate' => true,
        'failure_rate_threshold' => 0.1, // 10%
    ],
];
```

## 🏗️ Architecture

### Mail Drivers (Phase 2)

**SMTP Driver** (`SmtpMailer`):
- Native PHP socket implementation
- SSL/TLS encryption support
- SMTP authentication (LOGIN)
- Multipart message support
- Proper error handling

**Sendmail Driver** (`SendmailMailer`):
- Uses PHP's `mail()` function
- Configurable sendmail path
- Header and body formatting

**Log Driver** (`LogMailer`):
- Logs emails to files
- Perfect for development/testing
- Detailed formatting with all headers
- File size and entry management

### Queue System (Phase 3)

**MailQueue** (`MailQueue`):
- Database persistence with reservation system
- Batch processing with configurable limits
- Performance metrics tracking
- Exponential backoff retry logic
- Health monitoring and statistics

**MailQueueProcessor** (`MailQueueProcessor`):
- Built-in cron job running every minute
- Automatic queue processing
- Error handling and logging
- Performance tracking
- Configurable via mail configuration

**Console Commands**:
- `MailQueueStatusCommand` - Queue monitoring and statistics
- `MailQueueWorkCommand` - Manual queue processing
- `MailQueueClearCommand` - Queue cleanup with confirmation
- `MailQueueRetryCommand` - Failed email retry with filtering

### Address Management

**Address Class**:
```php
$address = new Address('user@example.com', 'John Doe');
echo $address->toString(); // "John Doe" <user@example.com>

// Parse from strings
$address = Address::parse('"Jane Doe" <jane@example.com>');
```

**AddressList Class**:
```php
$list = new AddressList();
$list->add('user1@example.com');
$list->add(new Address('user2@example.com', 'User Two'));

// Supports ArrayAccess and Iterator
$list[0]; // First address
foreach ($list as $address) { ... }
```

### Message System

**Message Class** features:
- To/CC/BCC recipient management
- Subject and body (HTML/Text)
- Custom headers and priority
- Validation before sending
- Fluent interface

```php
$message = new Message($mailManager);
$message->to('user@example.com')
        ->subject('Test')
        ->html('<p>HTML content</p>')
        ->text('Text content')
        ->priority(1)
        ->header('X-Custom', 'value')
        ->send();
```

## 🗄️ Database Schema (Phase 1)

The `queued_mails` table with 27 columns:

```sql
CREATE TABLE queued_mails (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Email Content (JSON-compatible TEXT fields)
    to_addresses TEXT NOT NULL,
    from_address TEXT NOT NULL,
    cc_addresses TEXT NULL,
    bcc_addresses TEXT NULL,
    subject VARCHAR(998) NOT NULL,
    body_text TEXT NULL,
    body_html TEXT NULL,
    attachments TEXT NULL,
    headers TEXT NULL,
    
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
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    -- Performance Indexes
    INDEX idx_available_reserved (available_at, reserved_at),
    INDEX idx_mailer_priority (mailer, priority, available_at),
    INDEX idx_status (failed_at, sent_at),
    INDEX idx_retry (next_retry_at, attempts, max_attempts),
    INDEX idx_performance (queue_time, processing_time, delivery_time),
    INDEX idx_last_attempt (last_attempt_at)
);
```

## 🧪 Testing

**Comprehensive Test Suite** (28 new tests added in Phase 3):

```bash
# Run all mail tests
./vendor/bin/phpunit --filter="Mail"

# Run specific test suites
./vendor/bin/phpunit tests/Unit/Mail/MailManagerTest.php
./vendor/bin/phpunit tests/Unit/Mail/Support/AddressTest.php
./vendor/bin/phpunit tests/Unit/Mail/Queue/QueuedMailTest.php
./vendor/bin/phpunit tests/Unit/Mail/Queue/MailQueueTest.php
./vendor/bin/phpunit tests/Unit/Mail/Queue/MailQueueProcessorTest.php

# Run console command tests
./vendor/bin/phpunit tests/Unit/Console/Commands/MailCommands/MailQueueRetryCommandTest.php

# Run cron system tests
./vendor/bin/phpunit tests/Unit/Cron/JobRegistryTest.php
```

**Test Coverage:**
- **MailManager**: 18 tests covering all driver functionality
- **Address/AddressList**: 35+ tests covering validation and parsing
- **QueuedMail Model**: 29 tests covering database operations
- **MailQueue**: 11 tests covering queue operations
- **MailQueueProcessor**: 7 tests covering cron job functionality
- **MailQueueRetryCommand**: 11 tests covering console command
- **JobRegistry**: Enhanced with 10 tests for centralized job management
- **Mail Drivers**: Integration tests for all transports

## 🚧 Upcoming Features (Phase 4+)

### Phase 4: Template Integration  
- View system integration
- Mailable classes
- Template rendering
- Layout support

### Phase 5: Advanced Features
- File attachments
- Mail events
- Validation rules
- Exception hierarchy

## 🔧 Framework Integration

**Service Registration** (in `Application.php`):
```php
$this->container->singleton('mail', function () {
    $config = $this->config['mail'] ?? [];
    return new \LengthOfRope\TreeHouse\Mail\MailManager($config, $this);
});

$this->container->singleton('mail.queue', function () {
    $config = $this->config['mail']['queue'] ?? [];
    return new \LengthOfRope\TreeHouse\Mail\Queue\MailQueue($config, $this);
});
```

**Built-in Cron Job** (automatically registered):
```php
// In JobRegistry::getBuiltInJobClasses()
\LengthOfRope\TreeHouse\Mail\Queue\MailQueueProcessor::class
```

**Helper Functions** (in `helpers.php`):
- `mailer()` - Get MailManager instance
- `sendMail()` - Send simple email immediately
- `queueMail()` - Queue email for processing

## 🎯 Performance & Production

**Production Features:**
- SMTP connection pooling and timeout handling
- Proper SSL/TLS certificate validation
- Comprehensive error logging
- Memory-efficient message processing
- Database connection optimization
- Automated queue processing every minute
- Exponential backoff retry logic
- Performance metrics and health monitoring

**Security:**
- RFC-compliant email validation
- SMTP authentication support
- Input sanitization and validation
- Secure header handling
- Database reservation system prevents race conditions

**Monitoring:**
- Real-time queue statistics
- Performance metrics (queue time, processing time, delivery time)
- Failure rate monitoring with configurable thresholds
- Health checks for queue processing
- Comprehensive logging with context

## 📈 Current Statistics

**Code Metrics:**
- **19 files created** (~3,500 lines of code)
- **Total tests**: 1891 (up from 1883)
- **New tests added**: 28 tests with comprehensive coverage
- **4 console commands** with full CLI integration
- **1 built-in cron job** for automated processing
- **Zero external dependencies**
- **Full PHP 8.4 type safety**

**Framework Enhancements:**
- Fixed 5 critical TreeHouse framework bugs
- Added universal JSON casting to ActiveRecord
- Enhanced migration system reliability
- Improved cross-database compatibility
- Centralized built-in cron job management

## 📚 Examples

### Working with the Queue

```php
// Add email to queue
$message = mailer()->to('user@example.com')->subject('Test');
$queuedMail = $message->queue();

// Check if email can be retried
if ($queuedMail->canRetry()) {
    // Reset for retry
    $queuedMail->failed_at = null;
    $queuedMail->error_message = null;
    $queuedMail->save();
}

// Get queue statistics
$stats = app('mail.queue')->getStats();
echo "Pending: {$stats['pending']}, Failed: {$stats['failed']}";
```

### Working with Different Drivers

```php
// Use specific driver
mailer()->mailer('smtp')
    ->to('user@example.com')
    ->subject('Production Email')
    ->send();

// Get driver info
$logMailer = mailer()->getMailer('log');
echo $logMailer->getTransport(); // "log"
```

### Address Parsing and Validation

```php
// Parse complex address formats
$address = Address::parse('"John Doe" <john@example.com>');
$list = AddressList::parse('user1@example.com, "Jane Doe" <jane@example.com>');

// Validation
try {
    new Address('invalid-email'); // Throws InvalidArgumentException
} catch (InvalidArgumentException $e) {
    echo "Invalid email: " . $e->getMessage();
}
```

### Message Validation

```php
$message = mailer()->compose();
$message->to('user@example.com')
        ->subject('Test');
        
// Check if valid before sending
if ($message->isValid()) {
    $message->send();
} else {
    $message->validate(); // Throws specific validation errors
}
```

## 🏆 Production Ready

The TreeHouse Mail System is now production-ready with complete email functionality:

- ✅ **Multiple driver support** (SMTP, Sendmail, Log)
- ✅ **Comprehensive validation and error handling**
- ✅ **Framework integration** with helper functions
- ✅ **Full test coverage** (1891 total tests)
- ✅ **Security best practices** and RFC compliance
- ✅ **Complete queue system** with automated processing
- ✅ **Console commands** for queue management
- ✅ **Performance monitoring** and metrics
- ✅ **Retry logic** with exponential backoff
- ✅ **Built-in cron integration** for automated processing
- ✅ **Production-grade architecture** with proper error handling

**Phase 3 Complete - Ready for Template Integration (Phase 4)**

The mail system now provides enterprise-grade email functionality with automated queue processing, comprehensive monitoring, and production-ready reliability.