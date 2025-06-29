# Cross-References and Dependencies (XREF)

XREF:DatabaseTestCase
- Used in: All RBAC model tests, console command tests
- Depends on: Connection, ActiveRecord, PHPUnit TestCase
- Provides: Database setup, table creation, test isolation

XREF:RoleCommand
- Used in: Console application, role management workflows
- Depends on: Connection, db() helper, Command base class
- Integrates with: Role and Permission models, user input/output

XREF:RBAC_Models
- Used in: Authentication, authorization, middleware, console commands
- Depends on: ActiveRecord, Connection, database tables
- Relationships: User->roles->permissions hierarchy

XREF:db()_Helper
- Used in: All database operations, models, commands, tests
- Depends on: Application container, Connection class, config
- Fallbacks: Container service, direct connection creation

XREF:Testing_Infrastructure
- Used in: All test suites requiring database access
- Depends on: PHPUnit, SQLite, mock objects
- Supports: Model tests, command tests, integration tests

XREF:Console_System
- Used in: CLI operations, administrative tasks, automation
- Depends on: Input/Output interfaces, Command base class
- Integrates with: Database operations, user management

XREF:Authentication_System
- Used in: Web requests, API calls, protected routes
- Depends on: User model, session management, RBAC
- Middleware: RoleMiddleware, PermissionMiddleware

XREF:Migration_System
- Used in: Database schema management, deployment
- Depends on: Connection, Migration base class
- Creates: RBAC tables, default roles, seed permissions