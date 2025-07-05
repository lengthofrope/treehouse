<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Application;
use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;

/**
 * Tests for Console Application
 */
class ApplicationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application();
    }

    public function testApplicationHasCorrectNameAndVersion(): void
    {
        $this->assertEquals('TreeHouse CLI', Application::NAME);
        $this->assertEquals('1.0.0', Application::VERSION);
    }

    public function testApplicationConstructorSetsDefaults(): void
    {
        $this->assertIsString($this->app->getWorkingDirectory());
        $this->assertNotNull($this->app->getConfig());
    }

    public function testApplicationRegistersCommandsOutsideProject(): void
    {
        // Create a temporary directory outside TreeHouse project
        $tempDir = sys_get_temp_dir() . '/test_treehouse_' . uniqid();
        mkdir($tempDir);
        
        // Change working directory temporarily
        $originalDir = getcwd();
        chdir($tempDir);
        
        try {
            // Create new application instance in temp directory
            $app = new Application();
            
            // Capture output to prevent console spam during tests
            ob_start();
            $app->run(['treehouse', '--help']);
            ob_end_clean();
            
            $commands = $app->getCommands();
            
            // Check that we have registered commands
            $this->assertNotEmpty($commands);
            
            // Check specific command types exist
            $commandNames = array_keys($commands);
            
            // Project commands (only outside TreeHouse project)
            $this->assertContains('new', $commandNames);
            
            // Should NOT contain project management commands
            $this->assertNotContains('cache:clear', $commandNames);
            $this->assertNotContains('serve', $commandNames);
        } finally {
            // Restore original directory and cleanup
            chdir($originalDir);
            if (is_dir($tempDir)) {
                // Recursively remove directory and all contents
                $this->removeDirectory($tempDir);
            }
        }
    }

    public function testApplicationRegistersCommandsInsideProject(): void
    {
        // Capture output to prevent console spam during tests
        ob_start();
        $this->app->run(['treehouse', '--help']);
        ob_end_clean();
        
        $commands = $this->app->getCommands();
        
        // Check that we have registered commands
        $this->assertNotEmpty($commands);
        
        // Check specific command types exist
        $commandNames = array_keys($commands);
        
        // Should NOT contain new command (inside project)
        $this->assertNotContains('new', $commandNames);
        
        // Cache commands
        $this->assertContains('cache:clear', $commandNames);
        $this->assertContains('cache:stats', $commandNames);
        $this->assertContains('cache:warm', $commandNames);
        
        // Database commands
        $this->assertContains('migrate:run', $commandNames);
        
        // Development commands
        $this->assertContains('serve', $commandNames);
        $this->assertContains('test:run', $commandNames);
    }

    public function testRegisterCommand(): void
    {
        $command = new TestCommand();
        $this->app->register($command);
        
        $commands = $this->app->getCommands();
        $this->assertArrayHasKey('test', $commands);
        $this->assertSame($command, $commands['test']);
    }

    public function testRegisterCommandWithAliases(): void
    {
        $command = new TestCommandWithAliases();
        $this->app->register($command);
        
        $commands = $this->app->getCommands();
        
        // Check main command
        $this->assertArrayHasKey('test:alias', $commands);
        $this->assertSame($command, $commands['test:alias']);
        
        // Check aliases
        $this->assertArrayHasKey('ta', $commands);
        $this->assertSame($command, $commands['ta']);
        
        $this->assertArrayHasKey('alias', $commands);
        $this->assertSame($command, $commands['alias']);
    }

    public function testGetWorkingDirectory(): void
    {
        $workingDir = $this->app->getWorkingDirectory();
        $this->assertIsString($workingDir);
        $this->assertNotEmpty($workingDir);
    }

    public function testGetConfig(): void
    {
        $config = $this->app->getConfig();
        $this->assertNotNull($config);
    }

    public function testGetCommands(): void
    {
        // Register commands first by simulating a run
        // Capture output to prevent console spam during tests
        ob_start();
        $this->app->run(['treehouse', '--help']);
        ob_end_clean();
        
        $commands = $this->app->getCommands();
        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);
        
        foreach ($commands as $name => $command) {
            $this->assertIsString($name);
            $this->assertInstanceOf(Command::class, $command);
        }
    }

    /**
     * Recursively remove a directory and all its contents
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

/**
 * Test command for testing purposes
 */
class TestCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('test')
             ->setDescription('A test command for unit testing');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Test command executed');
        return 0;
    }
}

/**
 * Test command with aliases
 */
class TestCommandWithAliases extends Command
{
    protected function configure(): void
    {
        $this->setName('test:alias')
             ->setDescription('A test command with aliases')
             ->setAliases(['ta', 'alias']);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Test command with aliases executed');
        return 0;
    }
}