<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\JwtCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Auth\Jwt\BreachDetectionManager;
use LengthOfRope\TreeHouse\Auth\Jwt\KeyRotationManager;
use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;

/**
 * JWT Security Command
 *
 * Displays JWT security status and monitoring information.
 * Shows breach detection stats, key rotation status, and threat levels.
 *
 * @package LengthOfRope\TreeHouse\Console\Commands\JwtCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtSecurityCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('jwt:security')
            ->setDescription('Display JWT security status and monitoring information')
            ->setHelp('This command shows JWT security statistics, threat levels, and key rotation status.')
            ->addOption('hours', 'h', InputOption::VALUE_OPTIONAL, 'Hours to look back for statistics', '24')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (json|table|plain)', 'table');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $hours = (int) $input->getOption('hours');
            $format = $input->getOption('format');
            
            $cache = new CacheManager();
            $logger = new ErrorLogger();
            $breachDetection = new BreachDetectionManager($cache, $logger);
            $keyManager = new KeyRotationManager($cache);
            
            $stats = $breachDetection->getSecurityStats($hours);
            $keyStats = $keyManager->getRotationStats();
            
            $this->outputSecurityStatus($output, $stats, $keyStats, $format, $hours);

            return 0;
        } catch (\Exception $e) {
            $this->error($output, 'Security status check failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Output security status
     */
    private function outputSecurityStatus(OutputInterface $output, array $securityStats, array $keyStats, string $format, int $hours): void
    {
        switch ($format) {
            case 'json':
                $output->writeln(json_encode([
                    'period_hours' => $hours,
                    'security' => $securityStats,
                    'keys' => $keyStats,
                    'timestamp' => time(),
                ], JSON_PRETTY_PRINT));
                break;
                
            case 'table':
                $this->displaySecurityTable($output, $securityStats, $keyStats, $hours);
                break;
                
            default:
                $this->displaySecurityPlain($output, $securityStats, $keyStats);
                break;
        }
    }

    /**
     * Display security information in table format
     */
    private function displaySecurityTable(OutputInterface $output, array $securityStats, array $keyStats, int $hours): void
    {
        $this->info($output, "JWT Security Status (Last {$hours} hours)");
        $output->writeln('');
        
        // Threat Level
        $threatLevel = strtoupper($securityStats['threat_level']);
        $threatColor = $this->getThreatLevelColor($securityStats['threat_level']);
        $this->$threatColor($output, "Threat Level: {$threatLevel}");
        $output->writeln('');
        
        // Security Statistics
        $this->comment($output, 'Security Statistics:');
        $output->writeln("  Total Alerts: {$securityStats['total_alerts']}");
        $output->writeln("  Blocked IPs: " . count($securityStats['blocked_ips']));
        $output->writeln("  Blocked Users: " . count($securityStats['blocked_users']));
        
        if (!empty($securityStats['alert_types'])) {
            $output->writeln('  Alert Types:');
            foreach ($securityStats['alert_types'] as $type => $count) {
                $output->writeln("    {$type}: {$count}");
            }
        }
        
        if (!empty($securityStats['top_ips'])) {
            $output->writeln('  Top Threatening IPs:');
            $count = 0;
            foreach ($securityStats['top_ips'] as $ip => $alerts) {
                if (++$count > 5) break; // Show top 5
                $output->writeln("    {$ip}: {$alerts} alerts");
            }
        }
        
        $output->writeln('');
        
        // Key Rotation Status
        $this->comment($output, 'Key Rotation Status:');
        $output->writeln("  Current Key ID: " . ($keyStats['current_key_id'] ?? 'None'));
        
        if (isset($keyStats['current_key_age'])) {
            $ageFormatted = $this->formatDuration($keyStats['current_key_age']);
            $output->writeln("  Key Age: {$ageFormatted}");
        }
        
        if (isset($keyStats['time_until_rotation'])) {
            $rotationFormatted = $this->formatDuration($keyStats['time_until_rotation']);
            $output->writeln("  Next Rotation: {$rotationFormatted}");
        }
        
        $output->writeln("  Valid Keys: {$keyStats['valid_keys_count']}");
        $output->writeln("  Total Rotations: {$keyStats['total_rotations']}");
        
        // Recommendations
        $output->writeln('');
        $this->displayRecommendations($output, $securityStats);
    }

    /**
     * Display security information in plain format
     */
    private function displaySecurityPlain(OutputInterface $output, array $securityStats, array $keyStats): void
    {
        $output->writeln("alerts={$securityStats['total_alerts']}");
        $output->writeln("threat_level={$securityStats['threat_level']}");
        $output->writeln("blocked_ips=" . count($securityStats['blocked_ips']));
        $output->writeln("blocked_users=" . count($securityStats['blocked_users']));
        $output->writeln("current_key=" . ($keyStats['current_key_id'] ?? 'none'));
        $output->writeln("valid_keys={$keyStats['valid_keys_count']}");
    }

    /**
     * Display security recommendations
     */
    private function displayRecommendations(OutputInterface $output, array $securityStats): void
    {
        $recommendations = [];
        
        if ($securityStats['threat_level'] === 'high' || $securityStats['threat_level'] === 'critical') {
            $recommendations[] = 'Consider increasing security monitoring frequency';
            $recommendations[] = 'Review and potentially tighten authentication thresholds';
        }
        
        if ($securityStats['total_alerts'] > 10) {
            $recommendations[] = 'High number of security alerts detected - investigate patterns';
        }
        
        if (!empty($securityStats['blocked_ips'])) {
            $recommendations[] = 'Review blocked IPs and consider permanent blacklisting for repeat offenders';
        }
        
        if (!empty($recommendations)) {
            $this->comment($output, 'Recommendations:');
            foreach ($recommendations as $recommendation) {
                $this->warn($output, "  • {$recommendation}");
            }
        } else {
            $this->success($output, 'No security recommendations at this time.');
        }
    }

    /**
     * Get threat level color
     */
    private function getThreatLevelColor(string $level): string
    {
        return match ($level) {
            'critical' => 'error',
            'high' => 'error',
            'medium' => 'warn',
            'low' => 'success',
            default => 'info',
        };
    }

    /**
     * Format duration in seconds to human readable format
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        }
        
        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return "{$minutes} minutes";
        }
        
        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        return "{$days}d {$hours}h";
    }
}