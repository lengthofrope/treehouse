<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Application;
use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\Output\ConsoleOutput;

/**
 * Integration tests for the entire Console layer
 */
class ConsoleIntegrationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application();
    }

    public function testFullCommandExecutionFlow(): void
    {
        // Register a comprehensive test command
        $command = new IntegrationTestCommand();
        $this->app->register($command);
        
        // Test successful execution with arguments and options
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'integration:test',
            'test-name',
            'optional-value',
            '--format=json',
            '--verbose',
            '--count=5'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Integration test executed', $output);
        $this->assertStringContainsString('Name: test-name', $output);
        $this->assertStringContainsString('Optional: optional-value', $output);
        $this->assertStringContainsString('Format: json', $output);
        $this->assertStringContainsString('Verbose: enabled', $output);
        $this->assertStringContainsString('Count: 5', $output);
    }

    public function testCommandWithDefaultValues(): void
    {
        $command = new IntegrationTestCommand();
        $this->app->register($command);
        
        // Test execution with only required arguments
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'integration:test',
            'required-name'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Name: required-name', $output);
        $this->assertStringContainsString('Optional: default-optional', $output);
        $this->assertStringContainsString('Format: text', $output);
        $this->assertStringContainsString('Verbose: disabled', $output);
        $this->assertStringContainsString('Count: 10', $output);
    }

    public function testCommandValidationFailure(): void
    {
        $command = new IntegrationTestCommand();
        $this->app->register($command);
        
        // Test execution without required arguments
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'integration:test'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Missing required argument: name', $output);
    }

    public function testCommandWithShortOptions(): void
    {
        $command = new IntegrationTestCommand();
        $this->app->register($command);
        
        // Test with short option flags
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'integration:test',
            'test-name',
            '-f', 'xml',
            '-v',
            '-c', '3'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Format: xml', $output);
        $this->assertStringContainsString('Verbose: enabled', $output);
        $this->assertStringContainsString('Count: 3', $output);
    }

    public function testCommandHelpDisplay(): void
    {
        $command = new IntegrationTestCommand();
        $this->app->register($command);
        
        // Test command-specific help
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'integration:test',
            '--help'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Description:', $output);
        $this->assertStringContainsString('A comprehensive test command', $output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('Options:', $output);
        $this->assertStringContainsString('--format', $output);
        $this->assertStringContainsString('--verbose', $output);
        $this->assertStringContainsString('--count', $output);
    }

    public function testOutputFormattingIntegration(): void
    {
        $command = new FormattingTestCommand();
        $this->app->register($command);
        
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'formatting:test'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Info message', $output);
        $this->assertStringContainsString('Error message', $output);
        $this->assertStringContainsString('Success message', $output);
        $this->assertStringContainsString('Warning message', $output);
    }

    public function testVerbosityLevelsIntegration(): void
    {
        $command = new VerbosityTestCommand();
        $this->app->register($command);
        
        // Test normal verbosity
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'verbosity:test'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Normal output', $output);
        
        // Test with verbose flag
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'verbosity:test',
            '--verbose'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Normal output', $output);
        $this->assertStringContainsString('Verbose output', $output);
    }

    public function testInteractiveCommandFeatures(): void
    {
        $command = new InteractiveTestCommand();
        $this->app->register($command);
        
        // Note: Interactive commands would require input mocking in real scenarios
        // For now, we test that the command can be registered and basic execution works
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'interactive:test',
            '--non-interactive'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Non-interactive mode', $output);
    }

    public function testCommandAliasesIntegration(): void
    {
        $command = new IntegrationTestCommand();
        $this->app->register($command);
        
        // Test execution using alias
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'itest',
            'alias-test'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Name: alias-test', $output);
    }

    public function testErrorHandlingIntegration(): void
    {
        $command = new ErrorTestCommand();
        $this->app->register($command);
        
        // Test command that throws an exception
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'error:test'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Error: Integration test error', $output);
    }

    public function testComplexArgumentParsing(): void
    {
        $command = new ComplexArgsCommand();
        $this->app->register($command);
        
        ob_start();
        $exitCode = $this->app->run([
            'script.php',
            'complex:args',
            'file.txt',
            '--config=/path/to/config.json',
            '--tags=tag1,tag2,tag3',
            '--dry-run',
            '-f',
            '--timeout=300'
        ]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('File: file.txt', $output);
        $this->assertStringContainsString('Config: /path/to/config.json', $output);
        $this->assertStringContainsString('Tags: tag1,tag2,tag3', $output);
        $this->assertStringContainsString('Dry run: enabled', $output);
        $this->assertStringContainsString('Force: enabled', $output);
        $this->assertStringContainsString('Timeout: 300', $output);
    }
}

/**
 * Comprehensive integration test command
 */
class IntegrationTestCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('integration:test')
             ->setDescription('A comprehensive test command for integration testing')
             ->setHelp('This command tests all aspects of console functionality.')
             ->setAliases(['itest', 'int-test'])
             ->addArgument('name', InputArgument::REQUIRED, 'The name argument')
             ->addArgument('optional', InputArgument::OPTIONAL, 'An optional argument', 'default-optional')
             ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format', 'text')
             ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Enable verbose output')
             ->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'Number of items', '10');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->validateInput($input, $output)) {
            return 1;
        }

        $this->info($output, 'Integration test executed');
        
        $output->writeln('Name: ' . $input->getArgument('name'));
        $output->writeln('Optional: ' . $input->getArgument('optional'));
        $output->writeln('Format: ' . $input->getOption('format'));
        $output->writeln('Verbose: ' . ($input->getOption('verbose') ? 'enabled' : 'disabled'));
        $output->writeln('Count: ' . $input->getOption('count'));
        
        return 0;
    }
}

