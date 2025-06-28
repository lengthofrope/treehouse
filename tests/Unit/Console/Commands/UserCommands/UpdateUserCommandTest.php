<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\UserCommands;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Commands\UserCommands\UpdateUserCommand;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Database\Connection;

/**
 * Tests for UpdateUserCommand
 */
class UpdateUserCommandTest extends TestCase
{
    private UpdateUserCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new UpdateUserCommand();
        
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
        $this->assertEquals('user:update', $this->command->getName());
        $this->assertEquals('Update an existing user account', $this->command->getDescription());
        $this->assertStringContainsString('update user account information', $this->command->getHelp());

        $arguments = $this->command->getArguments();
        $this->assertArrayHasKey('identifier', $arguments);

        $options = $this->command->getOptions();
        $this->assertArrayHasKey('name', $options);
        $this->assertArrayHasKey('email', $options);
        $this->assertArrayHasKey('password', $options);
        $this->assertArrayHasKey('role', $options);
        $this->assertArrayHasKey('verify', $options);
        $this->assertArrayHasKey('unverify', $options);
        $this->assertArrayHasKey('interactive', $options);
    }

    public function testUserNotFound(): void
    {
        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser'])
            ->getMock();

        $command->method('findUser')->willReturn(null);

        $input = $this->createMockInput(['identifier' => 'nonexistent@example.com'], []);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testSuccessfulUserUpdate(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser', 'updateUser'])
            ->getMock();

        // First call returns existing user, second call returns updated user
        $command->method('findUser')
                ->willReturnOnConsecutiveCalls($existingUser, array_merge($existingUser, ['name' => 'New Name']));
        
        $command->method('updateUser')->willReturn(true);

        $input = $this->createMockInput(
            ['identifier' => 'old@example.com'],
            ['name' => 'New Name']
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testNoUpdatesSpecified(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);

        $input = $this->createMockInput(['identifier' => 'test@example.com'], []);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testEmailValidation(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['email' => 'invalid-email-format']
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testRoleValidation(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['role' => 'invalid-role']
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testPasswordLengthValidation(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['password' => '123'] // Too short
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testEmailUniquenessCheck(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser', 'emailExistsForOtherUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);
        $command->method('emailExistsForOtherUser')->willReturn(true);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['email' => 'existing@example.com']
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testFindUserById(): void
    {
        $sampleUser = [
            'id' => 123,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
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
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
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

    public function testEmailVerificationUpdate(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser', 'updateUser'])
            ->getMock();

        $command->method('findUser')
                ->willReturnOnConsecutiveCalls($existingUser, array_merge($existingUser, ['email_verified' => 1]));
        
        $command->method('updateUser')->willReturn(true);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['verify' => true]
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testEmailUnverificationUpdate(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 1,
            'email_verified_at' => '2024-01-01 10:00:00',
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser', 'updateUser'])
            ->getMock();

        $command->method('findUser')
                ->willReturnOnConsecutiveCalls($existingUser, array_merge($existingUser, ['email_verified' => 0]));
        
        $command->method('updateUser')->willReturn(true);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['unverify' => true]
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testInteractiveMode(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['interactive' => true]
        );
        $output = $this->createMockOutput();

        // Interactive mode will fail in automated testing due to stdin requirements
        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testUpdateFailure(): void
    {
        $existingUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'email_verified' => 0,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 10:00:00'
        ];

        $command = $this->getMockBuilder(UpdateUserCommand::class)
            ->onlyMethods(['findUser', 'updateUser'])
            ->getMock();

        $command->method('findUser')->willReturn($existingUser);
        $command->method('updateUser')->willReturn(false);

        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['name' => 'New Name']
        );
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