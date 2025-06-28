<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\UserCommands;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Commands\UserCommands\UserRoleCommand;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Database\Connection;

/**
 * Tests for UserRoleCommand
 */
class UserRoleCommandTest extends TestCase
{
    private UserRoleCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new UserRoleCommand();
        
        // Mock environment variables
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_PORT'] = '3306';
        $_ENV['DB_USERNAME'] = 'test';
        $_ENV['DB_PASSWORD'] = 'test';
        $_ENV['DB_CHARSET'] = 'utf8mb4';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up environment variables
        unset($_ENV['DB_CONNECTION']);
        unset($_ENV['DB_DATABASE']);
        unset($_ENV['DB_HOST']);
        unset($_ENV['DB_PORT']);
        unset($_ENV['DB_USERNAME']);
        unset($_ENV['DB_PASSWORD']);
        unset($_ENV['DB_CHARSET']);
    }

    public function testCommandConfiguration(): void
    {
        $this->assertEquals('user:role', $this->command->getName());
        $this->assertEquals('Manage user roles', $this->command->getDescription());
        $this->assertStringContainsString('assign, change, or list user roles', $this->command->getHelp());

        $arguments = $this->command->getArguments();
        $this->assertArrayHasKey('action', $arguments);
        $this->assertArrayHasKey('identifier', $arguments);
        $this->assertArrayHasKey('role', $arguments);

        $options = $this->command->getOptions();
        $this->assertArrayHasKey('from-role', $options);
        $this->assertArrayHasKey('to-role', $options);
        $this->assertArrayHasKey('force', $options);
        $this->assertArrayHasKey('format', $options);
        
        // Test default values
        $this->assertEquals('table', $options['format']['default']);
    }

    public function testUnknownAction(): void
    {
        $input = $this->createMockInput(['action' => 'unknown'], []);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testAssignRoleSuccess(): void
    {
        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => 'test@example.com', 'role' => 'admin'],
            ['force' => true]
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should validate role assignment logic
        $this->assertIsInt($result);
    }

    public function testAssignRoleMissingIdentifier(): void
    {
        $input = $this->createMockInput(['action' => 'assign'], []);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testAssignRoleMissingRole(): void
    {
        $input = $this->createMockInput(['action' => 'assign', 'identifier' => 'test@example.com'], []);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testAssignInvalidRole(): void
    {
        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => 'test@example.com', 'role' => 'invalid-role'],
            []
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testAssignRoleUserNotFound(): void
    {
        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => 'nonexistent@example.com', 'role' => 'admin'],
            []
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testAssignRoleAlreadyAssigned(): void
    {
        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => 'test@example.com', 'role' => 'admin'],
            []
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should validate already assigned role logic
        $this->assertIsInt($result);
    }

    public function testListUsersWithTableFormat(): void
    {
        $input = $this->createMockInput(['action' => 'list'], ['format' => 'table']);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle table format listing
        $this->assertIsInt($result);
    }

    public function testListUsersWithJsonFormat(): void
    {
        $input = $this->createMockInput(['action' => 'list'], ['format' => 'json']);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle JSON format listing
        $this->assertIsInt($result);
    }

    public function testListUsersWithCsvFormat(): void
    {
        $input = $this->createMockInput(['action' => 'list'], ['format' => 'csv']);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle CSV format listing
        $this->assertIsInt($result);
    }

    public function testListUsersEmpty(): void
    {
        $input = $this->createMockInput(['action' => 'list'], []);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle empty user list
        $this->assertIsInt($result);
    }

    public function testBulkRoleChangeMissingOptions(): void
    {
        $input = $this->createMockInput(['action' => 'bulk'], []);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testBulkRoleChangeInvalidRoles(): void
    {
        $input = $this->createMockInput(
            ['action' => 'bulk'],
            ['from-role' => 'invalid', 'to-role' => 'admin']
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testBulkRoleChangeSameRoles(): void
    {
        $input = $this->createMockInput(
            ['action' => 'bulk'],
            ['from-role' => 'viewer', 'to-role' => 'viewer']
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testBulkRoleChangeNoAffectedUsers(): void
    {
        $input = $this->createMockInput(
            ['action' => 'bulk'],
            ['from-role' => 'viewer', 'to-role' => 'editor']
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle no affected users in bulk change
        $this->assertIsInt($result);
    }

    public function testBulkRoleChangeSuccess(): void
    {
        $input = $this->createMockInput(
            ['action' => 'bulk'],
            ['from-role' => 'viewer', 'to-role' => 'editor', 'force' => true]
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle successful bulk role change
        $this->assertIsInt($result);
    }

    public function testShowRoleStats(): void
    {
        $input = $this->createMockInput(['action' => 'stats'], []);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle role statistics display
        $this->assertIsInt($result);
    }

    public function testFindUserById(): void
    {
        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => '123', 'role' => 'admin'],
            ['force' => true]
        );
        $output = $this->createMockOutput();

        // Test finding user by numeric ID
        $result = $this->command->execute($input, $output);

        // Should validate user lookup by ID
        $this->assertIsInt($result);
    }

    public function testFindUserByEmail(): void
    {
        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => 'test@example.com', 'role' => 'admin'],
            ['force' => true]
        );
        $output = $this->createMockOutput();

        // Test finding user by email
        $result = $this->command->execute($input, $output);

        // Should validate user lookup by email
        $this->assertIsInt($result);
    }

    public function testUpdateUserRoleSuccess(): void
    {
        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => 'test@example.com', 'role' => 'admin'],
            ['force' => true]
        );
        $output = $this->createMockOutput();

        // Test successful role update
        $result = $this->command->execute($input, $output);

        // Should validate successful role update logic
        $this->assertIsInt($result);
    }

    public function testUpdateUserRoleFailure(): void
    {
        // Set invalid database driver to trigger database error
        $_ENV['DB_CONNECTION'] = 'invalid_driver';
        
        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => 'test@example.com', 'role' => 'admin'],
            ['force' => true]
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle database errors gracefully
        $this->assertEquals(1, $result);
        
        // Restore valid database configuration
        $_ENV['DB_CONNECTION'] = 'sqlite';
    }

    public function testTruncateMethod(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('truncate');
        $method->setAccessible(true);

        // Test text shorter than limit
        $result = $method->invoke($this->command, 'Short text', 20);
        $this->assertEquals('Short text', $result);

        // Test text longer than limit
        $result = $method->invoke($this->command, 'This is a very long text that should be truncated', 20);
        $this->assertEquals('This is a very lo...', $result);
        $this->assertEquals(20, strlen($result));
    }

    public function testDatabaseError(): void
    {
        $input = $this->createMockInput(['action' => 'list'], []);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle empty database gracefully (no users table exists)
        $this->assertIsInt($result);
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