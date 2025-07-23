<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\JwtCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * JWT Generate Command
 *
 * Generates JWT tokens via command line interface.
 * Supports custom claims, TTL, and multiple output formats.
 *
 * @package LengthOfRope\TreeHouse\Console\Commands\JwtCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtGenerateCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('jwt:generate')
            ->setDescription('Generate a new JWT token')
            ->setHelp('This command allows you to generate JWT tokens for testing and development.')
            ->addArgument('user-id', InputArgument::REQUIRED, 'User ID for the token')
            ->addOption('claims', 'c', InputOption::VALUE_OPTIONAL, 'Additional claims as JSON')
            ->addOption('ttl', 't', InputOption::VALUE_OPTIONAL, 'Token TTL in seconds')
            ->addOption('algorithm', 'a', InputOption::VALUE_OPTIONAL, 'JWT algorithm (HS256, RS256, ES256)')
            ->addOption('secret', 's', InputOption::VALUE_OPTIONAL, 'JWT secret key')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (json|table|plain)', 'plain');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $userId = $input->getArgument('user-id');
            if (!$userId) {
                $this->error($output, 'User ID is required for token generation.');
                return 1;
            }

            // Create JWT configuration
            $config = $this->createJwtConfig($input);
            $generator = new TokenGenerator($config);
            
            // Parse additional claims
            $additionalClaims = [];
            if ($claimsJson = $input->getOption('claims')) {
                $additionalClaims = json_decode($claimsJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error($output, 'Invalid JSON in claims: ' . json_last_error_msg());
                    return 1;
                }
            }

            // Add user context
            $claims = array_merge([
                'email' => "user{$userId}@example.com",
            ], $additionalClaims);

            // Generate token
            $token = $generator->generateAuthToken($userId, $claims);
            
            $format = $input->getOption('format');
            $this->outputTokenGeneration($output, $token, $config, $format);

            return 0;
        } catch (\Exception $e) {
            $this->error($output, 'Token generation failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Create JWT configuration from input options
     */
    private function createJwtConfig(InputInterface $input): JwtConfig
    {
        $config = [];
        
        if ($secret = $input->getOption('secret')) {
            $config['secret'] = $secret;
        } elseif ($envSecret = $_ENV['JWT_SECRET'] ?? null) {
            $config['secret'] = $envSecret;
        } else {
            $config['secret'] = 'cli-default-secret-key-change-in-production';
        }
        
        if ($algorithm = $input->getOption('algorithm')) {
            $config['algorithm'] = $algorithm;
        }
        
        if ($ttl = $input->getOption('ttl')) {
            $config['ttl'] = (int) $ttl;
        }

        return new JwtConfig($config);
    }

    /**
     * Output token generation results
     */
    private function outputTokenGeneration(OutputInterface $output, string $token, JwtConfig $config, string $format): void
    {
        switch ($format) {
            case 'json':
                $output->writeln(json_encode([
                    'token' => $token,
                    'algorithm' => $config->getAlgorithm(),
                    'ttl' => $config->getTtl(),
                    'expires_at' => Carbon::now()->addSeconds($config->getTtl())->format('c'),
                ], JSON_PRETTY_PRINT));
                break;
                
            case 'table':
                $this->info($output, 'JWT Token Generated:');
                $output->writeln('');
                $output->writeln('Algorithm: ' . $config->getAlgorithm());
                $output->writeln('TTL: ' . $config->getTtl() . ' seconds');
                $output->writeln('Expires: ' . Carbon::now()->addSeconds($config->getTtl())->format('Y-m-d H:i:s'));
                $output->writeln('');
                $output->writeln('Token:');
                $output->writeln($token);
                break;
                
            default:
                $output->writeln($token);
                break;
        }
    }
}