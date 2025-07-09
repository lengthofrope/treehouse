<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\MailCommands;

use LengthOfRope\TreeHouse\Console\Commands\MailCommands\MailQueueRetryCommand;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use Tests\TestCase;

/**
 * MailQueueRetryCommand Test
 * 
 * Tests for the mail queue retry console command
 */
class MailQueueRetryCommandTest extends TestCase
{
    protected MailQueueRetryCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new MailQueueRetryCommand();
    }

    public function testCommandConfiguration(): void
    {
        $this->assertEquals('mail:queue:retry', $this->command->getName());
        $this->assertEquals('Retry failed emails in the mail queue', $this->command->getDescription());
    }

    public function testCommandHasRequiredOptions(): void
    {
        $options = $this->command->getOptions();
        
        $this->assertArrayHasKey('limit', $options);
        $this->assertArrayHasKey('max-attempts', $options);
        $this->assertArrayHasKey('older-than', $options);
        $this->assertArrayHasKey('mailer', $options);
        $this->assertArrayHasKey('force', $options);
        $this->assertArrayHasKey('dry-run', $options);
    }

    public function testCommandHasRequiredArguments(): void
    {
        $arguments = $this->command->getArguments();
        
        $this->assertArrayHasKey('ids', $arguments);
    }

    public function testCommandDefaultValues(): void
    {
        $options = $this->command->getOptions();
        
        // Test default values
        $this->assertEquals(10, $options['limit']['default']);
        $this->assertEquals(30, $options['older-than']['default']);
    }

    public function testCommandInheritsFromCommand(): void
    {
        $this->assertInstanceOf(\LengthOfRope\TreeHouse\Console\Command::class, $this->command);
    }

    public function testCommandHasSynopsis(): void
    {
        $synopsis = $this->command->getSynopsis();
        $this->assertIsString($synopsis);
        $this->assertNotEmpty($synopsis);
    }

    public function testLimitOptionIsOptional(): void
    {
        $options = $this->command->getOptions();
        $limitOption = $options['limit'];
        
        $this->assertEquals(\LengthOfRope\TreeHouse\Console\InputOption::VALUE_OPTIONAL, $limitOption['mode']);
    }

    public function testForceOptionIsValueNone(): void
    {
        $options = $this->command->getOptions();
        $forceOption = $options['force'];
        
        $this->assertEquals(\LengthOfRope\TreeHouse\Console\InputOption::VALUE_NONE, $forceOption['mode']);
    }

    public function testDryRunOptionIsValueNone(): void
    {
        $options = $this->command->getOptions();
        $dryRunOption = $options['dry-run'];
        
        $this->assertEquals(\LengthOfRope\TreeHouse\Console\InputOption::VALUE_NONE, $dryRunOption['mode']);
    }

    public function testIdsArgumentIsOptional(): void
    {
        $arguments = $this->command->getArguments();
        $idsArgument = $arguments['ids'];
        
        $this->assertEquals(\LengthOfRope\TreeHouse\Console\InputArgument::OPTIONAL, $idsArgument['mode']);
    }

    public function testCommandHasShortcuts(): void
    {
        $options = $this->command->getOptions();
        
        $this->assertEquals('l', $options['limit']['shortcut']);
        $this->assertEquals('m', $options['max-attempts']['shortcut']);
        $this->assertEquals('o', $options['older-than']['shortcut']);
        $this->assertEquals('f', $options['force']['shortcut']);
        $this->assertEquals('d', $options['dry-run']['shortcut']);
    }
}