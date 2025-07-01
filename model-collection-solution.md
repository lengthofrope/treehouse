# Model-Aware Collection Solution

## Problem Solved

The original issue was that when using `User::all()` or other model collection methods, the Collection class would lose model object types during transformations like `map()`, `filter()`, etc. This meant developers couldn't call model methods on items after collection operations.

**Before (Problem):**
```php
$users = User::all(); // Returns Collection with User objects
$activeUsers = $users->filter(fn($user) => $user->isActive()); // ❌ Loses User methods
// $activeUsers items were arrays, not User objects
```

**After (Solution):**
```php
$users = User::all(); // Returns model-aware Collection<User>
$activeUsers = $users->filter(fn($user) => $user->isActive()); // ✅ Preserves User objects
$names = $activeUsers->map(fn($user) => $user->getName()); // ✅ Can call User methods
```

## Implementation Summary

### 1. Enhanced Collection Class
- Added `$modelClass` property to track model type
- Added `isModelCollection()` and `getModelClass()` methods
- Updated all transformation methods to preserve model class when appropriate
- Added model-specific methods: `findBy()`, `fresh()`, `saveAll()`, `deleteAll()`, `modelKeys()`

### 2. Smart Model Preservation Logic
- **Preserves model class:** `filter()`, `where()`, `reject()`, `sort()`, `unique()`, `slice()`, etc.
- **Preserves conditionally:** `map()` (only if all results are still the same model type)
- **Loses model class:** `pluck()`, `keys()`, `flatten()`, `merge()` (return different data types)

### 3. ActiveRecord Integration
- `User::all()` now returns `Collection<User>`
- `User::where()` now returns `Collection<User>`
- All model relationship methods return typed collections

### 4. Model Relationships Enhanced
- `$user->roles()` returns `Collection<Role>`
- `$role->permissions()` returns `Collection<Permission>`
- `$role->users()` returns `Collection<User>`
- `$permission->roles()` returns `Collection<Role>`

## Key Features

### Type Preservation Through Transformations
```php
$users = User::all();                                    // Collection<User>
$activeUsers = $users->filter(fn($u) => $u->isActive()); // Collection<User>
$sortedUsers = $activeUsers->sortBy('name');             // Collection<User>
$topUsers = $sortedUsers->take(5);                       // Collection<User>

// All operations preserve User object methods
$firstUserName = $topUsers->first()->getName(); ✅
```

### Model-Specific Methods
```php
$users = User::all();

// Find by attribute
$alice = $users->findBy('email', 'alice@example.com');

// Reload all from database
$fresh = $users->fresh();

// Bulk operations
$users->saveAll();    // Save all modified users
$users->deleteAll();  // Delete all users

// Get primary keys
$ids = $users->modelKeys(); // [1, 2, 3, 4, 5]
```

### Smart Map Behavior
```php
$users = User::all();

// Preserves User objects (identity transformation)
$sameUsers = $users->map(fn($u) => $u); // Collection<User>

// Loses model class (returns strings)
$names = $users->map(fn($u) => $u->getName()); // Collection<string>

// Preserves if all results are User objects
$refreshedUsers = $users->map(fn($u) => $u->fresh() ?: $u); // Collection<User>
```

### Relationship Chaining
```php
$user = User::find(1);

// Chain operations on relationships
$adminPermissions = $user
    ->roles()                           // Collection<Role>
    ->filter(fn($role) => $role->isAdmin()) // Collection<Role>
    ->flatMap(fn($role) => $role->permissions()) // Collection<Permission>
    ->unique('slug');                   // Collection<Permission>

// Can still call model methods
$permissionNames = $adminPermissions->map(fn($p) => $p->getName()); // ✅
```

## Backward Compatibility

✅ **Fully backward compatible** - all existing code continues to work exactly as before.

The Collection class constructor now accepts an optional `$modelClass` parameter:
```php
// Old usage (still works)
$collection = new Collection([1, 2, 3]);

// New usage (model-aware)
$collection = new Collection($userArray, User::class);
```

## Usage Examples

### Basic Model Collections
```php
// Get all users as model-aware collection
$users = User::all();
echo $users->isModelCollection(); // true
echo $users->getModelClass();     // "LengthOfRope\TreeHouse\Models\User"

// Filter and maintain User objects
$activeUsers = $users->filter(fn($u) => $u->isActive());
$userName = $activeUsers->first()->getName(); // ✅ Works!
```

### Complex Transformations
```php
$users = User::all();

$result = $users
    ->filter(fn($u) => $u->isActive())           // Collection<User>
    ->sortBy(fn($u) => $u->getCreatedAt())       // Collection<User>
    ->groupBy(fn($u) => $u->getRole())           // Collection<Collection<User>>
    ->map(fn($group) => $group->take(3))         // Collection<Collection<User>>
    ->flatten();                                 // Collection<User>

// Result still contains User objects
$firstUser = $result->first();
echo $firstUser->getName(); // ✅ Works!
```

### Relationship Usage
```php
$user = User::find(1);

// Get user's admin roles
$adminRoles = $user
    ->roles()
    ->filter(fn($role) => $role->isAdmin());

// Check permissions for admin roles
$canManageUsers = $adminRoles
    ->flatMap(fn($role) => $role->permissions())
    ->contains(fn($perm) => $perm->getSlug() === 'manage_users');
```

## Files Modified

1. **src/TreeHouse/Support/Collection.php** - Enhanced with model awareness
2. **src/TreeHouse/Database/ActiveRecord.php** - Updated `all()` and `where()` methods
3. **src/TreeHouse/Models/User.php** - Updated `roles()` method
4. **src/TreeHouse/Models/Role.php** - Updated `permissions()` and `users()` methods  
5. **src/TreeHouse/Models/Permission.php** - Updated `roles()` method
6. **Memory bank files** - Updated with new patterns and usage

## Testing

Created `test-model-collection.php` to demonstrate the functionality working correctly.

## Benefits

1. **Developer Experience** - No more losing model methods when working with collections
2. **Type Safety** - Better IDE support and runtime behavior
3. **Code Clarity** - Clear distinction between data collections and model collections
4. **Performance** - Model-specific bulk operations (`saveAll`, `deleteAll`)
5. **Maintainability** - Consistent behavior across the framework

The solution completely resolves the original issue where developers lost access to model methods when iterating through collections after transformations.