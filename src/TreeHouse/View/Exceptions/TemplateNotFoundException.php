<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Exceptions;

/**
 * Exception thrown when a template file is not found
 */
class TemplateNotFoundException extends ViewException
{
    protected string $errorCode = 'VIEW_002';
    protected int $statusCode = 500;
    protected string $severity = 'medium';

    public function __construct(string $template, array $searchedPaths = [], ?\Throwable $previous = null)
    {
        $pathsList = empty($searchedPaths) ? 'configured template paths' : implode("\n  - ", $searchedPaths);
        $message = "Template '{$template}' not found. Searched in:\n  - {$pathsList}";
        $userMessage = "The requested page template could not be found.";
        
        parent::__construct($message, $userMessage, $previous);
        
        $this->setContext([
            'template' => $template,
            'searched_paths' => $searchedPaths,
            'template_count' => count($searchedPaths)
        ]);
    }
}