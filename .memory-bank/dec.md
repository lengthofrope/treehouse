# Design Decisions (DEC)

DEC:RBAC_Architecture
- Database-driven roles and permissions instead of config-based
- Many-to-many relationships for flexible role-permission assignments
- User-role assignments support multiple roles per user
- Hierarchical permission inheritance through role membership

DEC:Testing_Strategy
- In-memory SQLite for fast, isolated database tests
- DatabaseTestCase base class for consistent test setup
- Mock application container for dependency injection in tests
- Automatic cleanup between tests for proper isolation

DEC:Console_Commands
- Interactive prompts with fallback to command arguments
- Graceful error handling with appropriate exit codes
- Cross-database compatibility using PHP date functions
- Slug generation for URL-friendly role identifiers

DEC:Database_Schema
- role_permissions table uses composite primary key (role_id, permission_id)
- No timestamps in pivot tables to keep schema simple
- Unique constraints on role/permission names and slugs
- Foreign key cascades for automatic cleanup on deletion

DEC:Code_Organization
- Separate models for each RBAC entity (Role, Permission, User)
- Helper functions in global namespace for easy access
- Middleware classes for route-level authorization
- Console commands grouped by functionality

DEC:Backward_Compatibility
- Legacy 'role' column maintained in users table
- Existing authentication system preserved
- New RBAC system as optional enhancement
- Migration path from simple to complex role systems