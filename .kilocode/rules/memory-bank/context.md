# TreeHouse Framework - Current Context

## Project Status
**Active Development** - Framework is functional but not yet production-ready. Currently in beta phase with feature completion and stability as primary focus.

## Current Work Focus
- **Production Readiness**: Final testing and bug fixes for stable release
- **Performance Benchmarking**: Compare against Laravel, Symfony for performance metrics
- **Documentation**: Complete user guides, API reference, and tutorials
- **Community**: Prepare for open-source release with contribution guidelines

## Recent Major Changes
- **Comprehensive Events System**: Complete event system implementation with synchronous dispatching, model lifecycle events, listener registration, and framework integration
- **Event Dispatching**: SyncEventDispatcher with priority support, propagation control, and container integration
- **Model Events**: Automatic lifecycle events (creating, created, updating, updated, deleting, deleted, saving, saved) with HasEvents trait
- **Event Listeners**: EventListener interface, AbstractEventListener base class, and automatic listener resolution
- **Event Configuration**: Complete configuration system with auto-discovery, debugging, and listener registration
- **Event Integration**: Framework-wide integration with helper functions (event(), listen(), until()) and container registration
- **Event Testing**: Comprehensive test coverage with integration tests and model event testing
- **Enterprise Rate Limiting System**: Comprehensive rate limiting middleware with multiple strategies (Fixed Window, Sliding Window, Token Bucket), key resolvers (IP, User, Header, Composite), and advanced features including configurable fallbacks, rate limit headers, and beautiful error pages
- **Rate Limiting Strategies**: Three distinct algorithms - Fixed Window (simple), Sliding Window (precise), Token Bucket (burst-friendly) with performance-optimized implementations
- **Rate Limiting Key Resolvers**: Multiple identification methods - IP-based with proxy support, User-based with authentication fallback, Header-based for API keys, Composite for combined limiting
- **Rate Limiting Test Coverage**: Comprehensive test suite with 94+ new tests covering all strategies, resolvers, and edge cases
- **CLI Working Directory Fix**: Implemented automatic working directory change to project root for all commands when executed from subdirectories, ensuring path-dependent commands like `treehouse serve` work correctly from any location within a TreeHouse project
- **CLI Application Enhancement**: Implemented intelligent context-aware CLI with single `treehouse` command that adapts based on project detection
- **Directory Traversal Support**: Enhanced project detection to work from any subdirectory within a TreeHouse project using recursive directory traversal
- **Command Grouping**: Added grouped command listing functionality where typing partial command names shows all related commands (user:*, cron:*, cache:*)
- **Project Context Detection**: Fixed package name detection from `lengthofrope/treehouse-framework` to correct `lengthofrope/treehouse`
- **Comprehensive Cron System**: Complete cron scheduling system with job registry, execution engine, locking mechanism, and CLI commands
- **Cron Job Management**: Built-in jobs for cache cleanup and lock cleanup with configurable schedules and priorities
- **Cron Expression Parser**: Full cron expression parsing with validation and human-readable descriptions
- **Cron Locking System**: File-based locking to prevent concurrent job execution with automatic cleanup
- **Comprehensive Test Suite Creation**: Created complete test coverage for all new template functionality with 3 specialized test suites
- **Template Engine Testing**: Extended TreeHouseCompilerTest.php with 18 new tests covering fragments, switch/case, form helpers, and complex scenarios
- **HTML Entity Preservation Testing**: Created HtmlEntityPreservationTest.php with 11 tests for emoji support and entity handling
- **Directive Processor Testing**: Created DirectiveProcessorsTest.php with 15 tests for individual processor classes
- **Missing Template Functions Implementation**: Complete implementation of all missing template functions from the object support plan
- **Advanced Template Features**: Added `th:switch`, `th:case`, `th:default`, `th:with`, `th:fragment`, `th:include`, `th:replace`, `th:field`, `th:errors`, `th:csrf`, `th:method`
- **Expression Validation Enhancement**: Fixed overly restrictive validation to properly support variables with double underscores (`__treehouse_config`, `__vite_assets`)
- **Template Caching Resolution**: Resolved homepage caching issue where old cached templates prevented new validation logic from taking effect
- **Template Compilation Fixes**: Complete resolution of template compilation issues in both debug and production modes
- **Error Template System**: Fixed raw `th:` directives appearing instead of compiled HTML in error pages
- **Expression Handling**: Enhanced brace expression processing and validation for complex template expressions
- **Test Warning Elimination**: Resolved all PHP array-to-string conversion warnings in test suite
- **Debug Information Display**: Proper stack trace and exception details in debug mode error pages
- **Error Handling Integration**: Complete framework-wide error handling integration with proper exception types across all layers
- **Error Views System**: Template-based error pages with shared layouts and debug/production modes
- **Framework Exception Hierarchy**: Router, View, Database, and Auth layers now use TreeHouse exception types
- **Test Suite Alignment**: All 1646 tests passing with zero warnings and updated expectations for new exception types
- **Error Handling Layer**: Complete PSR-3 compliant error handling system with hierarchical exceptions, structured logging, and multi-format rendering
- **ActiveRecord ORM**: Fully implemented with relationships, query builder, and model events
- **Authentication System**: Complete RBAC with roles, permissions, and policy-based authorization
- **Template Engine**: Custom templating with HTML-valid syntax, proper expression compilation, complete advanced features, and auth integration
- **CLI Framework**: Comprehensive console application with database migrations and user management
- **Dependency Injection**: Service container with automatic dependency resolution

