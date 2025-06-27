<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;

/**
 * Middleware interface
 * 
 * Defines the contract for HTTP middleware components that can
 * process requests and responses in the router pipeline.
 * 
 * @package LengthOfRope\TreeHouse\Router\Middleware
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
interface MiddlewareInterface
{
    /**
     * Handle an incoming request
     * 
     * @param Request $request The HTTP request
     * @param callable $next The next middleware in the stack
     * @return Response The HTTP response
     */
    public function handle(Request $request, callable $next): Response;
}