<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router;

use Exception;

/**
 * Route Not Found Exception
 * 
 * Thrown when a route cannot be found for the given request.
 * 
 * @package LengthOfRope\TreeHouse\Router
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RouteNotFoundException extends Exception
{
    /**
     * Create a new route not found exception
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = "Route not found", int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the HTTP status code for this exception
     *
     * @return int HTTP status code
     */
    public function getStatusCode(): int
    {
        return 404;
    }
}