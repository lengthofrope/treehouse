<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use LengthOfRope\TreeHouse\Mail\MailManager;
use LengthOfRope\TreeHouse\Mail\Messages\Message;
use LengthOfRope\TreeHouse\Mail\Support\Address;
use LengthOfRope\TreeHouse\Mail\Support\AddressList;
use LengthOfRope\TreeHouse\Mail\Mailers\LogMailer;
use LengthOfRope\TreeHouse\Foundation\Application;
use Tests\TestCase;

/**
 * MailManager Test
 * 
 * Tests for the mail manager functionality
 */
class MailManagerTest extends TestCase
{
    private MailManager $mailManager;
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock application
        $this->app = $this->createMock(Application::class);

        // Create mail configuration
        $config = [
            'default' => 'log',
            'mailers' => [
                'log' => [
                    'transport' => 'log',
                    'path' => 'storage/logs/test-mail.log',
                ],
                'smtp' => [
                    'transport' => 'smtp',
                    'host' => 'localhost',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => 'test@example.com',
                    'password' => 'password',
                ],
            ],
            'from' => [
                'address' => 'noreply@example.com',
                'name' => 'Test App',
            ],
        ];

        $this->mailManager = new MailManager($config, $this->app);
    }

    protected function tearDown(): void
    {
        // Clean up test log file
        $logPath = getcwd() . '/storage/logs/test-mail.log';
        if (file_exists($logPath)) {
            unlink($logPath);
        }

        parent::tearDown();
    }

    public function testMailManagerCreation(): void
    {
        $this->assertInstanceOf(MailManager::class, $this->mailManager);
    }

    public function testComposeMessage(): void
    {
        $mail = $this->mailManager->compose();
        $this->assertInstanceOf(MailManager::class, $mail);
    }

    public function testFluentInterface(): void
    {
        $mail = $this->mailManager
            ->to('user@example.com')
            ->from('sender@example.com')
            ->subject('Test Subject')
            ->html('<h1>Test HTML</h1>')
            ->text('Test Text')
            ->priority(3)
            ->header('X-Custom', 'value');

        $this->assertInstanceOf(MailManager::class, $mail);
    }

    public function testGetDefaultMailer(): void
    {
        $this->assertEquals('log', $this->mailManager->getDefaultMailer());
    }

    public function testGetMailerInstance(): void
    {
        $mailer = $this->mailManager->getMailer('log');
        $this->assertInstanceOf(LogMailer::class, $mailer);
    }

    public function testGetMailerWithInvalidName(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mailer [invalid] is not configured');
        $this->mailManager->getMailer('invalid');
    }

    public function testGetDefaultFrom(): void
    {
        $from = $this->mailManager->getDefaultFrom();
        $this->assertInstanceOf(Address::class, $from);
        $this->assertEquals('noreply@example.com', $from->getEmail());
        $this->assertEquals('Test App', $from->getName());
    }

    public function testSendSimpleEmail(): void
    {
        $result = $this->mailManager
            ->to('user@example.com')
            ->subject('Test Subject')
            ->text('Test message')
            ->send();

        $this->assertTrue($result);

        // Verify log file was created
        $logPath = getcwd() . '/storage/logs/test-mail.log';
        $this->assertTrue(file_exists($logPath));

        // Verify log content
        $logContent = file_get_contents($logPath);
        $this->assertStringContainsString('MAIL LOG ENTRY', $logContent);
        $this->assertStringContainsString('To: user@example.com', $logContent);
        $this->assertStringContainsString('Subject: Test Subject', $logContent);
        $this->assertStringContainsString('Test message', $logContent);
    }

    public function testSendMailHelper(): void
    {
        // Load helper functions
        require_once __DIR__ . '/../../../src/TreeHouse/Mail/helpers.php';
        
        // Mock the application global with make method
        $mockApp = $this->createMock(\LengthOfRope\TreeHouse\Foundation\Application::class);
        $mockApp->method('make')->with('mail')->willReturn($this->mailManager);
        $GLOBALS['app'] = $mockApp;
        
        $result = sendMail('user@example.com', 'Helper Test', 'Test message from helper');
        $this->assertTrue($result);
    }

    public function testQueueMailHelper(): void
    {
        // Load helper functions
        require_once __DIR__ . '/../../../src/TreeHouse/Mail/helpers.php';
        
        // Mock the application global with make method that falls back to send
        $mockApp = $this->createMock(\LengthOfRope\TreeHouse\Foundation\Application::class);
        $mockApp->method('make')->willReturnMap([
            ['mail', $this->mailManager],
            ['mail.queue', null] // This will trigger the fallback
        ]);
        $GLOBALS['app'] = $mockApp;
        
        // This should fallback to sending via the helper
        $result = queueMail('user@example.com', 'Queue Helper Test', 'Test queued message');
        $this->assertTrue($result);
    }

    public function testSendHtmlEmail(): void
    {
        $result = $this->mailManager
            ->to('user@example.com')
            ->subject('HTML Test')
            ->html('<h1>HTML Content</h1>')
            ->send();

        $this->assertTrue($result);

        $logPath = getcwd() . '/storage/logs/test-mail.log';
        $logContent = file_get_contents($logPath);
        $this->assertStringContainsString('<h1>HTML Content</h1>', $logContent);
    }

    public function testSendMultipartEmail(): void
    {
        $result = $this->mailManager
            ->to('user@example.com')
            ->subject('Multipart Test')
            ->html('<h1>HTML Version</h1>')
            ->text('Text Version')
            ->send();

        $this->assertTrue($result);

        $logPath = getcwd() . '/storage/logs/test-mail.log';
        $logContent = file_get_contents($logPath);
        $this->assertStringContainsString('HTML Version', $logContent);
        $this->assertStringContainsString('Text Version', $logContent);
    }

    public function testSendWithMultipleRecipients(): void
    {
        $result = $this->mailManager
            ->to(['user1@example.com', 'user2@example.com'])
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Multiple Recipients')
            ->text('Test message')
            ->send();

        $this->assertTrue($result);

        $logPath = getcwd() . '/storage/logs/test-mail.log';
        $logContent = file_get_contents($logPath);
        $this->assertStringContainsString('user1@example.com', $logContent);
        $this->assertStringContainsString('user2@example.com', $logContent);
        $this->assertStringContainsString('Cc: cc@example.com', $logContent);
        $this->assertStringContainsString('Bcc: bcc@example.com', $logContent);
    }

    public function testSendWithCustomHeaders(): void
    {
        $result = $this->mailManager
            ->to('user@example.com')
            ->subject('Custom Headers')
            ->text('Test message')
            ->header('X-Priority', '1')
            ->header('X-Custom', 'value')
            ->send();

        $this->assertTrue($result);

        $logPath = getcwd() . '/storage/logs/test-mail.log';
        $logContent = file_get_contents($logPath);
        $this->assertStringContainsString('X-Priority: 1', $logContent);
        $this->assertStringContainsString('X-Custom: value', $logContent);
    }

    public function testSendWithPriority(): void
    {
        $result = $this->mailManager
            ->to('user@example.com')
            ->subject('Priority Test')
            ->text('Test message')
            ->priority(1)
            ->send();

        $this->assertTrue($result);

        $logPath = getcwd() . '/storage/logs/test-mail.log';
        $logContent = file_get_contents($logPath);
        $this->assertStringContainsString('Priority: 1', $logContent);
    }

    public function testSendWithSpecificMailer(): void
    {
        $result = $this->mailManager
            ->to('user@example.com')
            ->subject('Specific Mailer')
            ->text('Test message')
            ->mailer('log')
            ->send();

        $this->assertTrue($result);
    }

    public function testQueueEmail(): void
    {
        // Create a mock mail queue
        $mockMailQueue = $this->createMock(\LengthOfRope\TreeHouse\Mail\Queue\MailQueue::class);
        $mockMailQueue->method('add')->willReturn(new \LengthOfRope\TreeHouse\Mail\Queue\QueuedMail());
        
        // Mock the application to return the mail queue
        $mockApp = $this->createMock(\LengthOfRope\TreeHouse\Foundation\Application::class);
        $mockApp->method('make')->with('mail.queue')->willReturn($mockMailQueue);
        
        // Create a new mail manager with the mocked app
        $mailManager = new \LengthOfRope\TreeHouse\Mail\MailManager([
            'default' => 'log',
            'mailers' => [
                'log' => [
                    'transport' => 'log',
                    'path' => 'storage/logs/test-mail.log',
                ],
            ],
            'queue' => ['enabled' => true],
        ], $mockApp);
        
        $result = $mailManager
            ->to('user@example.com')
            ->subject('Queue Test')
            ->text('Test message')
            ->queue();

        $this->assertTrue($result);
    }

    public function testChainedMethodCalls(): void
    {
        $mail = $this->mailManager->compose();
        
        $result = $mail
            ->to('user@example.com')
            ->from('custom@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Chained Test')
            ->html('<p>HTML content</p>')
            ->text('Text content')
            ->priority(2)
            ->header('X-Test', 'test')
            ->mailer('log')
            ->send();

        $this->assertTrue($result);
    }

    public function testCreateMailerForDifferentTransports(): void
    {
        $logMailer = $this->mailManager->getMailer('log');
        $this->assertEquals('log', $logMailer->getTransport());

        $smtpMailer = $this->mailManager->getMailer('smtp');
        $this->assertEquals('smtp', $smtpMailer->getTransport());
    }

    public function testUnsupportedTransport(): void
    {
        $config = [
            'default' => 'invalid',
            'mailers' => [
                'invalid' => [
                    'transport' => 'unsupported',
                ],
            ],
        ];

        $mailManager = new MailManager($config, $this->app);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported mail transport [unsupported]');
        $mailManager->getMailer('invalid');
    }
}