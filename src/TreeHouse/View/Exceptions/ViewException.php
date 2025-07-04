<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Exceptions;

use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;

/**
 * Exception thrown when view/template errors occur
 */
class ViewException extends BaseException
{
    protected string $errorCode = 'VIEW_001';
    protected int $statusCode = 500;
    protected string $severity = 'medium';

    public function __construct(string $message, ?string $userMessage = null, ?\Throwable $previous = null)
    {
        $userMessage = $userMessage ?: 'A template error occurred. Please try again or contact support.';
        
        parent::__construct($message, 0, $previous);
        
        $this->setUserMessage($userMessage);
    }
}