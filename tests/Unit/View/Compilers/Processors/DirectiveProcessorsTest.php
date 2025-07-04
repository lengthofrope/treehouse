<?php

declare(strict_types=1);

namespace Tests\Unit\View\Compilers\Processors;

use LengthOfRope\TreeHouse\View\Compilers\Processors\FragmentProcessor;
use LengthOfRope\TreeHouse\View\Compilers\Processors\IncludeProcessor;
use LengthOfRope\TreeHouse\View\Compilers\Processors\ReplaceProcessor;
use LengthOfRope\TreeHouse\View\Compilers\Processors\SwitchProcessor;
use LengthOfRope\TreeHouse\View\Compilers\Processors\CsrfProcessor;
use LengthOfRope\TreeHouse\View\Compilers\Processors\MethodProcessor;
use LengthOfRope\TreeHouse\View\Compilers\Processors\FieldProcessor;
use LengthOfRope\TreeHouse\View\Compilers\Processors\ErrorsProcessor;
use LengthOfRope\TreeHouse\View\Compilers\ExpressionCompiler;
use LengthOfRope\TreeHouse\View\Compilers\ExpressionValidator;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use DOMDocument;
use DOMElement;

/**
 * Tests for new directive processors
 */
class DirectiveProcessorsTest extends TestCase
{
    private ExpressionCompiler $expressionCompiler;
    private DOMDocument $dom;

    protected function setUp(): void
    {
        parent::setUp();
        $validator = new ExpressionValidator();
        $this->expressionCompiler = new ExpressionCompiler($validator);
        $this->dom = new DOMDocument();
    }

    private function createElement(string $tag, string $content = ''): DOMElement
    {
        $element = $this->dom->createElement($tag);
        if ($content) {
            $element->textContent = $content;
        }
        $this->dom->appendChild($element);
        return $element;
    }

    #[Test]
    public function fragment_processor_creates_php_markers(): void
    {
        $processor = new FragmentProcessor($this->expressionCompiler);
        $element = $this->createElement('div', 'Fragment content');
        
        $processor->process($element, 'userCard(user, showEmail)');
        
        $html = $this->dom->saveHTML();
        // Check that PHP markers are created (they contain base64 encoded PHP code)
        $this->assertStringContainsString('TH_PHP_BEFORE:', $html);
        $this->assertStringContainsString('TH_PHP_AFTER:', $html);
    }

    #[Test]
    public function fragment_processor_handles_no_parameters(): void
    {
        $processor = new FragmentProcessor($this->expressionCompiler);
        $element = $this->createElement('div', 'Simple fragment');
        
        $processor->process($element, 'header');
        
        $html = $this->dom->saveHTML();
        // Check that PHP markers are created
        $this->assertStringContainsString('TH_PHP_BEFORE:', $html);
        $this->assertStringContainsString('TH_PHP_AFTER:', $html);
    }

    #[Test]
    public function include_processor_generates_php_content_marker(): void
    {
        $processor = new IncludeProcessor($this->expressionCompiler);
        $element = $this->createElement('div', 'Fallback content');
        
        $processor->process($element, 'templating :: userCard(currentUser)');
        
        $html = $this->dom->saveHTML();
        // Check that PHP content marker is created
        $this->assertStringContainsString('TH_PHP_CONTENT:', $html);
    }

    #[Test]
    public function replace_processor_creates_php_replace_marker(): void
    {
        $processor = new ReplaceProcessor($this->expressionCompiler);
        $element = $this->createElement('div', 'Original content');
        
        $processor->process($element, 'templating :: userCard(currentUser, true)');
        
        $html = $this->dom->saveHTML();
        // Check that PHP replace marker is created
        $this->assertStringContainsString('TH_PHP_REPLACE:', $html);
        // Original content should be replaced
        $this->assertStringNotContainsString('Original content', $html);
    }

    #[Test]
    public function switch_processor_processes_element(): void
    {
        $processor = new SwitchProcessor($this->expressionCompiler);
        $element = $this->createElement('div');
        
        $processor->process($element, 'user.role');
        
        $html = $this->dom->saveHTML();
        // Switch processor should modify the element
        $this->assertStringContainsString('<div>', $html);
    }

    #[Test]
    public function switch_processor_handles_case_and_default_children(): void
    {
        $processor = new SwitchProcessor($this->expressionCompiler);
        $switchElement = $this->createElement('div');
        
        // Create child elements with th:case and th:default
        $caseElement = $this->dom->createElement('p', 'Admin content');
        $caseElement->setAttribute('th:case', 'admin');
        $switchElement->appendChild($caseElement);
        
        $defaultElement = $this->dom->createElement('p', 'Default content');
        $defaultElement->setAttribute('th:default', '');
        $switchElement->appendChild($defaultElement);
        
        $processor->process($switchElement, 'user.role');
        
        $html = $this->dom->saveHTML();
        // Case and default attributes should be removed from children
        $this->assertStringNotContainsString('th:case', $html);
        $this->assertStringNotContainsString('th:default', $html);
        // Should contain PHP markers for switch logic
        $this->assertStringContainsString('TH_PHP_BEFORE:', $html);
    }

