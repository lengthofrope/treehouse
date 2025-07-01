<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use LengthOfRope\TreeHouse\Support\Collection;
use LengthOfRope\TreeHouse\Models\User;
use LengthOfRope\TreeHouse\Models\Role;
use Tests\TestCase;
use stdClass;

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

    // Model-Aware Collection Tests

    public function testCreateModelAwareCollection(): void
    {
        $collection = new Collection([], User::class);
        
        $this->assertTrue($collection->isModelCollection());
        $this->assertEquals(User::class, $collection->getModelClass());
        $this->assertTrue($collection->isEmpty());
    }

    public function testCreateNonModelCollection(): void
    {
        $collection = new Collection([1, 2, 3]);
        
        $this->assertFalse($collection->isModelCollection());
        $this->assertNull($collection->getModelClass());
    }

    public function testMakeWithModelClass(): void
    {
        $collection = Collection::make([], User::class);
        
        $this->assertTrue($collection->isModelCollection());
        $this->assertEquals(User::class, $collection->getModelClass());
    }

    public function testModelTypePreservationInFilter(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        $user3 = $this->createMockUser(3, 'Bob', 'admin');
        
        $collection = new Collection([$user1, $user2, $user3], User::class);
        
        $admins = $collection->filter(fn($user) => $user->getAttribute('role') === 'admin');
        
        $this->assertTrue($admins->isModelCollection());
        $this->assertEquals(User::class, $admins->getModelClass());
        $this->assertCount(2, $admins);
    }

    public function testModelTypePreservationInMap(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        
        $collection = new Collection([$user1, $user2], User::class);
        
        // Map that returns User objects should preserve type
        $mapped = $collection->map(function($user) {
            // Just return the user object to preserve type
            return $user;
        });
        
        $this->assertTrue($mapped->isModelCollection());
        $this->assertEquals(User::class, $mapped->getModelClass());
        $this->assertCount(2, $mapped);
    }

    public function testModelTypeResetInMapWithDifferentType(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        
        $collection = new Collection([$user1, $user2], User::class);
        
        // Map that returns arrays should reset type
        $mapped = $collection->map(fn($user) => ['name' => 'TestName']);
        
        $this->assertFalse($mapped->isModelCollection());
        $this->assertNull($mapped->getModelClass());
        $this->assertEquals(['name' => 'TestName'], $mapped->first());
    }

    public function testModelTypePreservationInWhere(): void
    {
        // Use simple arrays for where testing since it uses dataGet
        $items = [
            ['id' => 1, 'name' => 'John', 'role' => 'admin'],
            ['id' => 2, 'name' => 'Jane', 'role' => 'user']
        ];
        
        $collection = new Collection($items, User::class);
        
        $admins = $collection->where('role', 'admin');
        
        $this->assertTrue($admins->isModelCollection());
        $this->assertEquals(User::class, $admins->getModelClass());
        $this->assertCount(1, $admins);
    }

    public function testModelTypePreservationInSortBy(): void
    {
        // Use simple arrays for sortBy testing since it uses dataGet
        $items = [
            ['id' => 1, 'name' => 'John', 'role' => 'admin'],
            ['id' => 2, 'name' => 'Jane', 'role' => 'user']
        ];
        
        $collection = new Collection($items, User::class);
        
        $sorted = $collection->sortBy('name');
        
        $this->assertTrue($sorted->isModelCollection());
        $this->assertEquals(User::class, $sorted->getModelClass());
        $this->assertEquals('Jane', $sorted->first()['name']);
    }

    public function testModelTypePreservationInGroupBy(): void
    {
        // Use simple arrays for groupBy testing since it uses dataGet
        $items = [
            ['id' => 1, 'name' => 'John', 'role' => 'admin'],
            ['id' => 2, 'name' => 'Jane', 'role' => 'user'],
            ['id' => 3, 'name' => 'Bob', 'role' => 'admin']
        ];
        
        $collection = new Collection($items, User::class);
        
        $grouped = $collection->groupBy('role');
        
        $this->assertFalse($grouped->isModelCollection()); // Grouped collection is not model collection
        $adminGroup = $grouped->get('admin');
        $this->assertNotNull($adminGroup);
        $this->assertTrue($adminGroup->isModelCollection()); // But groups are
        $this->assertEquals(User::class, $adminGroup->getModelClass());
        $this->assertCount(2, $adminGroup);
    }

    public function testFindByMethod(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $found = $collection->findBy('id', 2);
        $this->assertEquals($user2, $found);
        
        $notFound = $collection->findBy('id', 999);
        $this->assertNull($notFound);
        
        $foundByName = $collection->findBy('name', 'John');
        $this->assertEquals($user1, $foundByName);
    }

    public function testFindByOnNonModelCollection(): void
    {
        $collection = new Collection([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ]);
        
        $found = $collection->findBy('id', 2);
        $this->assertEquals(['id' => 2, 'name' => 'Jane'], $found);
    }

    public function testModelKeysMethod(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $keys = $collection->modelKeys();
        $this->assertIsArray($keys);
        $this->assertEquals([1, 2], $keys);
    }

    public function testModelKeysOnNonModelCollection(): void
    {
        $collection = new Collection([1, 2, 3]);
        
        $keys = $collection->modelKeys();
        $this->assertIsArray($keys);
        $this->assertEquals([], $keys);
    }

    public function testFreshMethod(): void
    {
        // Test with objects that don't have fresh method
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $fresh = $collection->fresh();
        
        $this->assertTrue($fresh->isModelCollection());
        $this->assertEquals(User::class, $fresh->getModelClass());
        $this->assertCount(2, $fresh);
    }

    public function testFreshOnNonModelCollection(): void
    {
        $collection = new Collection([1, 2, 3]);
        
        $fresh = $collection->fresh();
        $this->assertEquals($collection, $fresh);
    }

    public function testSaveAllMethod(): void
    {
        $user1 = $this->createMockUserWithSave(1, 'John', 'admin', true);
        $user2 = $this->createMockUserWithSave(2, 'Jane', 'user', true);
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $result = $collection->saveAll();
        $this->assertTrue($result);
    }

    public function testSaveAllWithFailure(): void
    {
        $user1 = $this->createMockUserWithSave(1, 'John', 'admin', true);
        $user2 = $this->createMockUserWithSave(2, 'Jane', 'user', false);
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $result = $collection->saveAll();
        $this->assertFalse($result);
    }

    public function testSaveAllOnNonModelCollection(): void
    {
        $collection = new Collection([1, 2, 3]);
        
        $result = $collection->saveAll();
        $this->assertFalse($result);
    }

    public function testDeleteAllMethod(): void
    {
        $user1 = $this->createMockUserWithDelete(1, 'John', 'admin', true);
        $user2 = $this->createMockUserWithDelete(2, 'Jane', 'user', true);
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $result = $collection->deleteAll();
        $this->assertTrue($result);
    }

    public function testDeleteAllWithFailure(): void
    {
        $user1 = $this->createMockUserWithDelete(1, 'John', 'admin', true);
        $user2 = $this->createMockUserWithDelete(2, 'Jane', 'user', false);
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $result = $collection->deleteAll();
        $this->assertFalse($result);
    }

    public function testDeleteAllOnNonModelCollection(): void
    {
        $collection = new Collection([1, 2, 3]);
        
        $result = $collection->deleteAll();
        $this->assertFalse($result);
    }

    public function testMergePreservesModelType(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        $user3 = $this->createMockUser(3, 'Bob', 'admin');
        
        $collection1 = new Collection([$user1], User::class);
        $collection2 = new Collection([$user2, $user3]);
        
        $merged = $collection1->merge($collection2);
        
        // Merge loses model class since we're merging with potentially different types
        $this->assertFalse($merged->isModelCollection());
        $this->assertNull($merged->getModelClass());
        $this->assertCount(3, $merged);
    }

    public function testMergeResetsModelTypeWithDifferentTypes(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $collection1 = new Collection([$user1], User::class);
        $collection2 = new Collection([1, 2, 3]);
        
        $merged = $collection1->merge($collection2);
        
        $this->assertFalse($merged->isModelCollection());
        $this->assertNull($merged->getModelClass());
        $this->assertCount(4, $merged);
    }

    public function testChunkPreservesModelType(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        $user3 = $this->createMockUser(3, 'Bob', 'admin');
        
        $collection = new Collection([$user1, $user2, $user3], User::class);
        
        $chunks = $collection->chunk(2);
        
        $this->assertFalse($chunks->isModelCollection()); // Collection of chunks
        $this->assertTrue($chunks->first()->isModelCollection()); // But chunks are model collections
        $this->assertEquals(User::class, $chunks->first()->getModelClass());
    }

    public function testTakePreservesModelType(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $taken = $collection->take(1);
        
        $this->assertTrue($taken->isModelCollection());
        $this->assertEquals(User::class, $taken->getModelClass());
        $this->assertCount(1, $taken);
    }

    public function testSkipPreservesModelType(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $skipped = $collection->skip(1);
        
        $this->assertTrue($skipped->isModelCollection());
        $this->assertEquals(User::class, $skipped->getModelClass());
        $this->assertCount(1, $skipped);
    }

    public function testSlicePreservesModelType(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        $user3 = $this->createMockUser(3, 'Bob', 'admin');
        
        $collection = new Collection([$user1, $user2, $user3], User::class);
        
        $sliced = $collection->slice(1, 1);
        
        $this->assertTrue($sliced->isModelCollection());
        $this->assertEquals(User::class, $sliced->getModelClass());
        $this->assertCount(1, $sliced);
    }

    public function testUniquePreservesModelType(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(1, 'John', 'admin'); // Duplicate
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $unique = $collection->unique();
        
        $this->assertTrue($unique->isModelCollection());
        $this->assertEquals(User::class, $unique->getModelClass());
    }

    public function testValuesPreservesModelType(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        
        $collection = new Collection(['a' => $user1, 'b' => $user2], User::class);
        
        $values = $collection->values();
        
        $this->assertTrue($values->isModelCollection());
        $this->assertEquals(User::class, $values->getModelClass());
        $this->assertEquals([0, 1], array_keys($values->all()));
    }

    public function testRejectPreservesModelType(): void
    {
        $user1 = $this->createMockUser(1, 'John', 'admin');
        $user2 = $this->createMockUser(2, 'Jane', 'user');
        
        $collection = new Collection([$user1, $user2], User::class);
        
        $rejected = $collection->reject(fn($user) => $user->getAttribute('role') === 'user');
        
        $this->assertTrue($rejected->isModelCollection());
        $this->assertEquals(User::class, $rejected->getModelClass());
        $this->assertCount(1, $rejected);
    }

    public function testEdgeCaseEmptyModelCollection(): void
    {
        $collection = new Collection([], User::class);
        
        $filtered = $collection->filter(fn($user) => true);
        $this->assertTrue($filtered->isModelCollection());
        $this->assertEquals(User::class, $filtered->getModelClass());
        $this->assertTrue($filtered->isEmpty());
        
        $mapped = $collection->map(fn($user) => $user);
        $this->assertTrue($mapped->isModelCollection());
        $this->assertEquals(User::class, $mapped->getModelClass());
        $this->assertTrue($mapped->isEmpty());
    }

    public function testModelClassValidation(): void
    {
        // Test with valid model class
        $collection = new Collection([], User::class);
        $this->assertEquals(User::class, $collection->getModelClass());
        
        // Test with invalid class should still work (no validation in constructor)
        $collection = new Collection([], 'NonExistentClass');
        $this->assertEquals('NonExistentClass', $collection->getModelClass());
    }

    /**
     * Create a mock User object for testing
     */
    private function createMockUser(int $id, string $name, string $role): User
    {
        $user = $this->createMock(User::class);
        $user->id = $id;
        $user->name = $name;
        $user->role = $role;
        
        $user->method('getKey')->willReturn($id);
        $user->method('save')->willReturn(true);
        $user->method('delete')->willReturn(true);
        $user->method('getAttribute')->willReturnCallback(function($attr) use ($id, $name, $role) {
            return match($attr) {
                'id' => $id,
                'name' => $name,
                'role' => $role,
                default => null
            };
        });
        
        return $user;
    }

    /**
     * Create a mock User object with specific save behavior
     */
    private function createMockUserWithSave(int $id, string $name, string $role, bool $saveResult): User
    {
        $user = $this->createMock(User::class);
        $user->id = $id;
        $user->name = $name;
        $user->role = $role;
        
        $user->method('getKey')->willReturn($id);
        $user->method('save')->willReturn($saveResult);
        
        return $user;
    }

    /**
     * Create a mock User object with specific delete behavior
     */
    private function createMockUserWithDelete(int $id, string $name, string $role, bool $deleteResult): User
    {
        $user = $this->createMock(User::class);
        $user->id = $id;
        $user->name = $name;
        $user->role = $role;
        
        $user->method('getKey')->willReturn($id);
        $user->method('delete')->willReturn($deleteResult);
        
        return $user;
    }
}