## Architecture State
- **Foundation Layer**: ✅ Complete - Application container, DI, configuration management
- **Database Layer**: ✅ Complete - ActiveRecord, QueryBuilder, migrations, relationships
- **Router Layer**: ✅ Complete - URL routing, middleware (including enterprise rate limiting), request/response handling
- **Auth Layer**: ✅ Complete - RBAC, guards, permissions, policies
- **View Layer**: ✅ Complete - Template engine with robust compilation, all advanced features, expression handling, and caching
- **Console Layer**: ✅ Complete - CLI framework with intelligent context detection, command grouping, and directory traversal
- **Cron Layer**: ✅ Complete - Comprehensive scheduling system with job registry, execution engine, locking, and CLI commands
- **Events Layer**: ✅ Complete - Synchronous event dispatching, model lifecycle events, listener registration, framework integration
- **Security Layer**: ✅ Complete - CSRF, encryption, hashing, sanitization, enterprise rate limiting
- **Validation Layer**: ✅ Complete - 25+ rules with custom rule support
- **Cache Layer**: ✅ Complete - File-based caching with pattern matching
- **Error Layer**: ✅ Complete - PSR-3 logging, hierarchical exceptions, context collection, multi-format rendering, framework-wide integration

## Current Technical State
- **PHP Version**: 8.4+ (utilizing modern PHP features)
- **Zero Dependencies**: Only requires PHP extensions (PDO, JSON, mbstring, OpenSSL, fileinfo, filter)
- **PSR-4 Autoloading**: Organized namespace structure under `LengthOfRope\TreeHouse`
- **Testing**: PHPUnit 11.0+ with comprehensive test coverage (1800+ tests, 0 warnings)
- **Code Quality**: Strict typing, modern PHP patterns, comprehensive documentation

## Next Steps
1. **Production Readiness**: Final testing and bug fixes for stable release
2. **Performance Benchmarking**: Compare against Laravel, Symfony for performance metrics
3. **Documentation**: Complete user guides, API reference, and tutorials
4. **Community**: Prepare for open-source release with contribution guidelines
5. **Advanced Features**: Consider additional framework features based on community feedback

## Known Issues
- Some edge cases in relationship loading may need optimization
- Cache invalidation patterns could be enhanced
- CLI commands may need additional validation

