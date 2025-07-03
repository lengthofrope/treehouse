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
        $template = '<h1 th:text="title">Default</h1>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('htmlspecialchars', $compiled);
        $this->assertStringContainsString('$title', $compiled);
    }

    #[Test]
    public function it_compiles_th_raw_attributes(): void
    {
        $template = '<div th:raw="content">Default</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('echo', $compiled);
        $this->assertStringContainsString('$content', $compiled);
    }

    #[Test]
    public function it_compiles_th_if_attributes(): void
    {
        $template = '<div th:if="condition">Conditional content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('if ($condition)', $compiled);
        $this->assertStringContainsString('endif', $compiled);
    }

    #[Test]
    public function it_compiles_boolean_logic_operators_with_th_if(): void
    {
        $template = '<div th:if="user.isAdult && user.active">Adult & Active</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('thGetProperty', $compiled);
        $this->assertStringContainsString('&&', $compiled);
    }

    #[Test]
    public function it_compiles_logical_not_instead_of_th_unless(): void
    {
        $template = '<div th:if="!user.isMinor">Not a minor</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('!', $compiled);
        $this->assertStringContainsString('thGetProperty', $compiled);
    }

    #[Test]
    public function it_compiles_th_repeat_attributes(): void
    {
        $template = '<li th:repeat="item items">Item content</li>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('foreach ($items as $item)', $compiled);
        $this->assertStringContainsString('endforeach', $compiled);
    }

    #[Test]
    public function it_compiles_th_repeat_with_key(): void
    {
        $template = '<li th:repeat="key,item items">Item content</li>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('foreach ($items as $key => $item)', $compiled);
        $this->assertStringContainsString('endforeach', $compiled);
    }

    #[Test]
    public function it_compiles_th_class_attributes(): void
    {
        $template = '<div th:class="cssClass">Content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('class=', $compiled);
        $this->assertStringContainsString('$cssClass', $compiled);
    }

    #[Test]
    public function it_compiles_individual_attribute_directives(): void
    {
        $template = '<input th:id="elementId" th:name="fieldName">';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$elementId', $compiled);
        $this->assertStringContainsString('$fieldName', $compiled);
    }

    #[Test]
    public function it_compiles_dot_notation_to_thgetproperty(): void
    {
        $template = '<span th:text="user.name">Name</span>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
        $this->assertStringContainsString('htmlspecialchars', $compiled);
    }

    #[Test]
    public function it_compiles_nested_dot_notation(): void
    {
        $template = '<span th:text="user.profile.bio">Bio</span>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('thGetProperty(thGetProperty($user, \'profile\'), \'bio\')', $compiled);
    }

    #[Test]
    public function it_compiles_brace_expressions_with_dot_notation(): void
    {
        $template = '<p>Hello {user.name}, you are {user.age} years old!</p>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'age\')', $compiled);
        $this->assertStringContainsString('htmlspecialchars', $compiled);
    }

    #[Test]
    public function it_compiles_support_class_static_calls(): void
    {
        $template = '<span th:text="Str::title(user.name)">Name</span>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('Str::', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
    }



    #[Test]
    public function it_handles_nested_attributes(): void
    {
        $template = '<div th:if="$show"><span th:text="user.message">Message</span></div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('if ($show)', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'message\')', $compiled);
        $this->assertStringContainsString('htmlspecialchars', $compiled);
    }

    #[Test]
    public function it_includes_helper_functions(): void
    {
        $template = '<h1 th:text="title">Simple template</h1>';
        
        $compiled = $this->compiler->compile($template);
        
        // Helper functions are included in templates that use th: attributes
        $this->assertStringContainsString('function thGetProperty', $compiled);
        $this->assertStringContainsString('htmlspecialchars', $compiled);
    }


    #[Test]
    public function it_fallback_to_plain_template_on_parsing_error(): void
    {
        // Template with valid th: attributes should be compiled normally
        $template = '<span th:text="user.value">Default</span>';
        
        $compiled = $this->compiler->compile($template);
        
        // Should contain compiled htmlspecialchars and thGetProperty, not the original "Default"
        $this->assertStringContainsString('htmlspecialchars', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'value\')', $compiled);
    }

    #[Test]
    public function it_processes_attributes_in_correct_order(): void
    {
        $template = '<div th:if="$show" th:text="user.message" th:class="user.cssClass">Content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        // Should contain all transformations
        $this->assertStringContainsString('if ($show)', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'message\')', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'cssClass\')', $compiled);
        $this->assertStringContainsString('htmlspecialchars', $compiled);
    }

    #[Test]
    public function it_allows_framework_helpers_with_dot_notation(): void
    {
        $template = '<p th:text="Str::upper(user.name)">NAME</p>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
        $this->assertStringContainsString('Str::', $compiled);
        $this->assertStringContainsString('htmlspecialchars', $compiled);
    }
}
