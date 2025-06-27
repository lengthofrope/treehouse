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
        $this->copyStubFile('config/app.php', $basePath . '/config/app.php', $projectName, $output);
        $this->copyStubFile('config/database.php', $basePath . '/config/database.php', $projectName, $output);
        $this->copyStubFile('config/cache.php', $basePath . '/config/cache.php', $projectName, $output);
        $this->copyStubFile('config/view.php', $basePath . '/config/view.php', $projectName, $output);
        $this->copyStubFile('config/auth.php', $basePath . '/config/auth.php', $projectName, $output);
        $this->copyStubFile('config/routes/web.php', $basePath . '/config/routes/web.php', $projectName, $output);
    }

    /**
     * Generate application files
     */
    private function generateApplicationFiles(string $basePath, string $projectName, OutputInterface $output): void
    {
        $this->copyStubFile('public/index.php', $basePath . '/public/index.php', $projectName, $output);
        $this->copyStubFile('public/.htaccess', $basePath . '/public/.htaccess', $projectName, $output);
        $this->copyStubFile('src/App/Controllers/HomeController.php', $basePath . '/src/App/Controllers/HomeController.php', $projectName, $output);
        
        // Models
        $this->copyStubFile('src/App/Models/User.php', $basePath . '/src/App/Models/User.php', $projectName, $output);
        
        // Views
        $this->copyStubFile('resources/views/layouts/app.th.html', $basePath . '/resources/views/layouts/app.th.html', $projectName, $output);
        $this->copyStubFile('resources/views/home.th.html', $basePath . '/resources/views/home.th.html', $projectName, $output);
        $this->copyStubFile('resources/views/about.th.html', $basePath . '/resources/views/about.th.html', $projectName, $output);
        
        // Database migrations
        $this->copyStubFile('database/migrations/001_create_users_table.php', $basePath . '/database/migrations/001_create_users_table.php', $projectName, $output);
        $this->copyStubFile('database/migrations/002_create_password_resets_table.php', $basePath . '/database/migrations/002_create_password_resets_table.php', $projectName, $output);
        
        // Environment file
        $this->copyStubFile('.env.example', $basePath . '/.env.example', $projectName, $output);
        
        $this->copyStubFile('bin/th', $basePath . '/bin/th', $projectName, $output);
        chmod($basePath . '/bin/th', 0755);
        
        $this->copyStubFile('README.md', $basePath . '/README.md', $projectName, $output);
    }

    /**
     * Generate composer.json
     */
    private function generateComposerJson(string $basePath, string $projectName, OutputInterface $output): void
    {
        $this->copyStubFile('composer.json', $basePath . '/composer.json', $projectName, $output);
    }

    /**
     * Copy a stub file from the framework to the project location with placeholder replacement
     */
    private function copyStubFile(string $stubPath, string $destPath, string $projectName, OutputInterface $output): void
    {
        $frameworkStubPath = __DIR__ . '/../../../../resources/stubs/new-project/' . $stubPath;
        
        if (!file_exists($frameworkStubPath)) {
            throw new \RuntimeException("Stub file not found: {$frameworkStubPath}");
        }
        
        $content = file_get_contents($frameworkStubPath);
        
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
        $output->writeln("  <info>Created:</info> " . str_replace($destDir . '/', '', $destPath));
    }

    // Template methods for generating file contents (DEPRECATED - now using stub files)
}