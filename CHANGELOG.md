# Changelog

All notable changes to the TreeHouse framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial framework separation from dorpsbelang-ha project
- Project scaffolding system via `treehouse new` command
- Foundation Application class with dependency injection
- Service container with automatic resolution
- Enhanced error handling with proper HTTP status codes

### Changed
- CLI command moved from `th` to `treehouse` for framework package
- Updated author information to Bas de Kort <bdekort@proton.me>

## [1.0.0] - 2025-06-27

### Added
- Complete PHP framework with zero external dependencies
- MVC architecture with routing, controllers, and views
- Active Record ORM with relationships
- Template engine with components and layouts
- Console application with development commands
- Caching system with file-based storage
- Validation system with extensible rules
- Security features (CSRF, encryption, sanitization)
- HTTP handling (Request, Response, Session, Cookies)
- Support utilities (Collections, Arrays, Strings, UUID)
- Comprehensive test suite with PHPUnit

### Framework Components
- **Auth**: Multi-guard authentication system
- **Cache**: File-based caching with manager
- **Console**: CLI application with commands
- **Database**: Active Record ORM with query builder
- **Foundation**: Application container and bootstrap
- **Http**: Request/Response handling
- **Router**: HTTP routing with middleware
- **Security**: CSRF, encryption, hashing, sanitization
- **Support**: Helper utilities and collections
- **Validation**: Form and data validation
- **View**: Template engine with inheritance

### CLI Commands
- `cache:clear` - Clear cached data
- `cache:stats` - Display cache statistics
- `cache:warm` - Warm up the cache
- `migrate:run` - Run database migrations
- `serve` - Start development server
- `test:run` - Run PHPUnit tests
- `new` - Create new TreeHouse applications

### Requirements
- PHP ^8.4
- ext-pdo, ext-json, ext-mbstring, ext-openssl, ext-fileinfo, ext-filter

### Author
- Bas de Kort <bdekort@proton.me>

[Unreleased]: https://github.com/lengthofrope/treehouse/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/lengthofrope/treehouse/releases/tag/v1.0.0