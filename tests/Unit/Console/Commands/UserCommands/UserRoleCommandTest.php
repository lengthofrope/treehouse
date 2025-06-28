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
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer'
        ];

        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['findUser', 'updateUserRole', 'confirm', 'ask'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);
        $command->method('updateUserRole')->willReturn(true);

        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => 'test@example.com', 'role' => 'admin'],
            ['force' => true]
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
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
        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['findUser'])
            ->getMock();

        $command->method('findUser')->willReturn(null);

        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => 'nonexistent@example.com', 'role' => 'admin'],
            []
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testAssignRoleAlreadyAssigned(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'admin'
        ];

        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['findUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);

        $input = $this->createMockInput(
            ['action' => 'assign', 'identifier' => 'test@example.com', 'role' => 'admin'],
            []
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testListUsersWithTableFormat(): void
    {
        $sampleUsers = [
            [
                'id' => 1,
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'created_at' => '2024-01-01 10:00:00'
            ],
            [
                'id' => 2,
                'name' => 'Regular User',
                'email' => 'user@example.com',
                'role' => 'viewer',
                'created_at' => '2024-01-02 10:00:00'
            ]
        ];

        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn($sampleUsers);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput(['action' => 'list'], ['format' => 'table']);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testListUsersWithJsonFormat(): void
    {
        $sampleUsers = [
            [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'editor',
                'created_at' => '2024-01-01 10:00:00'
            ]
        ];

        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn($sampleUsers);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput(['action' => 'list'], ['format' => 'json']);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testListUsersWithCsvFormat(): void
    {
        $sampleUsers = [
            [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'editor',
                'created_at' => '2024-01-01 10:00:00'
            ]
        ];

        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn($sampleUsers);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput(['action' => 'list'], ['format' => 'csv']);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testListUsersEmpty(): void
    {
        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn([]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput(['action' => 'list'], []);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
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
        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn([]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput(
            ['action' => 'bulk'],
            ['from-role' => 'viewer', 'to-role' => 'editor']
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testBulkRoleChangeSuccess(): void
    {
        $affectedUsers = [
            ['id' => 1, 'name' => 'User 1', 'email' => 'user1@example.com'],
            ['id' => 2, 'name' => 'User 2', 'email' => 'user2@example.com']
        ];

        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection', 'confirm', 'ask'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn($affectedUsers);
        $mockConnection->method('update')->willReturn(2);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput(
            ['action' => 'bulk'],
            ['from-role' => 'viewer', 'to-role' => 'editor', 'force' => true]
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testShowRoleStats(): void
    {
        $roleStats = [
            ['role' => 'admin', 'count' => 2],
            ['role' => 'editor', 'count' => 5],
            ['role' => 'viewer', 'count' => 10]
        ];

        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')
                      ->willReturnOnConsecutiveCalls($roleStats, [['total' => 17]]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput(['action' => 'stats'], []);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testFindUserById(): void
    {
        $sampleUser = [
            'id' => 123,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer'
        ];

        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn([$sampleUser]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findUser');
        $method->setAccessible(true);

        $result = $method->invoke($command, '123');

        $this->assertEquals($sampleUser, $result);
    }

    public function testFindUserByEmail(): void
    {
        $sampleUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer'
        ];

        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn([$sampleUser]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findUser');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'test@example.com');

        $this->assertEquals($sampleUser, $result);
    }

    public function testUpdateUserRoleSuccess(): void
    {
        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('update')->willReturn(1);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('updateUserRole');
        $method->setAccessible(true);

        $output = $this->createMockOutput();

        $result = $method->invoke($command, 1, 'admin', $output);

        $this->assertTrue($result);
    }

    public function testUpdateUserRoleFailure(): void
    {
        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('update')->willReturn(0);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('updateUserRole');
        $method->setAccessible(true);

        $output = $this->createMockOutput();

        $result = $method->invoke($command, 1, 'admin', $output);

        $this->assertFalse($result);
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
        $command = $this->getMockBuilder(UserRoleCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willThrowException(new \Exception('Database error'));

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput(['action' => 'list'], []);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
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