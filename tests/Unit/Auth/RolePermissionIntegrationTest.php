<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Tests\DatabaseTestCase;
use LengthOfRope\TreeHouse\Models\User;
use LengthOfRope\TreeHouse\Models\Role;
use LengthOfRope\TreeHouse\Models\Permission;
use LengthOfRope\TreeHouse\Router\Middleware\RoleMiddleware;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Support\Collection;

/**
 * Role Permission Integration Tests
 *
 * Tests the complete RBAC system integration including
 * models, middleware, and helper functions working together.
 */
class RolePermissionIntegrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestData();
    }

    public function testCompleteRolePermissionFlow(): void
    {
        // Create a user
        $user = new User();
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        // Create roles
        $adminRole = new Role();
        $adminRole->name = 'Administrator';
        $adminRole->slug = 'admin';
        $adminRole->description = 'Full system access';
        $adminRole->save();

        $editorRole = new Role();
        $editorRole->name = 'Editor';
        $editorRole->slug = 'editor';
        $editorRole->description = 'Content management access';
        $editorRole->save();

        // Create permissions
        $manageUsersPermission = new Permission();
        $manageUsersPermission->name = 'Manage Users';
        $manageUsersPermission->slug = 'manage-users';
        $manageUsersPermission->category = 'users';
        $manageUsersPermission->save();

        $editPostsPermission = new Permission();
        $editPostsPermission->name = 'Edit Posts';
        $editPostsPermission->slug = 'edit-posts';
        $editPostsPermission->category = 'content';
        $editPostsPermission->save();

        $viewPostsPermission = new Permission();
        $viewPostsPermission->name = 'View Posts';
        $viewPostsPermission->slug = 'view-posts';
        $viewPostsPermission->category = 'content';
        $viewPostsPermission->save();

        // Assign permissions to roles
        $adminRole->givePermission($manageUsersPermission);
        $adminRole->givePermission($editPostsPermission);
        $adminRole->givePermission($viewPostsPermission);

        $editorRole->givePermission($editPostsPermission);
        $editorRole->givePermission($viewPostsPermission);

        // Test user without roles
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('editor'));
        $this->assertFalse($user->hasPermission('manage-users'));
        $this->assertFalse($user->hasPermission('edit-posts'));
        $this->assertFalse($user->hasPermission('view-posts'));

        // Assign editor role to user
        $user->assignRole($editorRole);

        // Test user with editor role
        $this->assertFalse($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
        $this->assertFalse($user->hasPermission('manage-users'));
        $this->assertTrue($user->hasPermission('edit-posts'));
        $this->assertTrue($user->hasPermission('view-posts'));

        // Test can/cannot methods
        $this->assertFalse($user->can('manage-users'));
        $this->assertTrue($user->can('edit-posts'));
        $this->assertTrue($user->can('view-posts'));
        $this->assertTrue($user->cannot('manage-users'));
        $this->assertFalse($user->cannot('edit-posts'));

        // Assign admin role as well (multiple roles)
        $user->assignRole($adminRole);

        // Test user with multiple roles
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
        $this->assertTrue($user->hasAnyRole(['admin', 'editor']));
        $this->assertTrue($user->hasAllRoles(['admin', 'editor']));
        $this->assertFalse($user->hasAllRoles(['admin', 'editor', 'viewer']));

        // Test all permissions
        $this->assertTrue($user->hasPermission('manage-users'));
        $this->assertTrue($user->hasPermission('edit-posts'));
        $this->assertTrue($user->hasPermission('view-posts'));

        // Test permission arrays
        $this->assertTrue($user->hasAnyPermission(['manage-users', 'non-existent']));
        $this->assertTrue($user->hasAllPermissions(['edit-posts', 'view-posts']));
        $this->assertFalse($user->hasAllPermissions(['edit-posts', 'non-existent']));

        // Test role sync
        $user->syncRoles(['editor']);
        $this->assertFalse($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
        $this->assertFalse($user->hasPermission('manage-users'));
        $this->assertTrue($user->hasPermission('edit-posts'));

        // Test permission sync on role
        $editorRole->syncPermissions(['view-posts']);
        $this->assertFalse($user->hasPermission('edit-posts'));
        $this->assertTrue($user->hasPermission('view-posts'));
    }

    public function testRoleMiddlewareIntegration(): void
    {
        // Create user with admin role
        $user = new User();
        $user->name = 'Admin User';
        $user->email = 'admin@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $adminRole = new Role();
        $adminRole->name = 'Administrator';
        $adminRole->slug = 'admin';
        $adminRole->save();

        $user->assignRole($adminRole);

        // Test middleware configuration
        $config = [
            'roles' => [
                'admin' => ['*'],
                'editor' => ['edit-posts', 'view-posts'],
                'viewer' => ['view-posts'],
            ],
            'default_role' => 'viewer',
        ];

        $middleware = new RoleMiddleware($config);

        // Test unauthenticated request
        $request = $this->createRequestWithRoles('admin');
        $next = function ($req) {
            return new Response('Success', 200);
        };

        $response = $middleware->handle($request, $next);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testPermissionCategorization(): void
    {
        // Create permissions in different categories
        $userPermissions = [
            ['name' => 'Create User', 'slug' => 'create-user', 'category' => 'users'],
            ['name' => 'Edit User', 'slug' => 'edit-user', 'category' => 'users'],
            ['name' => 'Delete User', 'slug' => 'delete-user', 'category' => 'users'],
        ];

        $contentPermissions = [
            ['name' => 'Create Post', 'slug' => 'create-post', 'category' => 'content'],
            ['name' => 'Edit Post', 'slug' => 'edit-post', 'category' => 'content'],
        ];

        foreach ($userPermissions as $permData) {
            $permission = new Permission();
            $permission->name = $permData['name'];
            $permission->slug = $permData['slug'];
            $permission->category = $permData['category'];
            $permission->save();
        }

        foreach ($contentPermissions as $permData) {
            $permission = new Permission();
            $permission->name = $permData['name'];
            $permission->slug = $permData['slug'];
            $permission->category = $permData['category'];
            $permission->save();
        }

        // Test categorized permissions
        $categorized = Permission::categorized();
        $this->assertArrayHasKey('users', $categorized);
        $this->assertArrayHasKey('content', $categorized);
        $this->assertCount(3, $categorized['users']);
        $this->assertCount(2, $categorized['content']);

        // Test by category
        $userPerms = Permission::byCategory('users');
        $this->assertCount(3, $userPerms);

        // Test get categories
        $categories = Permission::getCategories();
        $this->assertContains('users', $categories);
        $this->assertContains('content', $categories);
        $this->assertCount(2, $categories);
    }

    public function testRolePermissionRelationships(): void
    {
        // Create role
        $role = new Role();
        $role->name = 'Test Role';
        $role->slug = 'test-role';
        $role->save();

        // Create permissions
        $permission1 = new Permission();
        $permission1->name = 'Permission 1';
        $permission1->slug = 'permission-1';
        $permission1->save();

        $permission2 = new Permission();
        $permission2->name = 'Permission 2';
        $permission2->slug = 'permission-2';
        $permission2->save();

        // Test role-permission relationships
        $role->givePermission($permission1);
        $role->givePermission($permission2);

        $rolePermissions = $role->permissions();
        $this->assertCount(2, $rolePermissions);

        $permissionRoles = $permission1->roles();
        $this->assertCount(1, $permissionRoles);
        $this->assertEquals('test-role', $permissionRoles->first()->slug);

        // Test user-role relationships
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $user->assignRole($role);

        $userRoles = $user->roles();
        $this->assertCount(1, $userRoles);
        $this->assertEquals('test-role', $userRoles->first()->slug);

        $roleUsers = $role->users();
        $this->assertCount(1, $roleUsers);
        $this->assertEquals('test@example.com', $roleUsers->first()->email);
    }

    public function testHelperFunctions(): void
    {
        // Test that helper functions work with the RBAC system
        $user = new User();
        $user->name = 'Helper Test User';
        $user->email = 'helper@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role = new Role();
        $role->name = 'Helper Role';
        $role->slug = 'helper-role';
        $role->save();

        $permission = new Permission();
        $permission->name = 'Helper Permission';
        $permission->slug = 'helper-permission';
        $permission->save();

        $role->givePermission($permission);
        $user->assignRole($role);

        // Test helper functions (these would be defined in helpers.php)
        $this->assertTrue($user->hasRole('helper-role'));
        $this->assertTrue($user->hasPermission('helper-permission'));
        $this->assertTrue($user->can('helper-permission'));
    }

    private function createRequestWithRoles(string $roles, array $headers = []): Request
    {
        $query = $roles ? ['_roles' => $roles] : [];
        $serverVars = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
        ];

        // Add headers to server vars
        foreach ($headers as $name => $value) {
            $serverVars['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return new Request($query, [], [], [], $serverVars);
    }


    private function setupTestData(): void
    {
        // This method can be used to set up common test data
        // if needed across multiple test methods
    }
}