# TreeHouse Framework - Technical Specifications

## Core Technologies

### **PHP Requirements**
- **Minimum Version**: PHP 8.4+
- **Strict Typing**: All files use `declare(strict_types=1);`
- **Modern Features**: Union types, match expressions, named arguments, attributes
- **Error Handling**: Comprehensive exception handling with custom exception types

### **Required PHP Extensions**
- **ext-pdo**: Database connectivity (PDO abstraction layer)
- **ext-json**: JSON encoding/decoding for API responses and caching
- **ext-mbstring**: Multi-byte string handling for internationalization
- **ext-openssl**: Encryption, CSRF tokens, and secure random generation
- **ext-fileinfo**: File type detection and validation
- **ext-filter**: Input validation and sanitization

### **Zero External Dependencies**
- **No Composer packages** required for core functionality
- **No vendor dependencies** - completely self-contained
- **Only development dependency**: PHPUnit 11.0+ for testing

## Architecture Standards

### **PSR Compliance**
- **PSR-4**: Autoloading standard with namespace `LengthOfRope\TreeHouse`
- **PSR-12**: Extended coding style guide
- **PSR-3**: Logger interface (planned for future implementation)

### **Design Patterns**
- **MVC Architecture**: Model-View-Controller separation
- **Dependency Injection**: Constructor injection with auto-wiring
- **Active Record**: Database models with integrated query capabilities
- **Service Container**: Centralized dependency management
- **Factory Pattern**: ViewFactory, QueryBuilder creation
- **Strategy Pattern**: Multiple auth guards, cache drivers, rate limiting strategies

## Database Support

### **Database Drivers**
- **Primary**: MySQL 8.0+ (native PDO driver)
- **Secondary**: SQLite 3.15+ (file-based development)
- **Planned**: PostgreSQL 12+ support

### **Database Features**
- **Query Builder**: Fluent SQL generation with parameter binding
- **Migrations**: Database versioning with up/down methods
- **Relationships**: HasOne, HasMany, BelongsTo, BelongsToMany
- **Connection Pooling**: Single connection reuse for performance
- **Prepared Statements**: SQL injection prevention

### **ORM Capabilities**
- **Mass Assignment Protection**: Fillable/guarded attributes
- **Automatic Timestamps**: created_at/updated_at handling
- **Attribute Casting**: Type conversion (json, datetime, boolean)
- **Model Events**: Hooks for save, update, delete operations
- **Soft Deletes**: Logical record deletion (planned)

## Security Implementation

### **Authentication & Authorization**
- **Session-based Auth**: PHP sessions with security headers
- **RBAC System**: Role-based access control with permissions
- **Password Security**: PHP `password_hash()` with bcrypt/argon2
- **Auth Guards**: Multiple authentication mechanisms
- **Gate System**: Policy-based authorization

### **Data Protection**
- **Encryption**: AES-256-CBC symmetric encryption
- **CSRF Protection**: Token-based request validation
- **Input Sanitization**: Automatic escaping in templates
- **XSS Prevention**: Context-aware output encoding
- **SQL Injection Prevention**: Prepared statements only

### **Security Headers**
- **HttpOnly Cookies**: Prevent XSS cookie access
- **Secure Cookies**: HTTPS-only transmission
- **SameSite Protection**: CSRF attack mitigation
- **Session Security**: Regeneration on authentication state changes

### **Rate Limiting Protection**
- **Enterprise Middleware**: Multi-strategy rate limiting with advanced features
- **Multiple Strategies**: Fixed Window, Sliding Window, Token Bucket algorithms
- **Key Resolvers**: IP, User, Header, Composite client identification
- **Standard Headers**: X-RateLimit-* response headers
- **Configurable Limits**: Per-endpoint and per-user-type rate limiting
- **Beautiful Error Pages**: HTTP 429 responses with debugging information

## Template Engine

### **Template Features Status**
#### ✅ **Currently Implemented**
- **Basic Directives**: `th:text`, `th:raw`, `th:if`, `th:repeat`
- **Layout System**: `th:extend`, `th:section`, `th:yield`
- **Object Access**: Deep dot notation (`user.profile.settings.theme`)
- **Brace Expressions**: `{user.name}` in text content
- **Universal Attributes**: `th:data-id="user.id"`, `th:src="user.avatar"`
- **Switch Logic**: `th:switch`, `th:case`, `th:default` - Complete implementation
- **Content Inclusion**: `th:fragment`, `th:include`, `th:replace` - Complete implementation
- **Local Variables**: `th:with` - Complete implementation
- **Form Helpers**: `th:field`, `th:errors`, `th:csrf`, `th:method` - Complete implementation