## Recent Technical Fixes (January 2025)
- **Missing Template Functions**: Implemented all missing template functions from the template object support plan
- **New Processor Classes**: Created 9 new directive processors (SwitchProcessor, WithProcessor, FragmentProcessor, IncludeProcessor, ReplaceProcessor, CsrfProcessor, FieldProcessor, ErrorsProcessor, MethodProcessor)
- **Expression Validation Fix**: Resolved overly restrictive validation that was blocking variables with double underscores and other valid patterns
- **Template Caching Issue**: Fixed homepage caching problem where cached templates prevented new compilation logic from taking effect
- **Template Compilation**: Fixed silent failures in production mode where templates fell back to raw `th:` directives
- **Expression Processing**: Enhanced `RepeatProcessor` to use proper `compileIteration()` method for expressions like `"suggestion in {suggestions}"`
- **Brace Handling**: Added consistent brace stripping across all template directive processors
- **Foreach Syntax**: Corrected malformed PHP generation from `foreach ($items) as $item` to `foreach ($items as $item)`
- **Array Conversion Warnings**: Eliminated PHP warnings by providing individual scalar debug fields instead of array variables in templates
- **Debug Information**: Restored full stack trace and exception details display in debug mode error pages
- **Backward Compatibility**: Maintained support for legacy repeat syntax while adding new formats

## Active File Locations
- **Core Framework**: `src/TreeHouse/` - All framework components
- **Events System**: `src/TreeHouse/Events/` - Complete event system with dispatching, model events, and listeners
- **Events Configuration**: `config/events.php` - Comprehensive event system configuration
- **Events Tests**: `tests/Unit/Events/` - Comprehensive test suite for event system functionality
- **Template Engine**: `src/TreeHouse/View/Compilers/` - Template compilation system with 9 directive processors
- **Template Tests**: `tests/Unit/View/Compilers/` - Comprehensive test suite (64 tests, 172 assertions)
- **Error Layer**: `src/TreeHouse/Errors/` - Complete error handling system with PSR-3 logging
- **Error Tests**: `tests/Unit/Errors/` - Comprehensive test suite (108 tests, 255 assertions)
- **Cron System**: `src/TreeHouse/Cron/` - Complete scheduling system with job registry, execution engine, locking
- **Cron Commands**: `src/TreeHouse/Console/Commands/CronCommands/` - CLI commands for cron management
- **Cron Configuration**: `config/cron.php` - Comprehensive cron system configuration
- **Sample Application**: `src/App/` - Example controllers and models
- **Configuration**: `config/` - Application configuration files
- **Tests**: `tests/Unit/` - Comprehensive test coverage (1800+ tests total)
- **Rate Limiting System**: `src/TreeHouse/Router/Middleware/RateLimit/` - Enterprise rate limiting with strategies and key resolvers
- **Rate Limiting Tests**: `tests/Unit/Router/Middleware/RateLimit/` - Comprehensive test suite for rate limiting functionality
- **CLI Entry Point**: `bin/treehouse` - Command-line interface with intelligent context detection
- **Web Entry Point**: `public/index.php` - HTTP request handler

## Error Layer Implementation Status
- **Exceptions**: ✅ Complete - BaseException hierarchy with 7 concrete exception types
- **Classification**: ✅ Complete - Pattern-based exception categorization with severity levels
- **Context Collection**: ✅ Complete - Request, User, Environment collectors with sanitization
- **Logging**: ✅ Complete - PSR-3 compliant logger with multiple output formats
- **Rendering**: ✅ Complete - JSON, HTML (templated), CLI error rendering
- **Documentation**: ✅ Complete - Comprehensive README.md with usage examples
- **Testing**: ✅ Complete - All 1516 tests passing with zero warnings and proper exception expectations
- **Framework Integration**: ✅ Complete - All layers (Router, View, Database, Auth) use TreeHouse exceptions
- **Error Views**: ✅ Complete - Template-based error pages with shared layouts (404, 500, debug) and proper compilation
- **Template Compilation**: ✅ Complete - Robust expression handling, debug information display, and production mode stability
- **Advanced Template Features**: ✅ Complete - All missing template functions implemented (switch/case, fragments, form handling, local variables)
- **Expression Validation**: ✅ Complete - Supports all variable patterns including double underscores, dot notation, boolean logic, and framework helpers
- **Template Caching**: ✅ Complete - Proper cache invalidation and compilation with new validation logic
- **Template Testing**: ✅ Complete - Comprehensive test suites covering all new functionality (64 tests, 172 assertions)

