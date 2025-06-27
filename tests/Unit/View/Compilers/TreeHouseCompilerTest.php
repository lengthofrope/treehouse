<?php

declare(strict_types=1);

namespace Tests\Unit\View\Compilers;

use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * TreeHouseCompiler tests
 */
class TreeHouseCompilerTest extends TestCase
{
    private TreeHouseCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new TreeHouseCompiler();
    }

    #[Test]
    public function it_compiles_simple_templates_without_th_attributes(): void
    {
        $template = '<h1>Hello World</h1>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('Hello World', $compiled);
        // Simple templates without th: attributes don't include helper functions anymore (they're global)
    }

    #[Test]
    public function it_compiles_th_text_attributes(): void
    {
        $template = '<h1 th:text="$title">Default</h1>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('thEscape', $compiled);
        $this->assertStringContainsString('$title', $compiled);
    }

    #[Test]
    public function it_compiles_th_html_attributes(): void
    {
        $template = '<div th:html="$content">Default</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('thRaw', $compiled);
        $this->assertStringContainsString('$content', $compiled);
    }

    #[Test]
    public function it_compiles_th_if_attributes(): void
    {
        $template = '<div th:if="$condition">Conditional content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('if ($condition)', $compiled);
        $this->assertStringContainsString('endif', $compiled);
    }

    #[Test]
    public function it_compiles_th_unless_attributes(): void
    {
        $template = '<div th:unless="$condition">Unless content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('if (!($condition))', $compiled);
        $this->assertStringContainsString('endif', $compiled);
    }

    #[Test]
    public function it_compiles_th_repeat_attributes(): void
    {
        $template = '<li th:repeat="item $items">Item content</li>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('foreach ($items as $item)', $compiled);
        $this->assertStringContainsString('endforeach', $compiled);
    }

    #[Test]
    public function it_compiles_th_repeat_with_key(): void
    {
        $template = '<li th:repeat="key,item $items">Item content</li>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('foreach ($items as $key => $item)', $compiled);
        $this->assertStringContainsString('endforeach', $compiled);
    }

    #[Test]
    public function it_compiles_th_class_attributes(): void
    {
        $template = '<div th:class="$cssClass">Content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('class=', $compiled);
        $this->assertStringContainsString('$cssClass', $compiled);
    }

    #[Test]
    public function it_compiles_th_attr_attributes(): void
    {
        $template = '<input th:attr="id=$elementId, name=$fieldName">';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$elementId', $compiled);
        $this->assertStringContainsString('$fieldName', $compiled);
    }

    #[Test]
    public function it_compiles_support_class_static_calls(): void
    {
        $template = '<span th:text="Str::title($name)">Name</span>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('LengthOfRope\\TreeHouse\\Support\\Str::', $compiled);
    }

    #[Test]
    public function it_compiles_collection_method_calls(): void
    {
        $template = '<span th:text="$items->count()">0</span>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('thCollect', $compiled);
        $this->assertStringContainsString('count()', $compiled);
    }

    #[Test]
    public function it_handles_nested_attributes(): void
    {
        $template = '<div th:if="$show"><span th:text="$message">Message</span></div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('if ($show)', $compiled);
        $this->assertStringContainsString('thEscape($message)', $compiled);
    }

    #[Test]
    public function it_includes_helper_functions(): void
    {
        $template = '<h1>Simple template</h1>';
        
        $compiled = $this->compiler->compile($template);
        
        // Simple templates without th: attributes don't include helper functions anymore (they're global)
        // Helper functions are loaded globally now, not injected into each template
        $this->assertStringNotContainsString('function thEscape', $compiled);
        $this->assertStringNotContainsString('function thRaw', $compiled);
        $this->assertStringNotContainsString('function thCollect', $compiled);
    }

    #[Test]
    public function it_returns_supported_attributes(): void
    {
        $attributes = $this->compiler->getSupportedAttributes();
        
        $this->assertContains('th:if', $attributes);
        $this->assertContains('th:text', $attributes);
        $this->assertContains('th:repeat', $attributes);
        $this->assertContains('th:class', $attributes);
    }

    #[Test]
    public function it_checks_if_attribute_is_supported(): void
    {
        $this->assertTrue($this->compiler->isAttributeSupported('th:if'));
        $this->assertTrue($this->compiler->isAttributeSupported('th:text'));
        $this->assertFalse($this->compiler->isAttributeSupported('th:invalid'));
    }

    #[Test]
    public function it_handles_complex_expressions(): void
    {
        $template = '<div th:text="$user->name . \' (\' . $user->email . \')\'">User</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$user->name', $compiled);
        $this->assertStringContainsString('$user->email', $compiled);
        $this->assertStringContainsString('thEscape', $compiled);
    }

    #[Test]
    public function it_fallback_to_plain_template_on_parsing_error(): void
    {
        // Template with valid th: attributes should be compiled normally
        $template = '<span th:text="$value">Default</span>';
        
        $compiled = $this->compiler->compile($template);
        
        // Should contain compiled thEscape, not the original "Default"
        $this->assertStringContainsString('thEscape', $compiled);
        $this->assertStringContainsString('$value', $compiled);
    }

    #[Test]
    public function it_processes_attributes_in_correct_order(): void
    {
        $template = '<div th:if="$show" th:text="$message" th:class="$cssClass">Content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        // Should contain all transformations
        $this->assertStringContainsString('if ($show)', $compiled);
        $this->assertStringContainsString('thEscape($message)', $compiled);
        $this->assertStringContainsString('$cssClass', $compiled);
    }
}
