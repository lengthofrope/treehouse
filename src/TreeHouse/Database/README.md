# TreeHouse Database Library

The TreeHouse Database library provides a comprehensive set of classes for database operations in PHP. This library is designed to simplify database development by offering well-tested, secure utilities for connection management, query building, Active Record modeling, database migrations, and relationship handling.

**Enhanced with Support Classes**: This library now fully integrates with TreeHouse Support classes including Collection, Carbon, Arr, Str, Uuid, and helper functions for improved data handling, date operations, and modern PHP development patterns.

## Table of Contents

- [Classes Overview](#classes-overview)
  - [Connection - Database Connection Manager](#connection---database-connection-manager)
  - [QueryBuilder - Fluent Query Builder](#querybuilder---fluent-query-builder)
  - [ActiveRecord - Base Model Class](#activerecord---base-model-class)
  - [Migration - Database Migration System](#migration---database-migration-system)
  - [Relations - Database Relationships](#relations---database-relationships)
- [Usage Examples](#usage-examples)

## Classes Overview

### Connection - Database Connection Manager

The `Connection` class provides comprehensive database connection management with PDO, including transaction support, query execution, and connection pooling capabilities.

#### Key Features:
- **Multiple Database Support**: MySQL, SQLite, PostgreSQL drivers
- **Transaction Management**: Nested transactions with savepoints
- **Query Logging**: Optional query execution logging with timing
- **Connection Pooling**: Efficient connection reuse and management
- **Schema Introspection**: Table and column information retrieval

#### Core Methods:
```php
// Connection management
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'user',
    'password' => 'pass'
]);

$connection->connect();                    // Establish connection
$connection->disconnect();                 // Close connection
$connection->isConnected();               // Check connection status

// Query execution
$results = $connection->select('SELECT * FROM users WHERE active = ?', [1]);
$user = $connection->selectOne('SELECT * FROM users WHERE id = ?', [123]);
$id = $connection->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['John', 'john@example.com']);
$affected = $connection->update('UPDATE users SET active = ? WHERE id = ?', [0, 123]);
$deleted = $connection->delete('DELETE FROM users WHERE active = ?', [0]);

// Transaction management
$connection->beginTransaction();           // Start transaction
$connection->commit();                     // Commit transaction
$connection->rollback();                   // Rollback transaction

// Transaction with callback
$result = $connection->transaction(function($conn) {
    $conn->insert('INSERT INTO users (name) VALUES (?)', ['Alice']);
    $conn->insert('INSERT INTO profiles (user_id) VALUES (?)', [1]);
    return 'success';
});

// Schema introspection
$tables = $connection->getTableNames();    // Get all table names
$exists = $connection->tableExists('users'); // Check if table exists
$columns = $connection->getTableColumns('users'); // Get table columns

// Query logging
$connection->enableQueryLog(true);         // Enable logging
$log = $connection->getQueryLog();         // Get query log
$connection->clearQueryLog();              // Clear log
```

### QueryBuilder - Fluent Query Builder

The `QueryBuilder` class provides a fluent interface for building SQL queries with support for complex conditions, joins, aggregations, and subqueries.

#### Key Features:
- **Fluent Interface**: Chainable method calls for readable query building
- **Collection Results**: Query results are returned as Collection instances for enhanced data manipulation
- **Complex Conditions**: Support for WHERE, IN, BETWEEN, NULL conditions
- **Join Support**: INNER, LEFT, RIGHT joins with multiple conditions
- **Aggregation**: COUNT, SUM, AVG, MIN, MAX functions
- **Pagination**: Built-in limit/offset and pagination support

#### Core Methods:
```php
$builder = new QueryBuilder($connection, 'users');

// Basic queries - returns Collection instance
$users = $builder->select(['name', 'email'])
    ->where('active', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get(); // Returns Collection, not array

// Collection methods available
$userNames = $users->pluck('name');
$activeUsers = $users->filter(fn($user) => $user['active']);
$firstUser = $users->first();

// Complex conditions
$builder->where('age', '>', 18)
    ->where('status', 'active')
    ->orWhere('role', 'admin')
    ->whereIn('department', ['IT', 'HR'])
    ->whereNotNull('email')
    ->whereBetween('salary', 50000, 100000)
    ->whereLike('name', '%john%');

// Joins
$builder->join('profiles', 'users.id', '=', 'profiles.user_id')
    ->leftJoin('departments', 'users.dept_id', '=', 'departments.id')
    ->select(['users.*', 'profiles.bio', 'departments.name as dept_name']);

// Aggregation and grouping
$stats = $builder->select(['department', 'COUNT(*) as count', 'AVG(salary) as avg_salary'])
    ->groupBy('department')
    ->having('COUNT(*)', '>', 5)
    ->get();

// Pagination
$page1 = $builder->paginate(1, 15);       // Page 1, 15 items per page
$page2 = $builder->paginate(2, 15);       // Page 2, 15 items per page

// Data modification
$id = $builder->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'active' => 1
]);

$affected = $builder->where('id', 123)
    ->update(['active' => 0]);

$deleted = $builder->where('active', 0)
    ->delete();

// Utility methods
$count = $builder->where('active', 1)->count();
$exists = $builder->where('email', 'john@example.com')->exists();
$first = $builder->where('id', 123)->first();
$user = $builder->find(123);              // Find by primary key
```

### ActiveRecord - Base Model Class

The `ActiveRecord` class provides an Active Record implementation with support for relationships, mass assignment protection, attribute casting, and model events.

#### Key Features:
- **Active Record Pattern**: Database records as objects with behavior
- **Collection Results**: Query methods return Collection instances instead of arrays
- **Enhanced Date Handling**: Carbon integration for superior date/time operations
- **UUID Support**: Built-in UUID generation and handling for primary keys
- **Mass Assignment Protection**: Fillable and guarded attributes with Arr utility support
- **Attribute Casting**: Automatic type conversion including Carbon for dates
- **Relationships**: HasMany, BelongsTo, BelongsToMany relationships with Collection support
- **Timestamps**: Automatic created_at and updated_at handling with Carbon
- **Helper Functions**: Integration with collect(), data_get(), data_set() helpers

#### Core Methods:
```php
// Model definition
class User extends ActiveRecord
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected array $casts = [
        'id' => 'uuid',           // UUID casting support
        'active' => 'boolean',
        'settings' => 'json',
        'created_at' => 'datetime' // Uses Carbon for enhanced date handling
    ];
    
    // Relationships
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
    
    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
    
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
}

// Basic operations
$user = new User(['name' => 'John', 'email' => 'john@example.com']);
$user->save();                            // Save to database

$user = User::create([                    // Create and save
    'name' => 'Jane',
    'email' => 'jane@example.com'
]);

$user = User::find(123);                  // Find by primary key
$user = User::findOrFail(123);           // Find or throw exception
$users = User::where('active', true);    // Returns Collection
$users = User::all();                    // Returns Collection

// Collection methods available on results
$activeUsers = $users->filter(fn($user) => $user->active);
$userNames = $users->pluck('name');
$userCount = $users->count();

// Updates and deletion
$user->update(['name' => 'John Smith']);  // Update attributes
$user->delete();                         // Delete record

User::updateOrCreate(
    ['email' => 'john@example.com'],      // Search conditions
    ['name' => 'John Doe', 'active' => true] // Update/create data
);

// Attribute access
$user->name = 'New Name';                // Set attribute
$name = $user->name;                     // Get attribute
$user->fill(['name' => 'John', 'email' => 'john@example.com']);

// Dirty checking
$user->isDirty();                        // Check if model has changes
$user->isDirty(['name', 'email']);       // Check specific attributes
$dirty = $user->getDirty();              // Get changed attributes

// Array/JSON conversion
$array = $user->toArray();               // Convert to array
$json = $user->toJson();                 // Convert to JSON
```

### Migration - Database Migration System

The `Migration` class provides database schema migration capabilities with support for creating, modifying, and dropping tables and columns.

#### Key Features:
- **Schema Definition**: Fluent interface for table creation and modification
- **UUID Support**: Built-in UUID column types and primary key support
- **Enhanced Timestamps**: Carbon integration for better date/time handling
- **Column Types**: Support for all common database column types including UUID
- **Index Management**: Primary keys, unique constraints, regular indexes
- **Foreign Keys**: Relationship constraints with cascade options
- **Rollback Support**: Up and down migration methods

#### Core Methods:
```php
// Migration class
class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->createTable('users', function (Blueprint $table) {
            $table->uuid('id')->primary();        // UUID primary key
            // OR $table->uuidPrimary();           // Shorthand for UUID primary
            // OR $table->id();                    // Traditional auto-increment
            $table->string('name');                // VARCHAR(255)
            $table->string('email')->unique();    // Unique email
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->json('settings')->nullable();
            $table->uuid('profile_id')->nullable(); // UUID foreign key
            $table->timestamps();                  // created_at, updated_at (Carbon enhanced)
            
            // Indexes
            $table->index(['active', 'created_at']);
            $table->unique(['email']);
        });
    }
    
    public function down(): void
    {
        $this->dropTable('users');
    }
}

// Column types
$table->id();                             // Auto-increment primary key
$table->uuid('id');                       // UUID column
$table->uuidPrimary();                    // UUID primary key (shorthand)
$table->bigIncrements('id');             // Big auto-increment
$table->string('name', 100);             // VARCHAR with length
$table->text('description');             // TEXT column
$table->longText('content');             // LONGTEXT column
$table->integer('count');                // INT column
$table->bigInteger('big_number');        // BIGINT column
$table->boolean('active');               // BOOLEAN (TINYINT(1))
$table->decimal('price', 8, 2);          // DECIMAL(8,2)
$table->float('rating', 3, 1);           // FLOAT(3,1)
$table->date('birth_date');              // DATE column (Carbon enhanced)
$table->dateTime('created_at');          // DATETIME column (Carbon enhanced)
$table->timestamp('updated_at');         // TIMESTAMP column (Carbon enhanced)
$table->json('metadata');                // JSON column
$table->enum('status', ['active', 'inactive']); // ENUM column

// Column modifiers
$table->string('name')->nullable();       // Allow NULL
$table->integer('sort')->default(0);     // Default value
$table->bigInteger('amount')->unsigned(); // Unsigned
$table->string('slug')->unique();        // Unique constraint
$table->text('notes')->comment('User notes'); // Column comment

// Foreign keys
$table->foreignId('user_id')             // Foreign key column
    ->constrained('users')               // References users.id
    ->onDelete('cascade')                // Cascade on delete
    ->onUpdate('restrict');              // Restrict on update

// Table modification
$this->table('users', function (Blueprint $table) {
    $table->string('phone')->nullable(); // Add column
    $table->dropColumn('old_field');     // Drop column
    $table->index('phone');              // Add index
    $table->dropIndex('users_email_index'); // Drop index
});

// Utility methods
$this->hasTable('users');                // Check if table exists
$this->hasColumn('users', 'email');      // Check if column exists
$this->renameTable('old_name', 'new_name'); // Rename table
$this->statement('CREATE INDEX custom_idx ON users (name, email)'); // Raw SQL
```

### Relations - Database Relationships

The Relations classes provide support for defining and working with database relationships between models.

#### Key Features:
- **Collection Results**: All relationship queries return Collection instances
- **HasMany**: One-to-many relationships with Collection support
- **BelongsTo**: Many-to-one relationships
- **BelongsToMany**: Many-to-many relationships with pivot tables and Collection support
- **Enhanced Array Operations**: Arr utility methods for better data handling
- **Eager Loading**: Efficient loading of related models
- **Lazy Loading**: On-demand loading of relationships

#### HasMany Relationship:
```php
class User extends ActiveRecord
{
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
}

// Usage
$user = User::find(1);
$posts = $user->posts;                   // Returns Collection
$posts = $user->posts()->where('published', true)->get(); // Returns Collection

// Collection methods available
$publishedPosts = $posts->filter(fn($post) => $post->published);
$postTitles = $posts->pluck('title');
$latestPost = $posts->sortByDesc('created_at')->first();

// Create related models
$post = $user->posts()->create([
    'title' => 'New Post',
    'content' => 'Post content'
]);

// Save existing model
$post = new Post(['title' => 'Another Post']);
$user->posts()->save($post);

// Save multiple models
$user->posts()->saveMany([$post1, $post2]);
```

#### BelongsTo Relationship:
```php
class Post extends ActiveRecord
{
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

// Usage
$post = Post::find(1);
$user = $post->user;                     // Get the user

// Associate with parent
$user = User::find(1);
$post->user()->associate($user);
$post->save();

// Dissociate from parent
$post->user()->dissociate();
$post->save();
```

#### BelongsToMany Relationship:
```php
class User extends ActiveRecord
{
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,           // Related model
            'user_roles',          // Pivot table
            'user_id',            // Foreign key for this model
            'role_id'             // Foreign key for related model
        )->withPivot(['assigned_at', 'assigned_by']); // Additional pivot columns
    }
}

// Usage
$user = User::find(1);
$roles = $user->roles;                   // Returns Collection

// Collection methods available
$adminRoles = $roles->filter(fn($role) => $role->name === 'admin');
$roleNames = $roles->pluck('name');

// Attach roles
$user->roles()->attach([1, 2, 3]);       // Attach role IDs
$user->roles()->attach(1, ['assigned_at' => now()]); // With pivot data

// Detach roles
$user->roles()->detach([1, 2]);          // Detach specific roles
$user->roles()->detach();                // Detach all roles

// Sync roles (attach new, detach missing)
$user->roles()->sync([1, 2, 4]);         // Keep only these roles

// Toggle roles
$changes = $user->roles()->toggle([1, 2]); // Attach if missing, detach if present

// Access pivot data
foreach ($user->roles as $role) {
    echo $role->pivot->assigned_at;       // Access pivot attributes
}
```

## Usage Examples

### Complete Database Setup and Usage
```php
// Database connection setup
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'blog',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8mb4'
]);

// Set connection for models
ActiveRecord::setConnection($connection);

// Migration example
class CreateBlogTables extends Migration
{
    public function up(): void
    {
        // Users table
        $this->createTable('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        
        // Posts table
        $this->createTable('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->boolean('published')->default(false);
            $table->timestamps();
            
            $table->index(['published', 'created_at']);
        });
        
        // Tags table
        $this->createTable('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });
        
        // Post-Tag pivot table
        $this->createTable('post_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['post_id', 'tag_id']);
        });
    }
    
    public function down(): void
    {
        $this->dropTable('post_tags');
        $this->dropTable('tags');
        $this->dropTable('posts');
        $this->dropTable('users');
    }
}

// Run migration
$migration = new CreateBlogTables($connection);
$migration->up();
```

### Model Definitions with Relationships
```php
class User extends ActiveRecord
{
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected array $casts = ['active' => 'boolean'];
    
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
    
    public function publishedPosts()
    {
        return $this->hasMany(Post::class, 'user_id')
            ->where('published', true);
    }
}

class Post extends ActiveRecord
{
    protected array $fillable = ['title', 'content', 'published'];
    protected array $casts = [
        'published' => 'boolean',
        'created_at' => 'datetime'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tags', 'post_id', 'tag_id')
            ->withPivot(['created_at']);
    }
}

class Tag extends ActiveRecord
{
    protected array $fillable = ['name', 'slug'];
    
    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_tags', 'tag_id', 'post_id');
    }
}
```

### Complex Query Operations
```php
// Create user with posts
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

// Create posts for user
$post1 = $user->posts()->create([
    'title' => 'My First Post',
    'content' => 'This is my first blog post.',
    'published' => true
]);

$post2 = $user->posts()->create([
    'title' => 'Draft Post',
    'content' => 'This is a draft.',
    'published' => false
]);

// Create tags and associate with posts
$tag1 = Tag::create(['name' => 'PHP', 'slug' => 'php']);
$tag2 = Tag::create(['name' => 'Web Development', 'slug' => 'web-development']);

$post1->tags()->attach([$tag1->id, $tag2->id]);

// Complex queries
$publishedPosts = Post::query()
    ->where('published', true)
    ->whereHas('user', function($query) {
        $query->where('active', true);
    })
    ->with(['user', 'tags'])
    ->orderBy('created_at', 'DESC')
    ->paginate(1, 10);

// Aggregate queries
$userStats = User::query()
    ->select(['users.*'])
    ->selectRaw('COUNT(posts.id) as post_count')
    ->selectRaw('COUNT(CASE WHEN posts.published = 1 THEN 1 END) as published_count')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->groupBy('users.id')
    ->having('post_count', '>', 0)
    ->get();

// Transaction example
$connection->transaction(function() use ($user) {
    // Update user
    $user->update(['name' => 'John Smith']);
    
    // Create new post
    $user->posts()->create([
        'title' => 'Transaction Post',
        'content' => 'Created in transaction',
        'published' => true
    ]);
    
    // Update all user's posts
    $user->posts()->update(['updated_at' => date('Y-m-d H:i:s')]);
});
```

### Advanced Relationship Operations
```php
// Eager loading to prevent N+1 queries
$posts = Post::query()
    ->with(['user', 'tags'])
    ->where('published', true)
    ->get();

foreach ($posts as $post) {
    echo $post->title . ' by ' . $post->user->name;
    echo 'Tags: ' . implode(', ', $post->tags->pluck('name'));
}

// Conditional eager loading
$posts = Post::query()
    ->with([
        'user' => function($query) {
            $query->select(['id', 'name', 'email']);
        },
        'tags' => function($query) {
            $query->where('active', true);
        }
    ])
    ->get();

// Many-to-many operations
$user = User::find(1);

// Sync user roles (replace all existing)
$user->roles()->sync([1, 2, 3]);

// Attach roles with pivot data
$user->roles()->attach([
    1 => ['assigned_at' => now(), 'assigned_by' => 'admin'],
    2 => ['assigned_at' => now(), 'assigned_by' => 'admin']
]);

// Toggle roles
$changes = $user->roles()->toggle([1, 2, 4]);
echo "Attached: " . implode(', ', $changes['attached']);
echo "Detached: " . implode(', ', $changes['detached']);
```

## Performance Considerations

- **Connection Pooling**: Reuse database connections efficiently
- **Query Optimization**: Use indexes and optimize WHERE clauses
- **Eager Loading**: Prevent N+1 query problems with relationship loading
- **Pagination**: Use limit/offset for large result sets
- **Transaction Management**: Group related operations for consistency
- **Query Logging**: Monitor and optimize slow queries

## Security Features

- **Parameter Binding**: All queries use prepared statements
- **Mass Assignment Protection**: Fillable/guarded attributes prevent unwanted updates
- **SQL Injection Prevention**: Parameterized queries and input validation
- **Transaction Isolation**: Proper transaction handling for data consistency
- **Schema Validation**: Migration system ensures proper database structure

## Contributing

This library follows PSR-12 coding standards and includes comprehensive test coverage. When contributing:

1. Write tests for new functionality
2. Follow existing code style and patterns
3. Update documentation for new features
4. Ensure backward compatibility
5. Consider security implications of changes

## License

This library is part of the TreeHouse framework and follows the same licensing terms.