## Cron Layer Implementation Status
- **Job Registry**: ✅ Complete - Job registration, validation, and discovery with metadata management
- **Job Executor**: ✅ Complete - Individual job execution with timeout handling, locking, and result tracking
- **Scheduler**: ✅ Complete - Main orchestrator with job discovery, scheduling, execution, and comprehensive locking
- **Expression Parser**: ✅ Complete - Full cron expression parsing with validation and human-readable descriptions
- **Locking System**: ✅ Complete - File-based locking to prevent concurrent executions with automatic cleanup
- **Built-in Jobs**: ✅ Complete - Cache cleanup and lock cleanup jobs with configurable schedules
- **CLI Commands**: ✅ Complete - `cron:run` and `cron:list` commands with comprehensive options
- **Configuration**: ✅ Complete - Comprehensive configuration system with scheduler, execution, and monitoring settings
- **Exception Handling**: ✅ Complete - Specialized cron exception hierarchy with proper error context
- **Results Tracking**: ✅ Complete - Detailed job execution results with timing, memory usage, and status tracking

## Template Engine Test Coverage Status
- **TreeHouseCompilerTest.php**: ✅ Complete - 38 tests covering all directives, complex scenarios, and edge cases
- **HtmlEntityPreservationTest.php**: ✅ Complete - 11 tests for HTML entity handling and emoji support
- **DirectiveProcessorsTest.php**: ✅ Complete - 15 tests for individual processor classes and instantiation
- **Test Results**: All 64 template tests passing with 172 assertions, integrated with full framework test suite

## Rate Limiting System Implementation Status
- **Core Middleware**: ✅ Complete - RateLimitMiddleware with configurable strategies and key resolvers
- **Rate Limiting Manager**: ✅ Complete - Orchestrates strategies and resolvers with extensible architecture
- **Configuration System**: ✅ Complete - RateLimitConfig with validation and middleware parameter parsing
- **Response Headers**: ✅ Complete - RateLimitHeaders for X-RateLimit-* standard headers
- **Result Tracking**: ✅ Complete - RateLimitResult for usage statistics and status tracking
- **Fixed Window Strategy**: ✅ Complete - Simple time-based windows with memory efficiency
- **Sliding Window Strategy**: ✅ Complete - Precise rate limiting without boundary bursts
- **Token Bucket Strategy**: ✅ Complete - Burst-friendly limiting with configurable refill rates
- **IP Key Resolver**: ✅ Complete - IP-based identification with proxy header support and IPv6 normalization
- **User Key Resolver**: ✅ Complete - User-based identification with IP fallback for unauthenticated requests
- **Header Key Resolver**: ✅ Complete - API key/header-based identification with privacy-friendly hashing
- **Composite Key Resolver**: ✅ Complete - Combined identification strategies (IP + User)
- **Comprehensive Testing**: ✅ Complete - 94+ tests covering all strategies, resolvers, edge cases, and integrations
- **Documentation**: ✅ Complete - Comprehensive README.md with usage examples, best practices, and architecture details

## Events Layer Implementation Status
- **Event Dispatching**: ✅ Complete - SyncEventDispatcher with priority support, propagation control, and container integration
- **Event Base Classes**: ✅ Complete - Event base class with metadata, context management, and serialization
- **Model Events**: ✅ Complete - ModelEvent base class and 8 lifecycle events (creating, created, updating, updated, deleting, deleted, saving, saved)
- **Event Listeners**: ✅ Complete - EventListener interface and AbstractEventListener base class with auto-detection
- **HasEvents Trait**: ✅ Complete - ActiveRecord integration with automatic event firing and listener registration
- **Event Configuration**: ✅ Complete - Comprehensive configuration system with auto-discovery, debugging, and aliases
- **Helper Functions**: ✅ Complete - Global helper functions (event(), listen(), until()) for easy event usage
- **Framework Integration**: ✅ Complete - Container registration, ActiveRecord integration, and service provider setup
- **Exception Handling**: ✅ Complete - EventException hierarchy with proper error context
- **Testing**: ✅ Complete - Comprehensive test suite with integration tests, model event tests, and dispatcher tests
- **Documentation**: ✅ Complete - Comprehensive README.md with usage examples, best practices, and architecture details