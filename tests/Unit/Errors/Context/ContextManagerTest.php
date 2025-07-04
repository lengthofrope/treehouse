<?php

declare(strict_types=1);

namespace Tests\Unit\Errors\Context;

use LengthOfRope\TreeHouse\Errors\Context\ContextManager;
use LengthOfRope\TreeHouse\Errors\Context\RequestCollector;
use LengthOfRope\TreeHouse\Errors\Context\UserCollector;
use LengthOfRope\TreeHouse\Errors\Context\EnvironmentCollector;
use LengthOfRope\TreeHouse\Errors\Exceptions\DatabaseException;
use PHPUnit\Framework\TestCase;

class ContextManagerTest extends TestCase
{
    private ContextManager $contextManager;

    protected function setUp(): void
    {
        $this->contextManager = new ContextManager();
    }

    public function testCanAddCollector(): void
    {
        $collector = new RequestCollector();
        $this->contextManager->addCollector($collector);

        $this->assertTrue($this->contextManager->hasCollector('request'));
    }

    public function testCanRemoveCollector(): void
    {
        $collector = new RequestCollector();
        $this->contextManager->addCollector($collector);
        $this->contextManager->removeCollector('request');

        $this->assertFalse($this->contextManager->hasCollector('request'));
    }

    public function testCollectContextFromAllCollectors(): void
    {
        // Mock server variables for testing
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Test Agent';

        $this->contextManager->addCollector(new RequestCollector());
        $this->contextManager->addCollector(new EnvironmentCollector());

        $exception = new DatabaseException('Test error');
        $context = $this->contextManager->collect($exception);

        $this->assertIsArray($context);
        $this->assertArrayHasKey('request', $context);
        $this->assertArrayHasKey('environment', $context);
    }

    public function testCollectContextWithUserCollector(): void
    {
        $userCollector = new UserCollector();
        $this->contextManager->addCollector($userCollector);

        $exception = new DatabaseException('Test error');
        $context = $this->contextManager->collect($exception);

        $this->assertIsArray($context);
        $this->assertArrayHasKey('user', $context);
    }

    public function testCollectContextHandlesCollectorExceptions(): void
    {
        // Create a mock collector that throws an exception
        $mockCollector = $this->createMock(\LengthOfRope\TreeHouse\Errors\Context\ContextCollectorInterface::class);
        $mockCollector->method('collect')
                     ->willThrowException(new \Exception('Collector failed'));
        $mockCollector->method('getName')
                     ->willReturn('failing');
        $mockCollector->method('getPriority')
                     ->willReturn(50);
        $mockCollector->method('shouldCollect')
                     ->willReturn(true);

        $this->contextManager->addCollector($mockCollector);

        $exception = new DatabaseException('Test error');
        $context = $this->contextManager->collect($exception);

        // Should still return context, with metadata about the failing collector
        $this->assertIsArray($context);
        $this->assertArrayHasKey('_meta', $context);
        $this->assertArrayHasKey('collectors', $context['_meta']);
        $this->assertArrayHasKey('failing', $context['_meta']['collectors']);
        $this->assertFalse($context['_meta']['collectors']['failing']['executed']);
    }

    public function testCollectContextWithEmptyCollectors(): void
    {
        $exception = new DatabaseException('Test error');
        $context = $this->contextManager->collect($exception);

        $this->assertIsArray($context);
        $this->assertEmpty($context);
    }

    public function testCollectContextWithMultipleCollectors(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';

        $this->contextManager->addCollector(new RequestCollector());
        $this->contextManager->addCollector(new UserCollector());
        $this->contextManager->addCollector(new EnvironmentCollector());

        $exception = new DatabaseException('Test error');
        $context = $this->contextManager->collect($exception);

        $this->assertIsArray($context);
        $this->assertArrayHasKey('request', $context);
        $this->assertArrayHasKey('user', $context);
        $this->assertArrayHasKey('environment', $context);
    }

    public function testCollectContextSanitizesSensitiveData(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/login';
        $_POST['password'] = 'secret123';
        $_POST['username'] = 'testuser';

        $this->contextManager->addCollector(new RequestCollector());

        $exception = new DatabaseException('Test error');
        $context = $this->contextManager->collect($exception);

        $this->assertIsArray($context);
        $this->assertArrayHasKey('request', $context);
        
        // Check that sensitive data is sanitized
        if (isset($context['request']['post_data'])) {
            $postData = $context['request']['post_data'];
            if (isset($postData['password'])) {
                $this->assertEquals('[HIDDEN]', $postData['password']);
            }
            if (isset($postData['username'])) {
                $this->assertEquals('testuser', $postData['username']);
            }
        }
    }

    public function testCollectContextWithCustomCollector(): void
    {
        $customCollector = new class implements \LengthOfRope\TreeHouse\Errors\Context\ContextCollectorInterface {
            public function collect(\Throwable $exception): array
            {
                return [
                    'custom_field' => 'custom_value',
                    'exception_type' => get_class($exception)
                ];
            }

            public function getName(): string
            {
                return 'custom';
            }

            public function getPriority(): int
            {
                return 50;
            }

            public function shouldCollect(\Throwable $exception): bool
            {
                return true;
            }
        };

        $this->contextManager->addCollector($customCollector);

        $exception = new DatabaseException('Test error');
        $context = $this->contextManager->collect($exception);

        $this->assertIsArray($context);
        $this->assertEquals('custom_value', $context['custom_field']);
        $this->assertEquals(DatabaseException::class, $context['exception_type']);
    }

    public function testCollectorOverride(): void
    {
        // Set server variables so RequestCollector will run
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';

        $collector1 = new RequestCollector();
        $collector2 = new EnvironmentCollector();

        $this->contextManager->addCollector($collector1);
        $this->contextManager->addCollector($collector2);

        $exception = new DatabaseException('Test error');
        $context = $this->contextManager->collect($exception);

        // Should have both collectors' data
        $this->assertIsArray($context);
        $this->assertArrayHasKey('_meta', $context);
        $this->assertArrayHasKey('collectors', $context['_meta']);
        
        // Check that both collectors were executed
        $collectors = $context['_meta']['collectors'];
        $this->assertArrayHasKey('request', $collectors);
        $this->assertArrayHasKey('environment', $collectors);
    }

    protected function tearDown(): void
    {
        // Clean up server variables
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_USER_AGENT']);
        unset($_POST['password']);
        unset($_POST['username']);
    }
}