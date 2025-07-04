<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\View\ViewFactory;
use LengthOfRope\TreeHouse\View\ViewEngine;

/**
 * Integration test for vendor dependency template resolution
 */
class VendorIntegrationTest extends TestCase
{
    private string $tempDir;
    private ViewFactory $factory;

    protected function setUp(): void
    {
        // Create temporary directory structure
        $this->tempDir = sys_get_temp_dir() . '/treehouse_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        mkdir($this->tempDir . '/resources/views/layouts', 0755, true);
        mkdir($this->tempDir . '/storage/views', 0755, true);

        // Create test templates
        $this->createTestTemplates();

        // Create ViewFactory with explicit paths (simulating proper configuration)
        $this->factory = new ViewFactory([
            'paths' => [$this->tempDir . '/resources/views'],
            'cache_path' => $this->tempDir . '/storage/views',
            'cache_enabled' => false // Disable cache for testing
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        $this->removeDirectory($this->tempDir);
    }

    private function createTestTemplates(): void
    {
        // Create layout template
        $layoutContent = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Test Layout</title>
</head>
<body>
    <header>Header Content</header>
    <main th:yield="content">Default Content</main>
    <footer>Footer Content</footer>
</body>
</html>
HTML;
        file_put_contents($this->tempDir . '/resources/views/layouts/app.th.html', $layoutContent);

        // Create child template that extends the layout
        $childContent = <<<'HTML'
<div th:extend="layouts.app">
    <section th:section="content">
        <h1>Child Template Content</h1>
        <p>This content should appear in the layout.</p>
    </section>
</div>
HTML;
        file_put_contents($this->tempDir . '/resources/views/child.th.html', $childContent);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testTemplateExtensionWorksWithExplicitPaths(): void
    {
        // Render the child template that extends the layout
        $output = $this->factory->render('child');

        // Verify the layout structure is present
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('<title>Test Layout</title>', $output);
        $this->assertStringContainsString('<header>Header Content</header>', $output);
        $this->assertStringContainsString('<footer>Footer Content</footer>', $output);

        // Verify the child content is injected
        $this->assertStringContainsString('<h1>Child Template Content</h1>', $output);
        $this->assertStringContainsString('<p>This content should appear in the layout.</p>', $output);

        // Verify the default content is replaced
        $this->assertStringNotContainsString('Default Content', $output);
    }

    public function testLayoutTemplateCanBeFoundByEngine(): void
    {
        // Verify that the layout template exists and can be found
        $this->assertTrue($this->factory->exists('layouts.app'));
        $this->assertTrue($this->factory->exists('child'));
    }

    public function testViewFactoryUsesCorrectPaths(): void
    {
        $config = $this->factory->getConfig();
        
        // Verify the paths are set correctly
        $this->assertContains($this->tempDir . '/resources/views', $config['paths']);
        $this->assertEquals($this->tempDir . '/storage/views', $config['cache_path']);
    }

    public function testViewEngineDirectlyWithPaths(): void
    {
        // Test ViewEngine directly with explicit paths
        $engine = new ViewEngine([$this->tempDir . '/resources/views']);
        
        // Verify templates can be found
        $this->assertTrue($engine->exists('layouts.app'));
        $this->assertTrue($engine->exists('child'));
        
        // Verify rendering works
        $output = $engine->render('child');
        $this->assertStringContainsString('<h1>Child Template Content</h1>', $output);
    }
}