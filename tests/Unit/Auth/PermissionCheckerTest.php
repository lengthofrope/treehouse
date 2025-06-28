<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Tests\DatabaseTestCase;
use LengthOfRope\TreeHouse\Auth\PermissionChecker;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Auth\AuthorizableUser;

/**
 * Permission Checker Tests
 *
 * Tests for the permission evaluation utility class.
 */
class PermissionCheckerTest extends DatabaseTestCase
{
    protected PermissionChecker $checker;
    protected array $authConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test data in database
        $this->setupTestData();

        $this->authConfig = [
            'roles' => [
                'admin' => ['*'],
                'editor' => ['edit-posts', 'delete-posts', 'view-posts'],
                'viewer' => ['view-posts'],
                'guest' => [],
            ],
            'permissions' => [
                'manage-users' => ['admin'],
                'edit-posts' => ['admin', 'editor'],
                'delete-posts' => ['admin', 'editor'],
                'view-posts' => ['admin', 'editor', 'viewer'],
                'special-permission' => [],
            ],
            'default_role' => 'viewer',
        ];

        $this->checker = new PermissionChecker($this->authConfig);
    }

    protected function setupTestData(): void
    {
        // Insert test roles
        $this->connection->insert("INSERT INTO roles (slug, name) VALUES (?, ?)", ['admin', 'Administrator']);
        $this->connection->insert("INSERT INTO roles (slug, name) VALUES (?, ?)", ['editor', 'Editor']);
        $this->connection->insert("INSERT INTO roles (slug, name) VALUES (?, ?)", ['viewer', 'Viewer']);
        $this->connection->insert("INSERT INTO roles (slug, name) VALUES (?, ?)", ['guest', 'Guest']);

        // Insert test permissions
        $this->connection->insert("INSERT INTO permissions (slug, name) VALUES (?, ?)", ['manage-users', 'Manage Users']);
        $this->connection->insert("INSERT INTO permissions (slug, name) VALUES (?, ?)", ['edit-posts', 'Edit Posts']);
        $this->connection->insert("INSERT INTO permissions (slug, name) VALUES (?, ?)", ['delete-posts', 'Delete Posts']);
        $this->connection->insert("INSERT INTO permissions (slug, name) VALUES (?, ?)", ['view-posts', 'View Posts']);
        $this->connection->insert("INSERT INTO permissions (slug, name) VALUES (?, ?)", ['special-permission', 'Special Permission']);

        // Set up role-permission relationships
        // Admin gets all permissions
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [1, 1]); // admin -> manage-users
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [1, 2]); // admin -> edit-posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [1, 3]); // admin -> delete-posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [1, 4]); // admin -> view-posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [1, 5]); // admin -> special-permission

        // Editor gets edit, delete, view posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [2, 2]); // editor -> edit-posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [2, 3]); // editor -> delete-posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [2, 4]); // editor -> view-posts

        // Viewer gets only view posts
        $this->connection->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [3, 4]); // viewer -> view-posts

        // Guest gets no permissions (empty)
    }

    public function testCheckPermissionWithAuthorizedUser(): void
    {
        $user = new PermissionTestUser(['id' => 1, 'role' => 'admin']);
        $user->setAuthConfig($this->authConfig);

        $this->assertTrue($this->checker->check($user, 'manage-users'));
        $this->assertTrue($this->checker->check($user, 'edit-posts'));
        $this->assertTrue($this->checker->check($user, 'view-posts'));
    }

    public function testCheckPermissionWithUnauthorizedUser(): void
    {
        $user = new PermissionTestUser(['id' => 2, 'role' => 'viewer']);
        $user->setAuthConfig($this->authConfig);

        $this->assertFalse($this->checker->check($user, 'manage-users'));
        $this->assertFalse($this->checker->check($user, 'edit-posts'));
        $this->assertTrue($this->checker->check($user, 'view-posts'));
    }

    public function testCheckPermissionWithNullUser(): void
    {
        $this->assertFalse($this->checker->check(null, 'manage-users'));
        $this->assertFalse($this->checker->check(null, 'view-posts'));
    }

    public function testHasRole(): void
    {
        $adminUser = new PermissionTestUser(['id' => 1, 'role' => 'admin']);
        $adminUser->setAuthConfig($this->authConfig);

        $viewerUser = new PermissionTestUser(['id' => 2, 'role' => 'viewer']);
        $viewerUser->setAuthConfig($this->authConfig);

        $this->assertTrue($this->checker->hasRole($adminUser, 'admin'));
        $this->assertFalse($this->checker->hasRole($adminUser, 'viewer'));
        
        $this->assertTrue($this->checker->hasRole($viewerUser, 'viewer'));
        $this->assertFalse($this->checker->hasRole($viewerUser, 'admin'));
        
        $this->assertFalse($this->checker->hasRole(null, 'admin'));
    }

    public function testHasAnyRole(): void
    {
        $editorUser = new PermissionTestUser(['id' => 1, 'role' => 'editor']);
        $editorUser->setAuthConfig($this->authConfig);

        $this->assertTrue($this->checker->hasAnyRole($editorUser, ['admin', 'editor']));
        $this->assertTrue($this->checker->hasAnyRole($editorUser, ['editor', 'viewer']));
        $this->assertFalse($this->checker->hasAnyRole($editorUser, ['admin', 'guest']));
        
        $this->assertFalse($this->checker->hasAnyRole(null, ['admin', 'editor']));
    }

    public function testHasAllRoles(): void
    {
        $adminUser = new PermissionTestUser(['id' => 1, 'role' => 'admin']);
        $adminUser->setAuthConfig($this->authConfig);

        // Single role check
        $this->assertTrue($this->checker->hasAllRoles($adminUser, ['admin']));
        $this->assertFalse($this->checker->hasAllRoles($adminUser, ['viewer']));
        
        // Multiple roles - should fail since user can only have one role
        $this->assertFalse($this->checker->hasAllRoles($adminUser, ['admin', 'editor']));
        
        $this->assertFalse($this->checker->hasAllRoles(null, ['admin']));
    }

    public function testHasAnyPermission(): void
    {
        $editorUser = new PermissionTestUser(['id' => 1, 'role' => 'editor']);
        $editorUser->setAuthConfig($this->authConfig);

        $this->assertTrue($this->checker->hasAnyPermission($editorUser, ['manage-users', 'edit-posts']));
        $this->assertTrue($this->checker->hasAnyPermission($editorUser, ['edit-posts', 'view-posts']));
        $this->assertFalse($this->checker->hasAnyPermission($editorUser, ['manage-users']));
        
        $this->assertFalse($this->checker->hasAnyPermission(null, ['edit-posts']));
    }

    public function testHasAllPermissions(): void
    {
        $adminUser = new PermissionTestUser(['id' => 1, 'role' => 'admin']);
        $adminUser->setAuthConfig($this->authConfig);

        $editorUser = new PermissionTestUser(['id' => 2, 'role' => 'editor']);
        $editorUser->setAuthConfig($this->authConfig);

        // Admin should have all permissions
        $this->assertTrue($this->checker->hasAllPermissions($adminUser, ['manage-users', 'edit-posts']));
        $this->assertTrue($this->checker->hasAllPermissions($adminUser, ['edit-posts', 'view-posts']));
        
        // Editor should have some but not all
        $this->assertFalse($this->checker->hasAllPermissions($editorUser, ['manage-users', 'edit-posts']));
        $this->assertTrue($this->checker->hasAllPermissions($editorUser, ['edit-posts', 'view-posts']));
        
        $this->assertFalse($this->checker->hasAllPermissions(null, ['edit-posts']));
    }

    public function testParseMiddlewareString(): void
    {
        // Test role parsing
        $roleResult = $this->checker->parseMiddlewareString('role:admin,editor');
        $this->assertEquals('role', $roleResult['type']);
        $this->assertEquals(['admin', 'editor'], $roleResult['values']);

        // Test permission parsing
        $permissionResult = $this->checker->parseMiddlewareString('permission:edit-posts,delete-posts');
        $this->assertEquals('permission', $permissionResult['type']);
        $this->assertEquals(['edit-posts', 'delete-posts'], $permissionResult['values']);

        // Test unknown type
        $unknownResult = $this->checker->parseMiddlewareString('unknown:something');
        $this->assertEquals('unknown', $unknownResult['type']);
        $this->assertEquals([], $unknownResult['values']);

        // Test single values
        $singleRole = $this->checker->parseMiddlewareString('role:admin');
        $this->assertEquals(['admin'], $singleRole['values']);
    }

    public function testCheckMiddleware(): void
    {
        $adminUser = new PermissionTestUser(['id' => 1, 'role' => 'admin']);
        $adminUser->setAuthConfig($this->authConfig);

        $editorUser = new PermissionTestUser(['id' => 2, 'role' => 'editor']);
        $editorUser->setAuthConfig($this->authConfig);

        // Test role middleware
        $this->assertTrue($this->checker->checkMiddleware($adminUser, 'role:admin'));
        $this->assertTrue($this->checker->checkMiddleware($editorUser, 'role:admin,editor'));
        $this->assertFalse($this->checker->checkMiddleware($editorUser, 'role:admin'));

        // Test permission middleware
        $this->assertTrue($this->checker->checkMiddleware($adminUser, 'permission:manage-users'));
        $this->assertFalse($this->checker->checkMiddleware($editorUser, 'permission:manage-users'));
        $this->assertTrue($this->checker->checkMiddleware($editorUser, 'permission:edit-posts,view-posts'));

        // Test unknown middleware
        $this->assertFalse($this->checker->checkMiddleware($adminUser, 'unknown:something'));
    }

    public function testGetPermissionsForRole(): void
    {
        $adminPermissions = $this->checker->getPermissionsForRole('admin');
        $this->assertEquals(['manage-users', 'edit-posts', 'delete-posts', 'view-posts', 'special-permission'], $adminPermissions);

        $editorPermissions = $this->checker->getPermissionsForRole('editor');
        $this->assertEquals(['edit-posts', 'delete-posts', 'view-posts'], $editorPermissions);

        $viewerPermissions = $this->checker->getPermissionsForRole('viewer');
        $this->assertEquals(['view-posts'], $viewerPermissions);

        $nonexistentPermissions = $this->checker->getPermissionsForRole('nonexistent');
        $this->assertEquals([], $nonexistentPermissions);
    }

    public function testGetAllPermissions(): void
    {
        $allPermissions = $this->checker->getAllPermissions();
        $expected = ['manage-users', 'edit-posts', 'delete-posts', 'view-posts', 'special-permission'];
        $this->assertEquals($expected, $allPermissions);
    }

    public function testGetAllRoles(): void
    {
        $allRoles = $this->checker->getAllRoles();
        $expected = ['admin', 'editor', 'viewer', 'guest'];
        $this->assertEquals($expected, $allRoles);
    }

    public function testRoleExists(): void
    {
        $this->assertTrue($this->checker->roleExists('admin'));
        $this->assertTrue($this->checker->roleExists('editor'));
        $this->assertFalse($this->checker->roleExists('nonexistent'));
    }

    public function testPermissionExists(): void
    {
        $this->assertTrue($this->checker->permissionExists('manage-users'));
        $this->assertTrue($this->checker->permissionExists('view-posts'));
        $this->assertFalse($this->checker->permissionExists('nonexistent'));
    }

    public function testGetRolesWithPermission(): void
    {
        $manageUsersRoles = $this->checker->getRolesWithPermission('manage-users');
        $this->assertEquals(['admin'], $manageUsersRoles);

        $editPostsRoles = $this->checker->getRolesWithPermission('edit-posts');
        $this->assertEquals(['admin', 'editor'], $editPostsRoles);

        $nonexistentRoles = $this->checker->getRolesWithPermission('nonexistent');
        $this->assertEquals([], $nonexistentRoles);
    }

    public function testSetAndGetConfig(): void
    {
        $newConfig = [
            'roles' => ['test-role' => ['test-permission']],
            'permissions' => ['test-permission' => ['test-role']],
        ];

        $this->checker->setConfig($newConfig);
        $this->assertEquals($newConfig, $this->checker->getConfig());
    }
}

/**
 * Test User class for PermissionChecker testing
 */
class PermissionTestUser implements Authorizable
{
    use AuthorizableUser;

    protected array $attributes;
    protected array $authConfig = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
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
        return true;
    }
}