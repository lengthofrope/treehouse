<?php

declare(strict_types=1);

namespace Tests;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Database\Connection;

/**
 * Base test case for database-related tests
 * 
 * Provides database connection setup and table creation for testing models.
 * 
 * @package Tests
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Connection $connection;

    /**
     * Setup the test environment with database connection
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];
        
        $this->connection = new Connection($config);
        $this->connection->connect();
        
        // Set the connection for ActiveRecord
        ActiveRecord::setConnection($this->connection);
        
        // Create a mock application container for helper functions
        $mockApp = new class($this->connection) {
            private $connection;
            
            public function __construct($connection) {
                $this->connection = $connection;
            }
            
            public function make(string $service) {
                if ($service === 'db') {
                    return $this->connection;
                }
                if ($service === 'auth') {
                    // Return a mock auth manager that has basic methods
                    return new class {
                        public function guard($guard = null) {
                            return $this;
                        }
                        public function user() {
                            return null;
                        }
                        public function check() {
                            return false;
                        }
                        public function guest() {
                            return true;
                        }
                        public function id() {
                            return null;
                        }
                    };
                }
                if ($service === 'events') {
                    // Return a mock event dispatcher
                    return new class {
                        public function dispatch($event) { return $event; }
                        public function listen($event, $listener, $priority = 0) {}
                        public function until($event) { return null; }
                    };
                }
                throw new \Exception("Service {$service} not found");
            }
        };
        
        $GLOBALS['app'] = $mockApp;
        
        $this->createTestTables();
    }

    /**
     * Clean up after each test
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Clear all tables to ensure test isolation
        $this->connection->statement("DELETE FROM user_roles");
        $this->connection->statement("DELETE FROM role_permissions");
        $this->connection->statement("DELETE FROM permissions");
        $this->connection->statement("DELETE FROM roles");
        $this->connection->statement("DELETE FROM users");
        
        parent::tearDown();
    }

    /**
     * Create test tables for RBAC system
     * 
     * @return void
     */
    protected function createTestTables(): void
    {
        // Create users table
        $this->connection->statement("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ");

        // Create roles table
        $this->connection->statement("
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                description TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ");

        // Create permissions table
        $this->connection->statement("
            CREATE TABLE permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                description TEXT,
                category TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ");

        // Create role_permissions table (matches actual migration)
        $this->connection->statement("
            CREATE TABLE role_permissions (
                role_id INTEGER NOT NULL,
                permission_id INTEGER NOT NULL,
                PRIMARY KEY (role_id, permission_id),
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            )
        ");

        // Create user_roles table
        $this->connection->statement("
            CREATE TABLE user_roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                UNIQUE(user_id, role_id)
            )
        ");
    }

    /**
     * Insert test data for RBAC system
     * 
     * @return void
     */
    protected function insertTestData(): void
    {
        // Insert test users
        $this->connection->insert(
            "INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
            ['John Doe', 'john@example.com', password_hash('password', PASSWORD_DEFAULT), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        $this->connection->insert(
            "INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
            ['Jane Smith', 'jane@example.com', password_hash('password', PASSWORD_DEFAULT), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        // Insert test roles
        $this->connection->insert(
            "INSERT INTO roles (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
            ['Administrator', 'admin', 'Full system access', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        $this->connection->insert(
            "INSERT INTO roles (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
            ['Editor', 'editor', 'Content management access', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        // Insert test permissions
        $this->connection->insert(
            "INSERT INTO permissions (name, slug, description, category, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            ['Manage Users', 'users.manage', 'Create, edit, and delete users', 'User Management', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        $this->connection->insert(
            "INSERT INTO permissions (name, slug, description, category, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            ['View Users', 'users.view', 'View user listings', 'User Management', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        $this->connection->insert(
            "INSERT INTO permissions (name, slug, description, category, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            ['Manage Content', 'content.manage', 'Create, edit, and delete content', 'Content Management', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        // Assign permissions to roles
        $this->connection->insert(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
            [1, 1] // Admin -> Manage Users
        );
        
        $this->connection->insert(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
            [1, 2] // Admin -> View Users
        );
        
        $this->connection->insert(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
            [1, 3] // Admin -> Manage Content
        );
        
        $this->connection->insert(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
            [2, 2] // Editor -> View Users
        );
        
        $this->connection->insert(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
            [2, 3] // Editor -> Manage Content
        );

        // Assign roles to users
        $this->connection->insert(
            "INSERT INTO user_roles (user_id, role_id, created_at, updated_at) VALUES (?, ?, ?, ?)",
            [1, 1, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')] // John -> Admin
        );
        
        $this->connection->insert(
            "INSERT INTO user_roles (user_id, role_id, created_at, updated_at) VALUES (?, ?, ?, ?)",
            [2, 2, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')] // Jane -> Editor
        );
    }
}