<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\UserCommands;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Commands\UserCommands\DeleteUserCommand;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Database\Connection;

/**
 * Tests for DeleteUserCommand
 */
class DeleteUserCommandTest extends TestCase
{
    private DeleteUserCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new DeleteUserCommand();
        
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
        $this->assertEquals('user:delete', $this->command->getName());
        $this->assertEquals('Delete a user account', $this->command->getDescription());
        $this->assertStringContainsString('delete a user account', $this->command->getHelp());

        $arguments = $this->command->getArguments();
        $this->assertArrayHasKey('identifier', $arguments);

        $options = $this->command->getOptions();
        $this->assertArrayHasKey('force', $options);
        $this->assertArrayHasKey('soft', $options);
    }

    public function testUserNotFound(): void
    {
        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['findUser'])
            ->getMock();

        $command->method('findUser')->willReturn(null);

        $input = $this->createMockInput(['identifier' => 'nonexistent@example.com'], []);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testForcedHardDelete(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['findUser', 'deleteUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);
        $command->method('deleteUser')->willReturn(true);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['force' => true]
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testForcedSoftDelete(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['findUser', 'softDeleteUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);
        $command->method('softDeleteUser')->willReturn(true);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['force' => true, 'soft' => true]
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testInteractiveDeleteWithoutConfirmation(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['findUser', 'confirmDeletion'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);
        $command->method('confirmDeletion')->willReturn(false);

        $input = $this->createMockInput(['identifier' => 'test@example.com'], []);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result); // Cancelled deletion should return 0
    }

    public function testFindUserById(): void
    {
        $sampleUser = [
            'id' => 123,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(DeleteUserCommand::class)
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
            'role' => 'viewer',
            'email_verified' => 0,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(DeleteUserCommand::class)
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

    public function testSoftDeleteWithDeletedAtColumn(): void
    {
        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('getTableColumns')->willReturn(['id', 'name', 'email', 'deleted_at']);
        $mockConnection->method('update')->willReturn(1);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('softDeleteUser');
        $method->setAccessible(true);

        $output = $this->createMockOutput();

        $result = $method->invoke($command, 1, $output);

        $this->assertTrue($result);
    }

    public function testSoftDeleteWithoutDeletedAtColumn(): void
    {
        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['getDatabaseConnection', 'deleteUser'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('getTableColumns')->willReturn(['id', 'name', 'email']); // No deleted_at

        $command->method('getDatabaseConnection')->willReturn($mockConnection);
        $command->method('deleteUser')->willReturn(true);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('softDeleteUser');
        $method->setAccessible(true);

        $output = $this->createMockOutput();

        $result = $method->invoke($command, 1, $output);

        $this->assertTrue($result);
    }

    public function testSoftDeleteUserAlreadyDeleted(): void
    {
        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('getTableColumns')->willReturn(['id', 'name', 'email', 'deleted_at']);
        $mockConnection->method('update')->willReturn(0); // No rows affected

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('softDeleteUser');
        $method->setAccessible(true);

        $output = $this->createMockOutput();

        $result = $method->invoke($command, 1, $output);

        $this->assertFalse($result);
    }

    public function testHardDeleteSuccess(): void
    {
        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('delete')->willReturn(1);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('deleteUser');
        $method->setAccessible(true);

        $output = $this->createMockOutput();

        $result = $method->invoke($command, 1, $output);

        $this->assertTrue($result);
    }

    public function testHardDeleteUserNotFound(): void
    {
        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('delete')->willReturn(0); // No rows affected

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('deleteUser');
        $method->setAccessible(true);

        $output = $this->createMockOutput();

        $result = $method->invoke($command, 1, $output);

        $this->assertFalse($result);
    }

    public function testDeleteFailure(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['findUser', 'deleteUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);
        $command->method('deleteUser')->willReturn(false);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['force' => true]
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testDatabaseError(): void
    {
        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['findUser'])
            ->getMock();

        $command->method('findUser')->willThrowException(new \Exception('Database error'));

        $input = $this->createMockInput(['identifier' => 'test@example.com'], []);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testSoftDeleteDatabaseError(): void
    {
        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('getTableColumns')->willThrowException(new \Exception('Database error'));

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('softDeleteUser');
        $method->setAccessible(true);

        $output = $this->createMockOutput();

        $result = $method->invoke($command, 1, $output);

        $this->assertFalse($result);
    }

    public function testHardDeleteDatabaseError(): void
    {
        $command = $this->getMockBuilder(DeleteUserCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('delete')->willThrowException(new \Exception('Database error'));

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('deleteUser');
        $method->setAccessible(true);

        $output = $this->createMockOutput();

        $result = $method->invoke($command, 1, $output);

        $this->assertFalse($result);
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