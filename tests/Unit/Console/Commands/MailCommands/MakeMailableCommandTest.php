<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\MailCommands;

use LengthOfRope\TreeHouse\Console\Commands\MailCommands\MakeMailableCommand;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use Tests\TestCase;

/**
 * MakeMailableCommand Test
 * 
 * Tests for the make:mailable console command
 */
class MakeMailableCommandTest extends TestCase
{
    protected MakeMailableCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new MakeMailableCommand();
    }

    public function testCommandConfiguration(): void
    {
        $this->assertEquals('make:mailable', $this->command->getName());
        $this->assertEquals('Generate a new Mailable class', $this->command->getDescription());
    }

    public function testCommandHasRequiredArguments(): void
    {
        $arguments = $this->command->getArguments();
        
        $this->assertArrayHasKey('name', $arguments);
        $this->assertEquals(\LengthOfRope\TreeHouse\Console\InputArgument::REQUIRED, $arguments['name']['mode']);
    }

    public function testCommandHasRequiredOptions(): void
    {
        $options = $this->command->getOptions();
        
        $this->assertArrayHasKey('template', $options);
        $this->assertArrayHasKey('force', $options);
        
        // Template option should be optional
        $this->assertEquals(\LengthOfRope\TreeHouse\Console\InputOption::VALUE_OPTIONAL, $options['template']['mode']);
        
        // Force option should be value none (flag)
        $this->assertEquals(\LengthOfRope\TreeHouse\Console\InputOption::VALUE_NONE, $options['force']['mode']);
    }

    public function testCommandHasShortcuts(): void
    {
        $options = $this->command->getOptions();
        
        $this->assertEquals('t', $options['template']['shortcut']);
        $this->assertEquals('f', $options['force']['shortcut']);
    }

    public function testCommandHelp(): void
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
        $this->assertNotEmpty($help);
        $this->assertStringContainsString('Mailable class', $help);
    }

    public function testCommandSynopsis(): void
    {
        $synopsis = $this->command->getSynopsis();
        $this->assertIsString($synopsis);
        $this->assertNotEmpty($synopsis);
    }

    public function testCommandInheritsFromCommand(): void
    {
        $this->assertInstanceOf(\LengthOfRope\TreeHouse\Console\Command::class, $this->command);
    }

    public function testOptionsHaveDescriptions(): void
    {
        $options = $this->command->getOptions();
        
        $this->assertNotEmpty($options['template']['description']);
        $this->assertNotEmpty($options['force']['description']);
    }

    public function testArgumentHasDescription(): void
    {
        $arguments = $this->command->getArguments();
        
        $this->assertNotEmpty($arguments['name']['description']);
        $this->assertEquals('The name of the Mailable class', $arguments['name']['description']);
    }

    public function testCommandMethodsAreCallable(): void
    {
        // Test that we can call the command methods without errors
        $this->assertIsString($this->command->getName());
        $this->assertIsString($this->command->getDescription());
        $this->assertIsArray($this->command->getArguments());
        $this->assertIsArray($this->command->getOptions());
    }
}