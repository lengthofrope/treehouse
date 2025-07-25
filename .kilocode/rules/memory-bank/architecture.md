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
    B --> J[Events Layer]
    B --> K[Security Layer]
    B --> L[Validation Layer]
    B --> M[Support Layer]
    B --> N[Http Layer]
    B --> O[Error Layer]
    B --> P[Mail Layer]
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
  - [`AuthManager.php`](src/TreeHouse/Auth/AuthManager.php) - Auth service orchestrator with JWT support
  - [`SessionGuard.php`](src/TreeHouse/Auth/SessionGuard.php) - Session-based authentication
  - [`JwtGuard.php`](src/TreeHouse/Auth/JwtGuard.php) - **JWT stateless authentication guard (558 lines)**
  - [`JwtUserProvider.php`](src/TreeHouse/Auth/JwtUserProvider.php) - **JWT user provider with stateless/hybrid modes (321 lines)**
  - [`Gate.php`](src/TreeHouse/Auth/Gate.php) - Authorization gate for permissions
  - [`Jwt/`](src/TreeHouse/Auth/Jwt/) - **JWT infrastructure components**
    - [`JwtConfig.php`](src/TreeHouse/Auth/Jwt/JwtConfig.php) - JWT configuration management
    - [`TokenGenerator.php`](src/TreeHouse/Auth/Jwt/TokenGenerator.php) - JWT token generation
    - [`TokenValidator.php`](src/TreeHouse/Auth/Jwt/TokenValidator.php) - JWT token validation
    - [`ClaimsManager.php`](src/TreeHouse/Auth/Jwt/ClaimsManager.php) - JWT claims management
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
- **Events/**: Event system and dispatching
  - [`EventDispatcher.php`](src/TreeHouse/Events/EventDispatcher.php) - Event dispatcher interface
  - [`SyncEventDispatcher.php`](src/TreeHouse/Events/SyncEventDispatcher.php) - Synchronous event dispatching implementation
  - [`Event.php`](src/TreeHouse/Events/Event.php) - Base event class with metadata and context management
  - [`ModelEvent.php`](src/TreeHouse/Events/ModelEvent.php) - Specialized event class for ActiveRecord model events
  - [`EventListener.php`](src/TreeHouse/Events/EventListener.php) - Event listener interface
  - [`AbstractEventListener.php`](src/TreeHouse/Events/AbstractEventListener.php) - Base listener implementation
  - [`Concerns/HasEvents.php`](src/TreeHouse/Events/Concerns/HasEvents.php) - ActiveRecord trait for automatic event firing
  - [`Events/`](src/TreeHouse/Events/Events/) - Model lifecycle events (creating, created, updating, updated, deleting, deleted, saving, saved)
  - [`Exceptions/`](src/TreeHouse/Events/Exceptions/) - Event-specific exception hierarchy
- **Mail/**: Email system (Complete Implementation)
  - [`MailManager.php`](src/TreeHouse/Mail/MailManager.php) - Mail service orchestrator
  - [`Message.php`](src/TreeHouse/Mail/Message.php) - Email message composition
  - [`Address.php`](src/TreeHouse/Mail/Address.php) - Email address handling with RFC validation
  - [`AddressList.php`](src/TreeHouse/Mail/AddressList.php) - Address collection management
  - [`Drivers/`](src/TreeHouse/Mail/Drivers/) - Mail delivery drivers (SMTP, Sendmail, Log)
  - [`Models/QueuedMail.php`](src/TreeHouse/Mail/Models/QueuedMail.php) - Database model for mail queue
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
  - [`Env.php`](src/TreeHouse/Support/Env.php) - Environment variable handling with .env file loading
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
- [`auth.php`](config/auth.php) - **Authentication configuration with complete JWT integration**
- [`view.php`](config/view.php) - Template engine settings
- [`cron.php`](config/cron.php) - Cron system configuration
- [`events.php`](config/events.php) - Event system configuration
- [`mail.php`](config/mail.php) - Mail system configuration
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

### 6. **Enterprise-Grade JWT Authentication**
- **RFC 7519 compliant** JWT implementation with zero external dependencies
- **Multi-algorithm support**: HS256, HS384, HS512, RS256, RS384, RS512, ES256, ES384, ES512
- **Stateless and hybrid modes** for flexible deployment scenarios
- **Multi-source token extraction** from Authorization header, cookies, and query parameters
- **Environment-driven configuration** with complete .env integration

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

### 6. **Strategy Pattern** (JWT Authentication)
- **JwtGuard**: Implements Guard interface for stateless authentication
- **JwtUserProvider**: Implements UserProvider interface with configurable modes
- **TokenExtraction**: Multiple extraction strategies with configurable priority

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
2. **Guard Selection** → Auth/SessionGuard.php or **Auth/JwtGuard.php**
3. **User Provider** → Auth/DatabaseUserProvider.php or **Auth/JwtUserProvider.php**
4. **Authorization** → Auth/Gate.php

### JWT Authentication Flow
1. **JWT Request** → Auth/JwtGuard.php
2. **Token Extraction** → Multi-source extraction (header/cookie/query)
3. **Token Validation** → Auth/Jwt/TokenValidator.php
4. **Claims Processing** → Auth/Jwt/ClaimsManager.php
5. **User Resolution** → Auth/JwtUserProvider.php (stateless/hybrid modes)

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

### Event Dispatching Flow
1. **Event Triggered** → Events/Event.php or Events/ModelEvent.php
2. **Dispatcher Resolution** → Events/SyncEventDispatcher.php
3. **Listener Discovery** → Container resolution and priority sorting
4. **Event Execution** → EventListener.handle() or callable execution
5. **Propagation Control** → Event.stopPropagation() and result handling

### Model Event Flow
1. **Model Operation** → Database/ActiveRecord.php with HasEvents trait
2. **Event Creation** → Events/Concerns/HasEvents.php creates lifecycle events
3. **Event Dispatching** → SyncEventDispatcher.dispatch() or until()
4. **Listener Execution** → Registered listeners handle model events
5. **Operation Continuation** → Model operation proceeds or halts based on results

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

### 6. **JWT Authentication**
```
JwtGuard::check()
├── Token extraction (header/cookie/query)
├── Token validation (signature, claims, expiration)
├── Claims processing and validation
├── User resolution (stateless/hybrid)
└── Authentication state management
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
- **JWT stateless authentication** with enterprise-grade security

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

### 5. **JWT Security Features**
- **RFC 7519 compliance** with comprehensive validation
- **Multi-algorithm support** for different security requirements
- **Timing-safe operations** to prevent timing attacks
- **Claims validation** with configurable required claims
- **Token expiration** with configurable TTL and refresh TTL

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

### 4. **JWT Performance**
- **Stateless authentication** eliminates session overhead
- **Token caching** for repeated validations
- **Efficient claim extraction** with minimal processing
- **Multi-source token extraction** with priority-based short-circuiting

## Framework Maturity Status

### All Core Components Complete ✅
TreeHouse Framework has reached full maturity with all 16+ core layers complete and extensively tested:

- **2393 Tests**: Comprehensive test coverage with 6787 assertions
- **Zero Dependencies**: Complete framework functionality in pure PHP 8.4+
- **Enterprise Features**: JWT authentication, rate limiting, mail system, events
- **Production Ready**: All layers tested and documented for production use
- **Developer Experience**: Rich CLI tools, comprehensive documentation
- **Security**: Enterprise-grade security features across all components
- **Performance**: Optimized for production deployment with caching and lazy loading

The framework represents a complete, production-ready web development solution with zero external dependencies and comprehensive feature coverage comparable to major PHP frameworks.