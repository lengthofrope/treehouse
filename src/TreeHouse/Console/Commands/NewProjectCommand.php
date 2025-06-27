<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;

/**
 * New Project Command
 * 
 * Creates a new TreeHouse application with the proper directory structure
 * and configuration files.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class NewProjectCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('new')
             ->setDescription('Create a new TreeHouse application')
             ->addArgument('name', InputArgument::OPTIONAL, 'Project name (optional if --existing flag is used)')
             ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Custom installation path')
             ->addOption('existing', 'e', InputOption::VALUE_NONE, 'Install framework in existing directory')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force installation even if directory exists')
             ->setHelp('This command creates a new TreeHouse application with the standard directory structure and configuration files.');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // The first argument after the command is stored as 'arg1'
        $projectName = $input->getArgument('arg1');
        $customPath = $input->getOption('path');
        $isExisting = $input->hasOption('existing');
        $force = $input->hasOption('force');

        // Determine installation path
        if ($isExisting) {
            $installPath = $customPath ?: getcwd();
            $projectName = $projectName ?: basename($installPath);
        } else {
            if (!$projectName) {
                $output->writeln('<error>Project name is required when creating a new project</error>');
                return 1;
            }
            $installPath = $customPath ? rtrim($customPath, '/') . '/' . $projectName : $projectName;
        }

        $output->writeln("<info>Creating TreeHouse application: {$projectName}</info>");
        $output->writeln("<comment>Installation path: {$installPath}</comment>");

        // Check if directory exists
        if (!$isExisting && file_exists($installPath) && !$force) {
            $output->writeln('<error>Directory already exists. Use --force to overwrite or --existing to install in existing directory.</error>');
            return 1;
        }

        try {
            // Create directory structure
            $this->createDirectoryStructure($installPath, $output);
            
            // Generate configuration files
            $this->generateConfigFiles($installPath, $projectName, $output);
            
            // Generate application files
            $this->generateApplicationFiles($installPath, $projectName, $output);
            
            // Generate composer.json
            $this->generateComposerJson($installPath, $projectName, $output);
            
            $output->writeln('');
            $output->writeln('<info>✓ TreeHouse application created successfully!</info>');
            $output->writeln('');
            $output->writeln('<comment>Next steps:</comment>');
            $output->writeln("  cd {$projectName}");
            $output->writeln('  composer install');
            $output->writeln('  ./bin/th serve');
            $output->writeln('');
            
            return 0;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to create application: {$e->getMessage()}</error>");
            return 1;
        }
    }

    /**
     * Create the directory structure
     */
    private function createDirectoryStructure(string $basePath, OutputInterface $output): void
    {
        $directories = [
            'src/App/Controllers',
            'src/App/Models',
            'src/App/Services',
            'src/App/Middleware',
            'config/routes',
            'public/assets',
            'resources/views/layouts',
            'storage/cache',
            'storage/logs',
            'storage/views',
            'tests/Unit',
            'tests/Feature',
            'database/migrations',
            'bin'
        ];

        foreach ($directories as $dir) {
            $fullPath = $basePath . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                $output->writeln("  <info>Created:</info> {$dir}");
            }
        }
    }

    /**
     * Generate configuration files
     */
    private function generateConfigFiles(string $basePath, string $projectName, OutputInterface $output): void
    {
        // app.php
        $appConfig = $this->getAppConfigContent($projectName);
        file_put_contents($basePath . '/config/app.php', $appConfig);
        $output->writeln("  <info>Created:</info> config/app.php");

        // database.php
        $dbConfig = $this->getDatabaseConfigContent();
        file_put_contents($basePath . '/config/database.php', $dbConfig);
        $output->writeln("  <info>Created:</info> config/database.php");

        // cache.php
        $cacheConfig = $this->getCacheConfigContent();
        file_put_contents($basePath . '/config/cache.php', $cacheConfig);
        $output->writeln("  <info>Created:</info> config/cache.php");

        // routes.php
        $routes = $this->getRoutesContent();
        file_put_contents($basePath . '/config/routes/web.php', $routes);
        $output->writeln("  <info>Created:</info> config/routes.php");
    }

    /**
     * Generate application files
     */
    private function generateApplicationFiles(string $basePath, string $projectName, OutputInterface $output): void
    {
        // public/index.php
        $indexContent = $this->getIndexContent();
        file_put_contents($basePath . '/public/index.php', $indexContent);
        $output->writeln("  <info>Created:</info> public/index.php");

        // public/.htaccess
        $htaccessContent = $this->getHtaccessContent();
        file_put_contents($basePath . '/public/.htaccess', $htaccessContent);
        $output->writeln("  <info>Created:</info> public/.htaccess");

        // HomeController
        $controllerContent = $this->getHomeControllerContent();
        file_put_contents($basePath . '/src/App/Controllers/HomeController.php', $controllerContent);
        $output->writeln("  <info>Created:</info> src/App/Controllers/HomeController.php");

        // Layout view
        $layoutContent = $this->getLayoutViewContent($projectName);
        file_put_contents($basePath . '/resources/views/layouts/app.th.html', $layoutContent);
        $output->writeln("  <info>Created:</info> resources/views/layouts/app.th.html");

        // Home view
        $homeViewContent = $this->getHomeViewContent($projectName);
        file_put_contents($basePath . '/resources/views/home.th.html', $homeViewContent);
        $output->writeln("  <info>Created:</info> resources/views/home.th.html");

        // About view
        $aboutViewContent = $this->getAboutViewContent($projectName);
        file_put_contents($basePath . '/resources/views/about.th.html', $aboutViewContent);
        $output->writeln("  <info>Created:</info> resources/views/about.th.html");

        // CLI symlink script
        $binContent = $this->getBinContent();
        file_put_contents($basePath . '/bin/th', $binContent);
        chmod($basePath . '/bin/th', 0755);
        $output->writeln("  <info>Created:</info> bin/th");

        // README.md
        $readmeContent = $this->getReadmeContent($projectName);
        file_put_contents($basePath . '/README.md', $readmeContent);
        $output->writeln("  <info>Created:</info> README.md");
    }

    /**
     * Generate composer.json
     */
    private function generateComposerJson(string $basePath, string $projectName, OutputInterface $output): void
    {
        $composerContent = $this->getComposerJsonContent($projectName);
        file_put_contents($basePath . '/composer.json', $composerContent);
        $output->writeln("  <info>Created:</info> composer.json");
    }

    // Template methods for generating file contents
    private function getAppConfigContent(string $projectName): string
    {
        return <<<PHP
<?php

return [
    'name' => '{$projectName}',
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => 'UTC',
];

function env(string \$key, mixed \$default = null): mixed
{
    \$value = \$_ENV[\$key] ?? getenv(\$key);
    
    if (\$value === false) {
        return \$default;
    }
    
    // Convert string booleans
    if (strtolower(\$value) === 'true') return true;
    if (strtolower(\$value) === 'false') return false;
    if (strtolower(\$value) === 'null') return null;
    
    return \$value;
}
PHP;
    }

    private function getDatabaseConfigContent(): string
    {
        return <<<PHP
<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'treehouse'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', __DIR__ . '/../database/database.sqlite'),
        ],
    ],
];
PHP;
    }

    private function getCacheConfigContent(): string
    {
        return <<<PHP
<?php

return [
    'default' => env('CACHE_DRIVER', 'file'),
    
    'file' => [
        'driver' => 'file',
        'path' => __DIR__ . '/../storage/cache',
        'default_ttl' => 3600,
    ],
];
PHP;
    }

    private function getRoutesContent(): string
    {
        return <<<PHP
<?php

use App\Controllers\HomeController;

// Define your routes here
\$router->get('/', [HomeController::class, 'index']);
\$router->get('/about', [HomeController::class, 'about']);
PHP;
    }

    private function getIndexContent(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LengthOfRope\TreeHouse\Foundation\Application;
use LengthOfRope\TreeHouse\Http\Request;

\$app = new Application(__DIR__ . '/../');

\$app->loadConfiguration(__DIR__ . '/../config');
// Routes are loaded automatically during bootstrap

\$request = Request::createFromGlobals();
\$response = \$app->handle(\$request);
\$response->send();
PHP;
    }

    private function getHtaccessContent(): string
    {
        return <<<HTACCESS
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS;
    }

    private function getHomeControllerContent(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\View\ViewFactory;

class HomeController
{
    private ViewFactory \$view;
    
    public function __construct()
    {
        \$this->view = new ViewFactory([
            'paths' => [__DIR__ . '/../../resources/views'],
            'cache_path' => __DIR__ . '/../../storage/views',
            'cache_enabled' => true,
        ]);
    }
    
    public function index(): Response
    {
        \$content = \$this->view->make('home', [
            'title' => 'Welcome to TreeHouse',
            'message' => 'Your TreeHouse application is running successfully!'
        ])->render();
        
        return new Response(\$content);
    }
    
    public function about(): Response
    {
        \$content = \$this->view->make('about', [
            'title' => 'About TreeHouse',
            'message' => 'TreeHouse is a modern PHP framework built for rapid development.'
        ])->render();
        
        return new Response(\$content);
    }
}
PHP;
    }

    private function getLayoutViewContent(string $projectName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title th:text="title" th:if="title">{$projectName}</title>
    <title th:unless="title">{$projectName}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8fafc;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .header {
            text-align: center;
            margin-bottom: 40px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #1a202c;
            margin: 0 0 10px 0;
        }
        .header p {
            color: #718096;
            margin: 0;
        }
        .content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        code {
            background: #f7fafc;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 0.9em;
        }
        ul li { margin: 8px 0; }
        .nav {
            text-align: center;
            margin-bottom: 20px;
        }
        .nav a {
            margin: 0 15px;
            color: #3182ce;
            text-decoration: none;
        }
        .nav a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$projectName}</h1>
            <p>Powered by TreeHouse Framework</p>
            <div class="nav">
                <a href="/">Home</a>
                <a href="/about">About</a>
                <a href="/api/status">API Status</a>
            </div>
        </div>
        <div class="content">
            <div th:yield="content"></div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getHomeViewContent(string $projectName): string
    {
        return <<<HTML
<div th:extend="layouts.app">
    <div th:section="content">
        <h2 th:text="title"></h2>
        <p th:text="message"></p>
        
        <h3>Getting Started</h3>
        <ul>
            <li>Edit your routes in <code>config/routes/web.php</code></li>
            <li>Create controllers in <code>src/App/Controllers/</code></li>
            <li>Add views in <code>resources/views/</code></li>
            <li>Configure your app in <code>config/</code></li>
        </ul>
        
        <h3>Framework Features</h3>
        <ul>
            <li>Automatic route loading from <code>config/routes/</code></li>
            <li>Framework default routes at <code>/</code>, <code>/api/status</code></li>
            <li>TreeHouse template engine with <code>th:</code> attributes</li>
            <li>Built-in cache, views, and database support</li>
            <li>Support classes: Collection, Arr, Str, Carbon, Uuid</li>
        </ul>
        
        <h3>TreeHouse Template Features</h3>
        <ul>
            <li><code>th:text="variable"</code> - Safe text output</li>
            <li><code>th:html="variable"</code> - Raw HTML output</li>
            <li><code>th:if="condition"</code> - Conditional rendering</li>
            <li><code>th:repeat="item items"</code> - Loop through arrays</li>
            <li><code>th:class="expression"</code> - Dynamic classes</li>
            <li><code>{variable}</code> - Inline expressions</li>
        </ul>
        
        <h3>Available Commands</h3>
        <ul>
            <li><code>./bin/th serve</code> - Start development server</li>
            <li><code>./bin/th cache:clear</code> - Clear cache</li>
            <li><code>./bin/th test:run</code> - Run tests</li>
            <li><code>./bin/th --help</code> - Show all commands</li>
        </ul>
    </div>
</div>
HTML;
    }

    private function getAboutViewContent(string $projectName): string
    {
        return <<<HTML
<div th:extend="layouts.app">
    <div th:section="content">
        <h2 th:text="title"></h2>
        <p th:text="message"></p>
        
        <h3>TreeHouse Framework</h3>
        <p>TreeHouse is a modern PHP framework designed for rapid web development. Built from scratch with zero external dependencies, it provides all the essential features you need to build robust web applications.</p>
        
        <h3>Key Features</h3>
        <ul>
            <li><strong>Zero Dependencies</strong> - Pure PHP implementation</li>
            <li><strong>Automatic Route Discovery</strong> - Routes load automatically from config/routes/</li>
            <li><strong>TreeHouse Template Engine</strong> - HTML-valid th: attributes</li>
            <li><strong>Built-in Cache System</strong> - File-based caching out of the box</li>
            <li><strong>Database Abstraction</strong> - ActiveRecord pattern with relations</li>
            <li><strong>Service Container</strong> - Dependency injection container</li>
            <li><strong>Console Commands</strong> - CLI tools for development</li>
            <li><strong>Support Classes</strong> - Collection, Arr, Str, Carbon, Uuid utilities</li>
        </ul>
        
        <h3>Template Syntax Examples</h3>
        <div>
            <p><code>th:text="user.name"</code> - Dot notation support</p>
            <p><code>th:if="user.active"</code> - Conditionals</p>
            <p><code>th:repeat="item items"</code> - Loops</p>
            <p><code>th:class="user.status"</code> - Dynamic attributes</p>
            <p><code>{user.name}</code> - Inline expressions</p>
        </div>
        
        <h3>Learn More</h3>
        <p>Visit the <a href="/">homepage</a> to explore framework features, or check out the API at <a href="/api/status">/api/status</a>.</p>
    </div>
</div>
HTML;
    }

    private function getBinContent(): string
    {
        return <<<PHP
#!/usr/bin/env php
<?php

// TreeHouse CLI - Application Entry Point
// This script creates a symlink to the framework CLI when composer installs

\$frameworkCli = __DIR__ . '/../vendor/bin/treehouse';

if (file_exists(\$frameworkCli)) {
    // Framework is installed, proxy to framework CLI
    \$args = array_slice(\$argv, 1);
    \$command = escapeshellcmd(\$frameworkCli) . ' ' . implode(' ', array_map('escapeshellarg', \$args));
    passthru(\$command, \$exitCode);
    exit(\$exitCode);
} else {
    echo "TreeHouse framework not found. Please run 'composer install' first.\n";
    exit(1);
}
PHP;
    }

    private function getComposerJsonContent(string $projectName): string
    {
        $vendorName = 'vendor';
        $packageName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $projectName));
        
        return json_encode([
            'name' => "{$vendorName}/{$packageName}",
            'description' => "TreeHouse application: {$projectName}",
            'type' => 'project',
            'require' => [
                'php' => '^8.4',
                'lengthofrope/treehouse' => '^1.0'
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^11.0'
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/App/'
                ]
            ],
            'autoload-dev' => [
                'psr-4' => [
                    'Tests\\' => 'tests/'
                ]
            ],
            'scripts' => [
                'post-install-cmd' => [
                    '@php -r "file_exists(\'bin/th\') || symlink(\'../vendor/bin/treehouse\', \'bin/th\');"'
                ],
                'post-update-cmd' => [
                    '@php -r "file_exists(\'bin/th\') || symlink(\'../vendor/bin/treehouse\', \'bin/th\');"'
                ]
            ],
            'config' => [
                'optimize-autoloader' => true,
                'sort-packages' => true
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function getReadmeContent(string $projectName): string
    {
        return <<<MD
# {$projectName}

A TreeHouse framework application.

## Installation

```bash
composer install
```

## Development

Start the development server:

```bash
./bin/th serve
```

## Available Commands

- `./bin/th serve` - Start development server
- `./bin/th cache:clear` - Clear application cache
- `./bin/th cache:stats` - Show cache statistics
- `./bin/th test:run` - Run PHPUnit tests
- `./bin/th migrate:run` - Run database migrations
- `./bin/th --help` - Show all available commands

## Directory Structure

```
src/App/          # Application code
config/           # Configuration files
public/           # Web root
resources/views/  # View templates
storage/          # Cache, logs, compiled views
tests/            # Test files
database/         # Migrations
```

## Framework Documentation

TreeHouse is a modern PHP framework built from scratch with zero external dependencies.

For more information, visit: https://github.com/lengthofrope/treehouse
MD;
    }
}