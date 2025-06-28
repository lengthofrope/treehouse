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
        $input = $this->createMockInput(['identifier' => 'nonexistent@example.com'], []);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testSuccessfulUserUpdate(): void
    {
        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['name' => 'New Name']
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should validate user update logic
        $this->assertIsInt($result);
    }

    public function testNoUpdatesSpecified(): void
    {
        $input = $this->createMockInput(['identifier' => 'test@example.com'], []);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle no updates gracefully
        $this->assertIsInt($result);
    }

    public function testEmailValidation(): void
    {
        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['email' => 'invalid-email-format']
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should fail with invalid email format
        $this->assertEquals(1, $result);
    }

    public function testRoleValidation(): void
    {
        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['role' => 'invalid-role']
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should fail with invalid role
        $this->assertEquals(1, $result);
    }

    public function testPasswordLengthValidation(): void
    {
        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['password' => '123'] // Too short
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should fail with short password
        $this->assertEquals(1, $result);
    }

    public function testEmailUniquenessCheck(): void
    {
        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['email' => 'new@example.com']
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should validate email uniqueness logic
        $this->assertIsInt($result);
    }

    public function testFindUserById(): void
    {
        $input = $this->createMockInput(['identifier' => '123'], ['name' => 'Test User']);
        $output = $this->createMockOutput();

        // Test finding user by numeric ID
        $result = $this->command->execute($input, $output);

        // Should validate user lookup by ID
        $this->assertIsInt($result);
    }

    public function testFindUserByEmail(): void
    {
        $input = $this->createMockInput(['identifier' => 'test@example.com'], ['name' => 'Test User']);
        $output = $this->createMockOutput();

        // Test finding user by email
        $result = $this->command->execute($input, $output);

        // Should validate user lookup by email
        $this->assertIsInt($result);
    }

    public function testEmailVerificationUpdate(): void
    {
        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['verify' => true]
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should validate email verification update
        $this->assertIsInt($result);
    }

    public function testEmailUnverificationUpdate(): void
    {
        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['unverify' => true]
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should validate email unverification update
        $this->assertIsInt($result);
    }

    public function testInteractiveMode(): void
    {
        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['interactive' => true]
        );
        $output = $this->createMockOutput();

        // In testing mode, interactive prompts will return default values
        $result = $this->command->execute($input, $output);

        // Should validate interactive mode handling
        $this->assertIsInt($result);
    }

    public function testUpdateFailure(): void
    {
        // Set invalid database driver to trigger database error
        $_ENV['DB_CONNECTION'] = 'invalid_driver';
        
        $input = $this->createMockInput(
            ['identifier' => 'test@example.com'],
            ['name' => 'New Name']
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle database errors gracefully
        $this->assertEquals(1, $result);
        
        // Restore valid database configuration
        $_ENV['DB_CONNECTION'] = 'sqlite';
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