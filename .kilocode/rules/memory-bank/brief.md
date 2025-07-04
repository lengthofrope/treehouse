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
- **Template Engine**: HTML-valid templating with auth integration and error views
- **CLI Framework**: Comprehensive console application with user management and development tools
- **Error Handling**: PSR-3 compliant logging, hierarchical exceptions, multi-format rendering
- **Security**: CSRF protection, AES-256-CBC encryption, password hashing, input sanitization
- **Validation**: 25+ built-in validation rules with custom rule support
- **Caching**: File-based caching with pattern matching and performance optimization

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
Work in progress - framework is functional but not yet production-ready. Active development focusing on feature completion and stability.

## Significance
TreeHouse addresses the complexity and dependency bloat common in modern PHP frameworks by providing a self-contained, comprehensive solution. It offers developers the power of full-featured frameworks like Laravel while maintaining complete control over dependencies and reducing security surface area through its zero-dependency approach.