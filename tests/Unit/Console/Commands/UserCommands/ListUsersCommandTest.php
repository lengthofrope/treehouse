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
        $command = $this->getMockBuilder(ListUsersCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn([]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput([], []);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testUsersListWithTableFormat(): void
    {
        $sampleUsers = [
            [
                'id' => 1,
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'email_verified' => 1,
                'email_verified_at' => '2024-01-01 10:00:00',
                'created_at' => '2024-01-01 10:00:00'
            ],
            [
                'id' => 2,
                'name' => 'Regular User',
                'email' => 'user@example.com',
                'role' => 'viewer',
                'email_verified' => 0,
                'email_verified_at' => null,
                'created_at' => '2024-01-02 10:00:00'
            ]
        ];

        $command = $this->getMockBuilder(ListUsersCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn($sampleUsers);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput([], ['format' => 'table']);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testUsersListWithJsonFormat(): void
    {
        $sampleUsers = [
            [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'editor',
                'email_verified' => 1,
                'email_verified_at' => '2024-01-01 10:00:00',
                'created_at' => '2024-01-01 10:00:00'
            ]
        ];

        $command = $this->getMockBuilder(ListUsersCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn($sampleUsers);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput([], ['format' => 'json']);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testUsersListWithCsvFormat(): void
    {
        $sampleUsers = [
            [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'editor',
                'email_verified' => 1,
                'email_verified_at' => '2024-01-01 10:00:00',
                'created_at' => '2024-01-01 10:00:00'
            ]
        ];

        $command = $this->getMockBuilder(ListUsersCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willReturn($sampleUsers);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput([], ['format' => 'csv']);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testRoleFiltering(): void
    {
        $command = $this->getMockBuilder(ListUsersCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        
        // Expect the query to include role filter
        $mockConnection->expects($this->once())
                      ->method('select')
                      ->with(
                          $this->stringContains('WHERE role = ?'),
                          $this->equalTo(['admin'])
                      )
                      ->willReturn([]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput([], ['role' => 'admin']);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testVerifiedUsersFiltering(): void
    {
        $command = $this->getMockBuilder(ListUsersCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        
        // Expect the query to include verified filter
        $mockConnection->expects($this->once())
                      ->method('select')
                      ->with(
                          $this->stringContains('WHERE email_verified = 1'),
                          $this->equalTo([])
                      )
                      ->willReturn([]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput([], ['verified' => true]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testUnverifiedUsersFiltering(): void
    {
        $command = $this->getMockBuilder(ListUsersCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        
        // Expect the query to include unverified filter
        $mockConnection->expects($this->once())
                      ->method('select')
                      ->with(
                          $this->stringContains('WHERE email_verified = 0'),
                          $this->equalTo([])
                      )
                      ->willReturn([]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput([], ['unverified' => true]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testLimitOption(): void
    {
        $command = $this->getMockBuilder(ListUsersCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        
        // Expect the query to include LIMIT clause
        $mockConnection->expects($this->once())
                      ->method('select')
                      ->with(
                          $this->stringContains('LIMIT 10'),
                          $this->equalTo([])
                      )
                      ->willReturn([]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput([], ['limit' => '10']);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testCombinedFilters(): void
    {
        $command = $this->getMockBuilder(ListUsersCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        
        // Expect the query to include both role and verified filters
        $mockConnection->expects($this->once())
                      ->method('select')
                      ->with(
                          $this->logicalAnd(
                              $this->stringContains('WHERE role = ? AND email_verified = 1'),
                              $this->stringContains('LIMIT 5')
                          ),
                          $this->equalTo(['admin'])
                      )
                      ->willReturn([]);

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput([], [
            'role' => 'admin',
            'verified' => true,
            'limit' => '5'
        ]);
        $output = $this->createMockOutput();

        $result = $command->execute($input, $output);

        $this->assertEquals(0, $result);
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
        $command = $this->getMockBuilder(ListUsersCommand::class)
            ->onlyMethods(['getDatabaseConnection'])
            ->getMock();

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('select')->willThrowException(new \Exception('Database error'));

        $command->method('getDatabaseConnection')->willReturn($mockConnection);

        $input = $this->createMockInput([], []);
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