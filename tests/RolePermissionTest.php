<?php

declare(strict_types=1);

/**
 * Basic Role Permission System Test
 * 
 * This is a simple test to verify the role-permission system works correctly.
 * Run this after setting up the database and running migrations.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Support\Env;

// Load environment
Env::loadIfNeeded();

// Create database connection
$config = [
    'driver' => Env::get('DB_CONNECTION', 'mysql'),
    'host' => Env::get('DB_HOST', 'localhost'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'database' => Env::get('DB_DATABASE', ''),
    'username' => Env::get('DB_USERNAME', ''),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
];

try {
    $connection = new Connection($config);
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 1: Check if tables exist
echo "\n=== Testing Database Schema ===\n";

$tables = ['roles', 'permissions', 'role_permissions', 'user_roles'];
foreach ($tables as $table) {
    if ($connection->tableExists($table)) {
        echo "✓ Table '{$table}' exists\n";
    } else {
        echo "✗ Table '{$table}' missing\n";
    }
}

// Test 2: Check if default data exists
echo "\n=== Testing Default Data ===\n";

// Check roles
$roles = $connection->select('SELECT name FROM roles ORDER BY name');
$expectedRoles = ['administrator', 'author', 'editor', 'member'];
$actualRoles = array_column($roles, 'name');

foreach ($expectedRoles as $role) {
    if (in_array($role, $actualRoles)) {
        echo "✓ Role '{$role}' exists\n";
    } else {
        echo "✗ Role '{$role}' missing\n";
    }
}

// Check permissions
$permissionCount = $connection->selectOne('SELECT COUNT(*) as count FROM permissions')['count'];
echo "✓ Found {$permissionCount} permissions\n";

// Check role-permission mappings
$adminPermissions = $connection->selectOne(
    'SELECT COUNT(*) as count FROM role_permissions rp 
     JOIN roles r ON rp.role_id = r.id 
     WHERE r.name = ?',
    ['administrator']
)['count'];
echo "✓ Administrator has {$adminPermissions} permissions\n";

// Test 3: Test User Model (if exists)
echo "\n=== Testing User Model ===\n";

if (class_exists('\App\Models\User')) {
    echo "✓ User model exists\n";
    
    // Try to create a test user
    try {
        $userClass = '\App\Models\User';
        
        // Check if we can instantiate the model
        $reflection = new ReflectionClass($userClass);
        if ($reflection->hasMethod('hasRole') && $reflection->hasMethod('can')) {
            echo "✓ User model has role/permission methods\n";
        } else {
            echo "✗ User model missing role/permission methods\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Error testing User model: " . $e->getMessage() . "\n";
    }
} else {
    echo "! User model not found (this is optional)\n";
}

// Test 4: Test Role Model
echo "\n=== Testing Role Model ===\n";

if (class_exists('\App\Models\Role')) {
    echo "✓ Role model exists\n";
    
    try {
        $roleClass = '\App\Models\Role';
        
        // Try to find administrator role
        $admin = $roleClass::where('name', 'administrator')->first();
        if ($admin) {
            echo "✓ Can load administrator role via model\n";
            
            // Test permissions relationship
            if (method_exists($admin, 'permissions')) {
                $permissions = $admin->permissions();
                echo "✓ Role model has permissions relationship\n";
            }
        }
        
    } catch (Exception $e) {
        echo "✗ Error testing Role model: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ Role model not found\n";
}

// Test 5: Test Permission Model
echo "\n=== Testing Permission Model ===\n";

if (class_exists('\App\Models\Permission')) {
    echo "✓ Permission model exists\n";
    
    try {
        $permissionClass = '\App\Models\Permission';
        
        // Try to find a permission
        $permission = $permissionClass::where('name', 'manage-users')->first();
        if ($permission) {
            echo "✓ Can load manage-users permission via model\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Error testing Permission model: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ Permission model not found\n";
}

// Test 6: Test Configuration
echo "\n=== Testing Configuration ===\n";

$configPath = __DIR__ . '/../config/permissions.php';
if (file_exists($configPath)) {
    echo "✓ Permissions configuration file exists\n";
    
    $config = require $configPath;
    if (is_array($config)) {
        echo "✓ Configuration file returns array\n";
        
        $expectedKeys = ['cache_duration', 'super_admin_role', 'default_role', 'categories'];
        foreach ($expectedKeys as $key) {
            if (isset($config[$key])) {
                echo "✓ Configuration has '{$key}'\n";
            } else {
                echo "✗ Configuration missing '{$key}'\n";
            }
        }
    }
} else {
    echo "✗ Permissions configuration file not found\n";
}

echo "\n=== Test Complete ===\n";
echo "If all tests pass, your role-permission system is ready to use!\n";
echo "\nNext steps:\n";
echo "1. Run migrations: php console migrate:run\n";
echo "2. Create users and assign roles using the role command\n";
echo "3. Use middleware in your routes: ->middleware('role:admin')\n";
echo "4. Use helper functions in your code: hasRole('admin')\n";