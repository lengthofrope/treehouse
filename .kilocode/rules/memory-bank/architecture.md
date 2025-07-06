# TreeHouse Framework - Architecture

## System Architecture

TreeHouse follows a **layered, modular architecture** with clear separation of concerns. The framework is organized into distinct layers that work together to provide a comprehensive web development solution.

### Core Architecture Layers

```mermaid
graph TB
    A[Application Layer] --> B[Foundation Layer]
    B --> C[Router Layer]
    B --> D[View Layer]
    B --> E[Auth Layer]
    B --> F[Database Layer]
    B --> G[Cache Layer]
    B --> H[Console Layer]
    B --> I[Cron Layer]
    B --> J[Security Layer]
    B --> K[Validation Layer]
    B --> L[Support Layer]
    B --> M[Http Layer]
    B --> N[Error Layer]
```

## Source Code Paths

### Core Framework (`src/TreeHouse/`)
- **Foundation/**: Application container, dependency injection, bootstrapping
  - [`Application.php`](src/TreeHouse/Foundation/Application.php) - Main application class, service registration
  - [`Container.php`](src/TreeHouse/Foundation/Container.php) - DI container with automatic resolution
- **Database/**: ORM, query builder, migrations
  - [`ActiveRecord.php`](src/TreeHouse/Database/ActiveRecord.php) - Base model class with relationships
  - [`QueryBuilder.php`](src/TreeHouse/Database/QueryBuilder.php) - SQL query builder
  - [`Connection.php`](src/TreeHouse/Database/Connection.php) - PDO wrapper
  - [`Relations/`](src/TreeHouse/Database/Relations/) - Relationship implementations
- **Router/**: URL routing and middleware
  - [`Router.php`](src/TreeHouse/Router/Router.php) - Main routing engine
  - [`RouteCollection.php`](src/TreeHouse/Router/RouteCollection.php) - Route registry
  - [`Middleware/`](src/TreeHouse/Router/Middleware/) - HTTP middleware
  - [`Middleware/RateLimit/`](src/TreeHouse/Router/Middleware/RateLimit/) - Enterprise rate limiting system
    - [`RateLimitMiddleware.php`](src/TreeHouse/Router/Middleware/RateLimit/RateLimitMiddleware.php) - Main middleware class
    - [`RateLimitManager.php`](src/TreeHouse/Router/Middleware/RateLimit/RateLimitManager.php) - Strategy and resolver orchestrator
    - [`RateLimitConfig.php`](src/TreeHouse/Router/Middleware/RateLimit/RateLimitConfig.php) - Configuration parsing and validation
    - [`Strategies/`](src/TreeHouse/Router/Middleware/RateLimit/Strategies/) - Rate limiting algorithms (Fixed Window, Sliding Window, Token Bucket)
    - [`KeyResolvers/`](src/TreeHouse/Router/Middleware/RateLimit/KeyResolvers/) - Client identification methods (IP, User, Header, Composite)
- **Auth/**: Authentication and authorization
  - [`AuthManager.php`](src/TreeHouse/Auth/AuthManager.php) - Auth service orchestrator
  - [`SessionGuard.php`](src/TreeHouse/Auth/SessionGuard.php) - Session-based authentication
  - [`Gate.php`](src/TreeHouse/Auth/Gate.php) - Authorization gate for permissions
- **View/**: Template engine and rendering
  - [`ViewFactory.php`](src/TreeHouse/View/ViewFactory.php) - View factory and management
  - [`ViewEngine.php`](src/TreeHouse/View/ViewEngine.php) - Template compilation
  - [`Compilers/`](src/TreeHouse/View/Compilers/) - Template processors
- **Console/**: CLI framework
  - [`Application.php`](src/TreeHouse/Console/Application.php) - CLI application runner with intelligent context detection
  - [`Commands/`](src/TreeHouse/Console/Commands/) - Built-in commands with grouping support
- **Cron/**: Scheduled job system
  - [`CronScheduler.php`](src/TreeHouse/Cron/CronScheduler.php) - Main scheduler orchestrator
  - [`JobRegistry.php`](src/TreeHouse/Cron/JobRegistry.php) - Job registration and discovery
  - [`JobExecutor.php`](src/TreeHouse/Cron/JobExecutor.php) - Individual job execution engine
  - [`CronExpressionParser.php`](src/TreeHouse/Cron/CronExpressionParser.php) - Cron expression parsing and validation
  - [`Locking/`](src/TreeHouse/Cron/Locking/) - File-based locking system
  - [`Jobs/`](src/TreeHouse/Cron/Jobs/) - Built-in cron jobs (cache cleanup, lock cleanup)
  - [`Exceptions/`](src/TreeHouse/Cron/Exceptions/) - Cron-specific exception hierarchy
- **Security/**: Security features
  - [`Hash.php`](src/TreeHouse/Security/Hash.php) - Password hashing
  - [`Csrf.php`](src/TreeHouse/Security/Csrf.php) - CSRF protection
  - [`Encryption.php`](src/TreeHouse/Security/Encryption.php) - AES-256-CBC encryption
- **Validation/**: Input validation
  - [`Validator.php`](src/TreeHouse/Validation/Validator.php) - Main validator
  - [`Rules/`](src/TreeHouse/Validation/Rules/) - 25+ validation rules
- **Support/**: Utility classes
  - [`Collection.php`](src/TreeHouse/Support/Collection.php) - Array collection helper
  - [`Carbon.php`](src/TreeHouse/Support/Carbon.php) - Date/time manipulation
  - [`Str.php`](src/TreeHouse/Support/Str.php) - String utilities
- **Errors/**: Error handling and logging
  - [`ErrorHandler.php`](src/TreeHouse/Errors/ErrorHandler.php) - Main error handler with PSR-3 logging
  - [`Exceptions/`](src/TreeHouse/Errors/Exceptions/) - Hierarchical exception system
  - [`Logging/`](src/TreeHouse/Errors/Logging/) - PSR-3 compliant logger with multiple formats
  - [`Rendering/`](src/TreeHouse/Errors/Rendering/) - Multi-format error rendering (JSON, HTML, CLI)
  - [`Context/`](src/TreeHouse/Errors/Context/) - Context collection and sanitization
  - [`Classification/`](src/TreeHouse/Errors/Classification/) - Exception categorization and severity

### Application Layer (`src/App/`)
- **Controllers/**: Application controllers
- **Models/**: Application models extending ActiveRecord

### Configuration (`config/`)
- [`app.php`](config/app.php) - Application settings
- [`database.php`](config/database.php) - Database connections
- [`auth.php`](config/auth.php) - Authentication configuration
- [`view.php`](config/view.php) - Template engine settings
- [`cron.php`](config/cron.php) - Cron system configuration
- [`routes/`](config/routes/) - Route definitions

### Entry Points
- [`public/index.php`](public/index.php) - HTTP request handler
- [`bin/treehouse`](bin/treehouse) - CLI entry point

## Key Technical Decisions

### 1. **Zero External Dependencies**
- All functionality implemented in pure PHP 8.4+
- Only requires standard PHP extensions (PDO, JSON, mbstring, OpenSSL, fileinfo, filter)
- Self-contained ecosystem eliminates dependency management complexity

### 2. **Laravel-Inspired API Design**
- Familiar method signatures for easy migration from Laravel
- Similar patterns for models, controllers, routing, and views
- Maintains Laravel conventions while being completely independent

### 3. **Service Container with Auto-Wiring**
- Dependency injection container with automatic resolution
- Constructor injection support
- Singleton and transient service lifetimes
- Circular dependency detection

### 4. **ActiveRecord ORM Pattern**
- Eloquent-style models with magic methods
- Relationship support (hasOne, hasMany, belongsTo, belongsToMany)
- Query scopes and model events
- Mass assignment protection with fillable/guarded

### 5. **Custom Template Engine**
- HTML-valid template syntax
- Server-side rendering with caching
- Auth integration and conditional rendering
- Component-based architecture

## Design Patterns in Use

### 1. **MVC (Model-View-Controller)**
- Clear separation between data (Models), presentation (Views), and logic (Controllers)
- Models extend ActiveRecord base class
- Controllers handle HTTP requests and return responses
- Views use template engine for rendering

### 2. **Service Container Pattern**
- Central registry for all application services
- Automatic dependency resolution via reflection
- Lazy loading of services

### 3. **Active Record Pattern**
- Models represent database tables
- Models contain both data and behavior
- Direct mapping between object attributes and database columns

### 4. **Repository Pattern** (via QueryBuilder)
- Query builders abstract database operations
- Fluent interface for building complex queries
- Separation of query logic from business logic

### 5. **Facade Pattern** (via Helper Functions)
- Simple interfaces to complex subsystems
- Global helper functions provide easy access to framework features
- Examples: `auth()`, `cache()`, `view()`

## Component Relationships

### Request Lifecycle
1. **HTTP Request** → `public/index.php`
2. **Application Bootstrap** → Foundation/Application.php
3. **Service Registration** → Foundation/Container.php
4. **Route Resolution** → Router/Router.php
5. **Controller Dispatch** → Application controllers
6. **Response Generation** → Http/Response.php

### Database Flow
1. **Model Query** → Database/ActiveRecord.php
2. **Query Building** → Database/QueryBuilder.php
3. **Connection Management** → Database/Connection.php
4. **Result Hydration** → Back to ActiveRecord models

### Authentication Flow
1. **Auth Request** → Auth/AuthManager.php
2. **Guard Selection** → Auth/SessionGuard.php
3. **User Provider** → Auth/DatabaseUserProvider.php
4. **Authorization** → Auth/Gate.php

### Cron Execution Flow
1. **Cron Trigger** → Console/Commands/CronCommands/CronRunCommand.php
2. **Scheduler Start** → Cron/CronScheduler.php
3. **Job Discovery** → Cron/JobRegistry.php
4. **Expression Parsing** → Cron/CronExpressionParser.php
5. **Job Execution** → Cron/JobExecutor.php
6. **Lock Management** → Cron/Locking/LockManager.php

### Error Handling Flow
1. **Exception Thrown** → Errors/ErrorHandler.php
2. **Exception Classification** → Errors/Classification/ExceptionClassifier.php
3. **Context Collection** → Errors/Context/ContextManager.php
4. **Logging** → Errors/Logging/Logger.php
5. **Error Rendering** → Errors/Rendering/[Json|Html|Cli]Renderer.php

### Rate Limiting Flow
1. **HTTP Request** → Router/Middleware/RateLimit/RateLimitMiddleware.php
2. **Strategy Selection** → RateLimitManager.php resolves configured strategy
3. **Client Identification** → KeyResolvers/[Ip|User|Header|Composite]KeyResolver.php
4. **Limit Checking** → Strategies/[FixedWindow|SlidingWindow|TokenBucket]Strategy.php
5. **Response Headers** → RateLimitHeaders.php adds X-RateLimit-* headers
6. **Rate Limit Exceeded** → HTTP 429 with beautiful error page

## Critical Implementation Paths

### 1. **Application Bootstrapping**
```
Foundation/Application::__construct()
├── Container initialization
├── Environment loading (Support/Env)
├── Configuration loading
├── Service registration
└── Route loading
```

### 2. **Request Handling**
```
Application::handle(Request)
├── Router::dispatch()
├── Middleware execution
├── Controller resolution
├── Method invocation
└── Response generation
```

### 3. **Database Operations**
```
ActiveRecord::save()
├── Attribute validation
├── Timestamp updates
├── QueryBuilder::insert/update
└── Connection::execute()
```

### 4. **Template Rendering**
```
ViewFactory::make()
├── Template location
├── ViewEngine::compile()
├── Cache checking
├── PHP execution
└── HTML output
```

### 5. **Error Handling**
```
ErrorHandler::handle(Exception)
├── Exception classification
├── Context collection (Request, User, Environment)
├── PSR-3 logging with structured data
├── Error rendering (JSON/HTML/CLI)
└── HTTP response generation
```

## Security Architecture

### 1. **Input Sanitization**
- Automatic escaping in templates
- Validation rules for all inputs
- SQL injection prevention via prepared statements

### 2. **Authentication Security**
- Secure password hashing (PHP password_hash)
- Session management with security headers
- CSRF protection for state-changing operations

### 3. **Data Protection**
- AES-256-CBC encryption for sensitive data
- Secure random token generation
- Mass assignment protection in models

### 4. **Rate Limiting Protection**
- Enterprise-grade request throttling middleware
- Multiple strategies: Fixed Window, Sliding Window, Token Bucket
- Flexible client identification: IP, User, Header, Composite
- Configurable limits per endpoint and user type
- Standard rate limit headers and error responses

## Performance Considerations

### 1. **Caching Strategy**
- Template compilation caching
- File-based application cache
- Query result caching capability

### 2. **Lazy Loading**
- Services loaded on-demand
- Relationship lazy loading
- Configuration lazy loading

### 3. **Memory Management**
- Efficient collection handling
- Connection pooling (single connection reuse)
- Minimal object creation overhead