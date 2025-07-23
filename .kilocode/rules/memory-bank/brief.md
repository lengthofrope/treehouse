# TreeHouse Framework - Project Brief

## Overview
TreeHouse is a modern, lightweight PHP web framework built entirely from scratch with zero external dependencies. It provides a comprehensive full-stack web development solution using pure PHP 8.4+ with a clean multilayer architectural design.

## Main Objectives
- **Zero Dependency Framework**: Create a production-ready PHP framework without relying on external libraries
- **Comprehensive Web Solution**: Provide all essential web development features (routing, ORM, templating, auth, validation) in one package
- **Clean Architecture**: Maintain a layered, modular design that promotes code maintainability and separation of concerns
- **Developer Experience**: Offer rich CLI tools, testing support, and intuitive APIs for rapid development

## Key Features
- **Multi-Layer Architecture**: Foundation, Database, Router, Auth, Console, Cache, Http, Security, Support, Validation, View, Errors
- **ActiveRecord ORM**: Eloquent-style models with relationships and query building
- **Role-Based Access Control**: Comprehensive RBAC system with permissions and policies
- **JWT Authentication**: Enterprise-grade stateless authentication with RFC 7519 compliance, multi-algorithm support, and production-ready configuration
- **Template Engine**: HTML-valid templating with auth integration and error views
- **CLI Framework**: Comprehensive console application with user management and development tools
- **Error Handling**: PSR-3 compliant logging, hierarchical exceptions, multi-format rendering
- **Security**: CSRF protection, AES-256-CBC encryption, password hashing, input sanitization, enterprise rate limiting
- **Rate Limiting**: Enterprise-grade middleware with multiple strategies (Fixed Window, Sliding Window, Token Bucket) and key resolvers
- **Validation**: 25+ built-in validation rules with custom rule support
- **Caching**: File-based caching with pattern matching and performance optimization
- **Events System**: Synchronous event dispatching with model lifecycle events and listener registration
- **Mail System**: Comprehensive email system with multiple drivers and queue support

## Technologies
- **Core**: Pure PHP 8.4+ (zero external dependencies)
- **Required Extensions**: PDO, JSON, mbstring, OpenSSL, fileinfo, filter
- **Testing**: PHPUnit 11.0+ for unit and integration testing
- **Architecture**: PSR-4 autoloading, MVC pattern, dependency injection
- **Database**: PDO-based with SQLite/MySQL support, migrations system
- **Frontend**: JavaScript/CSS compilation, asset management

# When writing code
- **Make sure you check existing classes in TreeHouse** to see if you can utilize them. I.E. Instead of writing queries, use the ORM. Instead of using Date Time classes provided in PHP, use Carbon, etc.
- **DO NOT think this project uses Laravel or other libraries** it merely uses some of their concepts but is a project on its own
- **The templating engine IS NOT Thymeleaf, nor was it inspired on it** Thymeleaf concepts do not apply, since the TreeHouse templating engine uses its own concepts.

## Project Status
Work in progress - framework is functional and nearing production-ready status. Active development focused on feature completion and stability. **JWT Authentication System Phase 2 completed** - enterprise-grade stateless authentication now available.

## Significance
TreeHouse addresses the complexity and dependency bloat common in modern PHP frameworks by providing a self-contained, comprehensive solution. It offers developers the power of full-featured frameworks like Laravel while maintaining complete control over dependencies and reducing security surface area through its zero-dependency approach. The addition of enterprise-grade JWT authentication makes it ideal for modern API-first applications and microservices architecture.