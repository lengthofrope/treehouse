<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Rendering;

use LengthOfRope\TreeHouse\Errors\Classification\ClassificationResult;
use LengthOfRope\TreeHouse\Http\Request;
use Throwable;

/**
 * Interface for error renderers
 */
interface RendererInterface
{
    /**
     * Render an error response
     *
     * @param Throwable $exception The exception that occurred
     * @param ClassificationResult $classification Exception classification
     * @param array $context Collected context data
     * @param Request|null $request HTTP request (if available)
     * @param bool $debug Whether debug mode is enabled
     * @return string Rendered error content
     */
    public function render(
        Throwable $exception,
        ClassificationResult $classification,
        array $context,
        ?Request $request = null,
        bool $debug = false
    ): string;

    /**
     * Check if this renderer can handle the given request
     *
     * @param Request|null $request HTTP request (if available)
     * @return bool True if this renderer can handle the request
     */
    public function canRender(?Request $request): bool;

    /**
     * Get the content type for this renderer
     *
     * @return string Content type (e.g., 'application/json', 'text/html')
     */
    public function getContentType(): string;

    /**
     * Get the priority of this renderer (higher numbers = higher priority)
     *
     * @return int Priority level (0-100)
     */
    public function getPriority(): int;

    /**
     * Get the name/identifier of this renderer
     *
     * @return string Renderer name
     */
    public function getName(): string;
}