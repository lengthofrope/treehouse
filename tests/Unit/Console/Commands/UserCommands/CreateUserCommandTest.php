<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\UserCommands;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Commands\UserCommands\CreateUserCommand;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Security\Hash;
use LengthOfRope\TreeHouse\Support\Env;

/**
 * Tests for CreateUserCommand
 */
class CreateUserCommandTest extends TestCase
{
    private CreateUserCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new CreateUserCommand();
        
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
        $this->assertEquals('user:create', $this->command->getName());
        $this->assertEquals('Create a new user account', $this->command->getDescription());
        $this->assertStringContainsString('create a new user account', $this->command->getHelp());

        $arguments = $this->command->getArguments();
        $this->assertArrayHasKey('name', $arguments);
        $this->assertArrayHasKey('email', $arguments);

        $options = $this->command->getOptions();
        $this->assertArrayHasKey('role', $options);
        $this->assertArrayHasKey('password', $options);
        $this->assertArrayHasKey('interactive', $options);
        $this->assertArrayHasKey('verified', $options);
    }

    public function testValidationWithMissingNameAndEmail(): void
    {
        $input = $this->createMockInput([], ['interactive' => false]);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testEmailValidation(): void
    {
        // Mock getDatabaseConnection method to avoid actual database connection
        $command = $this->getMockBuilder(CreateUserCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn([]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        // Test with invalid email
        $userData = [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
            'role' => 'viewer',
            'email_verified' => false
        ];

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getUserData');
        $method->setAccessible(true);

        $input = $this->createMockInput(
            ['name' => 'Test User', 'email' => 'invalid-email'],
            ['role' => 'viewer', 'password' => 'password123', 'interactive' => false]
        );
        $output = $this->createMockOutput();

        $result = $method->invoke($command, $input, $output);

        // Should return null due to invalid email
        $this->assertNull($result);
    }

    public function testRoleValidation(): void
    {
        $input = $this->createMockInput(
            ['name' => 'Test User', 'email' => 'test@example.com'],
            ['role' => 'invalid-role', 'password' => 'password123', 'interactive' => false]
        );
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testPasswordMinimumLength(): void
    {
        // Mock the command to avoid stdin interaction during password input
        $command = $this->getMockBuilder(CreateUserCommand::class)
            ->onlyMethods(['askForPassword'])
            ->getMock();

        // Mock password method to return a short password
        $command->method('askForPassword')->willReturn('123');

        $input = $this->createMockInput(
            ['name' => 'Test User', 'email' => 'test@example.com'],
            ['role' => 'viewer', 'interactive' => false]
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        // Should fail due to short password
        $this->assertEquals(1, $result);
    }

    public function testSuccessfulUserCreationConfiguration(): void
    {
        // Mock getDatabaseConnection and createUser methods
        $command = $this->getMockBuilder(CreateUserCommand::class)
            ->onlyMethods(['getDatabaseConnection', 'emailExists', 'createUser'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $command->method('getDatabaseConnection')->willReturn($mockConnection);
        $command->method('emailExists')->willReturn(false);
        $command->method('createUser')->willReturn([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'viewer'
        ]);

        $input = $this->createMockInput(
            ['name' => 'Test User', 'email' => 'test@example.com'],
            ['role' => 'viewer', 'password' => 'password123', 'interactive' => false]
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testInteractiveModeTriggering(): void
    {
        // Mock the command to avoid stdin interaction
        $command = $this->getMockBuilder(CreateUserCommand::class)
            ->onlyMethods(['getInteractiveUserData'])
            ->getMock();

        // Mock interactive method to return null (simulating user cancellation)
        $command->method('getInteractiveUserData')->willReturn(null);

        $input = $this->createMockInput([], ['interactive' => true]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testEmailUniquenessCheck(): void
    {
        // Mock getDatabaseConnection method
        $command = $this->getMockBuilder(CreateUserCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn([['id' => 1]]); // Email exists

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput(
            ['name' => 'Test User', 'email' => 'existing@example.com'],
            ['role' => 'viewer', 'password' => 'password123', 'interactive' => false]
        );
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testDefaultRoleAssignment(): void
    {
        $options = $this->command->getOptions();
        $this->assertEquals('viewer', $options['role']['default']);
    }

    public function testDatabaseConfigurationMethod(): void
    {
        // Test that getDatabaseConnection method properly loads environment variables
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getDatabaseConnection');
        $method->setAccessible(true);

        $connection = $method->invoke($this->command);

        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testHashPasswordSecurity(): void
    {
        $hash = new Hash();
        $password = 'testpassword123';
        $hashedPassword = $hash->make($password);

        $this->assertNotEquals($password, $hashedPassword);
        $this->assertTrue($hash->check($password, $hashedPassword));
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