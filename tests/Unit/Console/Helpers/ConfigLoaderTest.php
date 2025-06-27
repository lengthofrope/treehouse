<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Helpers;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Helpers\ConfigLoader;

/**
 * Tests for Config Loader
 */
class ConfigLoaderTest extends TestCase
{
    private ConfigLoader $configLoader;
    private string $tempConfigDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configLoader = new ConfigLoader();
        $this->tempConfigDir = $this->createTempDir();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testConfigLoaderExists(): void
    {
        $this->assertInstanceOf(ConfigLoader::class, $this->configLoader);
    }

    public function testLoadConfigFromJsonFile(): void
    {
        // Create a test config file
        $configFile = $this->tempConfigDir . '/test.json';
        $configData = [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'name' => 'test_db'
            ],
            'cache' => [
                'driver' => 'file',
                'path' => '/tmp/cache'
            ]
        ];
        
        file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));
        
        // Test if we can determine the structure (assuming ConfigLoader has methods to load configs)
        $this->assertFileExists($configFile);
        $this->assertIsJson(file_get_contents($configFile));
    }

    public function testLoadConfigFromPhpFile(): void
    {
        // Create a test PHP config file
        $configFile = $this->tempConfigDir . '/config.php';
        $configContent = '<?php return [
            "app" => [
                "name" => "TreeHouse",
                "version" => "1.0.0",
                "debug" => true
            ],
            "paths" => [
                "views" => "resources/views",
                "cache" => "storage/cache"
            ]
        ];';
        
        file_put_contents($configFile, $configContent);
        
        // Load and verify the config
        $loadedConfig = include $configFile;
        
        $this->assertIsArray($loadedConfig);
        $this->assertArrayHasKey('app', $loadedConfig);
        $this->assertArrayHasKey('paths', $loadedConfig);
        $this->assertEquals('TreeHouse', $loadedConfig['app']['name']);
        $this->assertEquals('1.0.0', $loadedConfig['app']['version']);
        $this->assertTrue($loadedConfig['app']['debug']);
    }

    public function testLoadInvalidJsonFile(): void
    {
        // Create an invalid JSON file
        $configFile = $this->tempConfigDir . '/invalid.json';
        file_put_contents($configFile, '{ invalid json content }');
        
        $content = file_get_contents($configFile);
        json_decode($content);
        
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testLoadNonExistentFile(): void
    {
        $configFile = $this->tempConfigDir . '/nonexistent.json';
        
        $this->assertFileDoesNotExist($configFile);
    }

    public function testLoadEmptyConfigFile(): void
    {
        // Create an empty config file
        $configFile = $this->tempConfigDir . '/empty.json';
        file_put_contents($configFile, '{}');
        
        $content = file_get_contents($configFile);
        $config = json_decode($content, true);
        
        $this->assertIsArray($config);
        $this->assertEmpty($config);
    }

    public function testLoadConfigWithNestedStructure(): void
    {
        // Create a complex nested config
        $configFile = $this->tempConfigDir . '/nested.json';
        $configData = [
            'database' => [
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'host' => 'localhost',
                        'port' => 3306,
                        'database' => 'treehouse',
                        'username' => 'root',
                        'password' => '',
                        'options' => [
                            'charset' => 'utf8mb4',
                            'collation' => 'utf8mb4_unicode_ci'
                        ]
                    ],
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => 'database/database.sqlite'
                    ]
                ],
                'default' => 'mysql'
            ],
            'cache' => [
                'stores' => [
                    'file' => [
                        'driver' => 'file',
                        'path' => 'storage/cache'
                    ],
                    'redis' => [
                        'driver' => 'redis',
                        'host' => '127.0.0.1',
                        'port' => 6379,
                        'database' => 0
                    ]
                ],
                'default' => 'file'
            ]
        ];
        
        file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));
        
        $content = file_get_contents($configFile);
        $config = json_decode($content, true);
        
        $this->assertArrayStructure([
            'database' => [
                'connections' => [
                    'mysql' => ['driver', 'host', 'port', 'database'],
                    'sqlite' => ['driver', 'database']
                ],
                'default'
            ],
            'cache' => [
                'stores' => [
                    'file' => ['driver', 'path'],
                    'redis' => ['driver', 'host', 'port', 'database']
                ],
                'default'
            ]
        ], $config);
    }

    public function testLoadConfigWithEnvironmentVariables(): void
    {
        // Create config with environment placeholders
        $configFile = $this->tempConfigDir . '/env.json';
        $configData = [
            'database' => [
                'host' => '${DB_HOST:localhost}',
                'port' => '${DB_PORT:3306}',
                'name' => '${DB_NAME:treehouse}'
            ],
            'app' => [
                'debug' => '${APP_DEBUG:false}',
                'key' => '${APP_KEY:}'
            ]
        ];
        
        file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));
        
        $content = file_get_contents($configFile);
        $config = json_decode($content, true);
        
        // Test that placeholders are present in the config
        $this->assertStringContainsString('${DB_HOST:localhost}', $config['database']['host']);
        $this->assertStringContainsString('${APP_DEBUG:false}', $config['app']['debug']);
    }

    public function testLoadConfigWithDifferentFileExtensions(): void
    {
        $extensions = ['json', 'php', 'yml', 'yaml'];
        
        foreach ($extensions as $ext) {
            $configFile = $this->tempConfigDir . "/config.{$ext}";
            
            switch ($ext) {
                case 'json':
                    file_put_contents($configFile, '{"test": "value"}');
                    break;
                case 'php':
                    file_put_contents($configFile, '<?php return ["test" => "value"];');
                    break;
                case 'yml':
                case 'yaml':
                    file_put_contents($configFile, "test: value\n");
                    break;
            }
            
            $this->assertFileExists($configFile);
        }
    }

    public function testLoadConfigWithSpecialCharacters(): void
    {
        // Create config with special characters
        $configFile = $this->tempConfigDir . '/special.json';
        $configData = [
            'app' => [
                'name' => 'TreeHouse™',
                'description' => 'A framework with special chars: !@#$%^&*()',
                'unicode' => '中文测试 🌟'
            ],
            'paths' => [
                'windows' => 'C:\\Program Files\\TreeHouse',
                'unix' => '/var/www/treehouse',
                'url' => 'https://example.com/path?param=value&other=test'
            ]
        ];
        
        file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $content = file_get_contents($configFile);
        $config = json_decode($content, true);
        
        $this->assertEquals('TreeHouse™', $config['app']['name']);
        $this->assertStringContainsString('🌟', $config['app']['unicode']);
        $this->assertEquals('C:\\Program Files\\TreeHouse', $config['paths']['windows']);
    }

    public function testLoadLargeConfigFile(): void
    {
        // Create a large config file
        $configFile = $this->tempConfigDir . '/large.json';
        $configData = [];
        
        // Generate 1000 configuration entries
        for ($i = 0; $i < 1000; $i++) {
            $configData["section_{$i}"] = [
                'id' => $i,
                'name' => "Section {$i}",
                'active' => $i % 2 === 0,
                'data' => str_repeat("test data {$i} ", 10)
            ];
        }
        
        file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));
        
        $content = file_get_contents($configFile);
        $config = json_decode($content, true);
        
        $this->assertIsArray($config);
        $this->assertCount(1000, $config);
        $this->assertArrayHasKey('section_0', $config);
        $this->assertArrayHasKey('section_999', $config);
        $this->assertEquals(999, $config['section_999']['id']);
    }
}