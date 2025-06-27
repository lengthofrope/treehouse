<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\DevelopmentCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\InputArgument;

/**
 * Run PHPUnit tests command
 * 
 * Executes PHPUnit tests using the existing phpunit.xml configuration.
 * Supports test filtering and code coverage generation.
 */
class TestRunCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('test:run')
            ->setDescription('Run PHPUnit tests')
            ->setHelp('This command runs PHPUnit tests using the existing phpunit.xml configuration.')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Filter tests by name or pattern')
            ->addOption('coverage', 'c', InputOption::VALUE_NONE, 'Generate code coverage report')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Show verbose output')
            ->addOption('stop-on-failure', null, InputOption::VALUE_NONE, 'Stop execution on first failure');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $filter = $input->getArgument('filter');
            $coverage = (bool) $input->getOption('coverage');
            $verbose = (bool) $input->getOption('verbose');
            $stopOnFailure = (bool) $input->getOption('stop-on-failure');
            
            // Check if PHPUnit is available
            if (!$this->isPhpUnitAvailable()) {
                $this->error($output, 'PHPUnit is not available. Please install PHPUnit to run tests.');
                return 1;
            }
            
            // Check if phpunit.xml exists
            if (!$this->hasPhpUnitConfig()) {
                $this->error($output, 'phpunit.xml configuration file not found.');
                return 1;
            }
            
            $output->writeln('<info>Running PHPUnit tests...</info>');
            
            // Build PHPUnit command
            $command = $this->buildPhpUnitCommand($filter, $coverage, $verbose, $stopOnFailure);
            
            if ($output->isVerbose()) {
                $output->writeln("Executing: {$command}");
            }
            
            // Execute PHPUnit
            $exitCode = $this->executePhpUnit($command, $output);
            
            if ($exitCode === 0) {
                $this->info($output, '✓ All tests passed successfully');
                
                if ($coverage) {
                    $this->displayCoverageInfo($output);
                }
            } else {
                $this->error($output, '✗ Some tests failed');
            }
            
            return $exitCode;
            
        } catch (\Exception $e) {
            $this->error($output, 'Failed to run tests: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Check if PHPUnit is available
     */
    private function isPhpUnitAvailable(): bool
    {
        // Check for PHPUnit in vendor/bin
        if (file_exists(getcwd() . '/vendor/bin/phpunit')) {
            return true;
        }
        
        // Check for global PHPUnit installation
        exec('which phpunit 2>/dev/null', $output, $exitCode);
        return $exitCode === 0;
    }

    /**
     * Check if phpunit.xml configuration exists
     */
    private function hasPhpUnitConfig(): bool
    {
        return file_exists(getcwd() . '/phpunit.xml') || file_exists(getcwd() . '/phpunit.xml.dist');
    }

    /**
     * Build PHPUnit command
     */
    private function buildPhpUnitCommand(?string $filter, bool $coverage, bool $verbose, bool $stopOnFailure): string
    {
        $phpunitPath = $this->getPhpUnitPath();
        $command = $phpunitPath;
        
        if ($filter) {
            $command .= " --filter=" . escapeshellarg($filter);
        }
        
        if ($coverage) {
            $command .= " --coverage-html=storage/coverage";
        }
        
        if ($verbose) {
            $command .= " --verbose";
        }
        
        if ($stopOnFailure) {
            $command .= " --stop-on-failure";
        }
        
        return $command;
    }

    /**
     * Get PHPUnit executable path
     */
    private function getPhpUnitPath(): string
    {
        $vendorPath = getcwd() . '/vendor/bin/phpunit';
        
        if (file_exists($vendorPath)) {
            return $vendorPath;
        }
        
        return 'phpunit'; // Assume global installation
    }

    /**
     * Execute PHPUnit command
     */
    private function executePhpUnit(string $command, OutputInterface $output): int
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];
        
        $process = proc_open($command, $descriptors, $pipes, getcwd());
        
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start PHPUnit process');
        }
        
        // Close stdin
        fclose($pipes[0]);
        
        // Read output in real-time
        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);
            if ($line !== false) {
                $output->write($line);
            }
        }
        
        // Read any error output
        $errorOutput = stream_get_contents($pipes[2]);
        if (!empty($errorOutput)) {
            $output->writeln('<error>' . trim($errorOutput) . '</error>');
        }
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        return proc_close($process);
    }

    /**
     * Display coverage information
     */
    private function displayCoverageInfo(OutputInterface $output): void
    {
        $coveragePath = getcwd() . '/storage/coverage';
        
        if (is_dir($coveragePath)) {
            $output->writeln('');
            $this->info($output, "Code coverage report generated in: {$coveragePath}");
            $output->writeln("Open {$coveragePath}/index.html in your browser to view the report.");
        }
    }
}