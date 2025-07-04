<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Context;

use Throwable;

/**
 * Collects environment and system context information
 */
class EnvironmentCollector implements ContextCollectorInterface
{
    private array $sensitiveEnvKeys = [
        'password',
        'secret',
        'key',
        'token',
        'api_key',
        'database_url',
        'db_password',
        'mail_password',
        'aws_secret',
        'private_key'
    ];

    /**
     * Collect environment context data
     */
    public function collect(Throwable $exception): array
    {
        return [
            'environment' => [
                'app' => $this->collectAppInfo(),
                'system' => $this->collectSystemInfo(),
                'php' => $this->collectPhpInfo(),
                'server' => $this->collectServerInfo(),
                'memory' => $this->collectMemoryInfo(),
                'disk' => $this->collectDiskInfo(),
                'network' => $this->collectNetworkInfo(),
                'timestamp' => time()
            ]
        ];
    }

    /**
     * Collect application information
     */
    private function collectAppInfo(): array
    {
        return [
            'name' => $_ENV['APP_NAME'] ?? 'TreeHouse Application',
            'env' => $_ENV['APP_ENV'] ?? 'production',
            'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'url' => $_ENV['APP_URL'] ?? '',
            'timezone' => $_ENV['APP_TIMEZONE'] ?? date_default_timezone_get(),
            'locale' => $_ENV['APP_LOCALE'] ?? 'en',
            'version' => $_ENV['APP_VERSION'] ?? 'unknown',
            'config' => $this->sanitizeEnvironmentVariables()
        ];
    }

    /**
     * Collect system information
     */
    private function collectSystemInfo(): array
    {
        return [
            'os' => [
                'name' => php_uname('s'),
                'version' => php_uname('r'),
                'architecture' => php_uname('m'),
                'hostname' => php_uname('n'),
                'full' => php_uname()
            ],
            'load_average' => $this->getLoadAverage(),
            'uptime' => $this->getSystemUptime(),
            'processes' => $this->getProcessCount(),
            'cpu_count' => $this->getCpuCount()
        ];
    }

