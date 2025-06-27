<?php

declare(strict_types=1);

namespace Tests\Unit\View\Compilers;

use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Universal th: Attribute System tests
 */
class UniversalThAttributeTest extends TestCase
{
    private TreeHouseCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new TreeHouseCompiler();
    }

    #[Test]
    public function it_compiles_universal_th_href_attributes(): void
    {
        $template = '<a th:href="/user/{user.id}">Link</a>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('href="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString("\$user['id']", $compiled);
    }

    #[Test]
    public function it_handles_dot_notation_conversion(): void
    {
        $template = '<div th:data-user="{user.name}">Content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString("\$user['name']", $compiled);
        $this->assertStringContainsString('data-user="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
    }

    #[Test]
    public function it_handles_nested_dot_notation(): void
    {
        $template = '<div th:data-theme="{user.profile.theme.primary}">Content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString("\$user['profile']['theme']['primary']", $compiled);
    }

    #[Test]
    public function it_compiles_mixed_static_dynamic_content(): void
    {
        $template = '<div th:class="btn btn-{user.role} {user.active ? \'active\' : \'inactive\'}">Button</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('class="<?php echo', $compiled);
        $this->assertStringContainsString('btn btn-', $compiled);
        $this->assertStringContainsString("\$user['role']", $compiled);
        $this->assertStringContainsString("\$user['active']", $compiled);
    }

    #[Test]
    public function it_handles_multiple_universal_attributes(): void
    {
        $template = '<input th:id="field_{user.id}" th:name="user[{user.id}][name]" th:value="{user.name}">';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('id="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString('name="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString('value="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString("\$user['id']", $compiled);
        $this->assertStringContainsString("\$user['name']", $compiled);
    }

    #[Test]
    public function it_preserves_standard_th_attributes(): void
    {
        $template = '<div th:if="$condition" th:href="/user/{user.id}" th:text="user.name">Content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        // Should contain th:if processing
        $this->assertStringContainsString('if ($condition)', $compiled);
        
        // Should contain th:text processing  
        $this->assertStringContainsString('thEscape', $compiled);
        
        // Should contain universal th:href processing
        $this->assertStringContainsString('href="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString("\$user['id']", $compiled);
        $this->assertStringContainsString("\$user['name']", $compiled);
    }

    #[Test]
    public function it_works_within_th_repeat_loops(): void
    {
        $template = '<tr th:repeat="user $users" th:data-user-id="{user.id}"><td th:text="user.name">Name</td></tr>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('foreach', $compiled);
        $this->assertStringContainsString('data-user-id="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString("\$user['id']", $compiled);
        $this->assertStringContainsString('thEscape', $compiled);
        $this->assertStringContainsString("\$user['name']", $compiled);
    }

    #[Test]
    public function it_handles_direct_php_expressions_without_braces(): void
    {
        $template = '<div th:data-id="$user[\'id\']">Content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('data-id="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString("\$user['id']", $compiled);
    }

    #[Test]
    public function it_escapes_quotes_in_brace_expressions(): void
    {
        $template = '<div th:title="View profile for {user.name}">Content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('title="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString('View profile for', $compiled);
        $this->assertStringContainsString("\$user['name']", $compiled);
    }

    #[Test]
    public function it_processes_attributes_in_correct_order(): void
    {
        $template = '<div th:if="$show" th:data-user="{user.id}" th:class="active">Content</div>';
        
        $compiled = $this->compiler->compile($template);
        
        // th:if should be processed first (structural)
        $this->assertStringContainsString('if ($show)', $compiled);
        
        // Universal attributes should be processed after
        $this->assertStringContainsString('data-user="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString("\$user['id']", $compiled);
    }

    #[Test]
    public function it_supports_complex_form_attributes(): void
    {
        $template = '<input th:id="field_{user.id}" th:name="users[{user.id}][profile][{field.name}]" th:placeholder="Enter {field.label} for {user.name}">';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('id="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString('name="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString('placeholder="<?php $val =', $compiled);
        $this->assertStringContainsString('if ($val !== null) echo $val', $compiled);
        $this->assertStringContainsString("\$user['id']", $compiled);
        $this->assertStringContainsString("\$field['name']", $compiled);
        $this->assertStringContainsString("\$field['label']", $compiled);
        $this->assertStringContainsString("\$user['name']", $compiled);
    }
}
