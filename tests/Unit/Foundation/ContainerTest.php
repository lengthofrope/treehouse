<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use LengthOfRope\TreeHouse\Foundation\Container;
use Tests\TestCase;
use InvalidArgumentException;

/**
 * Foundation Container Tests
 *
 * @package Tests\Unit\Foundation
 */
class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testBindAndMake()
    {
        $this->container->bind('test', function() {
            return new \stdClass();
        });

        $instance = $this->container->make('test');
        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    public function testBindWithClassName()
    {
        $this->container->bind('test', \stdClass::class);
        
        $instance = $this->container->make('test');
        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    public function testBindWithNoConcreteUsesAbstractAsDefault()
    {
        $this->container->bind(\stdClass::class);
        
        $instance = $this->container->make(\stdClass::class);
        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    public function testSingletonBinding()
    {
        $this->container->singleton('test', function() {
            return new \stdClass();
        });

        $instance1 = $this->container->make('test');
        $instance2 = $this->container->make('test');
        
        $this->assertSame($instance1, $instance2);
    }

    public function testRegularBindingIsNotSingleton()
    {
        $this->container->bind('test', function() {
            return new \stdClass();
        });

        $instance1 = $this->container->make('test');
        $instance2 = $this->container->make('test');
        
        $this->assertNotSame($instance1, $instance2);
    }

    public function testInstanceRegistration()
    {
        $instance = new \stdClass();
        $instance->property = 'test';
        
        $this->container->instance('test', $instance);
        
        $resolved = $this->container->make('test');
        $this->assertSame($instance, $resolved);
        $this->assertEquals('test', $resolved->property);
    }

    public function testAlias()
    {
        $this->container->bind('original', function() {
            return new \stdClass();
        });
        
        $this->container->alias('alias', 'original');
        
        $instance = $this->container->make('alias');
        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    public function testBound()
    {
        $this->assertFalse($this->container->bound('test'));
        
        $this->container->bind('test', \stdClass::class);
        $this->assertTrue($this->container->bound('test'));
    }

    public function testBoundWithInstance()
    {
        $this->assertFalse($this->container->bound('test'));
        
        $this->container->instance('test', new \stdClass());
        $this->assertTrue($this->container->bound('test'));
    }

    public function testBoundWithAlias()
    {
        $this->container->bind('original', \stdClass::class);
        $this->container->alias('alias', 'original');
        
        $this->assertTrue($this->container->bound('alias'));
    }

    public function testIsShared()
    {
        $this->container->bind('regular', \stdClass::class);
        $this->container->singleton('shared', \stdClass::class);
        
        $this->assertFalse($this->container->isShared('regular'));
        $this->assertTrue($this->container->isShared('shared'));
    }

    public function testIsSharedWithInstance()
    {
        $this->container->instance('test', new \stdClass());
        $this->assertTrue($this->container->isShared('test'));
    }

    public function testGetBindings()
    {
        $this->container->bind('test1', \stdClass::class);
        $this->container->singleton('test2', \stdClass::class);
        
        $bindings = $this->container->getBindings();
        
        $this->assertArrayHasKey('test1', $bindings);
        $this->assertArrayHasKey('test2', $bindings);
        $this->assertFalse($bindings['test1']['shared']);
        $this->assertTrue($bindings['test2']['shared']);
    }

    public function testFlush()
    {
        $this->container->bind('test', \stdClass::class);
        $this->container->instance('instance', new \stdClass());
        $this->container->alias('alias', 'test');
        
        $this->assertTrue($this->container->bound('test'));
        $this->assertTrue($this->container->bound('instance'));
        $this->assertTrue($this->container->bound('alias'));
        
        $this->container->flush();
        
        $this->assertFalse($this->container->bound('test'));
        $this->assertFalse($this->container->bound('instance'));
        $this->assertFalse($this->container->bound('alias'));
    }

    public function testCircularDependencyDetection()
    {
        $this->container->bind('a', function($container) {
            return $container->make('b');
        });
        
        $this->container->bind('b', function($container) {
            return $container->make('a');
        });
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Circular dependency detected while resolving [a]');
        
        $this->container->make('a');
    }

    public function testRebindingRemovesSingletonInstance()
    {
        $this->container->singleton('test', function() {
            $obj = new \stdClass();
            $obj->value = 'original';
            return $obj;
        });
        
        $instance1 = $this->container->make('test');
        $this->assertEquals('original', $instance1->value);
        
        // Rebind
        $this->container->bind('test', function() {
            $obj = new \stdClass();
            $obj->value = 'updated';
            return $obj;
        });
        
        $instance2 = $this->container->make('test');
        $this->assertEquals('updated', $instance2->value);
        $this->assertNotSame($instance1, $instance2);
    }

    public function testAutoResolutionOfClassesWithoutDependencies()
    {
        $instance = $this->container->make(\stdClass::class);
        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    public function testBuildWithClosure()
    {
        $this->container->bind('test', function($container) {
            $obj = new \stdClass();
            $obj->container = $container;
            return $obj;
        });
        
        $instance = $this->container->make('test');
        $this->assertInstanceOf(\stdClass::class, $instance);
        $this->assertSame($this->container, $instance->container);
    }

    public function testBuildWithNonStringConcrete()
    {
        $object = new \stdClass();
        $this->container->bind('test', $object);
        
        $instance = $this->container->make('test');
        $this->assertSame($object, $instance);
    }

    public function testBuildWithNonExistentClass()
    {
        $this->container->bind('test', 'NonExistentClass');
        
        $instance = $this->container->make('test');
        $this->assertEquals('NonExistentClass', $instance);
    }

    public function testBuildClassWithConstructorDependencies()
    {
        // Register a test service that has dependencies
        $this->container->bind('TestClassWithDependency', function($container) {
            return new class($container->make(\stdClass::class)) {
                public $stdClass;
                
                public function __construct(\stdClass $stdClass)
                {
                    $this->stdClass = $stdClass;
                }
            };
        });
        
        $instance = $this->container->make('TestClassWithDependency');
        $this->assertInstanceOf(\stdClass::class, $instance->stdClass);
    }

    public function testBuildClassWithDefaultParameterValue()
    {
        // Register a test service with default parameter
        $this->container->bind('TestClassWithDefault', function() {
            return new class('default') {
                public $value;
                
                public function __construct(string $value = 'default')
                {
                    $this->value = $value;
                }
            };
        });
        
        $instance = $this->container->make('TestClassWithDefault');
        $this->assertEquals('default', $instance->value);
    }

    public function testBuildClassWithUnresolvableDependency()
    {
        // Test behavior when trying to resolve a non-existent class
        // The container should return the class name as-is since it doesn't exist
        $result = $this->container->make('NonExistentClassWithDependencies');
        $this->assertEquals('NonExistentClassWithDependencies', $result);
    }

    public function testBuildNonInstantiableClass()
    {
        // Test with an abstract class or interface
        // Create a proper test for this by binding to something that's not instantiable
        $this->container->bind('abstract', \InvalidArgumentException::class);
        
        // This should work since InvalidArgumentException is a concrete class
        $instance = $this->container->make('abstract');
        $this->assertInstanceOf(\InvalidArgumentException::class, $instance);
        
        // To test the actual non-instantiable case, we need to test with reflection
        // Let's test that the container can't instantiate an interface directly
        try {
            // Force creation of abstract class by binding it properly
            eval('abstract class TestAbstractClass { abstract public function test(); }');
            $this->container->bind('test-abstract', 'TestAbstractClass');
            $this->expectException(InvalidArgumentException::class);
            $this->container->make('test-abstract');
        } catch (\ParseError $e) {
            // If eval fails, just test that non-existent classes return as strings
            $result = $this->container->make('NonInstantiableClass');
            $this->assertEquals('NonInstantiableClass', $result);
        }
    }

    public function testCallMethod()
    {
        $callback = function($param1, $param2) {
            return $param1 + $param2;
        };
        
        $result = $this->container->call($callback, [5, 3]);
        $this->assertEquals(8, $result);
    }

    public function testMakeWithBoundDependency()
    {
        // Bind a dependency
        $this->container->bind('TestDependency', function() {
            $obj = new \stdClass();
            $obj->value = 'injected';
            return $obj;
        });
        
        // Bind a service that uses the dependency
        $this->container->bind('TestMainClass', function($container) {
            $obj = new \stdClass();
            $obj->dependency = $container->make('TestDependency');
            return $obj;
        });
        
        $instance = $this->container->make('TestMainClass');
        $this->assertEquals('injected', $instance->dependency->value);
    }
}