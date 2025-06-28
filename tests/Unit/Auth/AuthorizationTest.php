<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Tests\DatabaseTestCase;
use LengthOfRope\TreeHouse\Auth\Gate;
use LengthOfRope\TreeHouse\Auth\PermissionChecker;
use LengthOfRope\TreeHouse\Auth\AuthorizableUser;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;

/**
 * Authorization System Tests
 *
 * Tests for the role-based authorization system including
 * roles, permissions, Gate, and middleware functionality.
 */
class AuthorizationTest extends DatabaseTestCase
{
    protected array $authConfig;
    protected TestUser $adminUser;
    protected TestUser $editorUser;
    protected TestUser $viewerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test data in database
        $this->setupTestData();

        // Mock auth configuration (for fallback compatibility)
        $this->authConfig = [
            'roles' => [
                'admin' => ['*'],
                'editor' => ['edit-posts', 'delete-posts', 'view-posts'],
                'viewer' => ['view-posts'],
            ],
            'permissions' => [
                'manage-users' => ['admin'],
                'edit-posts' => ['admin', 'editor'],
                'delete-posts' => ['admin', 'editor'],
                'view-posts' => ['admin', 'editor', 'viewer'],
            ],
            'default_role' => 'viewer',
        ];

        // Create test users
        $this->adminUser = new TestUser(['id' => 1, 'name' => 'Admin', 'role' => 'admin']);
        $this->editorUser = new TestUser(['id' => 2, 'name' => 'Editor', 'role' => 'editor']);
        $this->viewerUser = new TestUser(['id' => 3, 'name' => 'Viewer', 'role' => 'viewer']);

