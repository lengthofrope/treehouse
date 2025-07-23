<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\JwtCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenValidator;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenIntrospector;
use LengthOfRope\TreeHouse\Auth\Jwt\KeyRotationManager;
use LengthOfRope\TreeHouse\Auth\Jwt\BreachDetectionManager;
use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * JWT Management CLI Command
 *
 * Provides comprehensive JWT management capabilities through CLI including
 * token generation, validation, introspection, key rotation, and security
 * monitoring. Supports multiple output formats and advanced operations.
 *
 * @package LengthOfRope\TreeHouse\Console\Commands\JwtCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('jwt')
            ->setDescription('JWT token management and security operations')
            ->setHelp('Manage JWT tokens, keys, and security settings')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (generate|validate|decode|rotate-keys|security|config)')
            ->addArgument('token', InputArgument::OPTIONAL, 'JWT token (for validate/decode actions)')
            ->addOption('user-id', 'u', InputOption::VALUE_OPTIONAL, 'User ID for token generation')
            ->addOption('claims', 'c', InputOption::VALUE_OPTIONAL, 'Additional claims as JSON')
            ->addOption('ttl', 't', InputOption::VALUE_OPTIONAL, 'Token TTL in seconds')
            ->addOption('algorithm', 'a', InputOption::VALUE_OPTIONAL, 'JWT algorithm (HS256, RS256, ES256)')
            ->addOption('secret', 's', InputOption::VALUE_OPTIONAL, 'JWT secret key')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (json|table|plain)', 'plain')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Verbose output')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force operation without confirmation')
            ->setAliases(['token']);
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');

        return match ($action) {
            'generate' => $this->generateToken($input, $output),
            'validate' => $this->validateToken($input, $output),
            'decode' => $this->decodeToken($input, $output),
            'rotate-keys' => $this->rotateKeys($input, $output),
            'security' => $this->securityStatus($input, $output),
            'config' => $this->showConfig($input, $output),
            'help' => $this->showHelp($output),
            default => $this->showHelp($output),
        };
    }

    /**
     * Generate JWT token
     */
    private function generateToken(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = $this->createJwtConfig($input);
            $generator = new TokenGenerator($config);
            
            $userId = $input->getOption('user-id');
            if (!$userId) {
                $this->error($output, 'User ID is required for token generation. Use --user-id option.');
                return 1;
            }

            // Parse additional claims
            $additionalClaims = [];
            if ($claimsJson = $input->getOption('claims')) {
                $additionalClaims = json_decode($claimsJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error($output, 'Invalid JSON in claims: ' . json_last_error_msg());
                    return 1;
                }
            }

            // Generate token
            $token = $generator->generateAuthToken($userId, $additionalClaims);
            
            $format = $input->getOption('format');
            $this->outputTokenGeneration($output, $token, $config, $format);

            return 0;
        } catch (\Exception $e) {
            $this->error($output, 'Token generation failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Validate JWT token
     */
    private function validateToken(InputInterface $input, OutputInterface $output): int
    {
        try {
            $token = $input->getArgument('token');
            if (!$token) {
                $this->error($output, 'Token is required for validation.');
                return 1;
            }

            $config = $this->createJwtConfig($input);
            $validator = new TokenValidator($config);
            
            try {
                $claims = $validator->validate($token);
                $isValid = true;
                $errors = [];
            } catch (\Exception $e) {
                $isValid = false;
                $errors = [$e->getMessage()];
            }
            
            $format = $input->getOption('format');
            $this->outputTokenValidation($output, $token, $isValid, $errors, $format);

            return $isValid ? 0 : 1;
        } catch (\Exception $e) {
            $this->error($output, 'Token validation failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Decode JWT token
     */
    private function decodeToken(InputInterface $input, OutputInterface $output): int
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
     * Rotate JWT keys
     */
    private function rotateKeys(InputInterface $input, OutputInterface $output): int
    {
        try {
            if (!$input->getOption('force')) {
                if (!$this->confirm($output, 'This will rotate JWT signing keys. Continue?')) {
                    $this->info($output, 'Key rotation cancelled.');
                    return 0;
                }
            }

            $cache = new CacheManager();
            $keyManager = new KeyRotationManager($cache);
            
            $algorithm = $input->getOption('algorithm') ?? 'HS256';
            $newKey = $keyManager->rotateKey($algorithm);
            
            $format = $input->getOption('format');
            $this->outputKeyRotation($output, $newKey, $format);

            return 0;
        } catch (\Exception $e) {
            $this->error($output, 'Key rotation failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show security status
     */
    private function securityStatus(InputInterface $input, OutputInterface $output): int
    {
        try {
            $cache = new CacheManager();
            $logger = new ErrorLogger();
            $breachDetection = new BreachDetectionManager($cache, $logger);
            
            $stats = $breachDetection->getSecurityStats(24); // Last 24 hours
            $keyManager = new KeyRotationManager($cache);
            $keyStats = $keyManager->getRotationStats();
            
            $format = $input->getOption('format');
            $this->outputSecurityStatus($output, $stats, $keyStats, $format);

            return 0;
        } catch (\Exception $e) {
            $this->error($output, 'Security status check failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show JWT configuration
     */
    private function showConfig(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = $this->createJwtConfig($input);
            
            $format = $input->getOption('format');
            $this->outputConfig($output, $config, $format);

            return 0;
        } catch (\Exception $e) {
            $this->error($output, 'Configuration display failed: ' . $e->getMessage());
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
        } elseif ($envSecret = Env::get('JWT_SECRET')) {
            $config['secret'] = $envSecret;
        } else {
            // Use a default for CLI operations
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

    /**
     * Output token validation results
     */
    private function outputTokenValidation(OutputInterface $output, string $token, bool $isValid, array $errors, string $format): void
    {
        switch ($format) {
            case 'json':
                $output->writeln(json_encode([
                    'valid' => $isValid,
                    'errors' => $errors,
                ], JSON_PRETTY_PRINT));
                break;
                
            case 'table':
                if ($isValid) {
                    $this->success($output, 'Token is VALID');
                } else {
                    $this->error($output, 'Token is INVALID');
                    if (!empty($errors)) {
                        $output->writeln('');
                        $this->warn($output, 'Validation errors:');
                        foreach ($errors as $error) {
                            $output->writeln('  - ' . $error);
                        }
                    }
                }
                break;
                
            default:
                $output->writeln($isValid ? 'VALID' : 'INVALID');
                break;
        }
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
                foreach ($tokenInfo as $key => $value) {
                    if (is_array($value)) {
                        $output->writeln($key . ': ' . json_encode($value));
                    } else {
                        $output->writeln($key . ': ' . $value);
                    }
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
                ];
                $output->writeln(json_encode($safeData, JSON_PRETTY_PRINT));
                break;
                
            case 'table':
                $this->success($output, 'JWT keys rotated successfully');
                $output->writeln('');
                $output->writeln('Key ID: ' . $keyData['id']);
                $output->writeln('Algorithm: ' . $keyData['algorithm']);
                $output->writeln('Created: ' . date('Y-m-d H:i:s', $keyData['created_at']));
                $output->writeln('Expires: ' . date('Y-m-d H:i:s', $keyData['expires_at']));
                break;
                
            default:
                $output->writeln('Keys rotated. New key ID: ' . $keyData['id']);
                break;
        }
    }

    /**
     * Output security status
     */
    private function outputSecurityStatus(OutputInterface $output, array $securityStats, array $keyStats, string $format): void
    {
        switch ($format) {
            case 'json':
                $output->writeln(json_encode([
                    'security' => $securityStats,
                    'keys' => $keyStats,
                ], JSON_PRETTY_PRINT));
                break;
                
            case 'table':
                $this->info($output, 'JWT Security Status:');
                $output->writeln('');
                $output->writeln('Security Alerts (24h): ' . $securityStats['total_alerts']);
                $output->writeln('Threat Level: ' . strtoupper($securityStats['threat_level']));
                $output->writeln('Blocked IPs: ' . count($securityStats['blocked_ips']));
                $output->writeln('Blocked Users: ' . count($securityStats['blocked_users']));
                $output->writeln('');
                $output->writeln('Key Rotation:');
                $output->writeln('Current Key: ' . ($keyStats['current_key_id'] ?? 'None'));
                $output->writeln('Key Age: ' . gmdate('H:i:s', $keyStats['current_key_age']));
                $output->writeln('Next Rotation: ' . gmdate('H:i:s', $keyStats['time_until_rotation']));
                $output->writeln('Valid Keys: ' . $keyStats['valid_keys_count']);
                break;
                
            default:
                $output->writeln('Alerts: ' . $securityStats['total_alerts']);
                $output->writeln('Threat: ' . $securityStats['threat_level']);
                $output->writeln('Key: ' . ($keyStats['current_key_id'] ?? 'None'));
                break;
        }
    }

    /**
     * Output configuration
     */
    private function outputConfig(OutputInterface $output, JwtConfig $config, string $format): void
    {
        $configData = [
            'algorithm' => $config->getAlgorithm(),
            'ttl' => $config->getTtl(),
            'refresh_ttl' => $config->getRefreshTtl(),
            'blacklist_enabled' => $config->isBlacklistEnabled(),
            'issuer' => $config->getIssuer(),
            'audience' => $config->getAudience(),
            'subject' => $config->getSubject(),
        ];

        switch ($format) {
            case 'json':
                $output->writeln(json_encode($configData, JSON_PRETTY_PRINT));
                break;
                
            case 'table':
                $this->info($output, 'JWT Configuration:');
                $output->writeln('');
                foreach ($configData as $key => $value) {
                    $output->writeln(ucfirst(str_replace('_', ' ', $key)) . ': ' . ($value ?? 'Not set'));
                }
                break;
                
            default:
                foreach ($configData as $key => $value) {
                    $output->writeln($key . '=' . ($value ?? ''));
                }
                break;
        }
    }

    /**
     * Show help information
     */
    private function showHelp(OutputInterface $output): int
    {
        $this->info($output, 'JWT Management Commands:');
        $output->writeln('');
        $output->writeln('Actions:');
        $output->writeln('  generate      Generate a new JWT token');
        $output->writeln('  validate      Validate a JWT token');
        $output->writeln('  decode        Decode and inspect a JWT token');
        $output->writeln('  rotate-keys   Rotate JWT signing keys');
        $output->writeln('  security      Show security status');
        $output->writeln('  config        Show JWT configuration');
        $output->writeln('');
        $output->writeln('Examples:');
        $output->writeln('  php bin/treehouse jwt generate --user-id=123 --claims=\'{"role":"admin"}\'');
        $output->writeln('  php bin/treehouse jwt validate eyJ0eXAiOiJKV1QiLCJhbGc...');
        $output->writeln('  php bin/treehouse jwt decode eyJ0eXAiOiJKV1QiLCJhbGc... --format=json');
        $output->writeln('  php bin/treehouse jwt rotate-keys --algorithm=HS256 --force');
        $output->writeln('  php bin/treehouse jwt security --format=table');
        
        return 0;
    }
}