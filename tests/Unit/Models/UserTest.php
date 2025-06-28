<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Tests\DatabaseTestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use LengthOfRope\TreeHouse\Support\Collection;

/**
 * User Model Tests
 *
 * Tests for the User model including role and permission
 * management, authentication, and database operations.
 */
class UserTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testUserCanBeCreated(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $this->assertNotNull($user->id);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNotNull($user->password);
    }

    public function testUserHasRolesRelationship(): void
    {
        // Create user and role
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role = new Role();
        $role->name = 'Test Role';
        $role->slug = 'test-role';
        $role->save();

        // Assign role to user
        $user->assignRole($role);

        // Test relationship
        $roles = $user->roles();
        $this->assertInstanceOf(\LengthOfRope\TreeHouse\Support\Collection::class, $roles);
        $this->assertCount(1, $roles);
        $this->assertEquals('Test Role', $roles->first()->name);
    }

    public function testUserCanAssignRole(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role = new Role();
        $role->name = 'Test Role';
        $role->slug = 'test-role';
        $role->save();

        // Test assigning role
        $user->assignRole($role);

        // Verify the relationship exists
        $this->assertTrue($user->hasRole('test-role'));
        $roles = $user->roles();
        $this->assertCount(1, $roles);
        $this->assertEquals('test-role', $roles->first()->slug);
    }

    public function testUserCanAssignRoleBySlug(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role = new Role();
        $role->name = 'Test Role';
        $role->slug = 'test-role';
        $role->save();

        // Test assigning role by slug
        $user->assignRole('test-role');

        // Verify the relationship exists
        $this->assertTrue($user->hasRole('test-role'));
    }

    public function testUserCanRemoveRole(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role = new Role();
        $role->name = 'Test Role';
        $role->slug = 'test-role';
        $role->save();

        // Assign then remove
        $user->assignRole($role);
        $this->assertTrue($user->hasRole('test-role'));

        $user->removeRole($role);

        // Verify the relationship is removed
        $this->assertFalse($user->hasRole('test-role'));
        $roles = $user->roles();
        $this->assertCount(0, $roles);
    }

    public function testUserCanSyncRoles(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        // Create multiple roles
        $role1 = new Role();
        $role1->name = 'Role 1';
        $role1->slug = 'role-1';
        $role1->save();

        $role2 = new Role();
        $role2->name = 'Role 2';
        $role2->slug = 'role-2';
        $role2->save();

        $role3 = new Role();
        $role3->name = 'Role 3';
        $role3->slug = 'role-3';
        $role3->save();

        // Sync with first two roles
        $user->syncRoles(['role-1', 'role-2']);
        $roles = $user->roles();
        $this->assertCount(2, $roles);

        // Sync with different set
        $user->syncRoles(['role-2', 'role-3']);
        $roles = $user->roles();
        $this->assertCount(2, $roles);
        
        $roleSlugs = $roles->map(fn($r) => $r->slug)->all();
        $this->assertContains('role-2', $roleSlugs);
        $this->assertContains('role-3', $roleSlugs);
        $this->assertNotContains('role-1', $roleSlugs);
    }

    public function testUserHasRoleCheck(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role = new Role();
        $role->name = 'Test Role';
        $role->slug = 'test-role';
        $role->save();

        // Should not have role initially
        $this->assertFalse($user->hasRole('test-role'));

        // Assign role
        $user->assignRole($role);

        // Should have role now
        $this->assertTrue($user->hasRole('test-role'));
    }

    public function testUserHasAnyRole(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role1 = new Role();
        $role1->name = 'Role 1';
        $role1->slug = 'role-1';
        $role1->save();

        $role2 = new Role();
        $role2->name = 'Role 2';
        $role2->slug = 'role-2';
        $role2->save();

        // Should not have any roles initially
        $this->assertFalse($user->hasAnyRole(['role-1', 'role-2']));

        // Assign one role
        $user->assignRole($role1);

        // Should have any of the roles now
        $this->assertTrue($user->hasAnyRole(['role-1', 'role-2']));
        $this->assertTrue($user->hasAnyRole(['role-1', 'non-existent']));
        $this->assertFalse($user->hasAnyRole(['role-2', 'non-existent']));
    }

    public function testUserHasAllRoles(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role1 = new Role();
        $role1->name = 'Role 1';
        $role1->slug = 'role-1';
        $role1->save();

        $role2 = new Role();
        $role2->name = 'Role 2';
        $role2->slug = 'role-2';
        $role2->save();

        // Should not have all roles initially
        $this->assertFalse($user->hasAllRoles(['role-1', 'role-2']));

        // Assign one role
        $user->assignRole($role1);
        $this->assertFalse($user->hasAllRoles(['role-1', 'role-2']));

        // Assign second role
        $user->assignRole($role2);
        $this->assertTrue($user->hasAllRoles(['role-1', 'role-2']));
    }

    public function testUserHasPermission(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role = new Role();
        $role->name = 'Test Role';
        $role->slug = 'test-role';
        $role->save();

        $permission = new Permission();
        $permission->name = 'Test Permission';
        $permission->slug = 'test-permission';
        $permission->save();

        // Should not have permission initially
        $this->assertFalse($user->hasPermission('test-permission'));

        // Give permission to role
        $role->givePermission($permission);

        // Still should not have permission (user doesn't have role)
        $this->assertFalse($user->hasPermission('test-permission'));

        // Assign role to user
        $user->assignRole($role);

        // Should have permission now
        $this->assertTrue($user->hasPermission('test-permission'));
    }

    public function testUserCanMethod(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role = new Role();
        $role->name = 'Test Role';
        $role->slug = 'test-role';
        $role->save();

        $permission = new Permission();
        $permission->name = 'Test Permission';
        $permission->slug = 'test-permission';
        $permission->save();

        $role->givePermission($permission);
        $user->assignRole($role);

        // Test can method (alias for hasPermission)
        $this->assertTrue($user->can('test-permission'));
        $this->assertFalse($user->can('non-existent-permission'));
    }

    public function testUserCannotMethod(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        $role = new Role();
        $role->name = 'Test Role';
        $role->slug = 'test-role';
        $role->save();

        $permission = new Permission();
        $permission->name = 'Test Permission';
        $permission->slug = 'test-permission';
        $permission->save();

        $role->givePermission($permission);
        $user->assignRole($role);

        // Test cannot method
        $this->assertFalse($user->cannot('test-permission'));
        $this->assertTrue($user->cannot('non-existent-permission'));
    }

    public function testUserGetRole(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        // Should return default role when no roles assigned
        $this->assertEquals('member', $user->getRole());

        $role = new Role();
        $role->name = 'Test Role';
        $role->slug = 'test-role';
        $role->save();

        // Assign single role
        $user->assignRole($role);
        $this->assertEquals('test-role', $user->getRole());

        // Assign multiple roles
        $role2 = new Role();
        $role2->name = 'Test Role 2';
        $role2->slug = 'test-role-2';
        $role2->save();

        $user->assignRole($role2);
        $roles = $user->getRole();
        $this->assertIsArray($roles);
        $this->assertContains('test-role', $roles);
        $this->assertContains('test-role-2', $roles);
    }

    public function testUserAuthMethods(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        // Test auth identifier
        $this->assertEquals($user->id, $user->getAuthIdentifier());

        // Test auth password
        $this->assertEquals($user->password, $user->getAuthPassword());

        // Test remember token
        $this->assertNull($user->getRememberToken());
        
        $user->setRememberToken('test-token');
        $this->assertEquals('test-token', $user->getRememberToken());
    }

    public function testUserPasswordHiding(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->save();

        // Test that password is hidden in array representation
        $userArray = $user->toArray();
        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayHasKey('name', $userArray);
        $this->assertArrayHasKey('email', $userArray);
    }

    public function testUserBackwardCompatibilityWithLegacyRole(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = password_hash('password', PASSWORD_DEFAULT);
        $user->role = 'legacy-admin'; // Legacy role column
        $user->save();

        // Should work with legacy role column
        $this->assertTrue($user->hasRole('legacy-admin'));
        $this->assertEquals('legacy-admin', $user->getRole());
    }

}