<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use LengthOfRope\TreeHouse\Support\Collection;
use Tests\TestCase;

/**
 * Test cases for Collection class
 * 
 * @package Tests\Unit\Support
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
class CollectionTest extends TestCase
{
    public function testCanCreateEmptyCollection(): void
    {
        $collection = new Collection();
        
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertTrue($collection->isEmpty());
        $this->assertEquals(0, $collection->count());
        $this->assertEquals([], $collection->all());
    }

    public function testCanCreateCollectionWithItems(): void
    {
        $items = ['apple', 'banana', 'cherry'];
        $collection = new Collection($items);
        
        $this->assertFalse($collection->isEmpty());
        $this->assertEquals(3, $collection->count());
        $this->assertEquals($items, $collection->all());
    }

    public function testMakeStaticMethod(): void
    {
        $items = [1, 2, 3];
        $collection = Collection::make($items);
        
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals($items, $collection->all());
    }

    public function testPushMethod(): void
    {
        $collection = new Collection([1, 2]);
        $collection->push(3);
        
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testPutMethod(): void
    {
        $collection = new Collection();
        $collection->put('name', 'John');
        $collection->put('age', 30);
        
        $this->assertEquals(['name' => 'John', 'age' => 30], $collection->all());
    }

    public function testGetMethod(): void
    {
        $collection = new Collection(['name' => 'John', 'age' => 30]);
        
        $this->assertEquals('John', $collection->get('name'));
        $this->assertEquals(30, $collection->get('age'));
        $this->assertNull($collection->get('email'));
        $this->assertEquals('default', $collection->get('email', 'default'));
    }

    public function testHasMethod(): void
    {
        $collection = new Collection(['name' => 'John', 'age' => 30]);
        
        $this->assertTrue($collection->has('name'));
        $this->assertTrue($collection->has('age'));
        $this->assertFalse($collection->has('email'));
        $this->assertTrue($collection->has(['name', 'age']));
        $this->assertFalse($collection->has(['name', 'email']));
    }

    public function testFirstMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        
        $this->assertEquals(1, $collection->first());
        
        $firstEven = $collection->first(fn($value) => $value % 2 === 0);
        $this->assertEquals(2, $firstEven);
        
        $firstGreaterThan10 = $collection->first(fn($value) => $value > 10, 'default');
        $this->assertEquals('default', $firstGreaterThan10);
    }

    public function testLastMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        
        $this->assertEquals(5, $collection->last());
        
        $lastEven = $collection->last(fn($value) => $value % 2 === 0);
        $this->assertEquals(4, $lastEven);
    }

    public function testFilterMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        
        $evens = $collection->filter(fn($value) => $value % 2 === 0);
        $this->assertEquals([1 => 2, 3 => 4], $evens->all());
        
        $filtered = $collection->filter();
        $this->assertEquals([1, 2, 3, 4, 5], $filtered->all());
    }

    public function testMapMethod(): void
    {
        $collection = new Collection([1, 2, 3]);
        
        $doubled = $collection->map(fn($value) => $value * 2);
        $this->assertEquals([2, 4, 6], $doubled->all());
        
        // Original collection should be unchanged
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testReduceMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        
        $sum = $collection->reduce(fn($carry, $item) => $carry + $item, 0);
        $this->assertEquals(15, $sum);
        
        $product = $collection->reduce(fn($carry, $item) => $carry * $item, 1);
        $this->assertEquals(120, $product);
    }

    public function testSumMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals(15, $collection->sum());
        
        $items = [
            ['price' => 10],
            ['price' => 20],
            ['price' => 30],
        ];
        $collection = new Collection($items);
        $this->assertEquals(60, $collection->sum('price'));
    }

    public function testAvgMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals(3, $collection->avg());
        
        $items = [
            ['score' => 80],
            ['score' => 90],
            ['score' => 70],
        ];
        $collection = new Collection($items);
        $this->assertEquals(80, $collection->avg('score'));
    }

    public function testMaxMethod(): void
    {
        $collection = new Collection([1, 5, 3, 2, 4]);
        $this->assertEquals(5, $collection->max());
        
        $items = [
            ['score' => 80],
            ['score' => 90],
            ['score' => 70],
        ];
        $collection = new Collection($items);
        $this->assertEquals(90, $collection->max('score'));
    }

    public function testMinMethod(): void
    {
        $collection = new Collection([5, 1, 3, 2, 4]);
        $this->assertEquals(1, $collection->min());
        
        $items = [
            ['score' => 80],
            ['score' => 90],
            ['score' => 70],
        ];
        $collection = new Collection($items);
        $this->assertEquals(70, $collection->min('score'));
    }

    public function testSortMethod(): void
    {
        $collection = new Collection([3, 1, 4, 1, 5]);
        $sorted = $collection->sort();
        
        $this->assertEquals([1, 1, 3, 4, 5], array_values($sorted->all()));
    }

    public function testSortByMethod(): void
    {
        $items = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35],
        ];
        $collection = new Collection($items);
        
        $sortedByAge = $collection->sortBy('age');
        $ages = $sortedByAge->pluck('age')->all();
        $this->assertEquals([25, 30, 35], array_values($ages));
    }

    public function testGroupByMethod(): void
    {
        $items = [
            ['name' => 'John', 'department' => 'IT'],
            ['name' => 'Jane', 'department' => 'HR'],
            ['name' => 'Bob', 'department' => 'IT'],
        ];
        $collection = new Collection($items);
        
        $grouped = $collection->groupBy('department');
        
        $this->assertCount(2, $grouped);
        $this->assertTrue($grouped->has('IT'));
        $this->assertTrue($grouped->has('HR'));
        $this->assertCount(2, $grouped->get('IT'));
        $this->assertCount(1, $grouped->get('HR'));
    }

    public function testPluckMethod(): void
    {
        $items = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35],
        ];
        $collection = new Collection($items);
        
        $names = $collection->pluck('name');
        $this->assertEquals(['John', 'Jane', 'Bob'], $names->all());
        
        $namesByAge = $collection->pluck('name', 'age');
        $expected = [30 => 'John', 25 => 'Jane', 35 => 'Bob'];
        $this->assertEquals($expected, $namesByAge->all());
    }

    public function testWhereMethod(): void
    {
        $items = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35],
        ];
        $collection = new Collection($items);
        
        $adults = $collection->where('age', '>=', 30);
        $this->assertCount(2, $adults);
        
        $johns = $collection->where('name', 'John');
        $this->assertCount(1, $johns);
    }

    public function testUniqueMethod(): void
    {
        $collection = new Collection([1, 2, 2, 3, 3, 3]);
        $unique = $collection->unique();
        
        $this->assertEquals([1, 2, 3], array_values($unique->all()));
    }

    public function testChunkMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $chunks = $collection->chunk(2);
        
        $this->assertCount(3, $chunks);
        $this->assertEquals([1, 2], $chunks->first()->all());
        $this->assertEquals([4 => 5], $chunks->last()->all()); // Keys are preserved in chunks
    }

    public function testTakeMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        
        $first3 = $collection->take(3);
        $this->assertEquals([1, 2, 3], $first3->all());
        
        $last2 = $collection->take(-2);
        $this->assertEquals([4, 5], array_values($last2->all()));
    }

    public function testSkipMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $skipped = $collection->skip(2);
        
        $this->assertEquals([2 => 3, 3 => 4, 4 => 5], $skipped->all());
    }

    public function testSliceMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $slice = $collection->slice(1, 3);
        
        $this->assertEquals([1 => 2, 2 => 3, 3 => 4], $slice->all());
    }

    public function testContainsMethod(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        
        $this->assertTrue($collection->contains(3));
        $this->assertFalse($collection->contains(6));
        
        $this->assertTrue($collection->contains(fn($value) => $value > 4));
        $this->assertFalse($collection->contains(fn($value) => $value > 10));
    }

    public function testKeysMethod(): void
    {
        $collection = new Collection(['name' => 'John', 'age' => 30, 'city' => 'NYC']);
        $keys = $collection->keys();
        
        $this->assertEquals(['name', 'age', 'city'], $keys->all());
    }

    public function testValuesMethod(): void
    {
        $collection = new Collection(['name' => 'John', 'age' => 30, 'city' => 'NYC']);
        $values = $collection->values();
        
        $this->assertEquals(['John', 30, 'NYC'], $values->all());
    }

    public function testToArrayMethod(): void
    {
        $items = ['name' => 'John', 'age' => 30];
        $collection = new Collection($items);
        
        $this->assertEquals($items, $collection->all());
    }

    public function testToJsonMethod(): void
    {
        $items = ['name' => 'John', 'age' => 30];
        $collection = new Collection($items);
        
        $json = $collection->toJson();
        $this->assertIsJson($json);
        $this->assertEquals($items, json_decode($json, true));
    }

    public function testArrayAccess(): void
    {
        $collection = new Collection();
        
        // Test offsetSet
        $collection['name'] = 'John';
        $collection['age'] = 30;
        
        // Test offsetExists
        $this->assertTrue(isset($collection['name']));
        $this->assertFalse(isset($collection['email']));
        
        // Test offsetGet
        $this->assertEquals('John', $collection['name']);
        $this->assertEquals(30, $collection['age']);
        
        // Test offsetUnset
        unset($collection['age']);
        $this->assertFalse(isset($collection['age']));
    }

    public function testCountable(): void
    {
        $collection = new Collection([1, 2, 3]);
        
        $this->assertEquals(3, count($collection));
        $this->assertEquals(3, $collection->count());
    }

    public function testIterable(): void
    {
        $items = ['a' => 1, 'b' => 2, 'c' => 3];
        $collection = new Collection($items);
        
        $result = [];
        foreach ($collection as $key => $value) {
            $result[$key] = $value;
        }
        
        $this->assertEquals($items, $result);
    }

    public function testStringConversion(): void
    {
        $items = ['name' => 'John', 'age' => 30];
        $collection = new Collection($items);
        
        $string = (string) $collection;
        $this->assertIsJson($string);
        $this->assertEquals($items, json_decode($string, true));
    }
}