        // Set config for test users (for fallback compatibility)
        $this->adminUser->setAuthConfig($this->authConfig);
        $this->editorUser->setAuthConfig($this->authConfig);
        $this->viewerUser->setAuthConfig($this->authConfig);
    }

    protected function setupTestData(): void
    {
        // Insert test roles
        $this->connection->insert("INSERT INTO roles (slug, name) VALUES (?, ?)", ['admin', 'Administrator']);
        $this->connection->insert("INSERT INTO roles (slug, name) VALUES (?, ?)", ['editor', 'Editor']);
        $this->connection->insert("INSERT INTO roles (slug, name) VALUES (?, ?)", ['viewer', 'Viewer']);

        // Insert test permissions
        $this->connection->insert("INSERT INTO permissions (slug, name) VALUES (?, ?)", ['manage-users', 'Manage Users']);
        $this->connection->insert("INSERT INTO permissions (slug, name) VALUES (?, ?)", ['edit-posts', 'Edit Posts']);
        $this->connection->insert("INSERT INTO permissions (slug, name) VALUES (?, ?)", ['delete-posts', 'Delete Posts']);
        $this->connection->insert("INSERT INTO permissions (slug, name) VALUES (?, ?)", ['view-posts', 'View Posts']);

        // Set up role-permission relationships
        // Admin gets all permissions
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [1, 1]); // admin -> manage-users
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [1, 2]); // admin -> edit-posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [1, 3]); // admin -> delete-posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [1, 4]); // admin -> view-posts

        // Editor gets edit, delete, view posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [2, 2]); // editor -> edit-posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [2, 3]); // editor -> delete-posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [2, 4]); // editor -> view-posts

        // Viewer gets only view posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [3, 4]); // viewer -> view-posts
    }

    public function testUserHasRole(): void
    {
        $this->assertTrue($this->adminUser->hasRole('admin'));
        $this->assertFalse($this->adminUser->hasRole('editor'));

        $this->assertTrue($this->editorUser->hasRole('editor'));
        $this->assertFalse($this->editorUser->hasRole('admin'));

        $this->assertTrue($this->viewerUser->hasRole('viewer'));
        $this->assertFalse($this->viewerUser->hasRole('editor'));
    }

    public function testUserHasAnyRole(): void
    {
        $this->assertTrue($this->adminUser->hasAnyRole(['admin', 'editor']));
        $this->assertTrue($this->adminUser->hasAnyRole(['admin']));
        $this->assertFalse($this->adminUser->hasAnyRole(['editor', 'viewer']));

        $this->assertTrue($this->editorUser->hasAnyRole(['admin', 'editor']));
        $this->assertTrue($this->editorUser->hasAnyRole(['editor', 'viewer']));
        $this->assertFalse($this->editorUser->hasAnyRole(['admin']));
    }

    public function testUserCanPermission(): void
    {
        // Admin should have all permissions (wildcard)
        $this->assertTrue($this->adminUser->can('manage-users'));
        $this->assertTrue($this->adminUser->can('edit-posts'));
        $this->assertTrue($this->adminUser->can('view-posts'));

        // Editor should have specific permissions
        $this->assertFalse($this->editorUser->can('manage-users'));
        $this->assertTrue($this->editorUser->can('edit-posts'));
        $this->assertTrue($this->editorUser->can('delete-posts'));
        $this->assertTrue($this->editorUser->can('view-posts'));

        // Viewer should have limited permissions
        $this->assertFalse($this->viewerUser->can('manage-users'));
        $this->assertFalse($this->viewerUser->can('edit-posts'));
        $this->assertFalse($this->viewerUser->can('delete-posts'));
        $this->assertTrue($this->viewerUser->can('view-posts'));
    }

    public function testUserCannotPermission(): void
    {
        $this->assertFalse($this->adminUser->cannot('manage-users'));
        $this->assertTrue($this->editorUser->cannot('manage-users'));
        $this->assertTrue($this->viewerUser->cannot('edit-posts'));
    }

    public function testPermissionChecker(): void
    {
        $checker = new PermissionChecker($this->authConfig);

        // Test role checking
        $this->assertTrue($checker->hasRole($this->adminUser, 'admin'));
        $this->assertFalse($checker->hasRole($this->editorUser, 'admin'));

        // Test any role checking
        $this->assertTrue($checker->hasAnyRole($this->editorUser, ['admin', 'editor']));
        $this->assertFalse($checker->hasAnyRole($this->viewerUser, ['admin', 'editor']));

        // Test permission checking
        $this->assertTrue($checker->check($this->adminUser, 'manage-users'));
        $this->assertFalse($checker->check($this->editorUser, 'manage-users'));
        $this->assertTrue($checker->check($this->editorUser, 'edit-posts'));
    }

    public function testPermissionCheckerWithNullUser(): void
    {
        $checker = new PermissionChecker($this->authConfig);

        $this->assertFalse($checker->check(null, 'view-posts'));
        $this->assertFalse($checker->hasRole(null, 'viewer'));
        $this->assertFalse($checker->hasAnyRole(null, ['viewer']));
    }

    public function testMiddlewareStringParsing(): void
    {
        $checker = new PermissionChecker($this->authConfig);

        // Test role parsing
        $roleResult = $checker->parseMiddlewareString('role:admin,editor');
        $this->assertEquals('role', $roleResult['type']);
        $this->assertEquals(['admin', 'editor'], $roleResult['values']);

        // Test permission parsing
        $permissionResult = $checker->parseMiddlewareString('permission:edit-posts,delete-posts');
        $this->assertEquals('permission', $permissionResult['type']);
        $this->assertEquals(['edit-posts', 'delete-posts'], $permissionResult['values']);

        // Test unknown type
        $unknownResult = $checker->parseMiddlewareString('unknown:something');
        $this->assertEquals('unknown', $unknownResult['type']);
        $this->assertEquals([], $unknownResult['values']);
    }

    public function testMiddlewareChecking(): void
    {
        $checker = new PermissionChecker($this->authConfig);

        // Test role middleware
        $this->assertTrue($checker->checkMiddleware($this->adminUser, 'role:admin'));
        $this->assertTrue($checker->checkMiddleware($this->editorUser, 'role:admin,editor'));
        $this->assertFalse($checker->checkMiddleware($this->viewerUser, 'role:admin,editor'));

        // Test permission middleware
        $this->assertTrue($checker->checkMiddleware($this->adminUser, 'permission:manage-users'));
        $this->assertFalse($checker->checkMiddleware($this->editorUser, 'permission:manage-users'));
        $this->assertTrue($checker->checkMiddleware($this->editorUser, 'permission:edit-posts,view-posts'));
    }

    public function testGateDefinition(): void
    {
        Gate::flush(); // Clear any existing definitions

        // Define a custom gate
        Gate::define('edit-own-post', function($user, $post) {
            return $user->getAuthIdentifier() === $post['author_id'];
        });

        $post = ['author_id' => 2];

        // Test with matching user
        $this->assertTrue(Gate::forUser($this->editorUser, 'edit-own-post', $post));
        
        // Test with non-matching user
        $this->assertFalse(Gate::forUser($this->adminUser, 'edit-own-post', $post));
    }

    public function testRoleAssignment(): void
    {
        $user = new TestUser(['id' => 4, 'name' => 'Test', 'role' => 'viewer']);
        $user->setAuthConfig($this->authConfig);

        $this->assertTrue($user->hasRole('viewer'));
        $this->assertFalse($user->hasRole('editor'));

        // Assign new role
        $user->assignRole('editor');
        $this->assertTrue($user->hasRole('editor'));
        $this->assertFalse($user->hasRole('viewer'));

        // Remove role (should revert to default)
        $user->removeRole('editor');
        $this->assertTrue($user->hasRole('viewer'));
        $this->assertFalse($user->hasRole('editor'));
    }

    protected function tearDown(): void
    {
        Gate::flush();
        parent::tearDown();
    }
}

/**
 * Test User class for authorization testing
 */
class TestUser implements Authorizable
{
    use AuthorizableUser;

    protected array $attributes;
    protected array $authConfig = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->attributes['id'] ?? null;
    }

    public function getRole(): string|array
    {
        return $this->attributes['role'] ?? 'viewer';
    }

    protected function setRole(string|array $role): void
    {
        $this->attributes['role'] = $role;
    }

    protected function getAuthConfig(): array
    {
        return $this->authConfig;
    }

    public function setAuthConfig(array $config): void
    {
        $this->authConfig = $config;
    }

    public function save(): bool
    {
        // Mock save method
        return true;
    }
}