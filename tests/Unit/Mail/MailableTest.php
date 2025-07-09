<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use LengthOfRope\TreeHouse\Mail\Mailable;
use LengthOfRope\TreeHouse\Mail\Messages\Message;
use LengthOfRope\TreeHouse\Mail\MailManager;
use LengthOfRope\TreeHouse\Foundation\Application;
use Tests\TestCase;

/**
 * Mailable Test
 * 
 * Tests for the mailable base class functionality
 */
class MailableTest extends TestCase
{
    protected Application $app;
    protected MailManager $mailManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app = new Application(__DIR__ . '/../../..');
        
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

    public function testMailableSubjectSetting(): void
    {
        $mailable = new TestMailable();
        $mailable->subject('Test Subject');
        
        $this->assertEquals('Test Subject', $mailable->getSubject());
    }

    public function testMailableTemplateSetting(): void
    {
        $mailable = new TestMailable();
        $mailable->emailTemplate('emails.test', ['user' => 'John']);
        
        $this->assertEquals('emails.test', $mailable->getTemplate());
        $this->assertEquals(['user' => 'John'], $mailable->getData());
    }

    public function testMailableWithData(): void
    {
        $mailable = new TestMailable();
        $mailable->with('key1', 'value1')
                 ->with(['key2' => 'value2', 'key3' => 'value3']);
        
        $data = $mailable->getData();
        $this->assertEquals('value1', $data['key1']);
        $this->assertEquals('value2', $data['key2']);
        $this->assertEquals('value3', $data['key3']);
    }

    public function testMailableAttachments(): void
    {
        $mailable = new TestMailable();
        $mailable->attach('/path/to/file.pdf', ['as' => 'document.pdf']);
        
        $attachments = $mailable->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertEquals('/path/to/file.pdf', $attachments[0]['path']);
        $this->assertEquals(['as' => 'document.pdf'], $attachments[0]['options']);
    }

    public function testMailablePriority(): void
    {
        $mailable = new TestMailable();
        $mailable->priority(2);
        
        // We can't easily test the priority getter without exposing it
        // But we can test that it doesn't throw an error and returns self
        $this->assertInstanceOf(TestMailable::class, $mailable->priority(3));
    }

    public function testMailableMailer(): void
    {
        $mailable = new TestMailable();
        $result = $mailable->mailer('smtp');
        
        $this->assertInstanceOf(TestMailable::class, $result);
    }

    public function testTextVersionGeneration(): void
    {
        $mailable = new TestMailable();
        
        $html = '<h1>Test Title</h1><p>This is a <strong>test</strong> email.</p><br><a href="http://example.com">Link</a>';
        $text = $mailable->testGenerateTextVersion($html);
        
        $this->assertStringContainsString('Test Title', $text);
        $this->assertStringContainsString('This is a test email.', $text);
        $this->assertStringContainsString('Link (http://example.com)', $text);
        $this->assertStringNotContainsString('<h1>', $text);
        $this->assertStringNotContainsString('<p>', $text);
    }

    public function testComplexTextVersionGeneration(): void
    {
        $mailable = new TestMailable();
        
        $html = '
            <html>
                <head><style>body { color: red; }</style></head>
                <body>
                    <script>alert("test");</script>
                    <h1>Welcome</h1>
                    <p>Hello   world</p>
                    <div>Content</div>
                    <hr>
                    <h2>Section 2</h2>
                </body>
            </html>
        ';
        
        $text = $mailable->testGenerateTextVersion($html);
        
        // Should strip scripts and styles
        $this->assertStringNotContainsString('alert', $text);
        $this->assertStringNotContainsString('color: red', $text);
        
        // Should convert headers with double newlines
        $this->assertStringContainsString("Welcome", $text);
        $this->assertStringContainsString("Section 2", $text);
        // Check that there are multiple newlines between sections
        $this->assertMatchesRegularExpression('/Welcome\s*\n+.*Section 2/s', $text);
        
        // Should normalize whitespace
        $this->assertStringNotContainsString('Hello   world', $text);
        $this->assertStringContainsString('Hello world', $text);
        
        // Should convert HR to dashes
        $this->assertStringContainsString('--------------------------------------------------', $text);
    }
}

/**
 * Test Mailable Class
 */
class TestMailable extends Mailable
{
    public function build(): self
    {
        return $this->subject('Test Subject')
                    ->emailTemplate('emails.test', ['test' => true]);
    }
    
    // Expose protected method for testing
    public function testGenerateTextVersion(string $html): string
    {
        return $this->generateTextVersion($html);
    }
}