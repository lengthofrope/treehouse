<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers;

use DOMElement;

/**
 * Interface for TreeHouse directive processors
 * 
 * Each th: directive has its own processor class implementing this interface
 * for clean separation of concerns and extensibility.
 *
 * @package LengthOfRope\TreeHouse\View\Compilers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface DirectiveProcessorInterface
{
    /**
     * Process the directive on the given DOM element
     *
     * @param DOMElement $node The DOM element containing the directive
     * @param string $expression The directive expression value
     * @throws \RuntimeException If processing fails
     */
    public function process(DOMElement $node, string $expression): void;
}