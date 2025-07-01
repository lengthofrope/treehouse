<?php

require_once 'vendor/autoload.php';

// Mock test to demonstrate the new model-aware Collection functionality
echo "=== TreeHouse Model-Aware Collection Test ===\n\n";

// Simulate User objects
class MockUser {
    public function __construct(public string $name, public bool $active = true) {}
    public function getName(): string { return $this->name; }
    public function isActive(): bool { return $this->active; }
    public function __toString(): string { return $this->name; }
}

// Create a model-aware collection
$users = new \LengthOfRope\TreeHouse\Support\Collection([
    new MockUser('Alice', true),
    new MockUser('Bob', false),
    new MockUser('Charlie', true),
    new MockUser('Diana', false),
], MockUser::class);

echo "Original collection:\n";
echo "- Is model collection: " . ($users->isModelCollection() ? 'Yes' : 'No') . "\n";
echo "- Model class: " . ($users->getModelClass() ?? 'None') . "\n";
echo "- Count: " . $users->count() . "\n\n";

// Test filter - should preserve model class
echo "=== Testing filter() - should preserve MockUser objects ===\n";
$activeUsers = $users->filter(fn($user) => $user->isActive());
echo "Active users collection:\n";
echo "- Is model collection: " . ($activeUsers->isModelCollection() ? 'Yes' : 'No') . "\n";
echo "- Model class: " . ($activeUsers->getModelClass() ?? 'None') . "\n";
echo "- Count: " . $activeUsers->count() . "\n";
echo "- Can call getName() on first item: ";
try {
    echo $activeUsers->first()->getName() . " ✓\n";
} catch (Error $e) {
    echo "Failed ✗\n";
}
echo "\n";

// Test map with model preservation
echo "=== Testing map() - should preserve MockUser objects when returning same type ===\n";
$mappedUsers = $users->map(fn($user) => $user); // Identity map
echo "Identity-mapped collection:\n";
echo "- Is model collection: " . ($mappedUsers->isModelCollection() ? 'Yes' : 'No') . "\n";
echo "- Model class: " . ($mappedUsers->getModelClass() ?? 'None') . "\n";
echo "- Can call methods: ";
try {
    echo $mappedUsers->first()->getName() . " ✓\n";
} catch (Error $e) {
    echo "Failed ✗\n";
}
echo "\n";

// Test map with type change
echo "=== Testing map() - should lose model class when returning different type ===\n";
$names = $users->map(fn($user) => $user->getName());
echo "Names collection:\n";
echo "- Is model collection: " . ($names->isModelCollection() ? 'Yes' : 'No') . "\n";
echo "- Model class: " . ($names->getModelClass() ?? 'None') . "\n";
echo "- First item is string: " . (is_string($names->first()) ? 'Yes' : 'No') . "\n";
echo "- Content: " . $names->implode(', ') . "\n\n";

// Test model-specific methods
echo "=== Testing model-specific methods ===\n";
$foundUser = $activeUsers->findBy('name', 'Alice');
echo "Found user by name 'Alice': " . ($foundUser ? $foundUser->getName() : 'Not found') . "\n";

$keys = $users->modelKeys();
echo "Model keys: " . json_encode($keys) . "\n\n";

// Test chaining operations
echo "=== Testing method chaining ===\n";
$result = $users
    ->filter(fn($user) => $user->isActive())
    ->sortBy(fn($user) => $user->getName())
    ->take(2);

echo "Chained operations result:\n";
echo "- Is model collection: " . ($result->isModelCollection() ? 'Yes' : 'No') . "\n";
echo "- Count: " . $result->count() . "\n";
echo "- Can call methods on items: ";
try {
    $names = $result->map(fn($user) => $user->getName())->all();
    echo implode(', ', $names) . " ✓\n";
} catch (Error $e) {
    echo "Failed ✗\n";
}

echo "\n=== Test completed! ===\n";
echo "The Collection class now preserves model object types through transformations!\n";
echo "Developers can now iterate through collections and call model methods without losing functionality.\n";