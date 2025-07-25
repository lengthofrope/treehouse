<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers;

use DOMDocument;
use DOMXPath;
use DOMElement;
use RuntimeException;

/**
 * TreeHouse Template Compiler - Modular Architecture
 * 
 * Each directive type has its own processor class for clean separation of concerns.
 * Supports all features from template-object-support-plan.md
 *
 * @package LengthOfRope\TreeHouse\View\Compilers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class TreeHouseCompiler
{
    protected ExpressionValidator $validator;
    protected ExpressionCompiler $expressionCompiler;
    
    /** @var array<string, DirectiveProcessorInterface> */
    protected array $processors = [];
    
    /**
     * Processing order for directives (critical for correct compilation)
     */
    protected array $processingOrder = [
        'th:extend', 'th:section', 'th:yield', 'th:fragment', 'th:switch',
        'th:case', 'th:default', 'th:if', 'th:unless', 'th:repeat', 'th:include',
        'th:replace', 'th:method', 'th:csrf', 'th:field', 'th:errors', 'th:text',
        'th:raw', 'th:html'
    ];

    public function __construct(
        ?ExpressionValidator $validator = null,
        ?ExpressionCompiler $expressionCompiler = null
    ) {
        $this->validator = $validator ?? new ExpressionValidator();
        $this->expressionCompiler = $expressionCompiler ?? new ExpressionCompiler($this->validator);
        
        $this->initializeProcessors();
    }

    /**
     * Initialize directive processors
     */
    protected function initializeProcessors(): void
    {
        $this->processors = [
            // Layout and structure
            'th:extend' => new Processors\ExtendProcessor($this->expressionCompiler),
            'th:section' => new Processors\SectionProcessor($this->expressionCompiler),
            'th:yield' => new Processors\YieldProcessor($this->expressionCompiler),
            'th:fragment' => new Processors\FragmentProcessor($this->expressionCompiler),
            
            // Variables and logic
            'th:switch' => new Processors\SwitchProcessor($this->expressionCompiler),
            'th:case' => new Processors\SwitchProcessor($this->expressionCompiler), // Same processor handles case
            'th:default' => new Processors\SwitchProcessor($this->expressionCompiler), // Same processor handles default
            'th:if' => new Processors\IfProcessor($this->expressionCompiler),
            'th:unless' => new Processors\IfProcessor($this->expressionCompiler), // Same processor handles unless
            'th:repeat' => new Processors\RepeatProcessor($this->expressionCompiler),
            
            // Content inclusion
            'th:include' => new Processors\IncludeProcessor($this->expressionCompiler),
            'th:replace' => new Processors\ReplaceProcessor($this->expressionCompiler),
            
            // Form handling
            'th:method' => new Processors\MethodProcessor($this->expressionCompiler),
            'th:csrf' => new Processors\CsrfProcessor($this->expressionCompiler),
            'th:field' => new Processors\FieldProcessor($this->expressionCompiler),
            'th:errors' => new Processors\ErrorsProcessor($this->expressionCompiler),
            
            // Content rendering
            'th:text' => new Processors\TextProcessor($this->expressionCompiler),
            'th:raw' => new Processors\RawProcessor($this->expressionCompiler),
            'th:html' => new Processors\RawProcessor($this->expressionCompiler) // Same as raw
        ];
    }

    /**
     * Compile template to PHP
     */
    public function compile(string $template): string
    {
        // Simple templates (no th: attributes or brace expressions) can be returned as-is
        if (!str_contains($template, 'th:') && !preg_match('/\{[^}]+\}/', $template)) {
            return $this->wrapWithHelpers($template);
        }

        try {
            // Store original template for DOCTYPE preservation
            $originalTemplate = $template;
            $hasDoctype = preg_match('/^\s*<!DOCTYPE/i', $template);
            $hasHtmlTag = preg_match('/<html\b[^>]*>/i', $template);
            $isFullDocument = $hasDoctype && $hasHtmlTag;
            
            // Parse HTML with libxml
            $dom = $this->parseTemplate($template);
            $xpath = new DOMXPath($dom);
            
            // Process attributes in correct order
            $this->processAttributes($dom, $xpath);
            
            // Process text content with brace expressions
            $this->processTextContent($dom, $xpath);
            
            // Generate final PHP
            $compiled = $this->generatePhp($dom, $originalTemplate, $isFullDocument);
            
            return $this->wrapWithHelpers($compiled);
            
        } catch (\Throwable $e) {
            // Enhanced error reporting for debugging
            if (defined('TH_DEBUG') && constant('TH_DEBUG')) {
                throw new RuntimeException(
                    "TreeHouse Template Compilation Error: " . $e->getMessage() . 
                    "\nTemplate: " . substr($template, 0, 200) . "...",
                    0,
                    $e
                );
            }
            
            // In production, fall back to raw template
            return $this->wrapWithHelpers($template);
        }
    }

    /**
     * Parse template into DOM
     */
    protected function parseTemplate(string $template): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        
        // Suppress libxml errors
        $useInternalErrors = libxml_use_internal_errors(true);
        
        try {
            // Ensure proper UTF-8 encoding by adding XML declaration
            $template = '<?xml encoding="UTF-8">' . $template;
            
            // Try to load as HTML with proper UTF-8 handling
            if (!$dom->loadHTML($template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOENT)) {
                throw new RuntimeException('Failed to parse template as HTML');
            }
        } finally {
            libxml_use_internal_errors($useInternalErrors);
        }
        
        return $dom;
    }

    /**
     * Process th: attributes in correct order
     */
    protected function processAttributes(DOMDocument $dom, DOMXPath $xpath): void
    {
        // First, process specific directives in order
        foreach ($this->processingOrder as $directive) {
            if (!isset($this->processors[$directive])) {
                continue;
            }
            
            $processor = $this->processors[$directive];
            
            // Find elements with this directive using a more robust approach
            $elements = $this->findElementsWithAttribute($dom, $directive);
            
            foreach ($elements as $element) {
                if ($element instanceof DOMElement && $element->hasAttribute($directive)) {
                    $expression = $element->getAttribute($directive);
                    
                    try {
                        $processor->process($element, $expression);
                    } catch (\Throwable $e) {
                        throw new RuntimeException(
                            "Error processing {$directive}='{$expression}': " . $e->getMessage(),
                            0,
                            $e
                        );
                    }
                }
            }
        }
        
        // Then, process any remaining th: attributes as generic attribute setters
        $this->processGenericThAttributes($dom);
    }

    /**
     * Process any remaining th: attributes as generic attribute setters
     */
    protected function processGenericThAttributes(DOMDocument $dom): void
    {
        $allElements = $dom->getElementsByTagName('*');
        
        foreach ($allElements as $element) {
            if (!($element instanceof DOMElement)) {
                continue;
            }
            
            // Get all attributes that start with 'th:'
            $thAttributes = [];
            foreach ($element->attributes as $attr) {
                if (str_starts_with($attr->name, 'th:')) {
                    $thAttributes[] = $attr->name;
                }
            }
            
            // Process each th: attribute as a generic attribute setter
            foreach ($thAttributes as $thAttr) {
                $expression = $element->getAttribute($thAttr);
                $element->removeAttribute($thAttr);
                
                // Extract the actual attribute name (remove 'th:' prefix)
                $attributeName = substr($thAttr, 3);
                
                try {
                    // Check if expression contains brace expressions mixed with text
                    if (preg_match('/\{[^}]+\}/', $expression) && !preg_match('/^\{[^}]+\}$/', $expression)) {
                        // Handle mixed brace expressions and text (like "{users.john.name} Avatar")
                        $compiledExpression = $this->expressionCompiler->compileMixedText($expression);
                        $phpCode = "<?php echo {$compiledExpression}; ?>";
                    } elseif (preg_match('/^\{[^}]+\}$/', $expression)) {
                        // Single brace expression (like "{users.john.avatar}")
                        $innerExpression = trim(substr($expression, 1, -1));
                        $compiledExpression = $this->expressionCompiler->compileExpression($innerExpression);
                        $phpCode = "<?php echo htmlspecialchars({$compiledExpression}, ENT_QUOTES, 'UTF-8'); ?>";
                    } else {
                        // Handle pure expressions without braces (like "users.john.avatar")
                        $compiledExpression = $this->expressionCompiler->compileExpression($expression);
                        $phpCode = "<?php echo htmlspecialchars({$compiledExpression}, ENT_QUOTES, 'UTF-8'); ?>";
                    }
                    
                    // Use PHP marker to prevent HTML encoding
                    $marker = "<!--TH_PHP_ATTR:" . base64_encode($phpCode) . "-->";
                    $element->setAttribute($attributeName, $marker);
                } catch (\Throwable $e) {
                    throw new RuntimeException(
                        "Error processing {$thAttr}='{$expression}': " . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }
        }
    }

    /**
     * Find elements with a specific attribute (handles namespaced attributes)
     */
    protected function findElementsWithAttribute(DOMDocument $dom, string $attributeName): array
    {
        $elements = [];
        $allElements = $dom->getElementsByTagName('*');
        
        foreach ($allElements as $element) {
            if ($element instanceof DOMElement && $element->hasAttribute($attributeName)) {
                $elements[] = $element;
            }
        }
        
        return $elements;
    }

    /**
     * Process text content with brace expressions
     */
    protected function processTextContent(DOMDocument $dom, DOMXPath $xpath): void
    {
        $textNodes = $xpath->query('//text()');
        
        foreach ($textNodes as $textNode) {
            $content = $textNode->nodeValue;
            
            // Skip if no brace expressions
            if (!preg_match('/\{[^}]+\}/', $content)) {
                continue;
            }
            
            // Compile brace expressions using PHP markers
            $compiledContent = $this->compileBraceExpressions($content);
            
            if ($compiledContent !== $content) {
                // Replace the text node with a comment node containing the PHP marker
                $marker = "<!--TH_PHP_CONTENT:" . base64_encode($compiledContent) . "-->";
                $commentNode = $dom->createComment("TH_PHP_CONTENT:" . base64_encode($compiledContent));
                $textNode->parentNode->replaceChild($commentNode, $textNode);
            }
        }
    }

    /**
     * Compile brace expressions like {user.name}
     */
    protected function compileBraceExpressions(string $content): string
    {
        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) {
            $expression = trim($matches[1]);
            
            try {
                $compiled = $this->expressionCompiler->compileText($expression);
                return "<?php echo {$compiled}; ?>";
            } catch (\Throwable $e) {
                // Return original if compilation fails
                return $matches[0];
            }
        }, $content);
    }

    /**
     * Generate final PHP from DOM
     */
    protected function generatePhp(DOMDocument $dom, string $originalTemplate, bool $isFullDocument): string
    {
        if ($isFullDocument) {
            // For full documents, get the entire HTML
            $html = $dom->saveHTML();
            
            // Restore original DOCTYPE if it was modified
            if (preg_match('/^\s*<!DOCTYPE[^>]*>/i', $originalTemplate, $matches)) {
                $html = preg_replace('/^\s*<!DOCTYPE[^>]*>/i', $matches[0], $html);
            }
        } else {
            // For fragments, get only the body content
            $body = $dom->getElementsByTagName('body')->item(0);
            if ($body) {
                $html = '';
                foreach ($body->childNodes as $child) {
                    $html .= $dom->saveHTML($child);
                }
            } else {
                $html = $dom->saveHTML();
            }
        }
        
        // Remove the temporary XML declaration we added for UTF-8 handling
        $html = preg_replace('/<\?xml encoding="UTF-8"\?>/', '', $html);
        
        // Process PHP content markers FIRST (before any entity decoding)
        $processedHtml = $this->processPhpMarkers($html);
        
        // Then handle entity decoding while preserving code blocks
        $processedHtml = $this->preserveEntitiesInCodeBlocks($processedHtml);
        
        // Final UTF-8 entity decoding to ensure emojis display correctly
        $processedHtml = preg_replace_callback('/&#(\d+);/', function($matches) {
            return mb_chr((int)$matches[1], 'UTF-8');
        }, $processedHtml);
        
        return $processedHtml;
    }

    /**
     * Process PHP content markers and convert them back to PHP code
     */
    protected function processPhpMarkers(string $html): string
    {
        // Convert all PHP markers back to actual PHP code
        // Apply HTML entity decoding only to the PHP code content, not the entire document
        $html = preg_replace_callback('/<!--TH_PHP_CONTENT:([^-]+)-->/', function ($matches) {
            $decoded = base64_decode($matches[1]);
            // Only decode HTML entities within the PHP code itself
            return html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $html);
        
        $html = preg_replace_callback('/<!--TH_PHP_BEFORE:([^-]+)-->/', function ($matches) {
            $decoded = base64_decode($matches[1]);
            return html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $html);
        
        $html = preg_replace_callback('/<!--TH_PHP_AFTER:([^-]+)-->/', function ($matches) {
            $decoded = base64_decode($matches[1]);
            return html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $html);
        
        $html = preg_replace_callback('/<!--TH_PHP_REPLACE:([^-]+)-->/', function ($matches) {
            $decoded = base64_decode($matches[1]);
            return html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $html);
        
        $html = preg_replace_callback('/<!--TH_PHP_ATTR:([^-]+)-->/', function ($matches) {
            $decoded = base64_decode($matches[1]);
            return html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $html);
        
        return $html;
    }

    /**
     * Selectively decode HTML entities while preserving markup entities in code blocks
     */
    protected function preserveEntitiesInCodeBlocks(string $html): string
    {
        // Instead of global entity decoding, only decode specific emoji-related numeric entities
        // This preserves &lt; &gt; &amp; etc. in code blocks while still supporting emojis
        
        // Only decode numeric character references for emoji support (&#xxxx; format)
        $html = preg_replace_callback('/&#(\d+);/', function($matches) {
            $codePoint = (int)$matches[1];
            // Only decode if it's likely an emoji or special character (above basic ASCII)
            if ($codePoint > 127) {
                return mb_chr($codePoint, 'UTF-8');
            }
            // Keep basic ASCII numeric entities as-is
            return $matches[0];
        }, $html);
        
        // Decode some safe named entities that won't interfere with code display
        $safeEntities = [
            '&nbsp;' => ' ',
            '&ndash;' => '–',
            '&mdash;' => '—',
            '&lsquo;' => "'",
            '&rsquo;' => "'",
            '&ldquo;' => '"',
            '&rdquo;' => '"',
            '&hellip;' => '…'
        ];
        
        $html = str_replace(array_keys($safeEntities), array_values($safeEntities), $html);
        
        return $html;
    }

    /**
     * Wrap compiled template with helper functions
     */
    protected function wrapWithHelpers(string $compiled): string
    {
        $helpers = "<?php\n" .
                  "// TreeHouse Template Helper Functions\n" .
                  "if (!function_exists('thGetProperty')) {\n" .
                  "    function thGetProperty(\$object, \$property) {\n" .
                  "        if (is_array(\$object)) {\n" .
                  "            return \$object[\$property] ?? null;\n" .
                  "        }\n" .
                  "        if (is_object(\$object)) {\n" .
                  "            return \$object->{\$property} ?? null;\n" .
                  "        }\n" .
                  "        return null;\n" .
                  "    }\n" .
                  "}\n" .
                  "?>";
        
        return $helpers . $compiled;
    }
}
