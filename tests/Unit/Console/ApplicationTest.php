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

    public function testApplicationRegistersDefaultCommands(): void
    {
        $commands = $this->app->getCommands();
        
        // Check that we have registered commands
        $this->assertNotEmpty($commands);
        
        // Check specific command types exist
        $commandNames = array_keys($commands);
        
        // Project commands
        $this->assertContains('new', $commandNames);
        
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
        $commands = $this->app->getCommands();
        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);
        
        foreach ($commands as $name => $command) {
            $this->assertIsString($name);
            $this->assertInstanceOf(Command::class, $command);
        }
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