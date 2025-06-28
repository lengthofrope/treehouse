<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use Tests\DatabaseTestCase;
use LengthOfRope\TreeHouse\Console\Commands\RoleCommand;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

/**
 * Role Command Tests
 *
 * Tests for the role management console command including
 * role creation, permission assignment, and user role management.
 */
class RoleCommandTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCommandConfiguration(): void
    {
        $command = new RoleCommand();
        
        $this->assertEquals('role', $command->getName());
        $this->assertStringContainsString('Manage roles and their permissions', $command->getDescription());
        
        $arguments = $command->getArguments();
        $this->assertArrayHasKey('action', $arguments);
        $this->assertArrayHasKey('name', $arguments);
        
        $options = $command->getOptions();
        $this->assertArrayHasKey('permissions', $options);
    }

    public function testCreateRoleAction(): void
    {
        $command = $this->getMockBuilder(RoleCommand::class)
                        ->onlyMethods(['ask', 'confirm'])
                        ->getMock();
        
        $command->method('ask')
                ->willReturnOnConsecutiveCalls('Test description', null);
        
        $command->method('confirm')
                ->willReturn(false);

        $input = $this->createMockInput([
            'action' => 'create',
            'name' => 'Create Test Role'
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
        
        // Verify role was created in database
        $connection = db();
        $role = $connection->selectOne('SELECT * FROM roles WHERE name = ?', ['Create Test Role']);
        $this->assertNotNull($role);
        $this->assertEquals('Create Test Role', $role['name']);
    }

    public function testListRolesAction(): void
    {
        // Create test roles
        $role1 = new Role();
        $role1->name = 'Admin Role';
        $role1->slug = 'admin';
        $role1->description = 'Administrator role';
        $role1->save();

        $role2 = new Role();
        $role2->name = 'Editor Role';
        $role2->slug = 'editor';
        $role2->description = 'Editor role';
        $role2->save();

        $command = new RoleCommand();
        $input = $this->createMockInput([
            'action' => 'list'
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testDeleteRoleAction(): void
    {
        // Create test role directly in database
        $connection = db();
        $connection->insert(
            'INSERT INTO roles (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['Deletable Role', 'deletable-role', 'A role to be deleted', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        $command = new RoleCommand();
        $input = $this->createMockInput([
            'action' => 'delete',
            'name' => 'Deletable Role'
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
        
        // Verify role was deleted
        $deletedRole = $connection->selectOne('SELECT * FROM roles WHERE name = ?', ['Deletable Role']);
        $this->assertNull($deletedRole);
    }

    public function testAssignPermissionAction(): void
    {
        // Create test role and permission directly in database
        $connection = db();
        $connection->insert(
            'INSERT INTO roles (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['Assign Test Role', 'assign-test-role', 'A test role', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        $connection->insert(
            'INSERT INTO permissions (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['Assign Test Permission', 'assign-test-permission', 'A test permission', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        $command = new RoleCommand();
        $input = $this->createMockInput([
            'action' => 'assign',
            'name' => 'Assign Test Role'
        ], [
            'permissions' => 'Assign Test Permission'
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
        
        // Verify permission was assigned
        $rolePermission = $connection->selectOne(
            'SELECT rp.* FROM role_permissions rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.name = ? AND p.name = ?',
            ['Assign Test Role', 'Assign Test Permission']
        );
        $this->assertNotNull($rolePermission);
    }

    public function testRevokePermissionAction(): void
    {
        // Create test role and permission directly in database
        $connection = db();
        $connection->insert(
            'INSERT INTO roles (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['Revoke Test Role', 'revoke-test-role', 'A test role', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        $connection->insert(
            'INSERT INTO permissions (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['Revoke Test Permission', 'revoke-test-permission', 'A test permission', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        // Get the actual IDs
        $role = $connection->selectOne('SELECT id FROM roles WHERE name = ?', ['Revoke Test Role']);
        $permission = $connection->selectOne('SELECT id FROM permissions WHERE name = ?', ['Revoke Test Permission']);

        // Assign permission first
        $connection->insert(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
            [$role['id'], $permission['id']]
        );

        $command = new RoleCommand();
        $input = $this->createMockInput([
            'action' => 'revoke',
            'name' => 'Revoke Test Role'
        ], [
            'permissions' => 'Revoke Test Permission'
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
        
        // Verify permission was revoked
        $rolePermission = $connection->selectOne(
            'SELECT rp.* FROM role_permissions rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.name = ? AND p.name = ?',
            ['Revoke Test Role', 'Revoke Test Permission']
        );
        $this->assertNull($rolePermission);
    }


    public function testShowRoleAction(): void
    {
        // Create test role with permission directly in database
        $connection = db();
        $connection->insert(
            'INSERT INTO roles (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['Show Test Role', 'show-test-role', 'A test role', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        $connection->insert(
            'INSERT INTO permissions (name, slug, description, category, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            ['Show Test Permission', 'show-test-permission', 'A test permission', 'Test Category', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        // Get the actual IDs
        $role = $connection->selectOne('SELECT id FROM roles WHERE name = ?', ['Show Test Role']);
        $permission = $connection->selectOne('SELECT id FROM permissions WHERE name = ?', ['Show Test Permission']);

        // Assign permission to role
        $connection->insert(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
            [$role['id'], $permission['id']]
        );

        $command = new RoleCommand();
        $input = $this->createMockInput([
            'action' => 'show',
            'name' => 'Show Test Role'
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testInvalidActionReturnsHelp(): void
    {
        $command = new RoleCommand();
        $input = $this->createMockInput([
            'action' => 'invalid-action'
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result); // Shows help, returns 0
    }

    public function testCreateRoleWithoutRequiredOptionsReturnsError(): void
    {
        $command = new RoleCommand();
        $input = $this->createMockInput([
            'action' => 'create'
        ], [
            // Missing required name and slug options
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testDeleteNonExistentRoleReturnsError(): void
    {
        $command = new RoleCommand();
        $input = $this->createMockInput([
            'action' => 'delete'
        ], [
            'slug' => 'non-existent-role'
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testAssignNonExistentPermissionSkipsGracefully(): void
    {
        // Create test role
        $connection = db();
        $connection->insert(
            'INSERT INTO roles (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['NonExistent Test Role', 'nonexistent-test-role', 'A test role', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        $command = new RoleCommand();
        $input = $this->createMockInput([
            'action' => 'assign',
            'name' => 'NonExistent Test Role'
        ], [
            'permissions' => 'Non Existent Permission'
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result); // Command succeeds but skips non-existent permission
    }

    /**
     * Create a mock input interface
     */
    private function createMockInput(array $arguments = [], array $options = []): InputInterface
    {
        $input = $this->createMock(InputInterface::class);
        
        $input->method('hasArgument')
              ->willReturnCallback(fn($name) => isset($arguments[$name]));
              
        $input->method('getArgument')
              ->willReturnCallback(fn($name) => $arguments[$name] ?? null);
              
        $input->method('getArguments')
              ->willReturn($arguments);
              
        $input->method('hasOption')
              ->willReturnCallback(fn($name) => isset($options[$name]));
              
        $input->method('getOption')
              ->willReturnCallback(fn($name) => $options[$name] ?? null);
              
        $input->method('getOptions')
              ->willReturn($options);
              
        return $input;
    }

    /**
     * Create a mock output interface
     */
    private function createMockOutput(): OutputInterface
    {
        $output = $this->createMock(OutputInterface::class);
        
        $output->method('write');
        $output->method('writeln');
               
        return $output;
    }

}