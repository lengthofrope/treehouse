<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\MailCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Foundation\Application;
use Exception;

/**
 * Mail Queue Clear Command
 * 
 * Clears failed or sent emails from the queue.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\MailCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MailQueueClearCommand extends Command
{
    /**
     * The command name
     */
    protected string $name = 'mail:queue:clear';

    /**
     * The command description
     */
    protected string $description = 'Clear failed or sent emails from the queue';

    /**
     * Application instance
     */
    protected Application $app;

    /**
     * Create a new command instance
     */
    public function __construct(Application $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName($this->name)
             ->setDescription($this->description)
             ->addOption('failed', 'f', InputOption::VALUE_NONE, 'Clear only failed emails')
             ->addOption('sent', 's', InputOption::VALUE_NONE, 'Clear only sent emails')
             ->addOption('all', 'a', InputOption::VALUE_NONE, 'Clear both failed and sent emails')
             ->addOption('force', null, InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $mailQueue = $this->app->make('mail.queue');
            
            $clearFailed = $input->getOption('failed', false);
            $clearSent = $input->getOption('sent', false);
            $clearAll = $input->getOption('all', false);
            $force = $input->getOption('force', false);
            
            // Default to clearing failed if no specific option is given
            if (!$clearFailed && !$clearSent && !$clearAll) {
                $clearFailed = true;
            }
            
            // Get current stats
            $stats = $mailQueue->getStats();
            
            $output->writeln('<info>Mail Queue Clear</info>');
            $output->writeln('================');
            $output->writeln("Current status: {$stats['pending']} pending, {$stats['sent']} sent, {$stats['failed']} failed");
            $output->writeln('');
            
            // Determine what to clear
            $actions = [];
            if ($clearFailed || $clearAll) {
                if ($stats['failed'] > 0) {
                    $actions[] = "Clear {$stats['failed']} failed emails";
                }
            }
            
            if ($clearSent || $clearAll) {
                if ($stats['sent'] > 0) {
                    $actions[] = "Clear {$stats['sent']} sent emails";
                }
            }
            
            if (empty($actions)) {
                $output->writeln('<comment>No emails to clear</comment>');
                return 0; // SUCCESS
            }
            
            // Show what will be done
            $output->writeln('<comment>The following actions will be performed:</comment>');
            foreach ($actions as $action) {
                $output->writeln("  - {$action}");
            }
            $output->writeln('');
            
            // Confirm unless forced
            if (!$force && !$this->confirm($output, 'Are you sure you want to proceed?', false)) {
                $output->writeln('<comment>Operation cancelled</comment>');
                return 0; // SUCCESS
            }
            
            $totalCleared = 0;
            
            // Clear failed emails
            if ($clearFailed || $clearAll) {
                if ($stats['failed'] > 0) {
                    $cleared = $mailQueue->clearFailed();
                    $totalCleared += $cleared;
                    $output->writeln("<info>Cleared {$cleared} failed emails</info>");
                }
            }
            
            // Clear sent emails
            if ($clearSent || $clearAll) {
                if ($stats['sent'] > 0) {
                    $cleared = $mailQueue->clearSent();
                    $totalCleared += $cleared;
                    $output->writeln("<info>Cleared {$cleared} sent emails</info>");
                }
            }
            
            $output->writeln('');
            $output->writeln("<info>Total cleared: {$totalCleared} emails</info>");
            
            // Show updated stats
            $newStats = $mailQueue->getStats();
            $output->writeln("Updated status: {$newStats['pending']} pending, {$newStats['sent']} sent, {$newStats['failed']} failed");
            
            return 0; // SUCCESS
            
        } catch (Exception $e) {
            $output->writeln('<error>Failed to clear queue: ' . $e->getMessage() . '</error>');
            return 1; // FAILURE
        }
    }
}