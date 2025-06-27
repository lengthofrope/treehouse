<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use LengthOfRope\TreeHouse\View\{ViewEngine, Template};
use LengthOfRope\TreeHouse\Http\Response;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Template tests
 */
class TemplateTest extends TestCase
{
    private ViewEngine $engine;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tempDir = sys_get_temp_dir() . '/treehouse_template_tests_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/views');
        
        $this->engine = new ViewEngine([$this->tempDir . '/views']);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function it_can_be_created_with_data(): void
    {
        file_put_contents($this->tempDir . '/views/test.php', '<?php echo $title; ?>');
        
        $template = new Template($this->engine, $this->tempDir . '/views/test.php', ['title' => 'Test Title']);
        
        $this->assertInstanceOf(Template::class, $template);
        $this->assertEquals(['title' => 'Test Title'], $template->getData());
    }

    #[Test]
    public function it_can_render_php_templates(): void
    {
        file_put_contents($this->tempDir . '/views/simple.php', '<h1><?php echo $title; ?></h1>');
        
        $template = new Template($this->engine, $this->tempDir . '/views/simple.php', ['title' => 'Hello World']);
        $result = $template->render();
        
        $this->assertStringContainsString('Hello World', $result);
    }

    #[Test]
    public function it_can_add_data_with_with_method(): void
    {
        file_put_contents($this->tempDir . '/views/data.php', '<?php echo $name . " " . $age; ?>');
        
        $template = new Template($this->engine, $this->tempDir . '/views/data.php', ['name' => 'John']);
        $template->with('age', 30);
        
        $result = $template->render();
        
        $this->assertStringContainsString('John 30', $result);
    }

    #[Test]
    public function it_can_add_multiple_data_with_array(): void
    {
        file_put_contents($this->tempDir . '/views/multi.php', '<?php echo $a . $b . $c; ?>');
        
        $template = new Template($this->engine, $this->tempDir . '/views/multi.php');
        $template->with(['a' => '1', 'b' => '2', 'c' => '3']);
        
        $result = $template->render();
        
        $this->assertStringContainsString('123', $result);
    }

    #[Test]
    public function it_provides_template_helpers(): void
    {
        file_put_contents($this->tempDir . '/views/helpers.php', '<?php echo $th->e("<script>alert(1)</script>"); ?>');
        
        $template = new Template($this->engine, $this->tempDir . '/views/helpers.php');
        $result = $template->render();
        
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function it_can_include_other_templates(): void
    {
        file_put_contents($this->tempDir . '/views/main.php', '<?php echo $this->include("partial", ["data" => "included"]); ?>');
        file_put_contents($this->tempDir . '/views/partial.php', 'Partial: <?php echo $data; ?>');
        
        $template = new Template($this->engine, $this->tempDir . '/views/main.php');
        $result = $template->render();
        
        $this->assertStringContainsString('Partial: included', $result);
    }

    #[Test]
    public function it_can_manage_sections(): void
    {
        $template = new Template($this->engine, $this->tempDir . '/views/test.php');
        
        $template->startSection('content');
        echo 'Section content';
        $template->endSection();
        
        $this->assertEquals('Section content', $template->yieldSection('content'));
    }

    #[Test]
    public function it_can_yield_section_with_default(): void
    {
        $template = new Template($this->engine, $this->tempDir . '/views/test.php');
        
        $result = $template->yieldSection('missing', 'Default content');
        
        $this->assertEquals('Default content', $result);
    }

    #[Test]
    public function it_can_extend_layouts(): void
    {
        $template = new Template($this->engine, $this->tempDir . '/views/test.php');
        
        $template->extend('layouts.app');
        
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_can_convert_to_string(): void
    {
        file_put_contents($this->tempDir . '/views/string.php', 'String output');
        
        $template = new Template($this->engine, $this->tempDir . '/views/string.php');
        
        $this->assertEquals('String output', (string) $template);
    }

    #[Test]
    public function it_can_convert_to_response(): void
    {
        file_put_contents($this->tempDir . '/views/response.php', 'Response content');
        
        $template = new Template($this->engine, $this->tempDir . '/views/response.php');
        $response = $template->toResponse();
        
        $this->assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function it_can_get_template_name_from_path(): void
    {
        $template = new Template($this->engine, $this->tempDir . '/views/user/profile.php');
        
        $name = $template->getTemplateName();
        
        $this->assertEquals('user.profile', $name);
    }

    #[Test]
    public function it_can_get_path(): void
    {
        $path = $this->tempDir . '/views/test.php';
        $template = new Template($this->engine, $path);
        
        $this->assertEquals($path, $template->getPath());
    }

    #[Test]
    public function it_can_get_engine(): void
    {
        $template = new Template($this->engine, $this->tempDir . '/views/test.php');
        
        $this->assertSame($this->engine, $template->getEngine());
    }

    #[Test]
    public function it_handles_rendering_errors_gracefully(): void
    {
        // Create template with PHP error
        file_put_contents($this->tempDir . '/views/error.php', '<?php throw new Exception("Test error"); ?>');
        
        $template = new Template($this->engine, $this->tempDir . '/views/error.php');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error rendering template');
        
        $template->render();
    }

    #[Test]
    public function it_handles_section_errors(): void
    {
        $template = new Template($this->engine, $this->tempDir . '/views/test.php');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No section started');
        
        $template->endSection();
    }

    #[Test]
    public function template_helpers_provide_useful_functions(): void
    {
        file_put_contents($this->tempDir . '/views/helper_test.php', '<?php 
            echo $th->money(29.99, "€") . "|";
            echo $th->number(1234.56, 2) . "|";
            echo $th->isEmpty([]) ? "empty" : "not empty" . "|";
            echo $th->url("test/path") . "|";
            echo $th->asset("style.css");
        ?>');
        
        $template = new Template($this->engine, $this->tempDir . '/views/helper_test.php');
        $result = $template->render();
        
        $this->assertStringContainsString('€29,99', $result);
        $this->assertStringContainsString('1.234,56', $result);
        $this->assertStringContainsString('empty', $result);
        $this->assertStringContainsString('/test/path', $result);
        $this->assertStringContainsString('/assets/style.css', $result);
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
}