    /**
     * Collect PHP information
     */
    private function collectPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'version_id' => PHP_VERSION_ID,
            'major_version' => PHP_MAJOR_VERSION,
            'minor_version' => PHP_MINOR_VERSION,
            'release_version' => PHP_RELEASE_VERSION,
            'sapi' => PHP_SAPI,
            'extensions' => $this->getLoadedExtensions(),
            'ini' => $this->getPhpIniSettings(),
            'opcache' => $this->getOpcacheInfo(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];
    }

    /**
     * Collect server information
     */
    private function collectServerInfo(): array
    {
        $server = [
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'time' => $_SERVER['REQUEST_TIME'] ?? time(),
            'time_float' => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)
        ];

        // Add web server specific info
        if (isset($_SERVER['SERVER_NAME'])) {
            $server['name'] = $_SERVER['SERVER_NAME'];
        }

        if (isset($_SERVER['SERVER_PORT'])) {
            $server['port'] = (int) $_SERVER['SERVER_PORT'];
        }

        if (isset($_SERVER['DOCUMENT_ROOT'])) {
            $server['document_root'] = $_SERVER['DOCUMENT_ROOT'];
        }

        return $server;
    }

    /**
     * Collect memory information
     */
    private function collectMemoryInfo(): array
    {
        return [
            'usage' => memory_get_usage(true),
            'usage_formatted' => $this->formatBytes(memory_get_usage(true)),
            'peak_usage' => memory_get_peak_usage(true),
            'peak_usage_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
            'limit_formatted' => ini_get('memory_limit'),
            'available' => $this->getAvailableMemory()
        ];
    }

    /**
     * Collect disk information
     */
    private function collectDiskInfo(): array
    {
        $disk = [];
        
        // Get disk space for current directory
        $path = getcwd() ?: '/';
        
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $free = disk_free_space($path);
            $total = disk_total_space($path);
            
            if ($free !== false && $total !== false) {
                $freeInt = (int) $free;
                $totalInt = (int) $total;
                $usedInt = $totalInt - $freeInt;
                
                $disk = [
                    'path' => $path,
                    'total' => $totalInt,
                    'total_formatted' => $this->formatBytes($totalInt),
                    'used' => $usedInt,
                    'used_formatted' => $this->formatBytes($usedInt),
                    'free' => $freeInt,
                    'free_formatted' => $this->formatBytes($freeInt),
                    'usage_percentage' => $totalInt > 0 ? round(($usedInt / $totalInt) * 100, 2) : 0
                ];
            }
        }

        return $disk;
    }

    /**
     * Collect network information
     */
    private function collectNetworkInfo(): array
    {
        return [
            'hostname' => gethostname() ?: 'unknown',
            'interfaces' => $this->getNetworkInterfaces(),
            'dns' => $this->getDnsServers()
        ];
    }

    /**
     * Get system load average
     */
    private function getLoadAverage(): ?array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load !== false) {
                return [
                    '1min' => $load[0] ?? null,
                    '5min' => $load[1] ?? null,
                    '15min' => $load[2] ?? null
                ];
            }
        }

        return null;
    }

    /**
     * Get system uptime (Linux/Unix only)
     */
    private function getSystemUptime(): ?int
    {
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            if ($uptime !== false) {
                return (int) floatval(explode(' ', trim($uptime))[0]);
            }
        }

        return null;
    }

    /**
     * Get process count (Linux/Unix only)
     */
    private function getProcessCount(): ?int
    {
        if (file_exists('/proc/loadavg')) {
            $loadavg = file_get_contents('/proc/loadavg');
            if ($loadavg !== false) {
                $parts = explode(' ', trim($loadavg));
                if (isset($parts[3]) && str_contains($parts[3], '/')) {
                    return (int) explode('/', $parts[3])[1];
                }
            }
        }

        return null;
    }

    /**
     * Get CPU count
     */
    private function getCpuCount(): ?int
    {
        if (file_exists('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            if ($cpuinfo !== false) {
                return substr_count($cpuinfo, 'processor');
            }
        }

        return null;
    }

    /**
     * Get loaded PHP extensions
     */
    private function getLoadedExtensions(): array
    {
        return get_loaded_extensions();
    }

    /**
     * Get important PHP INI settings
     */
    private function getPhpIniSettings(): array
    {
        $settings = [
            'error_reporting',
            'display_errors',
            'log_errors',
            'error_log',
            'max_input_time',
            'max_input_vars',
            'default_charset',
            'date.timezone'
        ];

        $ini = [];
        foreach ($settings as $setting) {
            $ini[$setting] = ini_get($setting);
        }

        return $ini;
    }

    /**
     * Get OPcache information
     */
    private function getOpcacheInfo(): ?array
    {
        if (!extension_loaded('Zend OPcache')) {
            return null;
        }

        $status = opcache_get_status(false);
        if ($status === false) {
            return null;
        }

        return [
            'enabled' => $status['opcache_enabled'] ?? false,
            'cache_full' => $status['cache_full'] ?? false,
            'restart_pending' => $status['restart_pending'] ?? false,
            'restart_in_progress' => $status['restart_in_progress'] ?? false,
            'memory_usage' => $status['memory_usage'] ?? [],
            'opcache_statistics' => $status['opcache_statistics'] ?? []
        ];
    }

    /**
     * Get available memory
     */
    private function getAvailableMemory(): ?int
    {
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        if ($limit === -1) {
            return null; // No limit
        }

        $used = memory_get_usage(true);
        return max(0, $limit - $used);
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return -1;
        }

        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get network interfaces (Linux/Unix only)
     */
    private function getNetworkInterfaces(): array
    {
        $interfaces = [];
        
        if (function_exists('exec')) {
            $output = [];
            exec('ip addr show 2>/dev/null || ifconfig 2>/dev/null', $output);
            
            if (!empty($output)) {
                $interfaces['available'] = true;
                $interfaces['count'] = count(array_filter($output, fn($line) => str_contains($line, 'inet ')));
            }
        }

        return $interfaces;
    }

    /**
     * Get DNS servers (Linux/Unix only)
     */
    private function getDnsServers(): array
    {
        $dns = [];
        
        if (file_exists('/etc/resolv.conf')) {
            $resolv = file_get_contents('/etc/resolv.conf');
            if ($resolv !== false) {
                preg_match_all('/nameserver\s+([^\s]+)/', $resolv, $matches);
                $dns = $matches[1] ?? [];
            }
        }

        return $dns;
    }

    /**
     * Sanitize environment variables
     */
    private function sanitizeEnvironmentVariables(): array
    {
        $env = [];
        
        foreach ($_ENV as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;
            
            foreach ($this->sensitiveEnvKeys as $sensitiveKey) {
                if (str_contains($lowerKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }
            
            $env[$key] = $isSensitive ? '[REDACTED]' : $value;
        }

        return $env;
    }

    /**
     * Get collector priority
     */
    public function getPriority(): int
    {
        return 50; // Medium priority for environment context
    }

    /**
     * Check if this collector should run
     */
    public function shouldCollect(Throwable $exception): bool
    {
        // Always collect environment context
        return true;
    }

    /**
     * Get collector name
     */
    public function getName(): string
    {
        return 'environment';
    }

    /**
     * Add sensitive environment key pattern
     */
    public function addSensitiveKey(string $key): void
    {
        $this->sensitiveEnvKeys[] = strtolower($key);
    }
}