# Implementation Patterns (IMPL)

IMPL:DatabaseTestCase
- SETUP: In-memory SQLite with mock app container for db() helper
- TABLES: users, roles, permissions, role_permissions, user_roles
- ISOLATION: tearDown() cleans all tables between tests
- CONNECTION: $GLOBALS['app']->make('db') pattern for global access

IMPL:RoleCommand
- ACTIONS: list, create, delete, show, assign, revoke
- SLUG_GEN: strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $name)))
- DB_COMPAT: Uses date('Y-m-d H:i:s') instead of NOW() for SQLite
- INTERACTIVE: ask() and confirm() methods for user input

IMPL:RBAC_Models
- RELATIONSHIPS: Many-to-many through pivot tables
- PERMISSIONS: Role->permissions via role_permissions table
- USER_ROLES: User->roles via user_roles table
- INHERITANCE: User permissions inherited from assigned roles
- LEGACY_REMOVED: Old role column removed from users table, migration 008 handles cleanup

IMPL:Testing_Patterns
- MOCK_INPUT: createMockInput() with argument/option callbacks
- MOCK_OUTPUT: createMockOutput() with write/writeln methods
- MOCK_COMMANDS: getMockBuilder()->onlyMethods(['ask', 'confirm'])
- UNIQUE_DATA: Each test uses unique names to avoid conflicts

IMPL:Database_Helpers
- GLOBAL_ACCESS: db() function with app container fallback
- CONFIG_LOADING: config/database.php or environment variables
- CONNECTION_SHARING: Single connection instance across application
- MIGRATION_COMPAT: Schema matches actual migration structure

IMPL:Auth_Helpers
- GLOBAL_ACCESS: auth() function with app container and global fallback
- LIBRARY_SUPPORT: Creates minimal auth manager when used as library dependency
- FALLBACK_CREATION: Static instance with default config when no app bootstrap
- DEPENDENCY_INJECTION: Uses session, cookie, and hash services for auth manager

IMPL:Console_Testing
- COMMAND_EXECUTION: execute(InputInterface, OutputInterface):int
- RETURN_CODES: 0 for success, 1 for error, match expectations
- ARGUMENT_PARSING: Mock input with willReturnMap for arguments
- OUTPUT_CAPTURE: Mock output interfaces for assertion testing
IMPL:UserCommands_Updated
- CREATE: Uses new RBAC system, assigns roles via user_roles table
- ROLE_MGMT: UserRoleCommand handles assign/list/bulk/stats operations
- LIST: Shows roles from user_roles join, not legacy column
- UPDATE: Role updates removed, redirects to user:role command
- DELETE: Cleans up user_roles assignments before user deletion
- AVAILABLE_ROLES: admin, editor, author, member (updated from viewer)
- TESTS_FIXED: Updated default role test (viewer→member), removed role option test, removed legacy compatibility test
- NAMESPACE_MOVED: Models moved from App\Models to LengthOfRope\TreeHouse\Models, all references updated