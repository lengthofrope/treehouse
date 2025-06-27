<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers;

use LengthOfRope\TreeHouse\Support\{Collection, Arr, Str, Carbon, Uuid};
use DOMDocument;
use DOMXPath;
use DOMElement;
use RuntimeException;

/**
 * TreeHouse template compiler
 * 
 * Compiles .th.html and .th.php templates with th: attributes into optimized PHP code.
 * Provides HTML-valid syntax with deep Support class integration.
 * 
 * @package LengthOfRope\TreeHouse\View\Compilers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class TreeHouseCompiler
{
    /**
     * Support classes auto-imported in templates
     */
    protected array $supportClasses = [
        'Collection' => 'LengthOfRope\\TreeHouse\\Support\\Collection',
        'Arr' => 'LengthOfRope\\TreeHouse\\Support\\Arr',
        'Str' => 'LengthOfRope\\TreeHouse\\Support\\Str',
        'Carbon' => 'LengthOfRope\\TreeHouse\\Support\\Carbon',
        'Uuid' => 'LengthOfRope\\TreeHouse\\Support\\Uuid',
    ];

    /**
     * Attribute processing order (critical for correct compilation)
     */
    protected array $attributeProcessingOrder = [
        'th:if', 'th:unless',                    // Conditionals first
        'th:repeat',                             // Loops next
        'th:text', 'th:html',                   // Content modifications
        'th:attr', 'th:class', 'th:style',      // Attribute modifications
        'th:extend', 'th:section', 'th:yield',  // Layout directives
        'th:component',                         // Components
        'th:remove',                            // Removal last
    ];

    /**
     * Helper functions injected into templates (disabled - using global helpers)
     */
    protected array $helperFunctions = [
        // Disabled - using global helper functions instead
    ];

    /**
     * Boolean attribute placeholders for post-processing
     */
    protected array $booleanAttributePlaceholders = [];

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
            // Reset boolean attribute placeholders for each compilation
            $this->booleanAttributePlaceholders = [];
            
            // Parse HTML with libxml
            $dom = $this->parseTemplate($template);
            $xpath = new DOMXPath($dom);
            
            // Process attributes in correct order
            $this->processAttributes($dom, $xpath);
            
            // Process text content with brace expressions
            $this->processTextContent($dom, $xpath);
            
            // Generate final PHP
            $compiled = $this->generatePhp($dom);
            
            return $this->wrapWithHelpers($compiled);
            
        } catch (\Throwable $e) {
            // Fallback: treat as raw PHP if DOM parsing fails
            return $this->wrapWithHelpers($template);
        }
    }

    /**
     * Parse template HTML
     */
    protected function parseTemplate(string $template): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        
        // Wrap template content to ensure proper parsing with UTF-8 declaration
        $wrappedTemplate = '<?xml encoding="UTF-8"?><div>' . $template . '</div>';
        
        // Load HTML with error suppression and proper UTF-8 handling
        libxml_use_internal_errors(true);
        $success = $dom->loadHTML($wrappedTemplate, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        if (!$success) {
            throw new RuntimeException('Failed to parse template HTML');
        }
        
        return $dom;
    }

    /**
     * Process all th: attributes
     */
    protected function processAttributes(DOMDocument $dom, DOMXPath $xpath): void
    {
        // Process standard attributes in specific order
        foreach ($this->attributeProcessingOrder as $attribute) {
            $this->processAttributeType($dom, $xpath, $attribute);
        }
        
        // Process any remaining universal th: attributes not in the standard list
        $this->processUniversalAttributes($dom, $xpath);
    }

    /**
     * Process a specific attribute type
     */
    protected function processAttributeType(DOMDocument $dom, DOMXPath $xpath, string $attribute): void
    {
        // Use proper XPath syntax for attributes with colons
        $nodes = $xpath->query("//*[@*[name()='{$attribute}']]");
        
        // Check if query was successful
        if ($nodes === false) {
            return;
        }
        
        // Process nodes in reverse order to handle nested structures
        $nodeArray = [];
        foreach ($nodes as $node) {
            $nodeArray[] = $node;
        }
        
        foreach (array_reverse($nodeArray) as $node) {
            if ($node instanceof DOMElement) {
                $expression = $node->getAttribute($attribute);
                $this->processAttribute($node, $attribute, $expression);
                $node->removeAttribute($attribute);
            }
        }
    }

    /**
     * Process universal th: attributes not in the standard processing order
     */
    protected function processUniversalAttributes(DOMDocument $dom, DOMXPath $xpath): void
    {
        // Find all elements with th: attributes
        $allThNodes = $xpath->query("//*[@*[starts-with(name(), 'th:')]]");
        
        if ($allThNodes === false) {
            return;
        }

        foreach ($allThNodes as $node) {
            if ($node instanceof DOMElement) {
                // Get all th: attributes on this node
                $attributesToProcess = [];
                foreach ($node->attributes as $attr) {
                    if (str_starts_with($attr->name, 'th:') && !in_array($attr->name, $this->attributeProcessingOrder)) {
                        $attributesToProcess[] = $attr->name;
                    }
                }
                
                // Process universal attributes
                foreach ($attributesToProcess as $attribute) {
                    $expression = $node->getAttribute($attribute);
                    $this->processAttribute($node, $attribute, $expression);
                    $node->removeAttribute($attribute);
                }
            }
        }
    }

    /**
     * Process individual th: attribute
     */
    protected function processAttribute(DOMElement $node, string $attribute, string $expression): void
    {
        // TIER 1: Standard th: attributes with specialized processing
        switch ($attribute) {
            case 'th:if':
                $this->wrapWithCondition($node, "if ({$this->compileExpression($expression)})");
                break;
                
            case 'th:unless':
                $this->wrapWithCondition($node, "if (!({$this->compileExpression($expression)}))");
                break;
                
            case 'th:repeat':
                $this->wrapWithLoop($node, $expression);
                break;
                
            case 'th:text':
                $this->setTextContent($node, $this->compileTextExpression($expression));
                break;
                
            case 'th:html':
                $this->setHtmlContent($node, $this->compileHtmlExpression($expression));
                break;
                
            case 'th:class':
                $this->addClassAttribute($node, $expression);
                break;
                
            case 'th:attr':
                $this->processAttributeExpression($node, $expression);
                break;
                
            case 'th:extend':
                $this->processExtend($node, $expression);
                break;
                
            case 'th:section':
                $this->processSection($node, $expression);
                break;
                
            case 'th:yield':
                $this->processYield($node, $expression);
                break;
                
            case 'th:component':
                $this->processComponent($node, $expression);
                break;
                
            case 'th:remove':
                $this->removeNode($node);
                break;
                
            default:
                // TIER 2: Universal th: attributes - any attribute can be prefixed with th:
                if (str_starts_with($attribute, 'th:')) {
                    $htmlAttribute = substr($attribute, 3); // Remove 'th:' prefix
                    $this->processDynamicAttribute($node, $htmlAttribute, $expression);
                }
                break;
        }
    }

    /**
     * Process dynamic attribute with universal th: prefix support
     */
    protected function processDynamicAttribute(DOMElement $node, string $attrName, string $expression): void
    {
        // Handle different types of expressions
        if (str_contains($expression, '{')) {
            // Brace syntax: "static text {$dynamic} more text"
            if ($this->isBooleanAttribute($attrName)) {
                // For boolean attributes, compile the expression inside braces directly
                $compiledValue = $this->compileBooleanBraceExpression($expression);
            } else {
                $compiledValue = $this->compileBraceExpressions($expression);
            }
        } elseif ($this->isDotNotation($expression)) {
            // Dot notation: "user.name"
            $compiledValue = $this->compileExpression($expression);
        } elseif ($this->isStaticText($expression)) {
            // Static text: just output as string literal
            $compiledValue = "'" . addslashes($expression) . "'";
        } else {
            // Dynamic PHP expression: "$user['id']"
            $compiledValue = $this->compileExpression($expression);
        }
        
        // Handle boolean attributes (selected, checked, disabled, etc.)
        if ($this->isBooleanAttribute($attrName)) {
            // For boolean attributes, we need special handling since DOM normalizes them
            // Use a temporary non-boolean attribute that we'll replace in generatePhp
            $placeholder = "___TH_BOOL_" . uniqid() . "___";
            $this->booleanAttributePlaceholders[$placeholder] = [
                'condition' => $compiledValue,
                'attribute' => $attrName
            ];
            // Use a temporary attribute name that won't be normalized
            $node->setAttribute("data-th-bool-{$attrName}", $placeholder);
        } else {
            // For regular attributes, handle null values by not outputting anything
            $php = "<?php \$val = {$compiledValue}; if (\$val !== null) echo \$val; ?>";
            $node->setAttribute($attrName, $php);
        }
    }

    /**
     * Check if expression is dot notation: user.name, config.db.host, etc.
     */
    protected function isDotNotation(string $expression): bool
    {
        return (bool) preg_match('/^[a-zA-Z_]\w*\.[a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*$/', $expression);
    }

    /**
     * Check if expression is static text (no variables or PHP syntax)
     */
    protected function isStaticText(string $expression): bool
    {
        // Check if it contains PHP variables or PHP-specific syntax
        // Allow : and . for CSS and normal text, but not when part of PHP operators
        return !preg_match('/\$|\[|\]|\(|\)|->|::|==|!=|&&|\|\||(\?[^:]*:)/', $expression);
    }

    /**
     * Compile brace expressions: "text {expr} more {expr2}"
     */
    protected function compileBraceExpressions(string $value): string
    {
        $result = preg_replace_callback(
            '/\{([^}]+)\}/',
            function($matches) {
                $expr = trim($matches[1]);
                $expr = $this->compileDotNotation($expr);
                $expr = $this->compileExpression($expr);
                return "' . ({$expr}) . '";
            },
            "'" . addslashes($value) . "'"
        );
        
        // Clean up empty string concatenations
        $result = preg_replace("/^'' \. /", "", $result);
        $result = preg_replace("/ \. ''$/", "", $result);
        
        return $result;
    }

    /**
     * Convert dot notation to array access: user.name → $user['name']
     */
    protected function compileDotNotation(string $expression): string 
    {
        // Convert user.name.first → $user['name']['first']
        return preg_replace_callback(
            '/\b([a-zA-Z_]\w*)\.([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*)\b/',
            function($matches) {
                $var = $matches[1];
                $path = $matches[2];
                $keys = explode('.', $path);
                $result = '$' . $var;
                foreach ($keys as $key) {
                    $result .= "['$key']";
                }
                return $result;
            },
            $expression
        );
    }

    /**
     * Compile expression with Support class integration
     */
    protected function compileExpression(string $expression): string
    {
        // First, handle dot notation conversion
        $expression = $this->compileDotNotation($expression);
        
        // Handle Support class static calls
        foreach ($this->supportClasses as $alias => $class) {
            $expression = preg_replace(
                '/\b' . $alias . '::/i',
                '\\' . $class . '::',
                $expression
            );
        }
        
        // Handle collection method calls on arrays
        $expression = preg_replace_callback(
            '/\$(\w+)->(\w+)\(([^)]*)\)/',
            function ($matches) {
                $var = $matches[1];
                $method = $matches[2];
                $args = $matches[3];
                
                // Check if this is a Collection method
                $collectionMethods = ['count', 'isEmpty', 'isNotEmpty', 'first', 'last', 'take', 'skip', 'where', 'filter', 'map', 'sortBy', 'groupBy', 'pluck'];
                
                if (in_array($method, $collectionMethods)) {
                    return "(is_array(\${$var}) ? thCollect(\${$var})->{$method}({$args}) : \${$var}->{$method}({$args}))";
                }
                
                return $matches[0];
            },
            $expression
        );
        
        return $expression;
    }

    /**
     * Compile text expression (escaped)
     */
    protected function compileTextExpression(string $expression): string
    {
        // Handle brace expressions in text
        if (str_contains($expression, '{')) {
            $compiledValue = $this->compileBraceExpressions($expression);
        } else {
            $compiledValue = $this->compileExpression($expression);
        }
        
        return "<?php echo thEscape({$compiledValue}); ?>";
    }

    /**
     * Compile HTML expression (raw)
     */
    protected function compileHtmlExpression(string $expression): string
    {
        // Handle brace expressions in HTML
        if (str_contains($expression, '{')) {
            $compiledValue = $this->compileBraceExpressions($expression);
        } else {
            $compiledValue = $this->compileExpression($expression);
        }
        
        return "<?php echo thRaw({$compiledValue}); ?>";
    }

    /**
     * Wrap node with conditional
     */
    protected function wrapWithCondition(DOMElement $node, string $condition): void
    {
        $php = $node->ownerDocument->createTextNode("<?php {$condition}: ?>");
        $endPhp = $node->ownerDocument->createTextNode("<?php endif; ?>");
        
        $node->parentNode->insertBefore($php, $node);
        $node->parentNode->insertBefore($endPhp, $node->nextSibling);
    }

    /**
     * Wrap node with loop
     */
    protected function wrapWithLoop(DOMElement $node, string $expression): void
    {
        // Parse repeat expression: "item $items" or "key,item $items"
        if (preg_match('/^(\w+)(?:,(\w+))?\s+\$(\w+)$/', trim($expression), $matches)) {
            $first = $matches[1];
            $second = $matches[2] ?? null;
            $array = $matches[3];
            
            if ($second) {
                // key,item format
                $key = $first;
                $item = $second;
                $loop = "foreach (\${$array} as \${$key} => \${$item})";
            } else {
                // item format
                $item = $first;
                $loop = "foreach (\${$array} as \${$item})";
            }
            
            $php = $node->ownerDocument->createTextNode("<?php {$loop}: ?>");
            $endPhp = $node->ownerDocument->createTextNode("<?php endforeach; ?>");
            
            $node->parentNode->insertBefore($php, $node);
            $node->parentNode->insertBefore($endPhp, $node->nextSibling);
        }
    }

    /**
     * Set text content
     */
    protected function setTextContent(DOMElement $node, string $php): void
    {
        // Clear existing content and add PHP
        $node->nodeValue = '';
        $textNode = $node->ownerDocument->createTextNode($php);
        $node->appendChild($textNode);
    }

    /**
     * Set HTML content
     */
    protected function setHtmlContent(DOMElement $node, string $php): void
    {
        // Clear existing content and add PHP
        $node->nodeValue = '';
        $textNode = $node->ownerDocument->createTextNode($php);
        $node->appendChild($textNode);
    }

    /**
     * Add class attribute
     */
    protected function addClassAttribute(DOMElement $node, string $expression): void
    {
        // Use the same logic as universal th: attributes
        if (str_contains($expression, '{')) {
            // Brace syntax: "static text {$dynamic} more text"
            $compiledValue = $this->compileBraceExpressions($expression);
        } elseif ($this->isDotNotation($expression)) {
            // Dot notation: "user.name"
            $compiledValue = $this->compileExpression($expression);
        } elseif ($this->isStaticText($expression)) {
            // Static text: just output as string literal
            $compiledValue = "'" . addslashes($expression) . "'";
        } else {
            // Dynamic PHP expression: "$user['id']"
            $compiledValue = $this->compileExpression($expression);
        }
        
        $existing = $node->getAttribute('class');
        $php = "<?php echo " . ($existing ? "'{$existing} ' . " : '') . "{$compiledValue}; ?>";
        $node->setAttribute('class', $php);
    }

    /**
     * Process attribute expression
     */
    protected function processAttributeExpression(DOMElement $node, string $expression): void
    {
        // Parse attr expression: "id='user-' + $user->id, class=$user->status"
        $attributes = explode(',', $expression);
        
        foreach ($attributes as $attr) {
            if (preg_match('/^\s*(\w+)\s*=\s*(.+)$/', trim($attr), $matches)) {
                $name = $matches[1];
                $value = $matches[2];
                $php = "<?php echo {$this->compileExpression($value)}; ?>";
                $node->setAttribute($name, $php);
            }
        }
    }

    /**
     * Process extend directive
     */
    protected function processExtend(DOMElement $node, string $expression): void
    {
        // Add the extend call at the beginning of the document
        $php = "<?php \$this->extend('{$expression}'); ?>";
        $textNode = $node->ownerDocument->createTextNode($php);
        $node->parentNode->insertBefore($textNode, $node);
        
        // Move child nodes up to parent instead of removing them
        $parent = $node->parentNode;
        while ($node->firstChild) {
            $child = $node->firstChild;
            $node->removeChild($child);
            $parent->insertBefore($child, $node);
        }
        
        // Remove the empty extend element
        $this->removeNode($node);
    }

    /**
     * Process section directive
     */
    protected function processSection(DOMElement $node, string $expression): void
    {
        $startPhp = $node->ownerDocument->createTextNode("<?php \$this->startSection('{$expression}'); ?>");
        $endPhp = $node->ownerDocument->createTextNode("<?php \$this->endSection(); ?>");
        
        $node->parentNode->insertBefore($startPhp, $node);
        $node->parentNode->insertBefore($endPhp, $node->nextSibling);
        
        $node->removeAttribute('th:section');
    }

    /**
     * Process yield directive
     */
    protected function processYield(DOMElement $node, string $expression): void
    {
        // Parse expression to get section name and optional default
        $sectionName = trim($expression);
        $defaultValue = '';
        
        // Check if expression contains a default value: "content, 'default text'"
        if (preg_match('/^([^,]+),\s*(.+)$/', $expression, $matches)) {
            $sectionName = trim($matches[1], '\'"');
            $defaultValue = trim($matches[2]);
        } else {
            $sectionName = trim($sectionName, '\'"');
        }
        
        // Generate PHP code to yield the section
        if ($defaultValue) {
            $php = "<?php echo \$this->yieldSection('{$sectionName}', {$defaultValue}); ?>";
        } else {
            $php = "<?php echo \$this->yieldSection('{$sectionName}'); ?>";
        }
        
        // Replace the element content with the yield PHP code
        $node->nodeValue = '';
        $textNode = $node->ownerDocument->createTextNode($php);
        $node->appendChild($textNode);
        
        $node->removeAttribute('th:yield');
    }

    /**
     * Process component directive
     */
    protected function processComponent(DOMElement $node, string $expression): void
    {
        // Extract props from other th: attributes
        $props = [];
        foreach ($node->attributes as $attr) {
            if (str_starts_with($attr->name, 'th:props-')) {
                $propName = substr($attr->name, 9);
                $props[] = "'{$propName}' => {$this->compileExpression($attr->value)}";
                $node->removeAttribute($attr->name);
            }
        }
        
        $propsStr = $props ? '[' . implode(', ', $props) . ']' : '[]';
        $php = "<?php echo \$this->component('{$expression}', {$propsStr}); ?>";
        
        $textNode = $node->ownerDocument->createTextNode($php);
        $node->parentNode->insertBefore($textNode, $node);
        $this->removeNode($node);
    }

    /**
     * Remove node
     */
    protected function removeNode(DOMElement $node): void
    {
        if ($node->parentNode) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Generate final PHP from DOM
     */
    protected function generatePhp(DOMDocument $dom): string
    {
        $html = $dom->saveHTML();
        
        // Remove the wrapper div we added
        if (preg_match('/<div>(.*)<\/div>$/s', $html, $matches)) {
            $html = $matches[1];
        }
        
        // Decode PHP tags that were encoded by saveHTML
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Fix URL encoding in PHP tags (spaces become %20)
        $html = preg_replace_callback('/(<\?php[^>]*>)/', function($matches) {
            return urldecode($matches[1]);
        }, $html);
        
        // Process boolean attribute placeholders
        foreach ($this->booleanAttributePlaceholders as $placeholder => $config) {
            // Find and replace the temporary data-th-bool-* attributes
            $tempAttr = "data-th-bool-{$config['attribute']}=\"{$placeholder}\"";
            $replacement = "<?php if ({$config['condition']}) echo '{$config['attribute']}'; ?>";
            $html = str_replace($tempAttr, $replacement, $html);
        }
        
        // Fix emoji encoding issues by converting HTML numeric entities back to UTF-8
        $html = $this->fixEmojiEncoding($html);
        
        return $html;
    }

    /**
     * Fix emoji encoding by converting HTML numeric entities back to UTF-8
     */
    protected function fixEmojiEncoding(string $html): string
    {
        // Use mb_decode_numericentity to properly decode all numeric entities
        $convmap = [0x0, 0x10FFFF, 0, 0xFFFFFF];
        $html = mb_decode_numericentity($html, $convmap, 'UTF-8');
        
        return $html;
    }

    /**
     * Wrap compiled template with helper functions
     */
    protected function wrapWithHelpers(string $compiled): string
    {
        // Helper functions are now loaded globally, no need to inject
        return $compiled;
    }

    /**
     * Get supported attributes
     */
    public function getSupportedAttributes(): array
    {
        return $this->attributeProcessingOrder;
    }

    /**
     * Check if attribute is supported
     */
    public function isAttributeSupported(string $attribute): bool
    {
        return in_array($attribute, $this->attributeProcessingOrder);
    }

    /**
     * Process text content with brace expressions
     */
    protected function processTextContent(DOMDocument $dom, DOMXPath $xpath): void
    {
        // Find all text nodes that contain brace expressions, but exclude those inside code/pre tags
        $textNodes = $xpath->query('//text()[contains(., "{") and not(ancestor::code) and not(ancestor::pre)]');
        
        foreach ($textNodes as $textNode) {
            $content = $textNode->textContent;
            
            // Only process if it contains brace expressions
            if (preg_match('/\{[^}]+\}/', $content)) {
                // Compile the brace expressions
                $compiledContent = $this->compileBraceExpressions($content);
                
                // Create PHP output
                $phpCode = "<?php echo thEscape({$compiledContent}); ?>";
                
                // Replace the text node with the PHP code
                $newTextNode = $dom->createTextNode($phpCode);
                $textNode->parentNode->replaceChild($newTextNode, $textNode);
            }
        }
    }

    /**
     * Check if an attribute is a boolean attribute (should be present or absent, not have a value)
     */
    protected function isBooleanAttribute(string $attrName): bool
    {
        $booleanAttributes = [
            'selected', 'checked', 'disabled', 'readonly', 'multiple', 'autofocus',
            'autoplay', 'controls', 'defer', 'hidden', 'loop', 'open', 'required',
            'reversed', 'scoped', 'seamless', 'itemscope', 'novalidate', 'allowfullscreen',
            'formnovalidate', 'default', 'inert', 'truespeed'
        ];
        
        return in_array(strtolower($attrName), $booleanAttributes);
    }

    /**
     * Compile brace expressions for boolean attributes (no string wrapping)
     */
    protected function compileBooleanBraceExpression(string $value): string
    {
        // For boolean attributes, we only expect a single brace expression
        if (preg_match('/^\{([^}]+)\}$/', $value, $matches)) {
            $expr = trim($matches[1]);
            $expr = $this->compileDotNotation($expr);
            $expr = $this->compileExpression($expr);
            return $expr;
        }
        
        // Fallback to regular compilation
        return $this->compileExpression($value);
    }
}