### **Template Features**
- **Compilation Caching**: Compiled templates cached to disk
- **Inheritance**: Layout system with blocks and extends
- **Components**: Reusable template components
- **Auto-Escaping**: XSS prevention by default
- **Error Handling**: Detailed template error reporting

## CLI Framework

### **Console Application**
- **Command Registration**: Automatic command discovery
- **Argument Parsing**: POSIX-style argument handling
- **Interactive Prompts**: User input collection
- **Progress Bars**: Long-running task feedback
- **Colored Output**: Terminal color support

### **Built-in Commands**
- **Database Commands**: Migrations, seeders, schema management
- **User Commands**: User creation, role assignment
- **Cache Commands**: Clear, warm, status operations
- **Development Commands**: Asset compilation, optimization

## Validation System

### **25+ Built-in Rules**
- **Basic**: required, string, integer, boolean, array
- **Comparison**: min, max, between, in, not_in
- **Format**: email, url, uuid, regex, date
- **File**: file, image, mimes, size
- **Database**: unique, exists
- **Custom**: user-defined validation rules

### **Validation Features**
- **Conditional Rules**: Rules that apply based on other field values
- **Nested Validation**: Deep array and object validation
- **Error Messages**: Customizable error message templates
- **Multiple Languages**: Internationalization support (planned)

## Caching System

### **File-Based Caching**
- **Storage**: Filesystem cache with configurable paths
- **Serialization**: PHP serialize/unserialize for complex data
- **TTL Support**: Time-to-live expiration
- **Pattern Matching**: Wildcard cache key operations
- **Atomic Operations**: Race condition prevention

### **Cache Features**
- **Multiple Stores**: Different cache configurations
- **Tag Support**: Grouped cache invalidation (planned)
- **Compression**: Gzip compression for large values (planned)
- **Memory Limits**: Automatic cleanup of old entries

## Development Environment

### **Development Tools**
- **PHPUnit 11.0+**: Unit and integration testing
- **Code Coverage**: PCOV-based coverage reports
- **Static Analysis**: Compatible with PHPStan, Psalm
- **Debugging**: Xdebug integration support

### **Build System**
- **Asset Compilation**: JavaScript/CSS processing with PostCSS
- **Hot Reloading**: Development server with auto-refresh
- **Production Optimization**: Minification and optimization
- **Service Workers**: Progressive Web App support (planned)

### **Project Structure**
```
treehouse-framework/
├── bin/treehouse              # CLI entry point
├── config/                    # Configuration files
├── public/index.php           # Web entry point
├── resources/                 # Views, assets, translations
├── src/TreeHouse/            # Framework core
├── src/App/                  # Application code
├── storage/                  # Cache, logs, compiled views
├── tests/                    # Test suites
└── vendor/                   # Composer dependencies (dev only)
```

## Performance Characteristics

### **Benchmarks** (Target Goals)
- **Cold Start**: < 50ms application bootstrap
- **Hot Path**: < 5ms for cached route resolution
- **Memory Usage**: < 10MB baseline memory footprint
- **Database**: < 100 queries per page (with eager loading)

### **Optimization Strategies**
- **Opcode Caching**: OPcache optimization
- **Template Caching**: Compiled template storage
- **Query Optimization**: Efficient SQL generation
- **Lazy Loading**: On-demand service instantiation
- **Connection Reuse**: Single database connection per request

## Deployment Requirements

### **Server Requirements**
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: 8.4+ with required extensions
- **Database**: MySQL 8.0+ or SQLite 3.15+
- **Storage**: Writable storage/ directory for cache and logs

### **Production Configuration**
- **Environment Files**: `.env` for configuration
- **Error Handling**: Production-safe error messages
- **Logging**: File-based application logging
- **Security**: Production security headers and settings

### **Scalability Considerations**
- **Stateless Design**: Session-based but horizontally scalable
- **Database Scaling**: Read/write splitting support (planned)
- **Caching Layers**: Redis/Memcached support (planned)
- **Load Balancing**: Standard PHP application patterns