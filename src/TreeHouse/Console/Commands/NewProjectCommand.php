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
             ->addOption('no-init', null, InputOption::VALUE_NONE, 'Skip automatic initialization (composer install, npm install, .env setup)')
             ->addOption('no-interaction', null, InputOption::VALUE_NONE, 'Skip interactive prompts and use defaults')
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
        $noInit = $input->hasOption('no-init');
        $noInteraction = $input->hasOption('no-interaction');

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
            
            // Generate frontend assets
            $this->generateFrontendAssets($installPath, $projectName, $output);
            
            // Initialize project (unless --no-init is specified)
            if (!$noInit) {
                $this->initializeProject($installPath, $projectName, $output, $input, !$noInteraction);
            }
            
            $output->writeln('');
            $output->writeln('<info>✓ TreeHouse application created successfully!</info>');
            
            if ($noInit) {
                $output->writeln('');
                $output->writeln('<comment>Next steps:</comment>');
                $output->writeln("  cd {$projectName}");
                $output->writeln('  composer install');
                $output->writeln('  npm install');
                $output->writeln('  cp .env.example .env');
                $output->writeln('  npm run dev');
                $output->writeln('  ./bin/th serve');
            } else {
                $output->writeln('');
                $output->writeln('<comment>Your project is ready! Next steps:</comment>');
                $output->writeln("  cd {$projectName}");
                $output->writeln('  npm run dev');
                $output->writeln('  ./bin/th serve');
            }
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
            'public/build',
            'resources/views/layouts',
            'resources/js',
            'resources/css',
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
        $this->copyFrameworkFile('config/app.php', $basePath . '/config/app.php', $projectName, $output);
        $this->copyFrameworkFile('config/database.php', $basePath . '/config/database.php', $projectName, $output);
        $this->copyFrameworkFile('config/cache.php', $basePath . '/config/cache.php', $projectName, $output);
        $this->copyFrameworkFile('config/view.php', $basePath . '/config/view.php', $projectName, $output);
        $this->copyFrameworkFile('config/auth.php', $basePath . '/config/auth.php', $projectName, $output);
        $this->copyFrameworkFile('config/routes/web.php', $basePath . '/config/routes/web.php', $projectName, $output);
    }

    /**
     * Generate application files
     */
    private function generateApplicationFiles(string $basePath, string $projectName, OutputInterface $output): void
    {
        $this->copyFrameworkFile('public/index.php', $basePath . '/public/index.php', $projectName, $output);
        $this->copyFrameworkFile('public/.htaccess', $basePath . '/public/.htaccess', $projectName, $output);
        $this->copyFrameworkFile('src/App/Controllers/HomeController.php', $basePath . '/src/App/Controllers/HomeController.php', $projectName, $output);
        
        // Models
        $this->copyFrameworkFile('src/App/Models/User.php', $basePath . '/src/App/Models/User.php', $projectName, $output);
        
        // Views - use existing framework views as templates
        $this->copyFrameworkFile('resources/views/layouts/app.th.html', $basePath . '/resources/views/layouts/app.th.html', $projectName, $output);
        $this->copyFrameworkFile('resources/views/home.th.html', $basePath . '/resources/views/home.th.html', $projectName, $output);
        $this->copyFrameworkFile('resources/views/about.th.html', $basePath . '/resources/views/about.th.html', $projectName, $output);
        
        // Environment file
        $this->copyFrameworkFile('.env.example', $basePath . '/.env.example', $projectName, $output);
        
        // Console script
        $this->copyFrameworkFile('bin/th', $basePath . '/bin/th', $projectName, $output);
        chmod($basePath . '/bin/th', 0755);
        
        // Documentation
        $this->copyFrameworkFile('README.md', $basePath . '/README.md', $projectName, $output);
    }

    /**
     * Generate composer.json
     */
    private function generateComposerJson(string $basePath, string $projectName, OutputInterface $output): void
    {
        $content = $this->getComposerJsonTemplate($projectName);
        file_put_contents($basePath . '/composer.json', $content);
        $output->writeln("  <info>Created:</info> composer.json");
    }

    /**
     * Generate frontend assets (package.json, vite.config.js, etc.)
     */
    private function generateFrontendAssets(string $basePath, string $projectName, OutputInterface $output): void
    {
        // Copy package.json
        $this->copyFrameworkFile('package.json', $basePath . '/package.json', $projectName, $output);
        
        // Copy vite.config.js
        $this->copyFrameworkFile('vite.config.js', $basePath . '/vite.config.js', $projectName, $output);
        
        // Copy postcss.config.js
        $this->copyFrameworkFile('postcss.config.js', $basePath . '/postcss.config.js', $projectName, $output);
        
        // Copy tailwind.config.js if it exists
        $this->copyFrameworkFile('tailwind.config.js', $basePath . '/tailwind.config.js', $projectName, $output);
        
        // Create frontend directories
        $frontendDirs = [
            'resources/js',
            'resources/css'
        ];
        
        foreach ($frontendDirs as $dir) {
            $fullPath = $basePath . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                $output->writeln("  <info>Created:</info> {$dir}");
            }
        }
        
        // Copy frontend files
        $this->copyFrameworkFile('resources/js/app.js', $basePath . '/resources/js/app.js', $projectName, $output);
        $this->copyFrameworkFile('resources/css/app.css', $basePath . '/resources/css/app.css', $projectName, $output);
    }

    /**
     * Initialize the project by running composer install, npm install, and setting up .env
     */
    private function initializeProject(string $installPath, string $projectName, OutputInterface $output, InputInterface $input, bool $interactive = true): void
    {
        $output->writeln('');
        $output->writeln('<info>Initializing project...</info>');
        
        // Gather configuration
        $config = $this->gatherProjectConfiguration($input, $output, $projectName, $interactive);
        
        // Create .env file with configuration
        $this->createEnvFile($installPath, $config, $output);
        
        // Change to project directory for commands
        $originalDir = getcwd();
        chdir($installPath);
        
        try {
            // Run composer install
            $output->writeln('  <comment>Running composer install...</comment>');
            $composerOutput = [];
            $composerReturn = 0;
            exec('composer install --no-interaction 2>&1', $composerOutput, $composerReturn);
            
            if ($composerReturn === 0) {
                $output->writeln('  <info>✓</info> Composer dependencies installed');
                
                // Run database migrations
                $this->runMigrations($output, $config);
                
                // Create admin user
                $this->createAdminUser($output, $config);
                
            } else {
                $output->writeln('  <error>✗</error> Composer install failed');
                $output->writeln('    ' . implode("\n    ", $composerOutput));
            }
            
            // Run npm install
            $output->writeln('  <comment>Running npm install...</comment>');
            $npmOutput = [];
            $npmReturn = 0;
            exec('npm install --silent 2>&1', $npmOutput, $npmReturn);
            
            if ($npmReturn === 0) {
                $output->writeln('  <info>✓</info> NPM dependencies installed');
            } else {
                $output->writeln('  <error>✗</error> NPM install failed');
                $output->writeln('    ' . implode("\n    ", array_slice($npmOutput, -5))); // Show last 5 lines
            }
            
        } catch (\Exception $e) {
            $output->writeln("  <error>Initialization error: {$e->getMessage()}</error>");
        } finally {
            // Change back to original directory
            chdir($originalDir);
        }
    }

    /**
     * Gather project configuration through interactive prompts
     */
    private function gatherProjectConfiguration(InputInterface $input, OutputInterface $output, string $projectName, bool $interactive): array
    {
        $config = [
            'app_name' => $projectName,
            'app_url' => 'http://' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $projectName)) . '.dev:8000',
            'db_connection' => 'mysql',
            'db_database' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $projectName)),
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_username' => 'root',
            'db_password' => '',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'password'
        ];

        if (!$interactive) {
            return $config;
        }

        $output->writeln('');
        $output->writeln('<comment>Project Configuration</comment>');
        $output->writeln('Press Enter to use default values in [brackets]');
        $output->writeln('');

        // Application name
        $appName = $this->askQuestion($input, $output, "Application name [{$config['app_name']}]: ");
        if ($appName) $config['app_name'] = $appName;

        // Application URL
        $appUrl = $this->askQuestion($input, $output, "Application URL [{$config['app_url']}]: ");
        if ($appUrl) $config['app_url'] = $appUrl;

        // Database configuration
        $output->writeln('');
        $output->writeln('<comment>Database Configuration</comment>');
        
        $dbHost = $this->askQuestion($input, $output, "Database host [{$config['db_host']}]: ");
        if ($dbHost) $config['db_host'] = $dbHost;

        $dbPort = $this->askQuestion($input, $output, "Database port [{$config['db_port']}]: ");
        if ($dbPort) $config['db_port'] = $dbPort;

        $dbDatabase = $this->askQuestion($input, $output, "Database name [{$config['db_database']}]: ");
        if ($dbDatabase) $config['db_database'] = $dbDatabase;

        $dbUsername = $this->askQuestion($input, $output, "Database username [{$config['db_username']}]: ");
        if ($dbUsername) $config['db_username'] = $dbUsername;

        $dbPassword = $this->askQuestion($input, $output, "Database password: ", true);
        if ($dbPassword) $config['db_password'] = $dbPassword;

        // Admin user configuration
        $output->writeln('');
        $output->writeln('<comment>Admin User Configuration</comment>');
        
        $adminName = $this->askQuestion($input, $output, "Admin name [{$config['admin_name']}]: ");
        if ($adminName) $config['admin_name'] = $adminName;

        $adminEmail = $this->askQuestion($input, $output, "Admin email [{$config['admin_email']}]: ");
        if ($adminEmail) $config['admin_email'] = $adminEmail;

        $adminPassword = $this->askQuestion($input, $output, "Admin password [password]: ", true);
        if ($adminPassword) $config['admin_password'] = $adminPassword;

        return $config;
    }

    /**
     * Ask a question and get user input
     */
    private function askQuestion(InputInterface $input, OutputInterface $output, string $question, bool $hidden = false): string
    {
        $output->write($question);
        
        if ($hidden) {
            // For password inputs, disable echo
            system('stty -echo');
            $answer = trim(fgets(STDIN));
            system('stty echo');
            $output->writeln('');
        } else {
            $answer = trim(fgets(STDIN));
        }
        
        return $answer;
    }

    /**
     * Create .env file with configuration
     */
    private function createEnvFile(string $installPath, array $config, OutputInterface $output): void
    {
        $envContent = "APP_NAME=\"{$config['app_name']}\"\n";
        $envContent .= "APP_ENV=local\n";
        $envContent .= "APP_DEBUG=true\n";
        $envContent .= "APP_URL={$config['app_url']}\n";
        $envContent .= "APP_KEY=\n\n";
        
        $envContent .= "DB_CONNECTION={$config['db_connection']}\n";
        $envContent .= "DB_HOST={$config['db_host']}\n";
        $envContent .= "DB_PORT={$config['db_port']}\n";
        $envContent .= "DB_DATABASE={$config['db_database']}\n";
        $envContent .= "DB_USERNAME={$config['db_username']}\n";
        $envContent .= "DB_PASSWORD={$config['db_password']}\n";
        
        $envContent .= "\nCACHE_DRIVER=file\n";
        $envContent .= "SESSION_DRIVER=file\n";

        $envPath = $installPath . '/.env';
        file_put_contents($envPath, $envContent);
        $output->writeln('  <info>Created:</info> .env file with configuration');
    }

    /**
     * Run database migrations
     */
    private function runMigrations(OutputInterface $output, array $config): void
    {
        $output->writeln('  <comment>Running database migrations...</comment>');
        
        $migrateOutput = [];
        $migrateReturn = 0;
        exec('php ./bin/th migrate 2>&1', $migrateOutput, $migrateReturn);
        
        if ($migrateReturn === 0) {
            $output->writeln('  <info>✓</info> Database migrations completed');
        } else {
            $output->writeln('  <error>✗</error> Database migrations failed');
            $output->writeln('    ' . implode("\n    ", $migrateOutput));
        }
    }

    /**
     * Create admin user
     */
    private function createAdminUser(OutputInterface $output, array $config): void
    {
        $output->writeln('  <comment>Creating admin user...</comment>');
        
        $userOutput = [];
        $userReturn = 0;
        
        $command = sprintf(
            'php ./bin/th user:create "%s" "%s" "%s" --role=admin 2>&1',
            addslashes($config['admin_name']),
            addslashes($config['admin_email']),
            addslashes($config['admin_password'])
        );
        
        exec($command, $userOutput, $userReturn);
        
        if ($userReturn === 0) {
            $output->writeln('  <info>✓</info> Admin user created successfully');
            $output->writeln("    Email: {$config['admin_email']}");
            $output->writeln("    Password: {$config['admin_password']}");
        } else {
            $output->writeln('  <error>✗</error> Failed to create admin user');
            $output->writeln('    ' . implode("\n    ", $userOutput));
        }
    }

    /**
     * Copy a file from the framework to the project location with placeholder replacement
     */
    private function copyFrameworkFile(string $frameworkPath, string $destPath, string $projectName, OutputInterface $output): void
    {
        // For frontend files, always generate templates instead of copying
        $frontendFiles = [
            'package.json',
            'vite.config.js',
            'postcss.config.js',
            'tailwind.config.js',
            'resources/js/app.js',
            'resources/css/app.css'
        ];
        
        if (in_array($frameworkPath, $frontendFiles)) {
            $content = $this->generateBasicContent($frameworkPath, $projectName);
        } else {
            // Try multiple possible locations for framework files
            $possibleSources = [
                __DIR__ . '/../../../../' . $frameworkPath,  // From vendor perspective
                __DIR__ . '/../../../' . $frameworkPath,     // From framework root
                getcwd() . '/' . $frameworkPath,              // Current directory
            ];
            
            $sourceFound = false;
            $content = '';
            
            foreach ($possibleSources as $sourcePath) {
                if (file_exists($sourcePath)) {
                    $content = file_get_contents($sourcePath);
                    $sourceFound = true;
                    break;
                }
            }
            
            // If file not found in framework, generate basic content
            if (!$sourceFound) {
                $content = $this->generateBasicContent($frameworkPath, $projectName);
            }
        }
        
        // Replace placeholders
        $replacements = [
            '{{PROJECT_NAME}}' => $projectName,
            '{{VENDOR_NAME}}' => 'vendor',
            '{{PACKAGE_NAME}}' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $projectName)),
        ];
        
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        
        // Ensure destination directory exists
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        file_put_contents($destPath, $content);
        $fileName = basename($frameworkPath);
        $output->writeln("  <info>Created:</info> {$fileName}");
    }

    /**
     * Generate basic content when framework file is not found
     */
    private function generateBasicContent(string $filePath, string $projectName): string
    {
        switch ($filePath) {
            case 'resources/views/layouts/app.th.html':
                return $this->getLayoutTemplate($projectName);
            case 'resources/views/home.th.html':
                return $this->getHomeTemplate($projectName);
            case 'resources/views/about.th.html':
                return $this->getAboutTemplate();
            case 'src/App/Controllers/HomeController.php':
                return $this->getHomeControllerTemplate();
            case 'config/routes/web.php':
                return $this->getWebRoutesTemplate();
            case 'public/index.php':
                return $this->getPublicIndexTemplate();
            case 'public/.htaccess':
                return $this->getHtaccessTemplate();
            case 'bin/th':
                return $this->getBinThTemplate($projectName);
            case '.env.example':
                return $this->getEnvExampleTemplate();
            case 'README.md':
                return $this->getReadmeTemplate($projectName);
            case 'package.json':
                return $this->getPackageJsonTemplate($projectName);
            case 'vite.config.js':
                return $this->getViteConfigTemplate();
            case 'postcss.config.js':
                return $this->getPostcssConfigTemplate();
            case 'tailwind.config.js':
                return $this->getTailwindConfigTemplate();
            case 'resources/js/app.js':
                return $this->getAppJsTemplate();
            case 'resources/css/app.css':
                return $this->getAppCssTemplate();
            default:
                return "<?php\n\n// Generated file for {$projectName}\n";
        }
    }

    /**
     * Get composer.json template
     */
    private function getComposerJsonTemplate(string $projectName): string
    {
        $packageName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $projectName));
        
        return json_encode([
            'name' => "vendor/{$packageName}",
            'description' => "A TreeHouse application: {$projectName}",
            'type' => 'project',
            'require' => [
                'php' => '^8.4',
                'lengthofrope/treehouse' => 'dev-develop',
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
                'test' => 'phpunit',
                'serve' => './bin/th serve'
            ],
            'config' => [
                'optimize-autoloader' => true,
                'sort-packages' => true
            ],
            'minimum-stability' => 'dev',
            'prefer-stable' => true
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get layout template with TreeHouse JavaScript auto-injection
     */
    private function getLayoutTemplate(string $projectName): string
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
    
    <!-- Vite Assets (Dynamic: Development vs Production) -->
    <script th:raw="__vite_assets"></script>
        
    <!-- TreeHouse Framework Assets (auto-injected) -->
    <script th:raw="__treehouse_config"></script>
    <script th:raw="__treehouse_js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$projectName}</h1>
            <p>Powered by TreeHouse Framework</p>
            <div class="nav">
                <a href="/">Home</a>
                <a href="/about">About</a>
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

    /**
     * Get home page template
     */
    private function getHomeTemplate(string $projectName): string
    {
        return <<<HTML
<th:extends="layouts.app">
<th:block name="content">
    <h1>Welcome to {$projectName}!</h1>
    
    <p>Your TreeHouse application is up and running. This page demonstrates the new vendor-first asset system.</p>
    
    <h2>🚀 What's Working</h2>
    <ul>
        <li><strong>TreeHouse JavaScript Library:</strong> Automatically loaded from vendor package</li>
        <li><strong>CSRF Protection:</strong> Dynamic token injection for cache-friendly pages</li>
        <li><strong>Module System:</strong> Load only the JavaScript modules you need</li>
        <li><strong>Template Engine:</strong> Powerful templating with inheritance and components</li>
    </ul>
    
    <h2>🔧 Test the JavaScript Framework</h2>
    <p>Open your browser's developer console to see TreeHouse in action:</p>
    
    <form method="post" action="/test" data-ajax="true" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
        <h3>AJAX Form with CSRF Protection</h3>
        <input type="text" name="name" placeholder="Your name" required style="display: block; margin: 10px 0; padding: 8px; width: 200px;">
        <input type="email" name="email" placeholder="Your email" required style="display: block; margin: 10px 0; padding: 8px; width: 200px;">
        <button type="submit" style="margin: 10px 0; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px;">Submit</button>
        <p><small>This form automatically includes CSRF protection and will submit via AJAX.</small></p>
    </form>
    
    <h2>📁 Asset System</h2>
    <p>JavaScript assets are served directly from the TreeHouse vendor package:</p>
    <ul>
        <li><code>/_assets/treehouse/js/treehouse.js</code> - Core framework</li>
        <li><code>/_assets/treehouse/js/modules/csrf.js</code> - CSRF protection</li>
        <li><code>/_assets/treehouse/js/modules/forms.js</code> - Enhanced forms</li>
        <li><code>/_assets/treehouse/js/modules/utils.js</code> - Utility functions</li>
    </ul>
    
    <p>You can override any asset by placing a file in <code>public/assets/treehouse/</code></p>
    
    <h2>🛠️ Next Steps</h2>
    <ul>
        <li>Edit <code>config/routes/web.php</code> to add your routes</li>
        <li>Create controllers in <code>src/App/Controllers/</code></li>
        <li>Add models in <code>src/App/Models/</code></li>
        <li>Customize templates in <code>resources/views/</code></li>
    </ul>
    
    <p>
        <a href="/about">Learn more about TreeHouse</a> or
        <a href="https://github.com/lengthofrope/treehouse-framework" target="_blank">view the documentation</a>
    </p>
</th:block>
</th:extends>
HTML;
    }

    /**
     * Get about page template
     */
    private function getAboutTemplate(): string
    {
        return <<<HTML
<th:extends="layouts.app">
<th:block name="content">
    <h1>About TreeHouse Framework</h1>
    
    <p>TreeHouse is a modern PHP framework that emphasizes simplicity, performance, and developer experience.</p>
    
    <h2>🌟 Key Features</h2>
    <ul>
        <li><strong>Vendor-First Assets:</strong> JavaScript and CSS served directly from the framework package</li>
        <li><strong>Enhanced Security:</strong> Built-in CSRF protection with same-origin validation</li>
        <li><strong>Modular JavaScript:</strong> Load only the modules you need</li>
        <li><strong>Template Engine:</strong> Powerful templating with inheritance and components</li>
        <li><strong>Modern Router:</strong> Fast routing with middleware support</li>
        <li><strong>Database ORM:</strong> Elegant database interactions</li>
        <li><strong>Authentication:</strong> Built-in user authentication and authorization</li>
        <li><strong>Console Commands:</strong> CLI tools for development and deployment</li>
    </ul>
    
    <h2>🚀 Performance</h2>
    <ul>
        <li>Optimized autoloading and caching</li>
        <li>Efficient template compilation</li>
        <li>Smart asset caching with ETags</li>
        <li>Minimal memory footprint</li>
    </ul>
    
    <h2>🔧 Developer Experience</h2>
    <ul>
        <li>Zero configuration for common tasks</li>
        <li>Comprehensive error reporting</li>
        <li>Hot reloading in development</li>
        <li>Excellent IDE support</li>
    </ul>
    
    <p><a href="/">← Back to Home</a></p>
</th:block>
</th:extends>
HTML;
    }

    /**
     * Get other template files
     */
    private function getHomeControllerTemplate(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;

class HomeController
{
    public function index(Request \$request): string
    {
        return view('home', [
            'title' => 'Home'
        ]);
    }
    
    public function about(Request \$request): string
    {
        return view('about', [
            'title' => 'About TreeHouse'
        ]);
    }
}
PHP;
    }

    private function getWebRoutesTemplate(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Router\Router;

return function (Router \$router) {
    \$router->get('/', 'App\\Controllers\\HomeController@index')->name('home');
    \$router->get('/about', 'App\\Controllers\\HomeController@about')->name('about');
};
PHP;
    }

    private function getPublicIndexTemplate(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LengthOfRope\TreeHouse\Foundation\Application;

\$app = new Application(__DIR__ . '/..');
\$app->run();
PHP;
    }

    private function getHtaccessTemplate(): string
    {
        return <<<HTACCESS
RewriteEngine On

# Handle Angular and other front-end framework routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L,QSA]
HTACCESS;
    }

    private function getBinThTemplate(string $projectName): string
    {
        return <<<PHP
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LengthOfRope\TreeHouse\Console\Application;

\$app = new Application('{$projectName}');
\$app->run(\$argv);
PHP;
    }

    private function getEnvExampleTemplate(): string
    {
        return <<<ENV
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY=

DB_CONNECTION=sqlite
DB_DATABASE=database.sqlite

CACHE_DRIVER=file
SESSION_DRIVER=file
ENV;
    }

    private function getReadmeTemplate(string $projectName): string
    {
        return <<<MD
# {$projectName}

A TreeHouse Framework application with vendor-first asset management.

## Features

- 🚀 **Vendor-First Assets** - JavaScript and CSS served directly from framework
- 🔐 **Enhanced Security** - Built-in CSRF protection with same-origin validation
- 📦 **Modular JavaScript** - Load only the modules you need
- 🎨 **Template Engine** - Powerful templating with inheritance
- ⚡ **Performance** - Optimized caching and asset delivery

## Getting Started

1. Install dependencies:
   ```bash
   composer install
   ```

2. Start the development server:
   ```bash
   ./bin/th serve
   ```

3. Visit http://localhost:8000

## JavaScript Framework

TreeHouse includes a modular JavaScript framework that's automatically injected into your templates:

```javascript
// Framework ready
TreeHouse.ready(() => {
    console.log('TreeHouse loaded!');
});

// Load modules
TreeHouse.use('forms').then(forms => {
    // Enhanced form handling with CSRF protection
});

// Utilities
TreeHouse.utils.debounce(fn, 500);
TreeHouse.utils.formatNumber(1234567);
```

## Asset Override

Override any framework asset by placing files in `public/assets/treehouse/`:

```
public/assets/treehouse/
├── js/
│   ├── treehouse.js          # Override core
│   └── modules/
│       └── csrf.js           # Override CSRF module
└── css/
    └── treehouse.css         # Override styles
```

## Directory Structure

```
{$projectName}/
├── src/App/                  # Application code
├── config/                   # Configuration files
├── resources/views/          # Templates
├── public/                   # Web root
├── storage/                  # Cache and logs
├── tests/                    # Tests
└── vendor/                   # Dependencies (includes TreeHouse assets)
```

## Commands

```bash
# Development server
./bin/th serve

# Run tests
./bin/th test

# Database migrations
./bin/th migrate

# Clear cache
./bin/th cache:clear
```

## Learn More

- [TreeHouse Documentation](https://github.com/lengthofrope/treehouse-framework)
- [Template Engine Guide](https://github.com/lengthofrope/treehouse-framework/docs/templates)
- [JavaScript Framework API](https://github.com/lengthofrope/treehouse-framework/docs/javascript)
MD;
    }

    /**
     * Get package.json template
     */
    private function getPackageJsonTemplate(string $projectName): string
    {
        $packageName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $projectName));
        
        return json_encode([
            'name' => $packageName,
            'version' => '1.0.0',
            'description' => "Frontend assets for {$projectName}",
            'private' => true,
            'type' => 'module',
            'scripts' => [
                'dev' => 'touch VITE_RUNNING && vite -m DEV && rm VITE_RUNNING',
                'build' => 'vite build',
                'preview' => 'vite preview'
            ],
            'devDependencies' => [
                '@tailwindcss/forms' => '^0.5.7',
                '@tailwindcss/typography' => '^0.5.10',
                'autoprefixer' => '^10.4.16',
                'postcss' => '^8.4.32',
                'tailwindcss' => '^3.3.6',
                'vite' => '^5.0.8'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get vite.config.js template
     */
    private function getViteConfigTemplate(): string
    {
        return <<<JS
import { defineConfig } from 'vite'
import tailwindcss from 'tailwindcss'
import autoprefixer from 'autoprefixer'

export default defineConfig({
  // Build configuration
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: 'resources/js/app.js'
      }
    }
  },
  
  // Development server configuration
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: false,
    cors: true,
    hmr: {
      host: 'localhost'
    }
  },
  
  // CSS configuration
  css: {
    postcss: {
      plugins: [
        tailwindcss,
        autoprefixer
      ]
    }
  },
  
  // Public directory - set to false to avoid conflict with outDir
  publicDir: false,
  
  // Base URL for assets
  base: process.env.NODE_ENV === 'production' ? '/build/' : '/'
})
JS;
    }

    /**
     * Get postcss.config.js template
     */
    private function getPostcssConfigTemplate(): string
    {
        return <<<JS
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
JS;
    }

    /**
     * Get tailwind.config.js template
     */
    private function getTailwindConfigTemplate(): string
    {
        return <<<JS
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.{html,js,th.html,th.php}",
    "./src/**/*.{html,js,php}",
  ],
  theme: {
    extend: {
      colors: {
        'treehouse': {
          50: '#f0f9ff',
          100: '#e0f2fe',
          200: '#bae6fd',
          300: '#7dd3fc',
          400: '#38bdf8',
          500: '#0ea5e9',
          600: '#0284c7',
          700: '#0369a1',
          800: '#075985',
          900: '#0c4a6e',
        }
      }
    },
  },
  plugins: [],
}
JS;
    }

    /**
     * Get app.js template
     */
    private function getAppJsTemplate(): string
    {
        return <<<JS
import '../css/app.css'

// TreeHouse Framework integration
console.log('TreeHouse + Vite + Tailwind CSS loaded successfully!')

// Initialize when DOM is ready since TreeHouse is loaded separately via PHP
document.addEventListener('DOMContentLoaded', () => {
  // Wait for TreeHouse to be available from the PHP-injected scripts
  if (typeof window.TreeHouse !== 'undefined') {
    // Initialize TreeHouse when ready
    window.TreeHouse.ready(() => {
      console.log('TreeHouse Framework is ready!')
      
      // Add any custom initialization here
      initializeComponents()
    })
  } else {
    // Fallback if TreeHouse isn't available
    console.warn('TreeHouse not available, using fallback initialization')
    initializeComponents()
  }
})

function initializeComponents() {
  // Add smooth scrolling to navigation links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault()
      
      const target = document.querySelector(this.getAttribute('href'))
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth'
        })
      }
    })
  })
  
  // Add fade-in animation to cards
  const cards = document.querySelectorAll('.treehouse-card')
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-fade-in')
      }
    })
  })
  
  cards.forEach(card => {
    observer.observe(card)
  })
  
  // Mobile menu toggle
  window.toggleMobileMenu = function() {
    const menu = document.getElementById('mobile-menu')
    if (menu) {
      menu.classList.toggle('hidden')
    }
  }
  
  // Console welcome message
  console.log(`
  🌳 TreeHouse Framework
  
  A modern PHP framework with elegant JavaScript integration
  Version: 1.0.0
  `)
}
JS;
    }

    /**
     * Get app.css template
     */
    private function getAppCssTemplate(): string
    {
        return <<<CSS
@tailwind base;
@tailwind components;
@tailwind utilities;

/* TreeHouse Framework Custom Styles */
@layer components {
  .treehouse-btn {
    @apply inline-flex items-center px-4 py-2 bg-treehouse-600 hover:bg-treehouse-700 text-white font-medium rounded-md transition-colors duration-200;
  }
  
  .treehouse-card {
    @apply bg-white rounded-lg shadow-sm border border-gray-200 p-6;
  }
  
  .treehouse-input {
    @apply w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-treehouse-500 focus:border-treehouse-500;
  }
}

/* Custom animations */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fadeIn 0.5s ease-out forwards;
}

/* TreeHouse branding */
.treehouse-gradient {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
}
CSS;
    }
}