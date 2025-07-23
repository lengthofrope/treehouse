<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\JwtCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Auth\Jwt\KeyRotationManager;
use LengthOfRope\TreeHouse\Cache\CacheManager;

/**
 * JWT Rotate Keys Command
 *
 * Rotates JWT signing keys via command line interface.
 * Supports manual key rotation with safety checks and confirmations.
 *
 * @package LengthOfRope\TreeHouse\Console\Commands\JwtCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtRotateKeysCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('jwt:rotate-keys')
            ->setDescription('Rotate JWT signing keys')
            ->setHelp('This command rotates JWT signing keys. Use with caution in production.')
            ->addOption('algorithm', 'a', InputOption::VALUE_OPTIONAL, 'Algorithm to rotate keys for (HS256, RS256, ES256)', 'HS256')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force rotation without confirmation')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (json|table|plain)', 'table');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $algorithm = $input->getOption('algorithm');
            $force = $input->getOption('force');
            $format = $input->getOption('format');
            
            // Safety check - confirm rotation unless forced
            if (!$force) {
                $this->warn($output, 'Key rotation will invalidate existing tokens after grace period expires.');
                if (!$this->confirm($output, "Rotate {$algorithm} keys? This action cannot be undone.")) {
                    $this->info($output, 'Key rotation cancelled.');
                    return 0;
                }
            }

            $cache = new CacheManager();
            $keyManager = new KeyRotationManager($cache);
            
            // Display current key info before rotation
            $this->displayCurrentKeyInfo($output, $keyManager, $algorithm);
            
            // Perform rotation
            $this->info($output, "Rotating {$algorithm} keys...");
            $newKey = $keyManager->rotateKey($algorithm);
            
            $this->outputKeyRotation($output, $newKey, $format);

            return 0;
        } catch (\Exception $e) {
            $this->error($output, 'Key rotation failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display current key information
     */
    private function displayCurrentKeyInfo(OutputInterface $output, KeyRotationManager $keyManager, string $algorithm): void
    {
        try {
            $currentKey = $keyManager->getCurrentKey($algorithm);
            if ($currentKey) {
                $this->comment($output, 'Current Key Information:');
                $output->writeln("  Algorithm: {$currentKey['algorithm']}");
                $output->writeln("  Key ID: {$currentKey['id']}");
                $output->writeln("  Created: " . date('Y-m-d H:i:s', $currentKey['created_at']));
                $output->writeln("  Expires: " . date('Y-m-d H:i:s', $currentKey['expires_at']));
                $output->writeln('');
            }
        } catch (\Exception $e) {
            $this->warn($output, "Could not retrieve current key info: {$e->getMessage()}");
        }
    }

    /**
     * Output key rotation results
     */
    private function outputKeyRotation(OutputInterface $output, array $keyData, string $format): void
    {
        switch ($format) {
            case 'json':
                // Don't expose secret keys in JSON output
                $safeData = [
                    'id' => $keyData['id'],
                    'algorithm' => $keyData['algorithm'],
                    'created_at' => $keyData['created_at'],
                    'expires_at' => $keyData['expires_at'],
                    'grace_expires_at' => $keyData['grace_expires_at'],
                ];
                $output->writeln(json_encode($safeData, JSON_PRETTY_PRINT));
                break;
                
            case 'table':
                $this->success($output, 'JWT keys rotated successfully!');
                $output->writeln('');
                $this->comment($output, 'New Key Information:');
                $output->writeln("  Key ID: {$keyData['id']}");
                $output->writeln("  Algorithm: {$keyData['algorithm']}");
                $output->writeln("  Created: " . date('Y-m-d H:i:s', $keyData['created_at']));
                $output->writeln("  Expires: " . date('Y-m-d H:i:s', $keyData['expires_at']));
                $output->writeln("  Grace Period Ends: " . date('Y-m-d H:i:s', $keyData['grace_expires_at']));
                $output->writeln('');
                $this->info($output, 'Old keys will remain valid during grace period.');
                $this->warn($output, 'New tokens will be signed with the new key immediately.');
                break;
                
            default:
                $output->writeln("Keys rotated. New key ID: {$keyData['id']}");
                break;
        }
    }
}