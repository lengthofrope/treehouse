<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Exceptions;

use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;

/**
 * Exception thrown when a route is not found
 */
class RouteNotFoundException extends BaseException
{
    protected string $errorCode = 'ROUTE_001';
    protected int $statusCode = 404;
    protected string $severity = 'low';

    public function __construct(string $method, string $uri, ?\Throwable $previous = null)
    {
        $message = "Route not found: {$method} {$uri}";
        $userMessage = "The page you're looking for could not be found.";
        
        parent::__construct($message, 0, $previous);
        
        $this->setUserMessage($userMessage);
        
        $this->setContext([
            'method' => $method,
            'uri' => $uri,
            'requested_at' => date('Y-m-d H:i:s')
        ]);
    }
}