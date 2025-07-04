# TreeHouse Framework - Current Context

## Project Status
**Active Development** - Framework is functional but not yet production-ready. Currently in beta phase with feature completion and stability as primary focus.

## Current Work Focus
- **Production Readiness**: Final testing and bug fixes for stable release
- **Performance Benchmarking**: Compare against Laravel, Symfony for performance metrics
- **Documentation**: Complete user guides, API reference, and tutorials
- **Community**: Prepare for open-source release with contribution guidelines

## Recent Major Changes
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
- **Test Suite Alignment**: All 1516 tests passing with zero warnings and updated expectations for new exception types
- **Error Handling Layer**: Complete PSR-3 compliant error handling system with hierarchical exceptions, structured logging, and multi-format rendering
- **ActiveRecord ORM**: Fully implemented with relationships, query builder, and model events
- **Authentication System**: Complete RBAC with roles, permissions, and policy-based authorization
- **Template Engine**: Custom templating with HTML-valid syntax, proper expression compilation, complete advanced features, and auth integration
- **CLI Framework**: Comprehensive console application with database migrations and user management
- **Dependency Injection**: Service container with automatic dependency resolution

## Architecture State
- **Foundation Layer**: ✅ Complete - Application container, DI, configuration management
- **Database Layer**: ✅ Complete - ActiveRecord, QueryBuilder, migrations, relationships
- **Router Layer**: ✅ Complete - URL routing, middleware, request/response handling
- **Auth Layer**: ✅ Complete - RBAC, guards, permissions, policies
- **View Layer**: ✅ Complete - Template engine with robust compilation, all advanced features, expression handling, and caching
- **Console Layer**: ✅ Complete - CLI framework with commands and helpers
- **Security Layer**: ✅ Complete - CSRF, encryption, hashing, sanitization
- **Validation Layer**: ✅ Complete - 25+ rules with custom rule support
- **Cache Layer**: ✅ Complete - File-based caching with pattern matching
- **Error Layer**: ✅ Complete - PSR-3 logging, hierarchical exceptions, context collection, multi-format rendering, framework-wide integration

## Current Technical State
- **PHP Version**: 8.4+ (utilizing modern PHP features)
- **Zero Dependencies**: Only requires PHP extensions (PDO, JSON, mbstring, OpenSSL, fileinfo, filter)
- **PSR-4 Autoloading**: Organized namespace structure under `LengthOfRope\TreeHouse`
- **Testing**: PHPUnit 11.0+ with comprehensive test coverage (1516 tests, 0 warnings)
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
- **Template Engine**: `src/TreeHouse/View/Compilers/` - Template compilation system with 9 directive processors
- **Template Tests**: `tests/Unit/View/Compilers/` - Comprehensive test suite (64 tests, 172 assertions)
- **Error Layer**: `src/TreeHouse/Errors/` - Complete error handling system with PSR-3 logging
- **Error Tests**: `tests/Unit/Errors/` - Comprehensive test suite (108 tests, 255 assertions)
- **Sample Application**: `src/App/` - Example controllers and models
- **Configuration**: `config/` - Application configuration files
- **Tests**: `tests/Unit/` - Comprehensive test coverage (1516 tests total)
- **CLI Entry Point**: `bin/treehouse` - Command-line interface
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

## Template Engine Test Coverage Status
- **TreeHouseCompilerTest.php**: ✅ Complete - 38 tests covering all directives, complex scenarios, and edge cases
- **HtmlEntityPreservationTest.php**: ✅ Complete - 11 tests for HTML entity handling and emoji support
- **DirectiveProcessorsTest.php**: ✅ Complete - 15 tests for individual processor classes and instantiation
- **Test Results**: All 64 template tests passing with 172 assertions, integrated with full framework test suite