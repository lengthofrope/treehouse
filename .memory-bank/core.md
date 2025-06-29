# TreeHouse Framework Memory Bank - Core Overview

## Project Overview
TreeHouse Framework - A comprehensive PHP framework with MVC architecture, ActiveRecord ORM, console commands, authentication, and role-based access control (RBAC) system.

## Key Architecture Components
- **MVC Pattern**: Model-View-Controller architecture
- **ActiveRecord ORM**: Database abstraction with model relationships
- **Console System**: Command-line interface with argument parsing
- **Authentication**: User authentication with session management
- **RBAC System**: Database-driven role and permission management
- **Router**: HTTP request routing with middleware support
- **Container**: Dependency injection container
- **Testing**: PHPUnit-based testing with database test infrastructure

## Recent Major Additions
- Complete RBAC system with roles, permissions, and user assignments
- Console commands for role and user management
- Comprehensive test suite with database testing infrastructure
- Role and permission middleware for route protection

## Database Architecture
- **Users**: Core user authentication and profile data
- **Roles**: Named permission groups (admin, editor, author, member)
- **Permissions**: Granular access controls organized by category
- **Role_Permissions**: Many-to-many role-permission assignments
- **User_Roles**: Many-to-many user-role assignments

## Testing Infrastructure
- **DatabaseTestCase**: Base class for database-dependent tests
- **In-memory SQLite**: Fast, isolated test database
- **Mock Application Container**: Proper dependency injection for tests
- **Test Isolation**: Automatic cleanup between test methods