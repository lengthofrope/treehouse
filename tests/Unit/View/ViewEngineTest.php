<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use LengthOfRope\TreeHouse\View\ViewEngine;
use LengthOfRope\TreeHouse\View\Template;
use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;
use LengthOfRope\TreeHouse\Cache\FileCache;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * ViewEngine tests
 */
class ViewEngineTest extends TestCase
{
    private ViewEngine $engine;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tempDir = sys_get_temp_dir() . '/treehouse_view_tests_' . uniqid();
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
    public function it_can_create_templates(): void
    {
        // Create test template
        file_put_contents($this->tempDir . '/views/test.th.html', '<h1 th:text="$title">Default</h1>');
        
        $template = $this->engine->make('test', ['title' => 'Hello World']);
        
        $this->assertInstanceOf(Template::class, $template);
    }

    #[Test]
    public function it_can_render_templates(): void
    {
        // Create test template
        file_put_contents($this->tempDir . '/views/simple.php', '<h1><?php echo $title; ?></h1>');
        
        $result = $this->engine->render('simple', ['title' => 'Test Title']);
        
        $this->assertStringContainsString('Test Title', $result);
    }

    #[Test]
    public function it_can_find_templates_with_different_extensions(): void
    {
        // Test .th.html (highest priority)
        file_put_contents($this->tempDir . '/views/multi.th.html', 'TreeHouse template');
        file_put_contents($this->tempDir . '/views/multi.php', 'PHP template');
        
        $result = $this->engine->render('multi');
        
        $this->assertStringContainsString('TreeHouse template', $result);
    }

    #[Test]
    public function it_can_share_global_variables(): void
    {
        file_put_contents($this->tempDir . '/views/shared.php', '<?php echo $global; ?>');
        
        $this->engine->share('global', 'Shared Value');
        $result = $this->engine->render('shared');
        
        $this->assertStringContainsString('Shared Value', $result);
    }

    #[Test]
    public function it_can_register_aliases(): void
    {
        file_put_contents($this->tempDir . '/views/original.php', 'Original template');
        
        $this->engine->alias('aliased', 'original');
        $result = $this->engine->render('aliased');
        
        $this->assertStringContainsString('Original template', $result);
    }

    #[Test]
    public function it_can_check_if_template_exists(): void
    {
        file_put_contents($this->tempDir . '/views/exists.php', 'Template exists');
        
        $this->assertTrue($this->engine->exists('exists'));
        $this->assertFalse($this->engine->exists('nonexistent'));
    }

    #[Test]
    public function it_can_add_template_paths(): void
    {
        $extraPath = $this->tempDir . '/extra';
        mkdir($extraPath);
        file_put_contents($extraPath . '/extra.php', 'Extra template');
        
        $this->engine->addPath($extraPath);
        
        $this->assertTrue($this->engine->exists('extra'));
    }

    #[Test]
    public function it_can_compile_treehouse_templates(): void
    {
        $template = '<h1 th:text="$title">Default</h1>';
        file_put_contents($this->tempDir . '/views/compile.th.html', $template);
        
        $compiled = $this->engine->compile($this->tempDir . '/views/compile.th.html');
        
        // Since helpers are now loaded globally via Composer, we don't inject them
        // Just check that the template is compiled correctly
        $this->assertStringContainsString('htmlspecialchars((string)($title)', $compiled);
        $this->assertStringContainsString('<h1', $compiled);
    }

    #[Test]
    public function it_can_get_and_set_compiler(): void
    {
        $compiler = new TreeHouseCompiler();
        
        $this->engine->setCompiler($compiler);
        
        $this->assertSame($compiler, $this->engine->getCompiler());
    }

    #[Test]
    public function it_can_work_with_cache(): void
    {
        $cacheDir = $this->tempDir . '/cache';
        mkdir($cacheDir);
        $cache = new FileCache($cacheDir);
        
        $this->engine->setCache($cache);
        
        $this->assertSame($cache, $this->engine->getCache());
    }

    #[Test]
    public function it_can_register_view_composers(): void
    {
        file_put_contents($this->tempDir . '/views/composer.php', '<?php echo $composed; ?>');
        
        $this->engine->composer('composer', function ($data) {
            return array_merge($data, ['composed' => 'Composed Value']);
        });
        
        $result = $this->engine->render('composer');
        
        $this->assertStringContainsString('Composed Value', $result);
    }

    #[Test]
    public function it_can_clear_cache(): void
    {
        $cacheDir = $this->tempDir . '/cache';
        mkdir($cacheDir);
        $cache = new FileCache($cacheDir);
        $this->engine->setCache($cache);
        
        // This should not throw an exception
        $this->engine->clearCache();
        
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_throws_exception_for_missing_template(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Template 'missing' not found");
        
        $this->engine->make('missing');
    }

    #[Test]
    public function it_can_get_paths(): void
    {
        $paths = $this->engine->getPaths();
        
        $this->assertContains($this->tempDir . '/views', $paths);
    }

    #[Test]
    public function it_can_get_shared_variables(): void
    {
        $this->engine->share(['key1' => 'value1', 'key2' => 'value2']);
        
        $shared = $this->engine->getShared();
        
        $this->assertEquals('value1', $shared['key1']);
        $this->assertEquals('value2', $shared['key2']);
    }

    #[Test]
    public function it_can_register_components(): void
    {
        file_put_contents($this->tempDir . '/views/button.php', '<button><?php echo $text; ?></button>');
        
        $this->engine->component('btn', 'button');
        
        $result = $this->engine->renderComponent('btn', ['text' => 'Click me']);
        
        $this->assertStringContainsString('Click me', $result);
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
