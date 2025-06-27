<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\DevelopmentCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;

/**
 * Development Server Command
 * 
 * Start a local development server for testing.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\DevelopmentCommands
 * @author  TreeHouse Framework Team
 * @since   1.0.0
 */
class ServeCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('serve')
            ->setDescription('Start a local development server')
            ->setHelp('This command starts a local PHP development server for testing.')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Server host', '127.0.0.1')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Server port', '8000')
            ->addOption('docroot', 'd', InputOption::VALUE_OPTIONAL, 'Document root', 'public');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $docroot = $input->getOption('docroot');
        
        // Check if document root exists
        if (!is_dir($docroot)) {
            $this->error($output, "Document root directory not found: {$docroot}");
            return 1;
        }
        
        $address = "{$host}:{$port}";
        
        $this->info($output, "Starting TreeHouse development server...");
        $this->info($output, "Server running at: http://{$address}");
        $this->info($output, "Document root: " . realpath($docroot));
        $this->comment($output, "Press Ctrl+C to stop the server");
        
        // Start the PHP built-in server
        $command = sprintf(
            'php -S %s -t %s',
            escapeshellarg($address),
            escapeshellarg($docroot)
        );
        
        // Execute the server command
        passthru($command, $exitCode);
        
        return $exitCode;
    }
}