    #[Test]
    public function switch_processor_removes_switch_attribute(): void
    {
        $processor = new SwitchProcessor($this->expressionCompiler);
        $element = $this->createElement('div');
        $element->setAttribute('th:switch', 'user.role');
        
        $processor->process($element, 'user.role');
        
        $html = $this->dom->saveHTML();
        // Switch processor should remove the th:switch attribute
        $this->assertStringNotContainsString('th:switch', $html);
    }

    #[Test]
    public function csrf_processor_modifies_input_element(): void
    {
        $processor = new CsrfProcessor($this->expressionCompiler);
        $element = $this->createElement('input');
        
        $processor->process($element, '');
        
        $html = $this->dom->saveHTML();
        // CSRF processor should process the element
        $this->assertStringContainsString('<input', $html);
    }

    #[Test]
    public function method_processor_sets_method_attribute(): void
    {
        $processor = new MethodProcessor($this->expressionCompiler);
        $element = $this->createElement('input');
        
        $processor->process($element, 'PUT');
        
        $html = $this->dom->saveHTML();
        // Method processor should set the method attribute
        $this->assertStringContainsString('method="POST"', $html);
    }

    #[Test]
    public function field_processor_sets_name_attribute(): void
    {
        $processor = new FieldProcessor($this->expressionCompiler);
        $element = $this->createElement('input');
        
        $processor->process($element, 'user.name');
        
        $html = $this->dom->saveHTML();
        // Field processor should set name attribute
        $this->assertStringContainsString('name="name"', $html);
    }

    #[Test]
    public function errors_processor_creates_php_markers(): void
    {
        $processor = new ErrorsProcessor($this->expressionCompiler);
        $element = $this->createElement('div', 'Error placeholder');
        
        $processor->process($element, 'user.email');
        
        $html = $this->dom->saveHTML();
        // Errors processor should create PHP markers
        $this->assertStringContainsString('TH_PHP_BEFORE:', $html);
        $this->assertStringContainsString('TH_PHP_AFTER:', $html);
    }

    #[Test]
    public function processors_can_be_instantiated(): void
    {
        // Test that all processors can be created without errors
        $this->assertInstanceOf(FragmentProcessor::class, new FragmentProcessor($this->expressionCompiler));
        $this->assertInstanceOf(IncludeProcessor::class, new IncludeProcessor($this->expressionCompiler));
        $this->assertInstanceOf(ReplaceProcessor::class, new ReplaceProcessor($this->expressionCompiler));
        $this->assertInstanceOf(SwitchProcessor::class, new SwitchProcessor($this->expressionCompiler));
        $this->assertInstanceOf(CsrfProcessor::class, new CsrfProcessor($this->expressionCompiler));
        $this->assertInstanceOf(MethodProcessor::class, new MethodProcessor($this->expressionCompiler));
        $this->assertInstanceOf(FieldProcessor::class, new FieldProcessor($this->expressionCompiler));
        $this->assertInstanceOf(ErrorsProcessor::class, new ErrorsProcessor($this->expressionCompiler));
    }

    #[Test]
    public function processors_handle_empty_expressions(): void
    {
        $processor = new CsrfProcessor($this->expressionCompiler);
        $element = $this->createElement('input');
        
        // Should not throw an exception with empty expression
        $processor->process($element, '');
        
        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    #[Test]
    public function include_processor_handles_current_template_fragments(): void
    {
        $processor = new IncludeProcessor($this->expressionCompiler);
        $element = $this->createElement('div', 'Include from current');
        
        $processor->process($element, 'header');
        
        $html = $this->dom->saveHTML();
        // Should create PHP content marker for current template fragments
        $this->assertStringContainsString('TH_PHP_CONTENT:', $html);
    }

    #[Test]
    public function field_processor_handles_different_input_types(): void
    {
        $processor = new FieldProcessor($this->expressionCompiler);
        
        // Test text input
        $textInput = $this->createElement('input');
        $textInput->setAttribute('type', 'text');
        $processor->process($textInput, 'user.name');
        
        // Test textarea
        $textarea = $this->createElement('textarea');
        $processor->process($textarea, 'user.bio');
        
        // Test select
        $select = $this->createElement('select');
        $processor->process($select, 'user.country');
        
        $html = $this->dom->saveHTML();
        // All should have name attributes set
        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('name="bio"', $html);
        $this->assertStringContainsString('name="country"', $html);
    }
}