/**
 * Formatting test command
 */
class FormattingTestCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('formatting:test')
             ->setDescription('Test output formatting');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->info($output, 'Info message');
        $this->error($output, 'Error message');
        $this->success($output, 'Success message');
        $this->warn($output, 'Warning message');
        $this->comment($output, 'Comment message');
        
        return 0;
    }
}

/**
 * Verbosity test command
 */
class VerbosityTestCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('verbosity:test')
             ->setDescription('Test verbosity levels')
             ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Verbose output');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Normal output');
        
        if ($input->getOption('verbose')) {
            $output->writeln('Verbose output');
        }
        
        return 0;
    }
}

/**
 * Interactive test command
 */
class InteractiveTestCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('interactive:test')
             ->setDescription('Test interactive features')
             ->addOption('non-interactive', null, InputOption::VALUE_NONE, 'Non-interactive mode');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('non-interactive')) {
            $output->writeln('Non-interactive mode');
            return 0;
        }
        
        $output->writeln('Interactive mode (would ask questions)');
        return 0;
    }
}

/**
 * Error test command
 */
class ErrorTestCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('error:test')
             ->setDescription('Test error handling');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        throw new \Exception('Integration test error');
    }
}

/**
 * Complex arguments test command
 */
class ComplexArgsCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('complex:args')
             ->setDescription('Test complex argument parsing')
             ->addArgument('file', InputArgument::REQUIRED, 'File to process')
             ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Config file path')
             ->addOption('tags', null, InputOption::VALUE_REQUIRED, 'Comma-separated tags')
             ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force execution')
             ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Timeout in seconds', '60');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->validateInput($input, $output)) {
            return 1;
        }
        
        $output->writeln('File: ' . $input->getArgument('file'));
        $output->writeln('Config: ' . ($input->getOption('config') ?: 'default'));
        $output->writeln('Tags: ' . ($input->getOption('tags') ?: 'none'));
        $output->writeln('Dry run: ' . ($input->getOption('dry-run') ? 'enabled' : 'disabled'));
        $output->writeln('Force: ' . ($input->getOption('force') ? 'enabled' : 'disabled'));
        $output->writeln('Timeout: ' . $input->getOption('timeout'));
        
        return 0;
    }
}