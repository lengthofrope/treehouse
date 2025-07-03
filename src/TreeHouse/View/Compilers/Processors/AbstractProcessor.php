<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use LengthOfRope\TreeHouse\View\Compilers\DirectiveProcessorInterface;
use LengthOfRope\TreeHouse\View\Compilers\ExpressionCompiler;
use DOMElement;

/**
 * Abstract base class for directive processors
 * 
 * Provides common functionality for all directive processors
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
abstract class AbstractProcessor implements DirectiveProcessorInterface
{
    protected ExpressionCompiler $expressionCompiler;

    public function __construct(ExpressionCompiler $expressionCompiler)
    {
        $this->expressionCompiler = $expressionCompiler;
    }

    /**
     * Insert PHP code before the element
     */
    protected function insertBefore(DOMElement $node, string $phpCode): void
    {
        $textNode = $node->ownerDocument->createTextNode($phpCode);
        $node->parentNode->insertBefore($textNode, $node);
    }

    /**
     * Insert PHP code after the element
     */
    protected function insertAfter(DOMElement $node, string $phpCode): void
    {
        $textNode = $node->ownerDocument->createTextNode($phpCode);
        $node->parentNode->insertBefore($textNode, $node->nextSibling);
    }

    /**
     * Replace element content with PHP code
     */
    protected function replaceContent(DOMElement $node, string $phpCode): void
    {
        // Clear existing content
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        
        // For PHP code, we need to set it as raw content to avoid HTML encoding
        // We'll use a special marker that the compiler can recognize and process later
        $marker = "<!--TH_PHP_CONTENT:" . base64_encode($phpCode) . "-->";
        $commentNode = $node->ownerDocument->createComment("TH_PHP_CONTENT:" . base64_encode($phpCode));
        $node->appendChild($commentNode);
    }

    /**
     * Replace entire element with PHP code
     */
    protected function replaceElement(DOMElement $node, string $phpCode): void
    {
        $textNode = $node->ownerDocument->createTextNode($phpCode);
        $node->parentNode->insertBefore($textNode, $node);
        $this->removeNode($node);
    }

    /**
     * Remove node from DOM
     */
    protected function removeNode(DOMElement $node): void
    {
        if ($node->parentNode) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Move child nodes to parent and remove the element
     */
    protected function unwrapElement(DOMElement $node): void
    {
        $parent = $node->parentNode;
        while ($node->firstChild) {
            $child = $node->firstChild;
            $node->removeChild($child);
            $parent->insertBefore($child, $node);
        }
        $this->removeNode($node);
    }

    /**
     * Wrap element with conditional PHP code
     */
    protected function wrapWithCondition(DOMElement $node, string $condition): void
    {
        $startPhp = "<?php {$condition}: ?>";
        $endPhp = "<?php endif; ?>";
        
        $this->insertPhpBefore($node, $startPhp);
        $this->insertPhpAfter($node, $endPhp);
    }

    /**
     * Insert PHP code before element using marker system
     */
    protected function insertPhpBefore(DOMElement $node, string $phpCode): void
    {
        $marker = "<!--TH_PHP_BEFORE:" . base64_encode($phpCode) . "-->";
        $commentNode = $node->ownerDocument->createComment("TH_PHP_BEFORE:" . base64_encode($phpCode));
        $node->parentNode->insertBefore($commentNode, $node);
    }

    /**
     * Insert PHP code after element using marker system
     */
    protected function insertPhpAfter(DOMElement $node, string $phpCode): void
    {
        $marker = "<!--TH_PHP_AFTER:" . base64_encode($phpCode) . "-->";
        $commentNode = $node->ownerDocument->createComment("TH_PHP_AFTER:" . base64_encode($phpCode));
        $node->parentNode->insertBefore($commentNode, $node->nextSibling);
    }

    /**
     * Wrap element with loop PHP code
     */
    protected function wrapWithLoop(DOMElement $node, string $loopCode): void
    {
        $startPhp = "<?php {$loopCode}: ?>";
        $endPhp = "<?php endforeach; ?>";
        
        $this->insertBefore($node, $startPhp);
        $this->insertAfter($node, $endPhp);
    }
}