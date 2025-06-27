<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;

/**
 * Tests for Console Command
 */
class CommandTest extends TestCase
{
    public function testCommandConfigurationLazyLoading(): void
    {
        $command = new TestableCommand();
        
        // Name should be empty before configuration
        $this->assertEquals('', $this->getPrivateProperty($command, 'name'));
        
        // Accessing name should trigger configuration
        $name = $command->getName();
        $this->assertEquals('testable', $name);
    }

    public function testCommandNameConfiguration(): void
    {
        $command = new TestableCommand();
        
        $this->assertEquals('testable', $command->getName());
    }

    public function testCommandDescriptionConfiguration(): void
    {
        $command = new TestableCommand();
        
        $this->assertEquals('A testable command for unit testing', $command->getDescription());
    }

    public function testCommandHelpConfiguration(): void
    {
        $command = new TestableCommand();
        
        $this->assertEquals('This is the help text for the testable command.', $command->getHelp());
    }

    public function testCommandAliasesConfiguration(): void
    {
        $command = new TestableCommand();
        
        // Trigger configuration by calling getName() first
        $command->getName();
        
        $aliases = $command->getAliases();
        $this->assertEquals(['test', 't'], $aliases);
    }

    public function testCommandArgumentsConfiguration(): void
    {
        $command = new TestableCommand();
        
        $arguments = $command->getArguments();
        
        $this->assertArrayHasKey('required-arg', $arguments);
        $this->assertEquals(InputArgument::REQUIRED, $arguments['required-arg']['mode']);
        $this->assertEquals('A required argument', $arguments['required-arg']['description']);
        
        $this->assertArrayHasKey('optional-arg', $arguments);
        $this->assertEquals(InputArgument::OPTIONAL, $arguments['optional-arg']['mode']);
        $this->assertEquals('An optional argument', $arguments['optional-arg']['description']);
        $this->assertEquals('default-value', $arguments['optional-arg']['default']);
    }

    public function testCommandOptionsConfiguration(): void
    {
        $command = new TestableCommand();
        
        $options = $command->getOptions();
        
        $this->assertArrayHasKey('format', $options);
        $this->assertEquals('f', $options['format']['shortcut']);
        $this->assertEquals(InputOption::VALUE_REQUIRED, $options['format']['mode']);
        $this->assertEquals('Output format', $options['format']['description']);
        $this->assertEquals('text', $options['format']['default']);
        
        $this->assertArrayHasKey('verbose', $options);
        $this->assertEquals('v', $options['verbose']['shortcut']);
        $this->assertEquals(InputOption::VALUE_NONE, $options['verbose']['mode']);
        $this->assertEquals('Verbose output', $options['verbose']['description']);
    }

    public function testCommandSynopsisGeneration(): void
    {
        $command = new TestableCommand();
        
        $synopsis = $command->getSynopsis();
        
        $this->assertStringContainsString('<required-arg>', $synopsis);
        $this->assertStringContainsString('[<optional-arg>]', $synopsis);
        $this->assertStringContainsString('[options]', $synopsis);
    }

    public function testCommandSynopsisWithoutOptions(): void
    {
        $command = new SimpleCommand();
        
        $synopsis = $command->getSynopsis();
        
        $this->assertStringContainsString('<name>', $synopsis);
        $this->assertStringNotContainsString('[options]', $synopsis);
    }

    public function testValidateInputWithValidArguments(): void
    {
        $command = new TestableCommand();
        $input = $this->createMockInput(['required-arg' => 'value']);
        $output = $this->createMockOutput();
        
        $isValid = $this->callPrivateMethod($command, 'validateInput', [$input, $output]);
        
        $this->assertTrue($isValid);
    }

    public function testValidateInputWithMissingRequiredArgument(): void
    {
        $command = new TestableCommand();
        $input = $this->createMockInput([]);
        $output = $this->createMockOutput();
        
        $isValid = $this->callPrivateMethod($command, 'validateInput', [$input, $output]);
        
        $this->assertFalse($isValid);
    }

    public function testCommandOutputFormatting(): void
    {
        $command = new TestableCommand();
        $output = $this->createMockOutput();
        
        // Test line method
        $this->callPrivateMethod($command, 'line', [$output, 'Test message']);
        $this->callPrivateMethod($command, 'line', [$output, 'Styled message', 'info']);
        
        // Test convenience methods
        $this->callPrivateMethod($command, 'info', [$output, 'Info message']);
        $this->callPrivateMethod($command, 'error', [$output, 'Error message']);
        $this->callPrivateMethod($command, 'warn', [$output, 'Warning message']);
        $this->callPrivateMethod($command, 'success', [$output, 'Success message']);
        $this->callPrivateMethod($command, 'comment', [$output, 'Comment message']);
        
        // Since we're using a mock, we can't easily test the actual output
        // but we can verify the methods don't throw exceptions
        $this->assertTrue(true);
    }

    public function testInputArgumentModes(): void
    {
        $this->assertEquals(1, InputArgument::REQUIRED);
        $this->assertEquals(2, InputArgument::OPTIONAL);
        $this->assertEquals(4, InputArgument::IS_ARRAY);
    }

    public function testInputOptionModes(): void
    {
        $this->assertEquals(1, InputOption::VALUE_NONE);
        $this->assertEquals(2, InputOption::VALUE_REQUIRED);
        $this->assertEquals(4, InputOption::VALUE_OPTIONAL);
        $this->assertEquals(8, InputOption::VALUE_IS_ARRAY);
    }

    /**
     * Create a mock input interface
     */
    private function createMockInput(array $arguments = [], array $options = []): InputInterface
    {
        $input = $this->createMock(InputInterface::class);
        
        $input->method('hasArgument')
              ->willReturnCallback(fn($name) => isset($arguments[$name]));
              
        $input->method('getArgument')
              ->willReturnCallback(fn($name) => $arguments[$name] ?? null);
              
        $input->method('getArguments')
              ->willReturn($arguments);
              
        $input->method('hasOption')
              ->willReturnCallback(fn($name) => isset($options[$name]));
              
        $input->method('getOption')
              ->willReturnCallback(fn($name) => $options[$name] ?? null);
              
        $input->method('getOptions')
              ->willReturn($options);
              
        return $input;
    }

    /**
     * Create a mock output interface
     */
    private function createMockOutput(): OutputInterface
    {
        $output = $this->createMock(OutputInterface::class);
        
        $output->method('write');
        $output->method('writeln');
               
        return $output;
    }
}

/**
 * Testable command for unit testing
 */
class TestableCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('testable')
             ->setDescription('A testable command for unit testing')
             ->setHelp('This is the help text for the testable command.')
             ->setAliases(['test', 't'])
             ->addArgument('required-arg', InputArgument::REQUIRED, 'A required argument')
             ->addArgument('optional-arg', InputArgument::OPTIONAL, 'An optional argument', 'default-value')
             ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format', 'text')
             ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Verbose output');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Testable command executed');
        return 0;
    }
}

/**
 * Simple command without options
 */
class SimpleCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('simple')
             ->setDescription('A simple command')
             ->addArgument('name', InputArgument::REQUIRED, 'Name argument');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Simple command executed');
        return 0;
    }
}