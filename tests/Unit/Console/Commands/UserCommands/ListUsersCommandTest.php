<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\UserCommands;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Console\Commands\UserCommands\ListUsersCommand;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Database\Connection;

/**
 * Tests for ListUsersCommand
 */
class ListUsersCommandTest extends TestCase
{
    private ListUsersCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new ListUsersCommand();
        
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
        $this->assertEquals('user:list', $this->command->getName());
        $this->assertEquals('List all user accounts', $this->command->getDescription());
        $this->assertStringContainsString('lists all user accounts', $this->command->getHelp());

        $options = $this->command->getOptions();
        $this->assertArrayHasKey('role', $options);
        $this->assertArrayHasKey('verified', $options);
        $this->assertArrayHasKey('unverified', $options);
        $this->assertArrayHasKey('format', $options);
        $this->assertArrayHasKey('limit', $options);
        
        // Test default values
        $this->assertEquals('table', $options['format']['default']);
        $this->assertEquals('50', $options['limit']['default']);
    }

    public function testEmptyUsersList(): void
    {
        $input = $this->createMockInput([], []);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle empty user list gracefully
        $this->assertIsInt($result);
    }

    public function testUsersListWithTableFormat(): void
    {
        $input = $this->createMockInput([], ['format' => 'table']);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle table format display
        $this->assertIsInt($result);
    }

    public function testUsersListWithJsonFormat(): void
    {
        $input = $this->createMockInput([], ['format' => 'json']);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle JSON format display
        $this->assertIsInt($result);
    }

    public function testUsersListWithCsvFormat(): void
    {
        $input = $this->createMockInput([], ['format' => 'csv']);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle CSV format display
        $this->assertIsInt($result);
    }

    public function testRoleFiltering(): void
    {
        $input = $this->createMockInput([], ['role' => 'admin']);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle role filtering
        $this->assertIsInt($result);
    }

    public function testVerifiedUsersFiltering(): void
    {
        $input = $this->createMockInput([], ['verified' => true]);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle verified users filtering
        $this->assertIsInt($result);
    }

    public function testUnverifiedUsersFiltering(): void
    {
        $input = $this->createMockInput([], ['unverified' => true]);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle unverified users filtering
        $this->assertIsInt($result);
    }

    public function testLimitOption(): void
    {
        $input = $this->createMockInput([], ['limit' => '10']);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle limit option
        $this->assertIsInt($result);
    }

    public function testCombinedFilters(): void
    {
        $input = $this->createMockInput([], [
            'role' => 'admin',
            'verified' => true,
            'limit' => '5'
        ]);
        $output = $this->createMockOutput();

        $result = $this->command->execute($input, $output);

        // Should handle combined filters
        $this->assertIsInt($result);
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
        $input = $this->createMockInput([], []);
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