<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Rendering;

use LengthOfRope\TreeHouse\Errors\Classification\ClassificationResult;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use Throwable;

/**
 * Manages error renderers and coordinates error response generation
 */
class RenderManager
{
    /** @var RendererInterface[] */
    private array $renderers = [];
    
    private bool $debug;
    private string $defaultRenderer;

    public function __construct(bool $debug = false, string $defaultRenderer = 'html')
    {
        $this->debug = $debug;
        $this->defaultRenderer = $defaultRenderer;
        
        // Register default renderers
        $this->registerDefaultRenderers();
    }

    /**
     * Register a renderer
     */
    public function registerRenderer(RendererInterface $renderer): void
    {
        $this->renderers[$renderer->getName()] = $renderer;
        
        // Sort by priority (highest first)
        uasort($this->renderers, function (RendererInterface $a, RendererInterface $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    /**
     * Get a specific renderer by name
     */
    public function getRenderer(string $name): ?RendererInterface
    {
        return $this->renderers[$name] ?? null;
    }

    /**
     * Get all registered renderers
     */
    public function getRenderers(): array
    {
        return $this->renderers;
    }

    /**
     * Render an error response
     */
    public function render(
        Throwable $exception,
        ClassificationResult $classification,
        array $context,
        ?Request $request = null
    ): Response {
        $renderer = $this->selectRenderer($request);
        
        $content = $renderer->render(
            $exception,
            $classification,
            $context,
            $request,
            $this->debug
        );

        $statusCode = $this->getHttpStatusCode($exception, $classification);
        
        $response = new Response($content, $statusCode);
        $response->setHeader('Content-Type', $renderer->getContentType());
        
        // Add security headers for error responses
        $this->addSecurityHeaders($response, $classification);
        
        return $response;
    }

    /**
     * Render error content without creating a Response object
     */
    public function renderContent(
        Throwable $exception,
        ClassificationResult $classification,
        array $context,
        ?Request $request = null,
        ?string $rendererName = null
    ): string {
        if ($rendererName) {
            $renderer = $this->getRenderer($rendererName);
            if (!$renderer) {
                throw new \InvalidArgumentException("Renderer '{$rendererName}' not found");
            }
        } else {
            $renderer = $this->selectRenderer($request);
        }
        
        return $renderer->render(
            $exception,
            $classification,
            $context,
            $request,
            $this->debug
        );
    }

    /**
     * Select the appropriate renderer for the request
     */
    public function selectRenderer(?Request $request = null): RendererInterface
    {
        // Try each renderer in priority order
        foreach ($this->renderers as $renderer) {
            if ($renderer->canRender($request)) {
                return $renderer;
            }
        }

        // Fallback to default renderer
        $defaultRenderer = $this->getRenderer($this->defaultRenderer);
        if ($defaultRenderer) {
            return $defaultRenderer;
        }

        // Ultimate fallback - create a basic renderer
        return $this->createFallbackRenderer();
    }

    /**
     * Get HTTP status code for the exception
     */
    private function getHttpStatusCode(Throwable $exception, ClassificationResult $classification): int
    {
        // Check if it's an HTTP exception with status code
        if (method_exists($exception, 'getStatusCode')) {
            return call_user_func([$exception, 'getStatusCode']);
        }

        // Map based on classification
        return match ($classification->category) {
            'validation' => 400, // Bad Request
            'authentication' => 401, // Unauthorized
            'authorization' => 403, // Forbidden
            'not_found' => 404, // Not Found
            'method_not_allowed' => 405, // Method Not Allowed
            'conflict' => 409, // Conflict
            'rate_limit' => 429, // Too Many Requests
            'database', 'system' => 500, // Internal Server Error
            'service_unavailable' => 503, // Service Unavailable
            default => 500 // Internal Server Error
        };
    }

    /**
     * Add security headers to error responses
     */
    private function addSecurityHeaders(Response $response, ClassificationResult $classification): void
    {
        // Prevent caching of error responses
        $response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->setHeader('Pragma', 'no-cache');
        $response->setHeader('Expires', '0');
        
        // Security headers
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        
        // Additional security for security-related errors
        if ($classification->isSecurity) {
            $response->setHeader('X-Security-Error', 'true');
        }
        
        // Rate limiting hint for rate limit errors
        if ($classification->category === 'rate_limit') {
            $response->setHeader('Retry-After', '60');
        }
    }

    /**
     * Register default renderers
     */
    private function registerDefaultRenderers(): void
    {
        $this->registerRenderer(new JsonRenderer());
        $this->registerRenderer(new HtmlRenderer());
        $this->registerRenderer(new CliRenderer());
    }

    /**
     * Create a fallback renderer when no suitable renderer is found
     */
    private function createFallbackRenderer(): RendererInterface
    {
        return new class implements RendererInterface {
            public function render(
                Throwable $exception,
                ClassificationResult $classification,
                array $context,
                ?Request $request = null,
                bool $debug = false
            ): string {
                $message = $debug ? $exception->getMessage() : 'An error occurred';
                
                if ($request && $this->isJsonRequest($request)) {
                    return json_encode([
                        'error' => true,
                        'message' => $message,
                        'type' => 'fallback_renderer'
                    ]);
                }
                
                return "Error: {$message}";
            }
            
            public function canRender(?Request $request): bool
            {
                return true; // Fallback can handle anything
            }
            
            public function getContentType(): string
            {
                return 'text/plain; charset=utf-8';
            }
            
            public function getPriority(): int
            {
                return 0; // Lowest priority
            }
            
            public function getName(): string
            {
                return 'fallback';
            }
            
            private function isJsonRequest(?Request $request): bool
            {
                if (!$request) {
                    return false;
                }
                
                $accept = $request->header('Accept', '');
                return str_contains($accept, 'application/json') || 
                       str_contains($accept, 'application/vnd.api+json');
            }
        };
    }

    /**
     * Set debug mode
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Get debug mode
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set default renderer
     */
    public function setDefaultRenderer(string $rendererName): void
    {
        $this->defaultRenderer = $rendererName;
    }

    /**
     * Get default renderer name
     */
    public function getDefaultRenderer(): string
    {
        return $this->defaultRenderer;
    }

    /**
     * Check if a renderer is registered
     */
    public function hasRenderer(string $name): bool
    {
        return isset($this->renderers[$name]);
    }

    /**
     * Remove a renderer
     */
    public function removeRenderer(string $name): bool
    {
        if (isset($this->renderers[$name])) {
            unset($this->renderers[$name]);
            return true;
        }
        
        return false;
    }

    /**
     * Clear all renderers
     */
    public function clearRenderers(): void
    {
        $this->renderers = [];
    }

    /**
     * Get renderer statistics
     */
    public function getStats(): array
    {
        $stats = [
            'total_renderers' => count($this->renderers),
            'default_renderer' => $this->defaultRenderer,
            'debug_mode' => $this->debug,
            'renderers' => []
        ];

        foreach ($this->renderers as $name => $renderer) {
            $stats['renderers'][$name] = [
                'class' => get_class($renderer),
                'priority' => $renderer->getPriority(),
                'content_type' => $renderer->getContentType()
            ];
        }

        return $stats;
    }

    /**
     * Test renderer selection for different request types
     */
    public function testRendererSelection(array $testCases = []): array
    {
        $defaultTestCases = [
            'json_api' => ['Accept' => 'application/json'],
            'html_browser' => ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
            'cli' => null, // No request object
            'xml_api' => ['Accept' => 'application/xml'],
            'plain_text' => ['Accept' => 'text/plain']
        ];

        $testCases = array_merge($defaultTestCases, $testCases);
        $results = [];

        foreach ($testCases as $testName => $headers) {
            $request = null;
            if ($headers !== null) {
                // Create a mock request with the specified headers
                $request = new class($headers) {
                    private array $testHeaders;
                    
                    public function __construct(array $headers)
                    {
                        $this->testHeaders = $headers;
                    }
                    
                    public function header(string $key, $default = null)
                    {
                        return $this->testHeaders[$key] ?? $default;
                    }
                    
                    public function method(): string
                    {
                        return 'GET';
                    }
                    
                    public function uri(): string
                    {
                        return '/test';
                    }
                    
                    public function url(): string
                    {
                        return 'http://localhost/test';
                    }
                };
            }

            $selectedRenderer = $this->selectRenderer($request);
            $results[$testName] = [
                'renderer' => $selectedRenderer->getName(),
                'content_type' => $selectedRenderer->getContentType(),
                'priority' => $selectedRenderer->getPriority()
            ];
        }

        return $results;
    }
}