# TreeHouse Events Layer

The Events layer provides a comprehensive event system for the TreeHouse framework, enabling loose coupling between components through an observer pattern implementation. This layer supports synchronous event dispatching, model lifecycle events, and extensible listener registration.

## Overview

The Events system allows different parts of your application to communicate without direct dependencies. Components can fire events when something happens, and other components can listen for those events and react accordingly.

## Key Components

### Event Dispatching
- **EventDispatcher** - Interface defining event dispatching contracts
- **SyncEventDispatcher** - Synchronous event dispatching implementation
- **Event** - Base event class with metadata and context management
- **ModelEvent** - Specialized event class for ActiveRecord model events

### Event Listeners
- **EventListener** - Interface for event listeners
- **AbstractEventListener** - Base implementation with common functionality
- **HasEvents** - Trait for ActiveRecord models to automatically fire lifecycle events

### Model Events
- **ModelCreating/Created** - Fired before/after model creation
- **ModelUpdating/Updated** - Fired before/after model updates
- **ModelDeleting/Deleted** - Fired before/after model deletion
- **ModelSaving/Saved** - Fired before/after save operations

## Quick Start

### Basic Event Usage

```php
// Fire a custom event
event(new OrderProcessed($order));

// Listen for events
listen(OrderProcessed::class, function($event) {
    // Handle the event
    $order = $event->order;
    // Send confirmation email, update inventory, etc.
});
```

### Model Events

Model events are automatically fired during ActiveRecord operations:

```php
// These operations automatically fire events:
$user = User::create(['name' => 'John', 'email' => 'john@example.com']);
// Fires: ModelSaving, ModelCreating, ModelCreated, ModelSaved

$user->update(['name' => 'John Doe']);
// Fires: ModelSaving, ModelUpdating, ModelUpdated, ModelSaved

$user->delete();
// Fires: ModelDeleting, ModelDeleted
```

### Event Cancellation

Some events can be cancelled by returning `false` from a listener:

```php
User::creating(function($event) {
    if (!$event->model->isValid()) {
        return false; // Cancel the creation
    }
});
```

### Custom Event Listeners

Create dedicated listener classes:

```php
use LengthOfRope\TreeHouse\Events\AbstractEventListener;

class SendWelcomeEmail extends AbstractEventListener
{
    public function handle(UserCreated $event): void
    {
        $user = $event->model;
        
        // Send welcome email
        mail()->to($user->email)
            ->subject('Welcome!')
            ->send();
    }
    
    public function shouldQueue(): bool
    {
        return true; // Queue this listener for async processing
    }
}

// Register the listener
User::created(SendWelcomeEmail::class);
```

## Event Dispatcher

The `EventDispatcher` is the core component that manages event dispatching and listener registration.

### Basic Usage

```php
$dispatcher = app('events');

// Register a listener
$dispatcher->listen(UserCreated::class, $listener);

// Dispatch an event
$event = new UserCreated($user);
$dispatcher->dispatch($event);

// Dispatch until first non-null result
$result = $dispatcher->until($event);
```

### Listener Priority

Listeners can be registered with priority (higher numbers execute first):

```php
$dispatcher->listen(UserCreated::class, $highPriorityListener, 100);
$dispatcher->listen(UserCreated::class, $lowPriorityListener, 1);
```

## Events

### Base Event Class

All events extend the base `Event` class:

```php
use LengthOfRope\TreeHouse\Events\Event;

class OrderProcessed extends Event
{
    public function __construct(
        public readonly Order $order,
        array $context = []
    ) {
        parent::__construct($context);
    }
}
```

### Event Properties

Every event includes:
- `timestamp` - When the event was created
- `eventId` - Unique identifier for the event instance
- `context` - Additional context data
- Propagation control methods

### Event Context

Events can carry additional context data:

```php
event(new UserLoggedIn($user, [
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'timestamp' => now()
]));
```

## Model Events

### Automatic Events

When using the `HasEvents` trait, ActiveRecord models automatically fire events:

```php
use LengthOfRope\TreeHouse\Events\Concerns\HasEvents;

class User extends ActiveRecord
{
    use HasEvents;
    // Events are automatically fired during save(), delete(), etc.
}
```

### Event Types

- **creating** - Before a model is created (cancellable)
- **created** - After a model is created
- **updating** - Before a model is updated (cancellable)
- **updated** - After a model is updated
- **deleting** - Before a model is deleted (cancellable)
- **deleted** - After a model is deleted
- **saving** - Before a model is saved (cancellable)
- **saved** - After a model is saved

### Registering Model Event Listeners

```php
// Using static methods
User::creating(function($event) {
    // Validate before creation
});

User::created(function($event) {
    // Send welcome email
});

// Using classes
User::created(SendWelcomeEmailListener::class);

// With priority
User::creating($listener, 100);
```

## Event Listeners

### Listener Interface

All listeners implement the `EventListener` interface:

```php
interface EventListener
{
    public function handle(object $event): mixed;
    public function shouldQueue(): bool;
    public function getQueue(): ?string;
    public function getPriority(): int;
    public function canHandle(object $event): bool;
}
```

### Abstract Listener

Use `AbstractEventListener` for common functionality:

```php
class MyListener extends AbstractEventListener
{
    protected bool $shouldQueue = true;
    protected ?string $queue = 'emails';
    protected int $priority = 10;
    
    public function handle(UserCreated $event): void
    {
        // Handle the event
    }
}
```

### Auto-Detection

Listeners can automatically detect which events they handle based on method signatures:

```php
class AutoDetectListener extends AbstractEventListener
{
    public function handle(UserCreated $event): void
    {
        // Automatically handles UserCreated events
    }
}
```

## Helper Functions

### Global Helpers

```php
// Dispatch an event
event(new OrderProcessed($order));

// Register a listener
listen(UserCreated::class, $listener);

// Dispatch until first result
$result = until(new PaymentProcessing($payment));

// Access the event dispatcher
$dispatcher = app('events');
```

## Configuration

Configure the event system in `config/events.php`:

```php
return [
    'default_dispatcher' => 'sync',
    
    'model_events' => [
        'enabled' => true,
        'events' => ['creating', 'created', 'updating', 'updated', 'deleting', 'deleted', 'saving', 'saved'],
    ],
    
    'debugging' => [
        'enabled' => env('APP_DEBUG', false),
        'log_events' => env('LOG_EVENTS', false),
    ],
    
    'listeners' => [
        UserCreated::class => [
            SendWelcomeEmail::class,
            UpdateUserStatistics::class,
        ],
    ],
];
```

## Testing

### Testing Events

```php
class UserTest extends TestCase
{
    public function test_user_creation_fires_events()
    {
        $events = [];
        
        User::creating(function($event) use (&$events) {
            $events[] = 'creating';
        });
        
        User::created(function($event) use (&$events) {
            $events[] = 'created';
        });
        
        User::create(['name' => 'John']);
        
        $this->assertEquals(['creating', 'created'], $events);
    }
}
```

### Testing Listeners

```php
public function test_welcome_email_listener()
{
    $listener = new SendWelcomeEmail();
    $user = new User(['email' => 'test@example.com']);
    $event = new UserCreated($user);
    
    $listener->handle($event);
    
    // Assert email was sent
}
```

## Performance

### Listener Caching

The event dispatcher caches resolved listeners for improved performance:

```php
$dispatcher = app('events');

// Get performance statistics
$stats = $dispatcher->getStatistics();

// Clear listener cache if needed
$dispatcher->clearCache();
```

### Event Filtering

Listeners can filter events they handle:

```php
class ConditionalListener extends AbstractEventListener
{
    public function canHandle(object $event): bool
    {
        return $event instanceof UserCreated && 
               $event->model->isActive();
    }
}
```

## Error Handling

### Exception Handling

Event listeners that throw exceptions don't stop other listeners from executing:

```php
// This listener throws an exception
listen(UserCreated::class, function() {
    throw new Exception('Something went wrong');
});

// This listener will still execute
listen(UserCreated::class, function() {
    // This will still run
});
```

### Custom Error Handling

Listeners can implement custom error handling:

```php
class SafeListener extends AbstractEventListener
{
    protected function handleError(\Throwable $error, object $event): mixed
    {
        // Log the error and continue
        error_log("Listener failed: " . $error->getMessage());
        return null;
    }
}
```

## Best Practices

### Event Design

1. **Keep events focused** - One event should represent one thing that happened
2. **Use immutable data** - Events should contain read-only data about what happened
3. **Include context** - Provide enough information for listeners to act appropriately

### Listener Design

1. **Single responsibility** - Each listener should do one thing well
2. **Fail gracefully** - Don't let exceptions in one listener affect others
3. **Consider performance** - Use queued listeners for heavy operations

### Naming Conventions

1. **Events** - Use past tense: `UserCreated`, `OrderProcessed`, `PaymentCompleted`
2. **Listeners** - Use descriptive actions: `SendWelcomeEmail`, `UpdateInventory`, `LogUserActivity`

## Architecture

The Events layer integrates with other TreeHouse layers:

- **Foundation** - Automatic service registration and container integration
- **Database** - Model lifecycle events through ActiveRecord integration
- **Support** - Helper functions and utilities
- **Container** - Dependency injection for listeners

## File Structure

```
src/TreeHouse/Events/
├── EventDispatcher.php              # Event dispatcher interface
├── SyncEventDispatcher.php          # Synchronous implementation
├── Event.php                        # Base event class
├── ModelEvent.php                   # Model event base class
├── EventListener.php                # Listener interface
├── AbstractEventListener.php        # Base listener implementation
├── Concerns/
│   └── HasEvents.php                # ActiveRecord trait
├── Events/
│   ├── ModelCreating.php           # Model lifecycle events
│   ├── ModelCreated.php
│   ├── ModelUpdating.php
│   ├── ModelUpdated.php
│   ├── ModelDeleting.php
│   ├── ModelDeleted.php
│   ├── ModelSaving.php
│   └── ModelSaved.php
└── Exceptions/
    └── EventException.php           # Event system exceptions
```

## Future Enhancements

The Events layer is designed to support future enhancements:

- **QueuedEventDispatcher** - Asynchronous event processing
- **Event Broadcasting** - Real-time event broadcasting via WebSockets
- **Application Events** - HTTP request, authentication, and cache events
- **CLI Commands** - Event management and debugging commands
- **Event Testing** - Enhanced testing utilities and fakes

The current implementation provides a solid foundation for all these features while maintaining excellent performance and zero dependencies.