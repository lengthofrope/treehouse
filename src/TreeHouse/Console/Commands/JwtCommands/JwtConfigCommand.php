<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\JwtCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfigValidator;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;

/**
 * JWT Config Command
 *
 * Displays and validates JWT configuration.
 * Shows current settings and provides validation feedback.
 *
 * @package LengthOfRope\TreeHouse\Console\Commands\JwtCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtConfigCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('jwt:config')
            ->setDescription('Display and validate JWT configuration')
            ->setHelp('This command shows the current JWT configuration and validates it for security compliance.')
            ->addOption('validate', 'v', InputOption::VALUE_NONE, 'Validate configuration for security compliance')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (json|table|plain)', 'table');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $validate = $input->getOption('validate');
            $format = $input->getOption('format');
            
            // Create JWT configuration
            $config = $this->createJwtConfig();
            
            if ($validate) {
                return $this->validateConfiguration($output, $config, $format);
            } else {
                return $this->displayConfiguration($output, $config, $format);
            }

        } catch (\Exception $e) {
            $this->error($output, 'Configuration display failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display configuration
     */
    private function displayConfiguration(OutputInterface $output, JwtConfig $config, string $format): int
    {
        $configData = [
            'algorithm' => $config->getAlgorithm(),
            'ttl' => $config->getTtl(),
            'refresh_ttl' => $config->getRefreshTtl(),
            'leeway' => $config->getLeeway(),
            'blacklist_enabled' => $config->isBlacklistEnabled(),
            'issuer' => $config->getIssuer() ?: 'Not set',
            'audience' => $config->getAudience() ?: 'Not set',
            'subject' => $config->getSubject() ?: 'Not set',
            'required_claims' => $config->getRequiredClaims(),
        ];

        switch ($format) {
            case 'json':
                $output->writeln(json_encode($configData, JSON_PRETTY_PRINT));
                break;
                
            case 'table':
                $this->info($output, 'JWT Configuration:');
                $output->writeln('');
                
                $this->comment($output, 'Basic Settings:');
                $output->writeln("  Algorithm: {$configData['algorithm']}");
                $output->writeln("  TTL: {$configData['ttl']} seconds (" . $this->formatDuration($configData['ttl']) . ")");
                $output->writeln("  Refresh TTL: {$configData['refresh_ttl']} seconds (" . $this->formatDuration($configData['refresh_ttl']) . ")");
                $output->writeln("  Clock Leeway: {$configData['leeway']} seconds");
                $output->writeln("  Blacklist Enabled: " . ($configData['blacklist_enabled'] ? 'Yes' : 'No'));
                $output->writeln('');
                
                $this->comment($output, 'Claims:');
                $output->writeln("  Issuer: {$configData['issuer']}");
                $output->writeln("  Audience: {$configData['audience']}");
                $output->writeln("  Subject: {$configData['subject']}");
                
                if (!empty($configData['required_claims'])) {
                    $output->writeln("  Required Claims: " . implode(', ', $configData['required_claims']));
                } else {
                    $output->writeln("  Required Claims: None");
                }
                
                $output->writeln('');
                $this->displayEnvironmentInfo($output);
                break;
                
            default:
                foreach ($configData as $key => $value) {
                    if (is_array($value)) {
                        $output->writeln($key . '=' . implode(',', $value));
                    } else {
                        $output->writeln($key . '=' . $value);
                    }
                }
                break;
        }

        return 0;
    }

    /**
     * Validate configuration
     */
    private function validateConfiguration(OutputInterface $output, JwtConfig $config, string $format): int
    {
        $logger = new ErrorLogger();
        $validator = new JwtConfigValidator($logger);
        
        try {
            $validationResults = $validator->validate($config, false);
            
            switch ($format) {
                case 'json':
                    $output->writeln(json_encode($validationResults, JSON_PRETTY_PRINT));
                    break;
                    
                case 'table':
                    $this->displayValidationTable($output, $validationResults);
                    break;
                    
                default:
                    $this->displayValidationPlain($output, $validationResults);
                    break;
            }
            
            return $validationResults['valid'] ? 0 : 1;
            
        } catch (\Exception $e) {
            $this->error($output, 'Configuration validation failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display validation results in table format
     */
    private function displayValidationTable(OutputInterface $output, array $results): void
    {
        $status = $results['valid'] ? 'VALID' : 'INVALID';
        $color = $results['valid'] ? 'success' : 'error';
        
        $this->$color($output, "JWT Configuration: {$status}");
        $output->writeln('');
        
        // Summary
        $this->comment($output, 'Validation Summary:');
        $output->writeln("  Total Issues: {$results['total_issues']}");
        
        if (!empty($results['by_severity'])) {
            foreach ($results['by_severity'] as $severity => $count) {
                $severityColor = $this->getSeverityColor($severity);
                $this->$severityColor($output, "  {$severity}: {$count}");
            }
        }
        
        $output->writeln('');
        
        // Critical errors
        if (!empty($results['critical_errors'])) {
            $this->error($output, 'Critical Errors:');
            foreach ($results['critical_errors'] as $error) {
                $output->writeln("  • {$error}");
            }
            $output->writeln('');
        }
        
        // Issues by severity
        if (!empty($results['issues'])) {
            $this->comment($output, 'Detailed Issues:');
            foreach ($results['issues'] as $issue) {
                $severityColor = $this->getSeverityColor($issue['severity']);
                $this->$severityColor($output, "  [{$issue['severity']}] {$issue['message']}");
                if (!empty($issue['recommendation'])) {
                    $this->info($output, "    → {$issue['recommendation']}");
                }
            }
            $output->writeln('');
        }
        
        // Recommendations
        if (!empty($results['recommendations'])) {
            $this->comment($output, 'Recommendations:');
            foreach ($results['recommendations'] as $recommendation) {
                $output->writeln("  • {$recommendation}");
            }
        }
    }

    /**
     * Display validation results in plain format
     */
    private function displayValidationPlain(OutputInterface $output, array $results): void
    {
        $output->writeln('valid=' . ($results['valid'] ? 'true' : 'false'));
        $output->writeln('total_issues=' . $results['total_issues']);
        
        foreach ($results['by_severity'] as $severity => $count) {
            $output->writeln("{$severity}_count={$count}");
        }
    }

    /**
     * Display environment information
     */
    private function displayEnvironmentInfo(OutputInterface $output): void
    {
        $this->comment($output, 'Environment:');
        $env = $_ENV['APP_ENV'] ?? 'production';
        $output->writeln("  Environment: {$env}");
        
        $secretSource = 'Default';
        if (isset($_ENV['JWT_SECRET'])) {
            $secretSource = 'Environment Variable';
        }
        $output->writeln("  Secret Source: {$secretSource}");
        
        $isHttps = $this->isHttpsEnvironment();
        $output->writeln("  HTTPS: " . ($isHttps ? 'Yes' : 'No'));
    }

    /**
     * Create JWT configuration
     */
    private function createJwtConfig(): JwtConfig
    {
        $config = [];
        
        if ($secret = $_ENV['JWT_SECRET'] ?? null) {
            $config['secret'] = $secret;
        }
        
        if ($algorithm = $_ENV['JWT_ALGORITHM'] ?? null) {
            $config['algorithm'] = $algorithm;
        }
        
        if ($ttl = $_ENV['JWT_TTL'] ?? null) {
            $config['ttl'] = (int) $ttl;
        }
        
        if ($refreshTtl = $_ENV['JWT_REFRESH_TTL'] ?? null) {
            $config['refresh_ttl'] = (int) $refreshTtl;
        }
        
        if ($issuer = $_ENV['JWT_ISSUER'] ?? null) {
            $config['issuer'] = $issuer;
        }
        
        if ($audience = $_ENV['JWT_AUDIENCE'] ?? null) {
            $config['audience'] = $audience;
        }

        return new JwtConfig($config);
    }

    /**
     * Get severity color
     */
    private function getSeverityColor(string $severity): string
    {
        return match ($severity) {
            'critical' => 'error',
            'error' => 'error',
            'warning' => 'warn',
            'info' => 'info',
            default => 'comment',
        };
    }

    /**
     * Format duration in seconds to human readable format
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }
        
        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return "{$minutes}m";
        }
        
        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            return "{$hours}h";
        }
        
        $days = floor($seconds / 86400);
        return "{$days}d";
    }

    /**
     * Check if running in HTTPS environment
     */
    private function isHttpsEnvironment(): bool
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }
}