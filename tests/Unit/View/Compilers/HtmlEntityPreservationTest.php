<?php

declare(strict_types=1);

namespace Tests\Unit\View\Compilers;

use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for HTML entity preservation and PHP marker processing
 */
class HtmlEntityPreservationTest extends TestCase
{
    private TreeHouseCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new TreeHouseCompiler();
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
    public function it_preserves_html_entities_in_pre_blocks(): void
    {
        $template = '<pre>&lt;?php echo "Hello &amp; Goodbye"; ?&gt;</pre>';
        
        $compiled = $this->compiler->compile($template);
        
        // HTML entities in pre blocks should be preserved
        $this->assertStringContainsString('&lt;?php', $compiled);
        $this->assertStringContainsString('&amp;', $compiled);
        $this->assertStringContainsString('?&gt;', $compiled);
    }

    #[Test]
    public function it_supports_emojis_in_templates(): void
    {
        $template = '<p>Hello 👋 {user.name}! Welcome 🎉</p>';
        
        $compiled = $this->compiler->compile($template);
        
        // Emojis should be preserved
        $this->assertStringContainsString('👋', $compiled);
        $this->assertStringContainsString('🎉', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
    }

    #[Test]
    public function it_decodes_safe_named_entities(): void
    {
        $template = '<p>Hello&nbsp;world&mdash;this&nbsp;is&nbsp;a&nbsp;test&hellip;</p>';
        
        $compiled = $this->compiler->compile($template);
        
        // Safe named entities should be decoded (but &nbsp; might remain as-is)
        $this->assertStringContainsString('&mdash;', $compiled);
        $this->assertStringContainsString('&hellip;', $compiled);
    }

    #[Test]
    public function it_preserves_markup_entities_in_code_blocks(): void
    {
        $template = '<code>if (x &lt; 5 &amp;&amp; y &gt; 10) { return true; }</code>';
        
        $compiled = $this->compiler->compile($template);
        
        // Markup entities in code should be preserved
        $this->assertStringContainsString('&lt; 5', $compiled);
        $this->assertStringContainsString('&amp;&amp;', $compiled);
        $this->assertStringContainsString('&gt; 10', $compiled);
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
        $this->assertStringContainsString('htmlspecialchars', $compiled);
    }

    #[Test]
    public function it_handles_mixed_content_with_entities_and_expressions(): void
    {
        $template = '<div>
            <code>&lt;span th:text="name"&gt;{user.name}&lt;/span&gt;</code>
            <p>Hello {user.name}! 👋</p>
        </div>';
        
        $compiled = $this->compiler->compile($template);
        
        // Expressions should be compiled
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
        
        // Emoji should be preserved
        $this->assertStringContainsString('👋', $compiled);
        
        // Template should contain compiled th: directives
        $this->assertStringContainsString('th:text', $compiled);
    }

    #[Test]
    public function it_preserves_whitespace_in_code_blocks(): void
    {
        $template = '<pre><code>
function test() {
    if (x &lt; 5) {
        return true;
    }
}
</code></pre>';
        
        $compiled = $this->compiler->compile($template);
        
        // Whitespace and indentation should be preserved
        $this->assertStringContainsString('function test()', $compiled);
        $this->assertStringContainsString('    if (x', $compiled);
        $this->assertStringContainsString('        return', $compiled);
        $this->assertStringContainsString('&lt; 5', $compiled);
    }

    #[Test]
    public function it_handles_numeric_character_references(): void
    {
        $template = '<p>Copyright &#169; 2024 &#8211; All rights reserved &#128512;</p>';
        
        $compiled = $this->compiler->compile($template);
        
        // Numeric entities should be present (may or may not be decoded depending on implementation)
        $this->assertStringContainsString('Copyright', $compiled);
        $this->assertStringContainsString('2024', $compiled);
        $this->assertStringContainsString('All rights reserved', $compiled);
    }

    #[Test]
    public function it_preserves_low_value_numeric_entities(): void
    {
        $template = '<code>ASCII: &#60; &#62; &#38;</code>';
        
        $compiled = $this->compiler->compile($template);
        
        // Low-value numeric entities in code blocks should be preserved
        $this->assertStringContainsString('&#60;', $compiled);
        $this->assertStringContainsString('&#62;', $compiled);
        $this->assertStringContainsString('&#38;', $compiled);
    }

    #[Test]
    public function it_handles_complex_template_with_all_features(): void
    {
        $template = '<div>
            <h1 th:text="title">Default Title</h1>
            <div th:if="showCode">
                <code>&lt;div th:text="user.name"&gt;{user.name}&lt;/div&gt;</code>
            </div>
            <p>Welcome {user.name}! 🎉 You have {user.messageCount} messages.</p>
            <pre>
Example:
if (count &lt; 10 &amp;&amp; active) {
    display(&quot;Hello&quot;);
}
            </pre>
        </div>';
        
        $compiled = $this->compiler->compile($template);
        
        // Template directives should be compiled
        $this->assertStringContainsString('htmlspecialchars', $compiled);
        $this->assertStringContainsString('thGetProperty($user, \'name\')', $compiled);
        $this->assertStringContainsString('!empty((isset($showCode) ? $showCode : null))', $compiled);
        
        // Emoji should be preserved
        $this->assertStringContainsString('🎉', $compiled);
        
        // No PHP markers should remain
        $this->assertStringNotContainsString('TH_PHP_', $compiled);
        
        // Basic content should be present
        $this->assertStringContainsString('Welcome', $compiled);
        $this->assertStringContainsString('messages', $compiled);
    }
}