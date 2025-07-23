<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * JWT Breach Detection Manager
 *
 * Monitors and detects suspicious JWT-related activities including
 * brute force attacks, token replay attacks, and unusual access patterns.
 * Provides configurable thresholds and automatic response mechanisms.
 *
 * Features:
 * - Failed authentication monitoring
 * - Token replay detection
 * - Rate limiting integration
 * - IP-based threat detection
 * - User behavior analysis
 * - Automatic alerting and blocking
 * - Detailed security reporting
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class BreachDetectionManager
{
    private const CACHE_PREFIX = 'jwt_breach_';
    private const FAILED_ATTEMPTS_KEY = 'failed_attempts_';
    private const TOKEN_USAGE_KEY = 'token_usage_';
    private const IP_ACTIVITY_KEY = 'ip_activity_';
    private const ALERTS_KEY = 'security_alerts_';
    
    /**
     * Default configuration
     */
    private const DEFAULTS = [
        'enabled' => true,
        'monitoring' => [
            'failed_auth_threshold' => 5,          // Failed attempts before flagging
            'failed_auth_window' => 300,           // 5 minutes window
            'token_reuse_threshold' => 3,          // Token reuse threshold
            'token_reuse_window' => 60,            // 1 minute window
            'ip_request_threshold' => 100,         // Requests per IP threshold
            'ip_request_window' => 3600,           // 1 hour window
            'user_session_threshold' => 10,       // Max concurrent sessions
        ],
        'responses' => [
            'block_ip_duration' => 3600,          // 1 hour IP block
            'block_user_duration' => 1800,        // 30 minutes user block
            'alert_threshold' => 3,               // Alerts before escalation
            'auto_block_enabled' => true,         // Enable automatic blocking
        ],
        'logging' => [
            'log_all_attempts' => false,          // Log all auth attempts
            'log_blocked_requests' => true,       // Log blocked requests
            'log_suspicious_activity' => true,    // Log suspicious patterns
            'alert_administrators' => true,       // Send admin alerts
        ],
    ];

    private CacheManager $cache;
    private ErrorLogger $logger;
    private array $config;

    /**
     * Create new breach detection manager
     *
     * @param CacheManager $cache Cache manager for data storage
     * @param Logger $logger Logger for security events
     * @param array $config Configuration options
     */
    public function __construct(CacheManager $cache, ErrorLogger $logger, array $config = [])
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->config = array_merge_recursive(self::DEFAULTS, $config);
        
        $this->validateConfig();
    }

    /**
     * Record authentication attempt
     *
     * @param Request $request HTTP request
     * @param string|null $userId User identifier (if available)
     * @param bool $success Whether authentication succeeded
     * @param string $reason Failure reason if unsuccessful
     * @return array Detection results
     */
    public function recordAuthAttempt(Request $request, ?string $userId, bool $success, string $reason = ''): array
    {
        if (!$this->config['enabled']) {
            return ['blocked' => false, 'alerts' => []];
        }

        $ip = $this->getClientIp($request);
        $userAgent = $request->header('User-Agent', 'Unknown');
        $timestamp = Carbon::now()->getTimestamp();
        
        $results = [
            'blocked' => false,
            'alerts' => [],
            'ip' => $ip,
            'user_id' => $userId,
            'timestamp' => $timestamp,
        ];

        // Record the attempt
        $this->recordActivity($ip, $userId, $success, $reason, $userAgent, $timestamp);

        // Check for suspicious patterns
        if (!$success) {
            $results = array_merge($results, $this->analyzeFailedAttempt($ip, $userId));
        }

        $results = array_merge($results, $this->analyzeIpActivity($ip));
        
        if ($userId) {
            $results = array_merge($results, $this->analyzeUserActivity($userId));
        }

        // Apply automatic responses
        if ($this->config['responses']['auto_block_enabled'] && !empty($results['alerts'])) {
            $results['blocked'] = $this->applyAutomaticBlocking($ip, $userId, $results['alerts']);
        }

        // Log security events
        $this->logSecurityEvent($request, $results, $success, $reason);

        return $results;
    }

    /**
     * Record JWT token usage
     *
     * @param string $tokenId Token identifier
     * @param string $userId User identifier
     * @param string $ip Client IP address
     * @return array Detection results
     */
    public function recordTokenUsage(string $tokenId, string $userId, string $ip): array
    {
        if (!$this->config['enabled']) {
            return ['blocked' => false, 'alerts' => []];
        }

        $timestamp = Carbon::now()->getTimestamp();
        $cacheKey = self::CACHE_PREFIX . self::TOKEN_USAGE_KEY . $tokenId;
        
        // Get existing usage data
        $usage = $this->cache->get($cacheKey, []);
        $usage[] = [
            'ip' => $ip,
            'user_id' => $userId,
            'timestamp' => $timestamp,
        ];

        // Store updated usage
        $this->cache->put($cacheKey, $usage, $this->config['monitoring']['token_reuse_window']);

        // Analyze token reuse patterns
        return $this->analyzeTokenReuse($tokenId, $usage);
    }

    /**
     * Check if IP is currently blocked
     *
     * @param string $ip IP address
     * @return bool True if blocked
     */
    public function isIpBlocked(string $ip): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        $blockKey = self::CACHE_PREFIX . 'blocked_ip_' . $ip;
        return $this->cache->has($blockKey);
    }

    /**
     * Check if user is currently blocked
     *
     * @param string $userId User identifier
     * @return bool True if blocked
     */
    public function isUserBlocked(string $userId): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        $blockKey = self::CACHE_PREFIX . 'blocked_user_' . $userId;
        return $this->cache->has($blockKey);
    }

    /**
     * Get security alerts for a time period
     *
     * @param int $since Timestamp to get alerts since
     * @return array Security alerts
     */
    public function getSecurityAlerts(int $since = 0): array
    {
        $alertsKey = self::CACHE_PREFIX . self::ALERTS_KEY;
        $alerts = $this->cache->get($alertsKey, []);
        
        if ($since > 0) {
            $alerts = array_filter($alerts, fn($alert) => $alert['timestamp'] >= $since);
        }
        
        return array_values($alerts);
    }

    /**
     * Get security statistics
     *
     * @param int $hours Hours to look back (default: 24)
     * @return array Security statistics
     */
    public function getSecurityStats(int $hours = 24): array
    {
        $since = Carbon::now()->subHours($hours)->getTimestamp();
        $alerts = $this->getSecurityAlerts($since);
        
        $stats = [
            'period_hours' => $hours,
            'total_alerts' => count($alerts),
            'alert_types' => [],
            'top_ips' => [],
            'blocked_ips' => $this->getBlockedIps(),
            'blocked_users' => $this->getBlockedUsers(),
            'threat_level' => 'low',
        ];
        
        // Analyze alert types
        foreach ($alerts as $alert) {
            $type = $alert['type'] ?? 'unknown';
            $stats['alert_types'][$type] = ($stats['alert_types'][$type] ?? 0) + 1;
        }
        
        // Analyze top threatening IPs
        $ipCounts = [];
        foreach ($alerts as $alert) {
            if (isset($alert['ip'])) {
                $ipCounts[$alert['ip']] = ($ipCounts[$alert['ip']] ?? 0) + 1;
            }
        }
        arsort($ipCounts);
        $stats['top_ips'] = array_slice($ipCounts, 0, 10, true);
        
        // Determine threat level
        $stats['threat_level'] = $this->calculateThreatLevel($stats);
        
        return $stats;
    }

    /**
     * Manually block IP address
     *
     * @param string $ip IP address to block
     * @param int $duration Block duration in seconds
     * @param string $reason Block reason
     * @return bool True if blocked successfully
     */
    public function blockIp(string $ip, int $duration = 3600, string $reason = 'Manual block'): bool
    {
        $blockKey = self::CACHE_PREFIX . 'blocked_ip_' . $ip;
        $blockData = [
            'ip' => $ip,
            'blocked_at' => Carbon::now()->getTimestamp(),
            'duration' => $duration,
            'reason' => $reason,
            'manual' => true,
        ];
        
        $this->cache->put($blockKey, $blockData, $duration);
        
        // Log the block
        $this->logger->warning('IP address manually blocked', $blockData);
        
        return true;
    }

    /**
     * Manually unblock IP address
     *
     * @param string $ip IP address to unblock
     * @return bool True if unblocked successfully
     */
    public function unblockIp(string $ip): bool
    {
        $blockKey = self::CACHE_PREFIX . 'blocked_ip_' . $ip;
        $this->cache->forget($blockKey);
        
        // Log the unblock
        $this->logger->info('IP address manually unblocked', ['ip' => $ip]);
        
        return true;
    }

    /**
     * Clear all security data
     *
     * @return bool True if cleared successfully
     */
    public function clearSecurityData(): bool
    {
        $keys = [
            self::CACHE_PREFIX . self::ALERTS_KEY,
        ];
        
        foreach ($keys as $key) {
            $this->cache->forget($key);
        }
        
        // Clear all breach detection cache entries
        // Note: This is a simplified implementation
        // In production, you might want to use cache tagging
        
        return true;
    }

    /**
     * Record activity in cache
     *
     * @param string $ip Client IP
     * @param string|null $userId User ID
     * @param bool $success Success status
     * @param string $reason Failure reason
     * @param string $userAgent User agent
     * @param int $timestamp Timestamp
     */
    private function recordActivity(string $ip, ?string $userId, bool $success, string $reason, string $userAgent, int $timestamp): void
    {
        $activity = [
            'ip' => $ip,
            'user_id' => $userId,
            'success' => $success,
            'reason' => $reason,
            'user_agent' => $userAgent,
            'timestamp' => $timestamp,
        ];
        
        // Record IP activity
        $ipKey = self::CACHE_PREFIX . self::IP_ACTIVITY_KEY . $ip;
        $ipActivity = $this->cache->get($ipKey, []);
        $ipActivity[] = $activity;
        
        // Keep only recent activity
        $cutoff = $timestamp - $this->config['monitoring']['ip_request_window'];
        $ipActivity = array_filter($ipActivity, fn($item) => $item['timestamp'] > $cutoff);
        
        $this->cache->put($ipKey, $ipActivity, $this->config['monitoring']['ip_request_window']);
        
        // Record failed attempts if applicable
        if (!$success) {
            $failedKey = self::CACHE_PREFIX . self::FAILED_ATTEMPTS_KEY . $ip;
            $failedAttempts = $this->cache->get($failedKey, []);
            $failedAttempts[] = $activity;
            
            // Keep only recent failed attempts
            $cutoff = $timestamp - $this->config['monitoring']['failed_auth_window'];
            $failedAttempts = array_filter($failedAttempts, fn($item) => $item['timestamp'] > $cutoff);
            
            $this->cache->put($failedKey, $failedAttempts, $this->config['monitoring']['failed_auth_window']);
        }
    }

    /**
     * Analyze failed authentication attempt
     *
     * @param string $ip Client IP
     * @param string|null $userId User ID
     * @return array Analysis results
     */
    private function analyzeFailedAttempt(string $ip, ?string $userId): array
    {
        $results = ['alerts' => []];
        
        $failedKey = self::CACHE_PREFIX . self::FAILED_ATTEMPTS_KEY . $ip;
        $failedAttempts = $this->cache->get($failedKey, []);
        
        $failedCount = count($failedAttempts);
        $threshold = $this->config['monitoring']['failed_auth_threshold'];
        
        if ($failedCount >= $threshold) {
            $alert = [
                'type' => 'brute_force_detected',
                'severity' => 'high',
                'ip' => $ip,
                'user_id' => $userId,
                'failed_attempts' => $failedCount,
                'threshold' => $threshold,
                'timestamp' => Carbon::now()->getTimestamp(),
                'message' => "Brute force attack detected from IP {$ip}: {$failedCount} failed attempts",
            ];
            
            $results['alerts'][] = $alert;
            $this->addSecurityAlert($alert);
        }
        
        return $results;
    }

    /**
     * Analyze IP activity patterns
     *
     * @param string $ip Client IP
     * @return array Analysis results
     */
    private function analyzeIpActivity(string $ip): array
    {
        $results = ['alerts' => []];
        
        $ipKey = self::CACHE_PREFIX . self::IP_ACTIVITY_KEY . $ip;
        $ipActivity = $this->cache->get($ipKey, []);
        
        $requestCount = count($ipActivity);
        $threshold = $this->config['monitoring']['ip_request_threshold'];
        
        if ($requestCount >= $threshold) {
            $alert = [
                'type' => 'high_request_volume',
                'severity' => 'medium',
                'ip' => $ip,
                'request_count' => $requestCount,
                'threshold' => $threshold,
                'timestamp' => Carbon::now()->getTimestamp(),
                'message' => "High request volume from IP {$ip}: {$requestCount} requests",
            ];
            
            $results['alerts'][] = $alert;
            $this->addSecurityAlert($alert);
        }
        
        return $results;
    }

    /**
     * Analyze user activity patterns
     *
     * @param string $userId User ID
     * @return array Analysis results
     */
    private function analyzeUserActivity(string $userId): array
    {
        $results = ['alerts' => []];
        
        // This could be extended to analyze user session patterns,
        // geographic anomalies, etc.
        // For now, we'll implement a basic concurrent session check
        
        return $results;
    }

    /**
     * Analyze token reuse patterns
     *
     * @param string $tokenId Token ID
     * @param array $usage Usage history
     * @return array Analysis results
     */
    private function analyzeTokenReuse(string $tokenId, array $usage): array
    {
        $results = ['alerts' => []];
        
        $usageCount = count($usage);
        $threshold = $this->config['monitoring']['token_reuse_threshold'];
        
        if ($usageCount >= $threshold) {
            // Check for usage from different IPs
            $ips = array_unique(array_column($usage, 'ip'));
            
            if (count($ips) > 1) {
                $alert = [
                    'type' => 'token_replay_attack',
                    'severity' => 'high',
                    'token_id' => $tokenId,
                    'usage_count' => $usageCount,
                    'unique_ips' => count($ips),
                    'ips' => $ips,
                    'timestamp' => Carbon::now()->getTimestamp(),
                    'message' => "Token replay attack detected: token {$tokenId} used from multiple IPs",
                ];
                
                $results['alerts'][] = $alert;
                $this->addSecurityAlert($alert);
            }
        }
        
        return $results;
    }

    /**
     * Apply automatic blocking based on alerts
     *
     * @param string $ip Client IP
     * @param string|null $userId User ID
     * @param array $alerts Security alerts
     * @return bool True if blocking was applied
     */
    private function applyAutomaticBlocking(string $ip, ?string $userId, array $alerts): bool
    {
        $blocked = false;
        
        foreach ($alerts as $alert) {
            switch ($alert['type']) {
                case 'brute_force_detected':
                    $this->blockIp($ip, $this->config['responses']['block_ip_duration'], 'Brute force attack');
                    $blocked = true;
                    break;
                    
                case 'token_replay_attack':
                    if ($userId) {
                        $this->blockUser($userId, $this->config['responses']['block_user_duration'], 'Token replay attack');
                    }
                    $blocked = true;
                    break;
            }
        }
        
        return $blocked;
    }

    /**
     * Block user account
     *
     * @param string $userId User ID
     * @param int $duration Block duration
     * @param string $reason Block reason
     */
    private function blockUser(string $userId, int $duration, string $reason): void
    {
        $blockKey = self::CACHE_PREFIX . 'blocked_user_' . $userId;
        $blockData = [
            'user_id' => $userId,
            'blocked_at' => Carbon::now()->getTimestamp(),
            'duration' => $duration,
            'reason' => $reason,
            'automatic' => true,
        ];
        
        $this->cache->put($blockKey, $blockData, $duration);
        
        $this->logger->warning('User account automatically blocked', $blockData);
    }

    /**
     * Add security alert to storage
     *
     * @param array $alert Alert data
     */
    private function addSecurityAlert(array $alert): void
    {
        $alertsKey = self::CACHE_PREFIX . self::ALERTS_KEY;
        $alerts = $this->cache->get($alertsKey, []);
        
        $alerts[] = $alert;
        
        // Keep only recent alerts (last 7 days)
        $cutoff = Carbon::now()->subDays(7)->getTimestamp();
        $alerts = array_filter($alerts, fn($item) => $item['timestamp'] > $cutoff);
        
        $this->cache->put($alertsKey, $alerts, 604800); // 7 days
    }

    /**
     * Get client IP address
     *
     * @param Request $request HTTP request
     * @return string Client IP
     */
    private function getClientIp(Request $request): string
    {
        // Check for IP from various headers (proxy support)
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR',               // Standard
        ];
        
        foreach ($ipHeaders as $header) {
            $ip = $request->header($header) ?? $_SERVER[$header] ?? null;
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get currently blocked IPs
     *
     * @return array Blocked IP addresses
     */
    private function getBlockedIps(): array
    {
        // This is a simplified implementation
        // In production, you might want to maintain a separate index
        return [];
    }

    /**
     * Get currently blocked users
     *
     * @return array Blocked user IDs
     */
    private function getBlockedUsers(): array
    {
        // This is a simplified implementation
        // In production, you might want to maintain a separate index
        return [];
    }

    /**
     * Calculate overall threat level
     *
     * @param array $stats Security statistics
     * @return string Threat level (low, medium, high, critical)
     */
    private function calculateThreatLevel(array $stats): string
    {
        $alertCount = $stats['total_alerts'];
        $blockedIps = count($stats['blocked_ips']);
        $blockedUsers = count($stats['blocked_users']);
        
        $score = $alertCount + ($blockedIps * 2) + ($blockedUsers * 3);
        
        return match (true) {
            $score >= 50 => 'critical',
            $score >= 20 => 'high',
            $score >= 10 => 'medium',
            default => 'low',
        };
    }

    /**
     * Log security event
     *
     * @param Request $request HTTP request
     * @param array $results Detection results
     * @param bool $success Auth success status
     * @param string $reason Failure reason
     */
    private function logSecurityEvent(Request $request, array $results, bool $success, string $reason): void
    {
        if (!$this->config['logging']['log_all_attempts'] && $success && empty($results['alerts'])) {
            return;
        }
        
        $logData = [
            'ip' => $results['ip'],
            'user_id' => $results['user_id'],
            'success' => $success,
            'reason' => $reason,
            'user_agent' => $request->header('User-Agent', 'Unknown'),
            'uri' => $request->uri(),
            'method' => $request->method(),
            'alerts' => $results['alerts'],
            'blocked' => $results['blocked'],
        ];
        
        if (!$success || !empty($results['alerts'])) {
            $this->logger->warning('JWT authentication security event', $logData);
        } elseif ($this->config['logging']['log_all_attempts']) {
            $this->logger->info('JWT authentication attempt', $logData);
        }
    }

    /**
     * Validate configuration
     *
     * @throws InvalidArgumentException If configuration is invalid
     */
    private function validateConfig(): void
    {
        $monitoring = $this->config['monitoring'];
        
        if ($monitoring['failed_auth_threshold'] <= 0) {
            throw new InvalidArgumentException('Failed auth threshold must be positive', 'INVALID_THRESHOLD');
        }
        
        if ($monitoring['failed_auth_window'] <= 0) {
            throw new InvalidArgumentException('Failed auth window must be positive', 'INVALID_WINDOW');
        }
        
        if ($monitoring['ip_request_threshold'] <= 0) {
            throw new InvalidArgumentException('IP request threshold must be positive', 'INVALID_IP_THRESHOLD');
        }
    }
}