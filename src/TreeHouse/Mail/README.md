# TreeHouse Framework - Mail Layer

The Mail layer provides a comprehensive email system for the TreeHouse Framework. It offers multiple transport drivers, queue management, template integration, file attachments, event handling, and advanced validation features.

## Table of Contents

- [Overview](#overview)
- [Core Components](#core-components)
- [Mail Interface](#mail-interface)
- [Mail Manager](#mail-manager)
- [Transport Drivers](#transport-drivers)
- [Queue System](#queue-system)
- [Mailable Classes](#mailable-classes)
- [Template System](#template-system)
- [File Attachments](#file-attachments)
- [Event System](#event-system)
- [Validation](#validation)
- [Helper Functions](#helper-functions)
- [CLI Commands](#cli-commands)
- [Configuration](#configuration)
- [Usage Examples](#usage-examples)
- [Best Practices](#best-practices)

## Overview

The Mail layer implements a driver-based email system that supports:

- **Multiple Transport Drivers**: SMTP (SSL/TLS), Sendmail, and Log drivers
- **Database Queue System**: Reliable email queuing with retry logic and automated processing
- **Laravel-Style Mailables**: Object-oriented email classes with template integration
- **Template Engine Integration**: TreeHouse template system with email-safe layouts
- **File Attachments**: Support for file and data attachments with security validation
- **Event System**: Full integration with TreeHouse events for monitoring and control
- **Advanced Validation**: Comprehensive email validation with anti-spam features
- **CLI Management**: Complete command-line tools for queue and email management

## Core Components

### MailManager

The [`MailManager`](MailManager.php:31) manages multiple transport drivers and provides a unified interface:

```php
class MailManager
{
    public function __construct(array $config, Application $app);
    public function compose(): static;
    public function send(): bool;
    public function queue(): bool;
    public function getMailer(string $name): MailerInterface;
    public function getDefaultMailer(): string;
}
```

### Message

The [`Message`](Messages/Message.php:22) class represents an email message with all its properties:

```php
class Message
{
    public function to(string|array|Address|AddressList $recipients): static;
    public function cc(string|array|Address|AddressList $recipients): static;
    public function bcc(string|array|Address|AddressList $recipients): static;
    public function from(string|Address $sender): static;
    public function subject(string $subject): static;
    public function html(string $html): static;
    public function text(string $text): static;
    public function attach(string $file, array $options = []): static;
    public function attachData(string $data, string $name, array $options = []): static;
    public function send(): bool;
    public function queue(int $priority = null): QueuedMail|bool;
}
```

### Mailable

The [`Mailable`](Mailable.php:29) abstract class provides Laravel-style email classes:

```php
abstract class Mailable
{
    public function to(string|array $recipients): static;
    public function subject(string $subject): static;
    public function emailTemplate(string $template, array $data = []): static;
    public function with(array $data): static;
    public function attach(string $file, array $options = []): static;
    public function send(string|array $recipients = null): bool;
    public function queue(string|array $recipients = null, int $priority = 5): QueuedMail|bool;
    abstract public function build(): static;
}
```

## Mail Interface

### Basic Operations

#### Compose and Send Email
```php
// Fluent interface
mailer()
    ->to('user@example.com')
    ->subject('Welcome!')
    ->html('<h1>Welcome to our app!</h1>')
    ->text('Welcome to our app!')
    ->send();

// With multiple recipients
mailer()
    ->to(['user1@example.com', 'user2@example.com'])
    ->cc('manager@example.com')
    ->bcc('archive@example.com')
    ->subject('Team Update')
    ->html('<p>Update content</p>')
    ->send();
```

#### Queue Email for Later Processing
```php
// Queue with default priority
mailer()
    ->to('user@example.com')
    ->subject('Newsletter')
    ->html('<p>Newsletter content</p>')
    ->queue();

// Queue with high priority
mailer()
    ->to('user@example.com')
    ->subject('Important Notice')
    ->html('<p>Important content</p>')
    ->priority(1)
    ->queue();
```

#### File Attachments
```php
// Attach files
mailer()
    ->to('user@example.com')
    ->subject('Invoice')
    ->html('<p>Please find your invoice attached.</p>')
    ->attach('/path/to/invoice.pdf', ['as' => 'Invoice-2024.pdf'])
    ->attach('/path/to/receipt.jpg')
    ->send();

// Attach raw data
$csvData = "Name,Email\nJohn,john@example.com";
mailer()
    ->to('admin@example.com')
    ->subject('User Export')
    ->attachData($csvData, 'users.csv', ['mime' => 'text/csv'])
    ->send();
```

## Mail Manager

### Driver Management

#### Get Manager Instance
```php
$mailManager = new MailManager($config, $app);

// Or via helper
$mailManager = mailer();
```

#### Use Specific Driver
```php
// Use specific mailer
mailer()
    ->mailer('smtp')
    ->to('user@example.com')
    ->subject('Production Email')
    ->send();

// Get driver instance
$smtpMailer = mailer()->getMailer('smtp');
```

### Transport Configuration
```php
$config = [
    'default' => 'smtp',
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'postmaster@mg.example.com',
            'password' => 'secret',
        ],
        'sendmail' => [
            'transport' => 'sendmail',
            'path' => '/usr/sbin/sendmail -bs',
        ],
        'log' => [
            'transport' => 'log',
            'path' => 'storage/logs/mail.log',
        ],
    ],
    'from' => [
        'address' => 'noreply@example.com',
        'name' => 'Example App',
    ],
];
```

## Transport Drivers

### SMTP Driver

The [`SmtpMailer`](Mailers/SmtpMailer.php:31) provides production-ready SMTP support:

```php
// SMTP with TLS encryption
'smtp' => [
    'transport' => 'smtp',
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'your-email@gmail.com',
    'password' => 'your-app-password',
    'timeout' => 60,
]
```

### Sendmail Driver

The [`SendmailMailer`](Mailers/SendmailMailer.php:25) uses the system's sendmail:

```php
'sendmail' => [
    'transport' => 'sendmail',
    'path' => '/usr/sbin/sendmail -bs',
]
```

### Log Driver

The [`LogMailer`](Mailers/LogMailer.php:25) logs emails to files (perfect for development):

```php
'log' => [
    'transport' => 'log',
    'path' => 'storage/logs/mail.log',
]
```

## Queue System

### QueuedMail Model

The [`QueuedMail`](Queue/QueuedMail.php:29) model provides database persistence:

```php
class QueuedMail extends ActiveRecord
{
    // 27-column schema with performance tracking
    public function canRetry(): bool;
    public function markAsSent(): void;
    public function markAsFailed(string $error): void;
    public function calculateNextRetry(): void;
}
```

### Mail Queue

The [`MailQueue`](Queue/MailQueue.php:30) manages the email queue:

```php
$mailQueue = new MailQueue($config, $app);

// Add to queue
$queuedMail = $mailQueue->add($message, $priority);

// Process queue
$processed = $mailQueue->processQueue($batchSize);

// Get statistics
$stats = $mailQueue->getStats();
```

### Automated Processing

The system includes a built-in cron job that processes the queue every minute:

```php
// Automatic processing via MailQueueProcessor
// Runs: mail:queue:process every minute
```

## Mailable Classes

### Creating Mailables

```bash
# Generate a new Mailable class
treehouse make:mailable WelcomeEmail

# With custom template
treehouse make:mailable OrderConfirmation --template=emails.orders.confirmation
```

### Basic Mailable

```php
use LengthOfRope\TreeHouse\Mail\Mailable;

class WelcomeEmail extends Mailable
{
    public function __construct(
        protected mixed $user
    ) {}

    public function build(): static
    {
        return $this
            ->subject('Welcome to Our App!')
            ->emailTemplate('emails.welcome', [
                'user' => $this->user,
                'app_name' => 'MyApp',
            ]);
    }
}
```

### Using Mailables

```php
// Send immediately
$welcomeEmail = new WelcomeEmail($user);
$welcomeEmail->send($user->email);

// Queue for later
$welcomeEmail = new WelcomeEmail($user);
$welcomeEmail->queue($user->email, 1); // High priority

// With attachments
$welcomeEmail = new WelcomeEmail($user);
$welcomeEmail
    ->attach('/path/to/welcome.pdf')
    ->send($user->email);
```

## Template System

### Email Templates

The system integrates with TreeHouse's template engine:

```html
<!-- resources/views/emails/layouts/base.th.html -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{subject}</title>
</head>
<body style="font-family: Arial, sans-serif;">
    <div th:section="content">
        <!-- Content will be inserted here -->
    </div>
    
    <footer style="margin-top: 30px; color: #666;">
        <p>Best regards,<br>{app.name} Team</p>
    </footer>
</body>
</html>
```

```html
<!-- resources/views/emails/welcome.th.html -->
<div th:extend="emails.layouts.base">
    <div th:section="content">
        <h1>Welcome to {app.name}!</h1>
        <p>Hello {user.name},</p>
        <p>Thank you for joining us. We're excited to have you on board!</p>
        <a th:href="dashboard_url" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none;">
            Get Started
        </a>
    </div>
</div>
```

### Email Renderer

The [`EmailRenderer`](EmailRenderer.php:26) provides email-specific template rendering:

```php
// Render email template
$html = renderEmail('emails.welcome', [
    'user' => $user,
    'dashboard_url' => url('/dashboard')
]);

// Use in email
sendMail($user->email, 'Welcome!', $html);
```

## File Attachments

### File Attachments

```php
// Attach files with options
$message->attach('/path/to/file.pdf', [
    'as' => 'Custom-Name.pdf',
    'mime' => 'application/pdf'
]);

// Multiple attachments
$message
    ->attach('/path/to/invoice.pdf')
    ->attach('/path/to/receipt.jpg')
    ->attach('/path/to/terms.txt');
```

### Data Attachments

```php
// Attach generated content
$csvData = generateCsvReport();
$message->attachData($csvData, 'report.csv', [
    'mime' => 'text/csv'
]);

// Attach binary data
$pdfData = generatePdfInvoice();
$message->attachData($pdfData, 'invoice.pdf', [
    'mime' => 'application/pdf'
]);
```

### MIME Type Detection

The system automatically detects MIME types:

```php
// Automatic detection with fallback
$attachment = [
    'path' => '/path/to/file.pdf',
    'name' => 'document.pdf',
    'mime' => 'application/pdf', // Auto-detected
    'size' => 2048576, // Auto-calculated
];
```

## Event System

### Mail Events

The system dispatches events throughout the email lifecycle:

```php
use LengthOfRope\TreeHouse\Mail\Events\{MailSending, MailSent, MailFailed, MailQueued};

// Listen for events
app('events')->listen(MailSending::class, function(MailSending $event) {
    // Before email is sent - can cancel
    if ($shouldBlock) {
        $event->cancel();
    }
});

app('events')->listen(MailSent::class, function(MailSent $event) {
    // After successful send
    $sendTime = $event->getSendTime();
    $mailer = $event->getMailerUsed();
});

app('events')->listen(MailFailed::class, function(MailFailed $event) {
    // When send fails
    $error = $event->getErrorMessage();
    $isRetryable = $event->isRetryable();
});

app('events')->listen(MailQueued::class, function(MailQueued $event) {
    // When email is queued
    $queueId = $event->getQueueId();
    $priority = $event->getPriority();
});
```

### Event Context

Events include rich contextual data:

```php
$event->getSubject();           // Email subject
$event->getRecipients();        // Recipient list
$event->hasAttachments();       // Attachment presence
$event->getAttachmentCount();   // Number of attachments
$event->getContext();           // Full event context
```

## Validation

### Email Validator

The [`EmailValidator`](Validation/EmailValidator.php:27) provides comprehensive validation:

```php
use LengthOfRope\TreeHouse\Mail\Validation\EmailValidator;

$validator = new EmailValidator([
    'max_attachment_size' => 10 * 1024 * 1024, // 10MB
    'max_total_size' => 25 * 1024 * 1024,      // 25MB
    'max_recipients' => 100,
    'blocked_domains' => ['spam-domain.com'],
    'check_disposable_emails' => true,
    'allowed_attachment_types' => ['pdf', 'jpg', 'png', 'doc'],
]);

if (!$validator->validate($message)) {
    foreach ($validator->getErrors() as $error) {
        echo "Validation error: {$error}\n";
    }
}
```

### Validation Features

- **Recipient Validation**: Email format, domain blocking, disposable email detection
- **Content Validation**: Subject/body length, suspicious pattern detection
- **Attachment Validation**: File types, size limits, security checks
- **Size Limits**: Individual and total attachment size validation
- **Anti-Spam**: Pattern detection for suspicious content

## Helper Functions

### Mail Helpers

The mail system provides convenient global helper functions:

```php
// Get mail manager
$mailManager = mailer();

// Send simple email
sendMail('user@example.com', 'Subject', 'Body content');

// Queue simple email
queueMail('user@example.com', 'Subject', 'Body content', 1); // High priority

// Render email template
$html = renderEmail('emails.welcome', ['user' => $user]);

// Send templated email
sendMail($user->email, 'Welcome!', renderEmail('emails.welcome', ['user' => $user]));
```

## CLI Commands

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

# Show detailed information
treehouse mail:queue:status --details --metrics

# Process emails manually
treehouse mail:queue:work --limit=50

# Process continuously (development)
treehouse mail:queue:work --continuous --timeout=300

# Retry failed emails
treehouse mail:queue:retry --limit=20 --older-than=60

# Clear failed emails
treehouse mail:queue:clear --failed

# Clear all processed emails
treehouse mail:queue:clear --all
```

### Automated Processing

```bash
# Built-in cron job (runs automatically every minute)
treehouse cron:list    # View all cron jobs
treehouse cron:run     # Manual cron execution
```

## Configuration

### Basic Configuration

```php
// config/mail.php
return [
    'default' => env('MAIL_MAILER', 'log'),
    
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
            'path' => 'storage/logs/mail.log',
        ],
    ],
    
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],
    
    // Queue Configuration
    'queue' => [
        'enabled' => env('MAIL_QUEUE_ENABLED', true),
        'batch_size' => env('MAIL_QUEUE_BATCH_SIZE', 10),
        'max_attempts' => env('MAIL_QUEUE_MAX_ATTEMPTS', 3),
        'retry_strategy' => env('MAIL_QUEUE_RETRY_STRATEGY', 'exponential'),
        'base_retry_delay' => env('MAIL_QUEUE_BASE_RETRY_DELAY', 300),
        'max_retry_delay' => env('MAIL_QUEUE_MAX_RETRY_DELAY', 3600),
        'fallback_to_send' => true,
    ],
];
```

### Environment Variables

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@mg.example.com
MAIL_PASSWORD=your-secret-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Example App"

MAIL_QUEUE_ENABLED=true
MAIL_QUEUE_BATCH_SIZE=10
MAIL_QUEUE_MAX_ATTEMPTS=3
```

## Usage Examples

### User Registration Email

```php
class UserRegistrationService
{
    public function register(array $userData): User
    {
        $user = User::create($userData);
        
        // Send welcome email
        $welcomeEmail = new WelcomeEmail($user);
        $welcomeEmail->queue($user->email, 2); // Medium priority
        
        return $user;
    }
}
```

### Order Confirmation

```php
class OrderService
{
    public function processOrder(Order $order): void
    {
        $order->status = 'confirmed';
        $order->save();
        
        // Send confirmation email with invoice
        mailer()
            ->to($order->customer_email)
            ->subject("Order Confirmation #{$order->id}")
            ->emailTemplate('emails.order-confirmation', [
                'order' => $order,
                'customer' => $order->customer,
            ])
            ->attach($order->generateInvoicePdf(), [
                'as' => "invoice-{$order->id}.pdf"
            ])
            ->send();
    }
}
```

### Newsletter System

```php
class NewsletterService
{
    public function sendNewsletter(Newsletter $newsletter): void
    {
        $subscribers = $this->getActiveSubscribers();
        
        foreach ($subscribers->chunk(100) as $chunk) {
            foreach ($chunk as $subscriber) {
                $newsletterEmail = new NewsletterEmail($newsletter, $subscriber);
                $newsletterEmail->queue($subscriber->email, 4); // Low priority
            }
        }
    }
}
```

### Password Reset

```php
class PasswordResetService
{
    public function sendResetLink(string $email): void
    {
        $user = User::where('email', $email)->first();
        if (!$user) return;
        
        $token = $this->generateResetToken($user);
        
        mailer()
            ->to($user->email)
            ->subject('Password Reset Request')
            ->emailTemplate('emails.password-reset', [
                'user' => $user,
                'reset_url' => url("/reset-password/{$token}"),
                'expires_at' => now()->addHours(2),
            ])
            ->send();
    }
}
```

## Best Practices

### Email Security

```php
// Always validate email addresses
$validator = new EmailValidator([
    'check_disposable_emails' => true,
    'blocked_domains' => ['suspicious-domain.com'],
]);

// Use HTTPS for production SMTP
'smtp' => [
    'host' => 'smtp.example.com',
    'port' => 587,
    'encryption' => 'tls', // Always use encryption
];
```

### Performance Optimization

```php
// Use queue for non-critical emails
queueMail($email, $subject, $body, 3); // Normal priority

// Batch email operations
$emails = [];
foreach ($users as $user) {
    $emails[] = new WelcomeEmail($user);
}
// Queue all at once instead of individual sends
```

### Template Organization

```php
// Organize templates hierarchically
resources/views/emails/
├── layouts/
│   └── base.th.html
├── auth/
│   ├── welcome.th.html
│   └── password-reset.th.html
├── orders/
│   ├── confirmation.th.html
│   └── shipped.th.html
└── newsletters/
    └── monthly.th.html
```

### Error Handling

```php
try {
    mailer()->to($email)->subject($subject)->html($body)->send();
} catch (Exception $e) {
    // Log error and fallback
    error_log("Mail send failed: " . $e->getMessage());
    
    // Queue for retry
    queueMail($email, $subject, $body, 1);
}
```

### Monitoring and Maintenance

```php
// Regular queue monitoring
$stats = app('mail.queue')->getStats();
if ($stats['failed'] > 100) {
    // Alert administrators
}

// Periodic cleanup (in cron job)
app('mail.queue')->clearOldMessages(30); // Keep 30 days
```

The Mail layer provides a complete, production-ready email solution with Laravel-style convenience, robust queue management, and comprehensive monitoring capabilities.