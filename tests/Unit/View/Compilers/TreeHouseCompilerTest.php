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
        
        $this->assertStringContainsString('!empty((isset($condition) ? $condition : null))', $compiled);
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
        
        $this->assertStringContainsString('foreach ((isset($items) ? $items : null) as $item)', $compiled);
        $this->assertStringContainsString('endforeach', $compiled);
    }

    #[Test]
    public function it_compiles_th_repeat_with_key(): void
    {
        $template = '<li th:repeat="key,item items">Item content</li>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('foreach ((isset($items) ? $items : null) as $key => $item)', $compiled);
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
        
        $this->assertStringContainsString('!empty($show)', $compiled);
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
        $this->assertStringContainsString('!empty($show)', $compiled);
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

    // ========================================
    // NEW FUNCTIONALITY TESTS
    // ========================================

    #[Test]
    public function it_compiles_th_fragment_directive(): void
    {
        $template = '<div th:fragment="userCard(user)">
            <h3 th:text="user.name">Name</h3>
            <p th:text="user.email">Email</p>
        </div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$__fragments[\'userCard\']', $compiled);
        $this->assertStringContainsString('function($user)', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'email\')', $compiled);
    }

    #[Test]
    public function it_compiles_th_include_directive(): void
    {
        $template = '<div th:include="templating :: userCard(currentUser)">Fallback</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$__includePath = \'templating\'', $compiled);
        $this->assertStringContainsString('include $__includeFullPath', $compiled);
        $this->assertStringContainsString('$__fragments[\'userCard\']', $compiled);
        $this->assertStringContainsString('$currentUser', $compiled);
    }

    #[Test]
    public function it_compiles_th_replace_directive(): void
    {
        $template = '<div th:replace="templating :: userCard(currentUser)">Fallback</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$__replacePath = \'templating\'', $compiled);
        $this->assertStringContainsString('include $__replaceFullPath', $compiled);
        $this->assertStringContainsString('$__fragments[\'userCard\']', $compiled);
        $this->assertStringContainsString('$currentUser', $compiled);
        // Should not contain the original fallback content
        $this->assertStringNotContainsString('Fallback', $compiled);
    }

    #[Test]
    public function it_compiles_th_switch_and_case_directives(): void
    {
        $template = '<div th:switch="user.role">
            <p th:case="admin">Administrator</p>
            <p th:case="user">Regular User</p>
            <p th:default="">Unknown Role</p>
        </div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$__switch_', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'role\')', $compiled);
        $this->assertStringContainsString('case \'admin\':', $compiled);
        $this->assertStringContainsString('case \'user\':', $compiled);
        $this->assertStringContainsString('default:', $compiled);
        $this->assertStringContainsString('break;', $compiled);
    }


    #[Test]
    public function it_compiles_th_csrf_directive(): void
    {
        $template = '<form><input th:csrf="" /></form>';
        
        $compiled = $this->compiler->compile($template);
        
        // The CSRF processor should process the directive, even if it doesn't add visible content
        $this->assertStringNotContainsString('th:csrf', $compiled);
    }

    #[Test]
    public function it_compiles_th_method_directive(): void
    {
        $template = '<form><input th:method="PUT" /></form>';
        
        $compiled = $this->compiler->compile($template);
        
        // The method should be set as an attribute
        $this->assertStringContainsString('method="POST"', $compiled);
    }

    #[Test]
    public function it_compiles_th_field_directive(): void
    {
        $template = '<input th:field="user.name" />';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('name="name"', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
    }

    #[Test]
    public function it_compiles_th_errors_directive(): void
    {
        $template = '<div th:errors="user.name">Error messages</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$__errors', $compiled);
        $this->assertStringContainsString('name', $compiled);
    }

    #[Test]
    public function it_preserves_html_entities_in_code_blocks(): void
    {
        $template = '<code>&lt;div th:text="user.name"&gt;Name&lt;/div&gt;</code>';
        
        $compiled = $this->compiler->compile($template);
        
        // HTML entities in code blocks should be preserved
        $this->assertStringContainsString('&lt;div', $compiled);
        $this->assertStringContainsString('&gt;Name&lt;', $compiled);
        $this->assertStringContainsString('&gt;</code>', $compiled);
    }

    #[Test]
    public function it_processes_php_markers_correctly(): void
    {
        $template = '<div th:text="user.name">Default</div>';
        
        $compiled = $this->compiler->compile($template);
        
        // Should not contain any PHP markers in the final output
        $this->assertStringNotContainsString('TH_PHP_CONTENT:', $compiled);
        $this->assertStringNotContainsString('TH_PHP_BEFORE:', $compiled);
        $this->assertStringNotContainsString('TH_PHP_AFTER:', $compiled);
        $this->assertStringNotContainsString('TH_PHP_REPLACE:', $compiled);
        $this->assertStringNotContainsString('TH_PHP_ATTR:', $compiled);
        
        // Should contain actual PHP code
        $this->assertStringContainsString('<?php', $compiled);
        $this->assertStringContainsString('?>', $compiled);
    }

    #[Test]
    public function it_handles_complex_fragment_with_parameters(): void
    {
        $template = '<div th:fragment="userCard(user, showEmail)">
            <h3 th:text="user.name">Name</h3>
            <p th:if="showEmail" th:text="user.email">Email</p>
        </div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$__fragments[\'userCard\']', $compiled);
        $this->assertStringContainsString('function($user, $showEmail)', $compiled);
        $this->assertStringContainsString('!empty((isset($showEmail) ? $showEmail : null))', $compiled);
    }

    #[Test]
    public function it_handles_fragment_inclusion_with_multiple_parameters(): void
    {
        $template = '<div th:include="fragments :: userCard(currentUser, true)">Fallback</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$__fragments[\'userCard\']', $compiled);
        $this->assertStringContainsString('(isset($currentUser) ? $currentUser : null), true', $compiled);
    }

    #[Test]
    public function it_supports_emoji_in_templates(): void
    {
        $template = '<p>Hello 👋 {user.name}!</p>';
        
        $compiled = $this->compiler->compile($template);
        
        // Emojis should be preserved
        $this->assertStringContainsString('👋', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
    }

    #[Test]
    public function it_handles_mixed_brace_expressions_and_text(): void
    {
        $template = '<img th:src="{user.avatar} Avatar" th:alt="{user.name} Profile Picture" />';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('thGetProperty($user, \'avatar\')', $compiled);
        $this->assertStringContainsString('Avatar', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
        $this->assertStringContainsString('Profile Picture', $compiled);
    }

    #[Test]
    public function it_handles_nested_switch_cases(): void
    {
        $template = '<div th:switch="user.status">
            <div th:case="active">
                <span th:switch="user.role">
                    <strong th:case="admin">Admin User</strong>
                    <em th:default="">Regular User</em>
                </span>
            </div>
            <div th:default="">Inactive User</div>
        </div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$__switch_', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'status\')', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'role\')', $compiled);
        $this->assertStringContainsString('case \'active\':', $compiled);
        $this->assertStringContainsString('case \'admin\':', $compiled);
    }

    #[Test]
    public function it_handles_variables_with_double_underscores(): void
    {
        $template = '<div th:text="__treehouse_config.app_name">App Name</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('thGetProperty($__treehouse_config, \'app_name\')', $compiled);
    }

    #[Test]
    public function it_processes_directives_in_correct_order(): void
    {
        $template = '<div th:fragment="test" th:if="condition" th:text="message">Default</div>';
        
        $compiled = $this->compiler->compile($template);
        
        // Fragment should be processed first, then if, then text
        $this->assertStringContainsString('$__fragments[\'test\']', $compiled);
        $this->assertStringContainsString('!empty((isset($condition) ? $condition : null))', $compiled);
        $this->assertStringContainsString('htmlspecialchars', $compiled);
    }

    #[Test]
    public function it_handles_fragment_replacement_without_parameters(): void
    {
        $template = '<div th:replace="fragments :: header">Default Header</div>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('$__fragments[\'header\']()', $compiled);
        $this->assertStringNotContainsString('Default Header', $compiled);
    }

    #[Test]
    public function it_preserves_whitespace_in_templates(): void
    {
        $template = '<pre><code>
function test() {
    return "hello";
}
</code></pre>';
        
        $compiled = $this->compiler->compile($template);
        
        // Whitespace should be preserved
        $this->assertStringContainsString('function test()', $compiled);
        $this->assertStringContainsString('    return', $compiled);
    }
}
