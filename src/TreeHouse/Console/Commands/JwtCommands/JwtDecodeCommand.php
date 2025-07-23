<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\JwtCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenIntrospector;

/**
 * JWT Decode Command
 *
 * Decodes and analyzes JWT tokens via command line interface.
 * Provides detailed token information and structure analysis.
 *
 * @package LengthOfRope\TreeHouse\Console\Commands\JwtCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtDecodeCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('jwt:decode')
            ->setDescription('Decode and analyze a JWT token')
            ->setHelp('This command decodes JWT tokens and provides detailed information about their structure and claims.')
            ->addArgument('token', InputArgument::REQUIRED, 'JWT token to decode')
            ->addOption('secret', 's', InputOption::VALUE_OPTIONAL, 'JWT secret key')
            ->addOption('algorithm', 'a', InputOption::VALUE_OPTIONAL, 'JWT algorithm (HS256, RS256, ES256)')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (json|table|plain)', 'table');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $token = $input->getArgument('token');
            if (!$token) {
                $this->error($output, 'Token is required for decoding.');
                return 1;
            }

            $config = $this->createJwtConfig($input);
            $introspector = new TokenIntrospector($config);
            
            $tokenInfo = $introspector->introspect($token);
            
            $format = $input->getOption('format');
            $this->outputTokenDecoding($output, $tokenInfo, $format);

            return 0;
        } catch (\Exception $e) {
            $this->error($output, 'Token decoding failed: ' . $e->getMessage());
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

        return new JwtConfig($config);
    }

    /**
     * Output token decoding results
     */
    private function outputTokenDecoding(OutputInterface $output, array $tokenInfo, string $format): void
    {
        switch ($format) {
            case 'json':
                $output->writeln(json_encode($tokenInfo, JSON_PRETTY_PRINT));
                break;
                
            case 'table':
                $this->info($output, 'Token Information:');
                $output->writeln('');
                
                // Display header information
                if (isset($tokenInfo['header'])) {
                    $this->comment($output, 'Header:');
                    foreach ($tokenInfo['header'] as $key => $value) {
                        $output->writeln("  {$key}: " . $this->formatValue($value));
                    }
                    $output->writeln('');
                }
                
                // Display claims
                if (isset($tokenInfo['claims'])) {
                    $this->comment($output, 'Claims:');
                    foreach ($tokenInfo['claims'] as $key => $value) {
                        $output->writeln("  {$key}: " . $this->formatValue($value));
                    }
                    $output->writeln('');
                }
                
                // Display timing information
                if (isset($tokenInfo['timing'])) {
                    $this->comment($output, 'Timing:');
                    foreach ($tokenInfo['timing'] as $key => $value) {
                        $output->writeln("  {$key}: " . $this->formatValue($value));
                    }
                    $output->writeln('');
                }
                
                // Display validation status
                if (isset($tokenInfo['valid'])) {
                    $status = $tokenInfo['valid'] ? 'VALID' : 'INVALID';
                    $color = $tokenInfo['valid'] ? 'success' : 'error';
                    $this->$color($output, "Token Status: {$status}");
                }
                break;
                
            default:
                foreach ($tokenInfo as $key => $value) {
                    if (is_array($value)) {
                        $output->writeln($key . '=' . json_encode($value));
                    } else {
                        $output->writeln($key . '=' . $value);
                    }
                }
                break;
        }
    }

    /**
     * Format value for display
     */
    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_null($value)) {
            return 'null';
        }
        
        // Format timestamps
        if (is_numeric($value) && $value > 1000000000 && $value < 3000000000) {
            return $value . ' (' . date('Y-m-d H:i:s', (int) $value) . ')';
        }
        
        return (string) $value;
    }
}