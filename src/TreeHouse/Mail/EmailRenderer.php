<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail;

use LengthOfRope\TreeHouse\View\ViewEngine;
use LengthOfRope\TreeHouse\Foundation\Application;

/**
 * Email Renderer
 * 
 * Specialized template renderer for email templates with email-specific optimizations.
 * Extends TreeHouse's view system with email-safe features.
 * 
 * @package LengthOfRope\TreeHouse\Mail
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class EmailRenderer
{
    /**
     * View engine instance
     */
    private ViewEngine $viewEngine;

    /**
     * Application instance
     */
    private Application $app;

    /**
     * Create new email renderer
     * 
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->viewEngine = $this->createEmailViewEngine();
    }

    /**
     * Render an email template
     * 
     * @param string $template Template path (e.g., 'emails.welcome')
     * @param array $data Template data
     * @return string
     */
    public function render(string $template, array $data = []): string
    {
        // Add default email context
        $data = $this->addEmailContext($data);

        // Convert dot notation to path
        $templatePath = str_replace('.', '/', $template);
        
        return $this->viewEngine->render($templatePath, $data);
    }

    /**
     * Create view engine configured for emails
     * 
     * @return ViewEngine
     */
    private function createEmailViewEngine(): ViewEngine
    {
        $paths = [
            $this->app->getBasePath() . '/resources/views',
            $this->app->getBasePath() . '/resources/views/emails',
        ];

        $viewEngine = new ViewEngine($paths);

        // Share global email data
        $viewEngine->share([
            'app' => [
                'name' => $this->app->config('app.name', 'TreeHouse App'),
                'url' => $this->app->config('app.url', 'http://localhost'),
            ],
            'year' => date('Y'),
        ]);

        return $viewEngine;
    }

    /**
     * Add email-specific context to template data
     * 
     * @param array $data
     * @return array
     */
    private function addEmailContext(array $data): array
    {
        // Add unsubscribe and view URLs if not provided
        if (!isset($data['unsubscribe_url'])) {
            $data['unsubscribe_url'] = '#'; // Default placeholder
        }

        if (!isset($data['view_url'])) {
            $data['view_url'] = '#'; // Default placeholder
        }

        // Add dashboard URL if not provided
        if (!isset($data['dashboard_url'])) {
            $data['dashboard_url'] = $this->app->config('app.url', 'http://localhost') . '/dashboard';
        }

        return $data;
    }

    /**
     * Check if email template exists
     * 
     * @param string $template
     * @return bool
     */
    public function exists(string $template): bool
    {
        $templatePath = str_replace('.', '/', $template);
        return $this->viewEngine->exists($templatePath);
    }

    /**
     * Get the view engine
     * 
     * @return ViewEngine
     */
    public function getViewEngine(): ViewEngine
    {
        return $this->viewEngine;
    }
}