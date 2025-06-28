# TreeHouse Database Layer

## Overview

The Database layer provides a comprehensive ORM (Object-Relational Mapping) system with ActiveRecord pattern, query builder, migrations, and relationship management. This layer handles all database operations with support for multiple database engines and advanced querying capabilities.

## Table of Contents

- [Connection - Database Connection Manager](#connection---database-connection-manager)
- [QueryBuilder - Fluent Query Builder](#querybuilder---fluent-query-builder)
- [ActiveRecord - Base Model Class](#activerecord---base-model-class)
- [Migration - Database Migration System](#migration---database-migration-system)
- [Relations - Database Relationships](#relations---database-relationships)

### Connection - Database Connection Manager

The [`Connection`](Connection.php:23) class manages database connections with support for multiple database engines, transaction handling, and query logging.

#### Key Features:
- **Multi-Database Support**: MySQL, PostgreSQL, SQLite support
- **Connection Pooling**: Efficient connection management
- **Transaction Support**: Nested transactions with rollback capabilities
- **Query Logging**: Debug and performance monitoring
- **Schema Introspection**: Table and column information retrieval

#### Core Methods:

```php
// Connection management
$connection = new Connection($config);
$pdo = $connection->getPdo();
$connection->connect();
$connection->disconnect();

// Query execution
$results = $connection->select('SELECT * FROM users WHERE active = ?', [1]);
$user = $connection->selectOne('SELECT * FROM users WHERE id = ?', [1]);
$id = $connection->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['John', 'john@example.com']);
$affected = $connection->update('UPDATE users SET active = ? WHERE id = ?', [1, 1]);
$deleted = $connection->delete('DELETE FROM users WHERE id = ?', [1]);

// Transaction handling
$connection->beginTransaction();
$connection->commit();
$connection->rollback();

// Transaction with callback
$result = $connection->transaction(function() use ($connection) {
    $connection->insert('INSERT INTO users ...');
    $connection->update('UPDATE profiles ...');
    return 'success';
});

// Schema introspection
$tables = $connection->getTableNames();
$exists = $connection->tableExists('users');
$columns = $connection->getTableColumns('users');
```

### QueryBuilder - Fluent Query Builder

The [`QueryBuilder`](QueryBuilder.php:21) class provides a fluent interface for building complex SQL queries with method chaining and parameter binding.

#### Key Features:
- **Fluent Interface**: Method chaining for readable queries
- **Parameter Binding**: Automatic SQL injection protection
- **Complex Queries**: Joins, subqueries, aggregations
- **Pagination Support**: Built-in pagination with offset/limit
- **Collection Results**: Returns structured data collections

#### Core Methods:

```php
// Basic queries
$users = QueryBuilder::table('users')
    ->select(['id', 'name', 'email'])
    ->where('active', 1)
    ->orderBy('name')
    ->get();

// Complex conditions
$results = QueryBuilder::table('users')
    ->where('age', '>', 18)
    ->whereIn('status', ['active', 'pending'])
    ->whereNotNull('email')
    ->whereBetween('created_at', '2023-01-01', '2023-12-31')
    ->whereLike('name', '%john%')
    ->get();

// Joins and relationships
$data = QueryBuilder::table('users')
    ->join('profiles', 'users.id', '=', 'profiles.user_id')
    ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
    ->select(['users.*', 'profiles.bio', 'roles.name as role_name'])
    ->get();

// Aggregations and grouping
$stats = QueryBuilder::table('orders')
    ->select(['status', 'COUNT(*) as count', 'SUM(total) as total_amount'])
    ->groupBy('status')
    ->having('count', '>', 10)
    ->get();

// Pagination
$users = QueryBuilder::table('users')
    ->paginate(1, 15); // page 1, 15 per page

// Data modification
$id = QueryBuilder::table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

$affected = QueryBuilder::table('users')
    ->where('id', 1)
    ->update(['name' => 'Jane Doe']);

$deleted = QueryBuilder::table('users')
    ->where('active', 0)
    ->delete();
```

### ActiveRecord - Base Model Class

The [`ActiveRecord`](ActiveRecord.php:26) class provides an elegant ORM implementation with automatic table mapping, relationships, and data validation.

#### Key Features:
- **Automatic Table Mapping**: Convention-based table names
- **Mass Assignment Protection**: Fillable and guarded attributes
- **Timestamps**: Automatic created_at/updated_at handling
- **Type Casting**: Automatic data type conversion
- **Relationships**: HasMany, BelongsTo, BelongsToMany support
- **Query Scopes**: Reusable query constraints
- **Events**: Model lifecycle hooks

#### Core Methods:

```php
// Model definition
class User extends ActiveRecord
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email', 'password'];
    protected array $guarded = ['id', 'created_at', 'updated_at'];
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'settings' => 'json'
    ];
    
    // Relationships
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
    
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
}

// Basic operations
$users = User::all();
$user = User::find(1);
$user = User::findOrFail(1);
$activeUsers = User::where('active', 1);

// Creating records
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

$user = new User();
$user->name = 'Jane Doe';
$user->email = 'jane@example.com';
$user->save();

// Updating records
$user = User::find(1);
$user->update(['name' => 'Updated Name']);

$user->name = 'Another Update';
$user->save();

// Mass updates
User::where('active', 0)->update(['status' => 'inactive']);

// Deleting records
$user = User::find(1);
$user->delete();

User::where('created_at', '<', '2023-01-01')->delete();

// Attribute access
$user = User::find(1);
echo $user->name;
echo $user->email;
$user->name = 'New Name';

// Array/JSON conversion
$array = $user->toArray();
$json = $user->toJson();
```

### Migration - Database Migration System

The [`Migration`](Migration.php:21) class provides version control for database schema with up/down migrations and schema building capabilities.

#### Key Features:
- **Schema Building**: Fluent schema definition
- **Version Control**: Track and rollback database changes
- **Cross-Database**: Works with MySQL, PostgreSQL, SQLite
- **Index Management**: Primary keys, unique constraints, indexes
- **Foreign Keys**: Relationship constraints with cascading
- **Column Types**: Comprehensive data type support

#### Core Methods:

```php
// Migration class
class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->createTable('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        $this->dropTable('users');
    }
}

// Schema building
$this->createTable('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->string('title');
    $table->longText('content');
    $table->enum('status', ['draft', 'published', 'archived']);
    $table->json('metadata')->nullable();
    $table->decimal('price', 8, 2)->default(0.00);
    $table->timestamps();
    
    $table->index(['user_id', 'status']);
    $table->unique(['user_id', 'title']);
});

// Table modifications
$this->table('users', function (Blueprint $table) {
    $table->string('phone')->nullable();
    $table->dropColumn('old_column');
    $table->index('email');
});

// Foreign key constraints
$table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->onDelete('cascade')
    ->onUpdate('restrict');
```

### Relations - Database Relationships

The Relations system provides powerful relationship management with lazy and eager loading capabilities.

#### Key Features:
- **HasMany**: One-to-many relationships
- **BelongsTo**: Many-to-one relationships  
- **BelongsToMany**: Many-to-many relationships
- **Eager Loading**: Prevent N+1 query problems
- **Lazy Loading**: Load relationships on demand
- **Constraint Management**: Automatic foreign key constraints

#### HasMany Relationship:

```php
class User extends ActiveRecord
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
}

// Usage
$user = User::find(1);
$posts = $user->posts; // Lazy loading
$posts = $user->posts()->where('published', true)->get();
$count = $user->posts()->count();
```

#### BelongsTo Relationship:

```php
class Post extends ActiveRecord
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

// Usage
$post = Post::find(1);
$author = $post->user; // Lazy loading
$authorName = $post->user->name;
```

#### BelongsToMany Relationship:

```php
class User extends ActiveRecord
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
}

class Role extends ActiveRecord
{
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }
}

// Usage
$user = User::find(1);
$roles = $user->roles; // Collection of roles
$hasRole = $user->roles()->where('name', 'admin')->exists();

// Attach/detach relationships
$user->roles()->attach($roleId);
$user->roles()->detach($roleId);
$user->roles()->sync([$role1Id, $role2Id]);
```

## Complete Database Setup and Usage

```php
// 1. Database configuration
$config = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'treehouse_app',
    'username' => 'root',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

// 2. Create connection
$connection = new Connection($config);
ActiveRecord::setConnection($connection);

// 3. Run migrations
$migration = new CreateUsersTable($connection);
$migration->up();

// 4. Use models
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

$posts = $user->posts()->create([
    'title' => 'My First Post',
    'content' => 'This is the content of my first post.'
]);
```

## Model Definitions with Relationships

```php
// User model with relationships
class User extends ActiveRecord
{
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean'
    ];
    
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
    
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
    
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
}

// Post model
class Post extends ActiveRecord
{
    protected array $fillable = ['title', 'content', 'status', 'user_id'];
    protected array $casts = [
        'published_at' => 'datetime',
        'metadata' => 'json'
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
    }
}

// Role model for RBAC
class Role extends ActiveRecord
{
    protected array $fillable = ['name', 'slug', 'description'];
    
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
    
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }
}
```

## Complex Query Operations

```php
// Advanced querying with relationships
$users = User::query()
    ->with(['posts', 'roles'])
    ->where('active', true)
    ->whereHas('posts', function($query) {
        $query->where('published', true);
    })
    ->orderBy('created_at', 'desc')
    ->paginate(1, 20);

// Aggregations with relationships
$userStats = User::query()
    ->select(['users.*'])
    ->withCount(['posts', 'roles'])
    ->having('posts_count', '>', 5)
    ->get();

// Complex joins
$data = User::query()
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->join('categories', 'posts.category_id', '=', 'categories.id')
    ->select([
        'users.name',
        'COUNT(posts.id) as post_count',
        'categories.name as category'
    ])
    ->groupBy(['users.id', 'categories.id'])
    ->orderBy('post_count', 'desc')
    ->get();
```

## Advanced Relationship Operations

```php
// Eager loading with constraints
$users = User::with(['posts' => function($query) {
    $query->where('published', true)
          ->orderBy('created_at', 'desc')
          ->limit(5);
}])->get();

// Nested eager loading
$posts = Post::with(['user.roles', 'tags'])->get();

// Relationship existence queries
$usersWithPosts = User::has('posts')->get();
$usersWithPublishedPosts = User::whereHas('posts', function($query) {
    $query->where('published', true);
})->get();

// Relationship counting
$users = User::withCount(['posts', 'roles'])->get();
foreach ($users as $user) {
    echo "{$user->name} has {$user->posts_count} posts and {$user->roles_count} roles";
}
```

## Performance Considerations

- **Connection Pooling**: Reuse database connections efficiently
- **Query Optimization**: Use indexes and proper WHERE clauses
- **Eager Loading**: Prevent N+1 query problems with `with()`
- **Pagination**: Use `paginate()` for large result sets
- **Query Caching**: Cache frequently used queries
- **Batch Operations**: Use bulk inserts/updates for large datasets

## Security Features

- **Parameter Binding**: Automatic SQL injection prevention
- **Mass Assignment Protection**: Fillable/guarded attributes
- **Query Logging**: Monitor and audit database operations
- **Transaction Support**: Ensure data consistency
- **Connection Security**: Encrypted connections and credential management

## Integration with Other Layers

The Database layer integrates seamlessly with:
- **Foundation Layer**: Automatic service registration and dependency injection
- **Auth Layer**: User authentication and RBAC system
- **Console Layer**: Database commands and migrations
- **Cache Layer**: Query result caching and performance optimization