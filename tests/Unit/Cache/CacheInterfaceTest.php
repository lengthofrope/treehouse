<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use LengthOfRope\TreeHouse\Cache\CacheInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Cache Interface Test
 * 
 * Tests the CacheInterface contract to ensure all required methods are defined.
 */
class CacheInterfaceTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(CacheInterface::class));
    }

    public function testInterfaceHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        
        $expectedMethods = [
            'put',
            'get',
            'has',
            'forget',
            'flush',
            'remember',
            'rememberForever',
            'increment',
            'decrement',
            'forever',
            'many',
            'putMany'
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "CacheInterface should have method: {$method}"
            );
        }
    }

    public function testPutMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('put');
        
        $this->assertEquals('put', $method->getName());
        $this->assertEquals(3, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('key', $parameters[0]->getName());
        $this->assertEquals('value', $parameters[1]->getName());
        $this->assertEquals('ttl', $parameters[2]->getName());
        $this->assertTrue($parameters[2]->allowsNull());
    }

    public function testGetMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('get');
        
        $this->assertEquals('get', $method->getName());
        $this->assertEquals(2, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('key', $parameters[0]->getName());
        $this->assertEquals('default', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->isOptional());
    }

    public function testHasMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('has');
        
        $this->assertEquals('has', $method->getName());
        $this->assertEquals(1, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('key', $parameters[0]->getName());
    }

    public function testForgetMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('forget');
        
        $this->assertEquals('forget', $method->getName());
        $this->assertEquals(1, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('key', $parameters[0]->getName());
    }

    public function testFlushMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('flush');
        
        $this->assertEquals('flush', $method->getName());
        $this->assertEquals(0, $method->getNumberOfParameters());
    }

    public function testRememberMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('remember');
        
        $this->assertEquals('remember', $method->getName());
        $this->assertEquals(3, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('key', $parameters[0]->getName());
        $this->assertEquals('ttl', $parameters[1]->getName());
        $this->assertEquals('callback', $parameters[2]->getName());
        $this->assertTrue($parameters[1]->allowsNull());
    }

    public function testRememberForeverMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('rememberForever');
        
        $this->assertEquals('rememberForever', $method->getName());
        $this->assertEquals(2, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('key', $parameters[0]->getName());
        $this->assertEquals('callback', $parameters[1]->getName());
    }

    public function testIncrementMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('increment');
        
        $this->assertEquals('increment', $method->getName());
        $this->assertEquals(2, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('key', $parameters[0]->getName());
        $this->assertEquals('value', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->isOptional());
    }

    public function testDecrementMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('decrement');
        
        $this->assertEquals('decrement', $method->getName());
        $this->assertEquals(2, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('key', $parameters[0]->getName());
        $this->assertEquals('value', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->isOptional());
    }

    public function testForeverMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('forever');
        
        $this->assertEquals('forever', $method->getName());
        $this->assertEquals(2, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('key', $parameters[0]->getName());
        $this->assertEquals('value', $parameters[1]->getName());
    }

    public function testManyMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('many');
        
        $this->assertEquals('many', $method->getName());
        $this->assertEquals(1, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('keys', $parameters[0]->getName());
    }

    public function testPutManyMethodSignature(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod('putMany');
        
        $this->assertEquals('putMany', $method->getName());
        $this->assertEquals(2, $method->getNumberOfParameters());
        
        $parameters = $method->getParameters();
        $this->assertEquals('values', $parameters[0]->getName());
        $this->assertEquals('ttl', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->allowsNull());
    }
}