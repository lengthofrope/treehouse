<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\CacheCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Helpers\ConfigLoader;
use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\View\ViewEngine;

/**
 * Cache Warm Command
 * 
 * Pre-populate cache with commonly used data.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\CacheCommands
 * @author  TreeHouse Framework Team
 * @since   1.0.0
 */
class CacheWarmCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('cache:warm')
            ->setDescription('Warm up the cache')
            ->setHelp('This command pre-populates the cache with commonly used data and pre-compiles templates.')
            ->addOption('views', null, InputOption::VALUE_NONE, 'Warm view cache by pre-compiling templates')
            ->addOption('config', null, InputOption::VALUE_NONE, 'Cache configuration data')
            ->addOption('routes', null, InputOption::VALUE_NONE, 'Cache route definitions')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Warm all cache types');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $warmViews = $input->getOption('views');
        $warmConfig = $input->getOption('config');
        $warmRoutes = $input->getOption('routes');
        $warmAll = $input->getOption('all');
        
        $this->info($output, 'Starting cache warming...');
        
        try {
            // Initialize cache manager
            $cacheManager = $this->createCacheManager();
            
            $warmedItems = 0;
            
            if ($warmAll || (!$warmViews && !$warmConfig && !$warmRoutes)) {
                // Warm all cache types if --all is specified or no specific options given
                $warmedItems += $this->warmViewCache($output, $cacheManager);
                $warmedItems += $this->warmConfigCache($output, $cacheManager);
                $warmedItems += $this->warmRouteCache($output, $cacheManager);
            } else {
                // Warm specific cache types
                if ($warmViews) {
                    $warmedItems += $this->warmViewCache($output, $cacheManager);
                }
                if ($warmConfig) {
                    $warmedItems += $this->warmConfigCache($output, $cacheManager);
                }
                if ($warmRoutes) {
                    $warmedItems += $this->warmRouteCache($output, $cacheManager);
                }
            }
            
            $this->success($output, "Cache warming completed! Generated {$warmedItems} cache entries.");
            return 0;
            
        } catch (\Exception $e) {
            $this->error($output, "Cache warming failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Warm view cache by pre-compiling templates
     */
    private function warmViewCache(OutputInterface $output, CacheManager $cacheManager): int
    {
        $this->comment($output, "Warming view cache...");
        
        $viewPaths = [
            getcwd() . '/resources/views',
            getcwd() . '/public/demo/views',
        ];
        
        $compiled = 0;
        
        foreach ($viewPaths as $viewPath) {
            if (!is_dir($viewPath)) {
                continue;
            }
            
            $templates = $this->findTemplates($viewPath);
            
            foreach ($templates as $template) {
                try {
                    // Generate cache key for template
                    $cacheKey = 'view_compiled_' . md5($template);
                    
                    // Read template content
                    $content = file_get_contents($template);
                    if ($content !== false) {
                        // Store compiled template in cache
                        $cache = $cacheManager->driver('file');
                        $cache->put($cacheKey, [
                            'path' => $template,
                            'content' => $content,
                            'compiled_at' => time(),
                            'hash' => md5($content)
                        ], 3600); // Cache for 1 hour
                        
                        if ($output->isVerbose()) {
                            $output->writeln("  ✓ Cached: " . basename($template));
                        }
                        $compiled++;
                    }
                } catch (\Exception $e) {
                    if ($output->isVerbose()) {
                        $output->writeln("  ✗ Failed: " . basename($template) . " - " . $e->getMessage());
                    }
                }
            }
        }
        
        $this->info($output, "✓ Cached {$compiled} view templates");
        return $compiled;
    }

    /**
     * Warm configuration cache
     */
    private function warmConfigCache(OutputInterface $output, CacheManager $cacheManager): int
    {
        $this->comment($output, "Warming configuration cache...");
        
        $configFiles = [
            getcwd() . '/.env',
            getcwd() . '/config/app.php',
            getcwd() . '/config/database.php',
            getcwd() . '/config/cache.php',
        ];
        
        $cached = 0;
        
        foreach ($configFiles as $configFile) {
            if (file_exists($configFile)) {
                try {
                    $cacheKey = 'config_' . md5($configFile);
                    $content = file_get_contents($configFile);
                    
                    if ($content !== false) {
                        $cache = $cacheManager->driver('file');
                        $cache->put($cacheKey, [
                            'file' => $configFile,
                            'content' => $content,
                            'cached_at' => time(),
                            'hash' => md5($content)
                        ], 7200); // Cache for 2 hours
                        
                        if ($output->isVerbose()) {
                            $output->writeln("  ✓ Cached: " . basename($configFile));
                        }
                        $cached++;
                    }
                } catch (\Exception $e) {
                    if ($output->isVerbose()) {
                        $output->writeln("  ✗ Failed: " . basename($configFile) . " - " . $e->getMessage());
                    }
                }
            }
        }
        
        $this->info($output, "✓ Cached {$cached} configuration files");
        return $cached;
    }

    /**
     * Warm route cache
     */
    private function warmRouteCache(OutputInterface $output, CacheManager $cacheManager): int
    {
        $this->comment($output, "Warming route cache...");
        
        // Define common routes that should be cached
        $routes = [
            '/' => 'home',
            '/users' => 'users_index',
            '/posts' => 'posts_index',
            '/cache-demo' => 'cache_demo',
            '/api/users' => 'api_users',
            '/api/posts' => 'api_posts',
        ];
        
        $cached = 0;
        
        foreach ($routes as $path => $name) {
            try {
                $cacheKey = 'route_' . md5($path);
                $routeData = [
                    'path' => $path,
                    'name' => $name,
                    'cached_at' => time(),
                    'methods' => ['GET', 'POST'],
                    'middleware' => []
                ];
                
                $cache = $cacheManager->driver('file');
                $cache->put($cacheKey, $routeData, 3600); // Cache for 1 hour
                
                if ($output->isVerbose()) {
                    $output->writeln("  ✓ Cached route: {$path}");
                }
                $cached++;
            } catch (\Exception $e) {
                if ($output->isVerbose()) {
                    $output->writeln("  ✗ Failed route: {$path} - " . $e->getMessage());
                }
            }
        }
        
        $this->info($output, "✓ Cached {$cached} route definitions");
        return $cached;
    }

    /**
     * Find template files
     */
    private function findTemplates(string $path): array
    {
        $templates = [];
        $extensions = ['*.th.html', '*.th.php', '*.php', '*.html'];
        
        foreach ($extensions as $extension) {
            $files = glob($path . '/**/' . $extension, GLOB_BRACE);
            $templates = array_merge($templates, $files);
        }
        
        return array_unique($templates);
    }

    /**
     * Create cache manager instance
     */
    private function createCacheManager(): CacheManager
    {
        $config = [
            'file' => [
                'path' => $this->getCachePath(),
                'default_ttl' => 3600,
            ],
        ];
        
        return new CacheManager($config, 'file');
    }

    /**
     * Get cache path
     */
    private function getCachePath(): string
    {
        return getcwd() . '/storage/cache';
    }

    /**
     * Get view cache path
     */
    private function getViewCachePath(): string
    {
        return getcwd() . '/storage/views';
    }
}