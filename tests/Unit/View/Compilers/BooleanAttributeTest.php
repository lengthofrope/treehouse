<?php

namespace Tests\Unit\View\Compilers;

use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BooleanAttributeTest extends TestCase
{
    private TreeHouseCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new TreeHouseCompiler();
    }

    #[Test]
    public function it_handles_th_selected_boolean_attribute(): void
    {
        $template = '<option th:selected="{user.role == \'admin\'}">Admin</option>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('<?php if ($user[\'role\'] == \'admin\') echo \'selected\'; ?>', $compiled);
        $this->assertStringNotContainsString('th:selected', $compiled);
    }

    #[Test]
    public function it_handles_th_checked_boolean_attribute(): void
    {
        $template = '<input type="checkbox" th:checked="{user.active}">';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('<?php if ($user[\'active\']) echo \'checked\'; ?>', $compiled);
        $this->assertStringNotContainsString('th:checked', $compiled);
    }

    #[Test]
    public function it_handles_th_disabled_boolean_attribute(): void
    {
        $template = '<input th:disabled="{!user.canEdit}" type="text">';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('<?php if (!$user[\'canEdit\']) echo \'disabled\'; ?>', $compiled);
        $this->assertStringNotContainsString('th:disabled', $compiled);
    }

    #[Test]
    public function it_handles_th_required_boolean_attribute(): void
    {
        $template = '<input th:required="{field.isRequired}" type="email">';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('<?php if ($field[\'isRequired\']) echo \'required\'; ?>', $compiled);
        $this->assertStringNotContainsString('th:required', $compiled);
    }

    #[Test]
    public function it_handles_multiple_boolean_attributes(): void
    {
        $template = '<input th:checked="{user.active}" th:disabled="{user.isLocked}" th:required="{user.needsEmail}" type="checkbox">';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('<?php if ($user[\'active\']) echo \'checked\'; ?>', $compiled);
        $this->assertStringContainsString('<?php if ($user[\'isLocked\']) echo \'disabled\'; ?>', $compiled);
        $this->assertStringContainsString('<?php if ($user[\'needsEmail\']) echo \'required\'; ?>', $compiled);
    }

    #[Test]
    public function it_handles_boolean_attributes_with_complex_expressions(): void
    {
        $template = '<option th:selected="{user.role == \'admin\' && user.active}">Admin</option>';
        
        $compiled = $this->compiler->compile($template);
        
        $this->assertStringContainsString('<?php if ($user[\'role\'] == \'admin\' && $user[\'active\']) echo \'selected\'; ?>', $compiled);
    }

    #[Test]
    public function it_supports_all_html_boolean_attributes(): void
    {
        $booleanAttributes = [
            'selected' => '<option th:selected="{true}">Test</option>',
            'checked' => '<input th:checked="{true}" type="checkbox">',
            'disabled' => '<input th:disabled="{true}" type="text">',
            'readonly' => '<input th:readonly="{true}" type="text">',
            'multiple' => '<select th:multiple="{true}"></select>',
            'autofocus' => '<input th:autofocus="{true}" type="text">',
            'autoplay' => '<video th:autoplay="{true}"></video>',
            'controls' => '<video th:controls="{true}"></video>',
            'defer' => '<script th:defer="{true}"></script>',
            'hidden' => '<div th:hidden="{true}"></div>',
            'loop' => '<video th:loop="{true}"></video>',
            'open' => '<details th:open="{true}"></details>',
            'required' => '<input th:required="{true}" type="text">',
            'reversed' => '<ol th:reversed="{true}"></ol>',
        ];

        foreach ($booleanAttributes as $attr => $template) {
            $compiled = $this->compiler->compile($template);
            $this->assertStringContainsString("echo '{$attr}';", $compiled, "Boolean attribute '{$attr}' should be handled correctly");
        }
    }

    #[Test]
    public function it_does_not_add_boolean_attribute_when_condition_is_false(): void
    {
        // This test requires actual execution to verify the condition works
        $template = '<option th:selected="{false}">Test</option>';
        $compiled = $this->compiler->compile($template);
        
        // The compiled template should contain the conditional PHP
        $this->assertStringContainsString('<?php if (false) echo \'selected\'; ?>', $compiled);
        
        // When executed, it should not have the selected attribute
        ob_start();
        eval('?>' . $compiled);
        $output = ob_get_clean();
        
        $this->assertEquals('<option >Test</option>', $output);
    }

    #[Test]
    public function it_adds_boolean_attribute_when_condition_is_true(): void
    {
        // This test requires actual execution to verify the condition works
        $template = '<option th:selected="{true}">Test</option>';
        $compiled = $this->compiler->compile($template);
        
        // The compiled template should contain the conditional PHP
        $this->assertStringContainsString('<?php if (true) echo \'selected\'; ?>', $compiled);
        
        // When executed, it should have the selected attribute
        ob_start();
        eval('?>' . $compiled);
        $output = ob_get_clean();
        
        $this->assertEquals('<option selected>Test</option>', $output);
    }
}
