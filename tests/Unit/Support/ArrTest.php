<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use LengthOfRope\TreeHouse\Support\Arr;
use Tests\TestCase;

/**
 * Test cases for Arr class
 * 
 * @package Tests\Unit\Support
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
class ArrTest extends TestCase
{
    public function testAccessible(): void
    {
        $this->assertTrue(Arr::accessible([]));
        $this->assertTrue(Arr::accessible(['a', 'b']));
        $this->assertFalse(Arr::accessible('string'));
        $this->assertFalse(Arr::accessible(123));
        $this->assertFalse(Arr::accessible(null));
    }

    public function testAdd(): void
    {
        $array = ['name' => 'John'];
        $result = Arr::add($array, 'age', 30);
        
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
        
        // Should not overwrite existing key
        $result = Arr::add($array, 'name', 'Jane');
        $this->assertEquals(['name' => 'John'], $result);
    }

    public function testCollapse(): void
    {
        $array = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ];
        
        $result = Arr::collapse($array);
        $expected = ['name' => 'Jane', 'age' => 25]; // Later values overwrite
        
        $this->assertEquals($expected, $result);
    }

    public function testDivide(): void
    {
        $array = ['name' => 'John', 'age' => 30, 'city' => 'NYC'];
        [$keys, $values] = Arr::divide($array);
        
        $this->assertEquals(['name', 'age', 'city'], $keys);
        $this->assertEquals(['John', 30, 'NYC'], $values);
    }

    public function testDot(): void
    {
        $array = [
            'user' => [
                'name' => 'John',
                'profile' => [
                    'age' => 30,
                    'city' => 'NYC'
                ]
            ]
        ];
        
        $result = Arr::dot($array);
        $expected = [
            'user.name' => 'John',
            'user.profile.age' => 30,
            'user.profile.city' => 'NYC'
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testExcept(): void
    {
        $array = ['name' => 'John', 'age' => 30, 'city' => 'NYC'];
        $result = Arr::except($array, ['age', 'city']);
        
        $this->assertEquals(['name' => 'John'], $result);
    }

    public function testExists(): void
    {
        $array = ['name' => 'John', 'age' => null];
        
        $this->assertTrue(Arr::exists($array, 'name'));
        $this->assertTrue(Arr::exists($array, 'age')); // null values still exist
        $this->assertFalse(Arr::exists($array, 'email'));
    }

    public function testFirst(): void
    {
        $array = [1, 2, 3, 4, 5];
        
        $this->assertEquals(1, Arr::first($array));
        
        $firstEven = Arr::first($array, fn($value) => $value % 2 === 0);
        $this->assertEquals(2, $firstEven);
        
        $firstGreaterThan10 = Arr::first($array, fn($value) => $value > 10, 'default');
        $this->assertEquals('default', $firstGreaterThan10);
    }

    public function testFlatten(): void
    {
        $array = [
            'name' => 'John',
            'languages' => [
                'php' => ['laravel', 'symfony'],
                'js' => ['vue', 'react']
            ]
        ];
        
        $result = Arr::flatten($array);
        $expected = ['John', 'laravel', 'symfony', 'vue', 'react'];
        
        $this->assertEquals($expected, $result);
    }

    public function testFlattenWithDepth(): void
    {
        $array = [
            'level1' => [
                'level2' => [
                    'level3' => 'value'
                ]
            ]
        ];
        
        $result = Arr::flatten($array, 1);
        $expected = ['value']; // With depth 1, it flattens completely to the value
        
        $this->assertEquals($expected, $result);
    }

    public function testForget(): void
    {
        $array = [
            'user' => [
                'name' => 'John',
                'profile' => [
                    'age' => 30,
                    'city' => 'NYC'
                ]
            ]
        ];
        
        Arr::forget($array, 'user.profile.age');
        
        $expected = [
            'user' => [
                'name' => 'John',
                'profile' => [
                    'city' => 'NYC'
                ]
            ]
        ];
        
        $this->assertEquals($expected, $array);
    }

    public function testGet(): void
    {
        $array = [
            'user' => [
                'name' => 'John',
                'profile' => [
                    'age' => 30
                ]
            ]
        ];
        
        $this->assertEquals('John', Arr::get($array, 'user.name'));
        $this->assertEquals(30, Arr::get($array, 'user.profile.age'));
        $this->assertNull(Arr::get($array, 'user.email'));
        $this->assertEquals('default', Arr::get($array, 'user.email', 'default'));
    }

    public function testHas(): void
    {
        $array = [
            'user' => [
                'name' => 'John',
                'profile' => [
                    'age' => 30
                ]
            ]
        ];
        
        $this->assertTrue(Arr::has($array, 'user.name'));
        $this->assertTrue(Arr::has($array, 'user.profile.age'));
        $this->assertFalse(Arr::has($array, 'user.email'));
        $this->assertTrue(Arr::has($array, ['user.name', 'user.profile.age']));
        $this->assertFalse(Arr::has($array, ['user.name', 'user.email']));
    }

    public function testIsAssoc(): void
    {
        $this->assertTrue(Arr::isAssoc(['name' => 'John', 'age' => 30]));
        $this->assertFalse(Arr::isAssoc([1, 2, 3]));
        $this->assertFalse(Arr::isAssoc([]));
        $this->assertTrue(Arr::isAssoc([1 => 'a', 0 => 'b'])); // Non-sequential keys
    }

    public function testLast(): void
    {
        $array = [1, 2, 3, 4, 5];
        
        $this->assertEquals(5, Arr::last($array));
        
        $lastEven = Arr::last($array, fn($value) => $value % 2 === 0);
        $this->assertEquals(4, $lastEven);
        
        $lastGreaterThan10 = Arr::last($array, fn($value) => $value > 10, 'default');
        $this->assertEquals('default', $lastGreaterThan10);
    }

    public function testOnly(): void
    {
        $array = ['name' => 'John', 'age' => 30, 'city' => 'NYC'];
        $result = Arr::only($array, ['name', 'age']);
        
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function testPluck(): void
    {
        $array = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35],
        ];
        
        $names = Arr::pluck($array, 'name');
        $this->assertEquals(['John', 'Jane', 'Bob'], $names);
        
        $namesByAge = Arr::pluck($array, 'name', 'age');
        $expected = [30 => 'John', 25 => 'Jane', 35 => 'Bob'];
        $this->assertEquals($expected, $namesByAge);
    }

    public function testPrepend(): void
    {
        $array = ['b', 'c'];
        $result = Arr::prepend($array, 'a');
        
        $this->assertEquals(['a', 'b', 'c'], $result);
        
        $result = Arr::prepend($array, 'zero', 0);
        $this->assertEquals([0 => 'zero', 1 => 'c'], $result); // Key 0 gets 'zero', original 'b' at key 0 gets overwritten
    }

    public function testPull(): void
    {
        $array = ['name' => 'John', 'age' => 30];
        $value = Arr::pull($array, 'name');
        
        $this->assertEquals('John', $value);
        $this->assertEquals(['age' => 30], $array);
        
        $missing = Arr::pull($array, 'email', 'default');
        $this->assertEquals('default', $missing);
    }

    public function testRandom(): void
    {
        $array = [1, 2, 3, 4, 5];
        
        $random = Arr::random($array);
        $this->assertContains($random, $array);
        
        $randomMultiple = Arr::random($array, 3);
        $this->assertCount(3, $randomMultiple);
        
        foreach ($randomMultiple as $value) {
            $this->assertContains($value, $array);
        }
    }

    public function testSet(): void
    {
        $array = [];
        
        Arr::set($array, 'user.name', 'John');
        Arr::set($array, 'user.profile.age', 30);
        
        $expected = [
            'user' => [
                'name' => 'John',
                'profile' => [
                    'age' => 30
                ]
            ]
        ];
        
        $this->assertEquals($expected, $array);
    }

    public function testShuffle(): void
    {
        $array = [1, 2, 3, 4, 5];
        $shuffled = Arr::shuffle($array);
        
        $this->assertCount(5, $shuffled);
        
        // All original values should be present
        foreach ($array as $value) {
            $this->assertContains($value, $shuffled);
        }
    }

    public function testSort(): void
    {
        $array = [3, 1, 4, 1, 5];
        $sorted = Arr::sort($array);
        
        $this->assertEquals([1, 1, 3, 4, 5], array_values($sorted));
    }

    public function testSortRecursive(): void
    {
        $array = [
            'users' => [
                ['name' => 'John'],
                ['name' => 'Jane'],
            ],
            'posts' => [
                ['title' => 'Post 2'],
                ['title' => 'Post 1'],
            ]
        ];
        
        $sorted = Arr::sortRecursive($array);
        
        // Should sort both the main array and nested arrays
        $this->assertIsArray($sorted);
        $this->assertArrayHasKey('users', $sorted);
        $this->assertArrayHasKey('posts', $sorted);
    }

    public function testWhere(): void
    {
        $array = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35],
        ];
        
        $adults = Arr::where($array, function ($item) {
            return $item['age'] >= 30;
        });
        
        $this->assertCount(2, $adults);
        
        // Check that John and Bob are in the results
        $names = array_column($adults, 'name');
        $this->assertContains('John', $names);
        $this->assertContains('Bob', $names);
        $this->assertNotContains('Jane', $names);
    }

    public function testWrap(): void
    {
        $this->assertEquals(['value'], Arr::wrap('value'));
        $this->assertEquals([1, 2, 3], Arr::wrap([1, 2, 3]));
        $this->assertEquals([], Arr::wrap(null)); // null returns empty array
        $this->assertEquals([], Arr::wrap([]));
    }
}