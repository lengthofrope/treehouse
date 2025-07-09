<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use LengthOfRope\TreeHouse\Mail\EmailRenderer;
use LengthOfRope\TreeHouse\Foundation\Application;
use Tests\TestCase;

/**
 * EmailRenderer Test
 * 
 * Tests for the email template renderer
 */
class EmailRendererTest extends TestCase
{
    protected Application $app;
    protected EmailRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app = new Application(__DIR__ . '/../../..');
        $this->renderer = new EmailRenderer($this->app);
    }

    public function testEmailRendererCreation(): void
    {
        $this->assertInstanceOf(EmailRenderer::class, $this->renderer);
    }

    public function testEmailRendererHasViewEngine(): void
    {
        $viewEngine = $this->renderer->getViewEngine();
        $this->assertInstanceOf(\LengthOfRope\TreeHouse\View\ViewEngine::class, $viewEngine);
    }

    public function testTemplateExistence(): void
    {
        // This will likely return false since we don't have actual templates in test environment
        // but it tests the method works
        $exists = $this->renderer->exists('emails.welcome');
        $this->assertIsBool($exists);
    }

    public function testEmailContextGeneration(): void
    {
        // Create a simple template for testing
        $testTemplatePath = $this->app->getBasePath() . '/resources/views/emails/test.th.html';
        $testTemplateDir = dirname($testTemplatePath);
        
        if (!is_dir($testTemplateDir)) {
            mkdir($testTemplateDir, 0755, true);
        }
        
        file_put_contents($testTemplatePath, '<p>Test template with {app.name} and {year}</p>');
        
        try {
            $html = $this->renderer->render('emails.test', []);
            
            // Should contain default context
            $this->assertStringContainsString((string)date('Y'), $html);
            
        } finally {
            // Clean up
            if (file_exists($testTemplatePath)) {
                unlink($testTemplatePath);
            }
        }
    }

    public function testDotNotationConversion(): void
    {
        // Test that dot notation is converted to paths
        // We can't easily test the internal conversion without modifying the class
        // but we can test it doesn't throw an error with dot notation
        $exists = $this->renderer->exists('emails.layouts.base');
        $this->assertIsBool($exists);
    }

    public function testRenderWithCustomData(): void
    {
        // Create a test template
        $testTemplatePath = $this->app->getBasePath() . '/resources/views/emails/custom.th.html';
        $testTemplateDir = dirname($testTemplatePath);
        
        if (!is_dir($testTemplateDir)) {
            mkdir($testTemplateDir, 0755, true);
        }
        
        file_put_contents($testTemplatePath, '<p>Hello {user.name}! Welcome to {app.name}.</p>');
        
        try {
            $html = $this->renderer->render('emails.custom', [
                'user' => ['name' => 'John Doe']
            ]);
            
            $this->assertStringContainsString('John Doe', $html);
            
        } finally {
            // Clean up
            if (file_exists($testTemplatePath)) {
                unlink($testTemplatePath);
            }
        }
    }

    public function testEmailContextDefaults(): void
    {
        // Create a test template that uses default URLs
        $testTemplatePath = $this->app->getBasePath() . '/resources/views/emails/defaults.th.html';
        $testTemplateDir = dirname($testTemplatePath);
        
        if (!is_dir($testTemplateDir)) {
            mkdir($testTemplateDir, 0755, true);
        }
        
        file_put_contents($testTemplatePath, '<p><a th:href="unsubscribe_url">Unsubscribe</a> | <a th:href="dashboard_url">Dashboard</a></p>');
        
        try {
            $html = $this->renderer->render('emails.defaults', []);
            
            // Should contain default placeholder URLs
            $this->assertStringContainsString('href="#"', $html);
            
        } finally {
            // Clean up
            if (file_exists($testTemplatePath)) {
                unlink($testTemplatePath);
            }
        }
    }
}