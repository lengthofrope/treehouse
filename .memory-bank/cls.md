# Class Definitions (CLS)

CLS:DatabaseTestCase|DESC:Base test class for database-dependent tests with SQLite setup and isolation
CLS:RoleCommand|DESC:Console command for managing roles and permissions in RBAC system
CLS:Role|DESC:ActiveRecord model for role entities with permission relationships (namespace: TreeHouse\Models)
CLS:Permission|DESC:ActiveRecord model for permission entities with role relationships (namespace: TreeHouse\Models)
CLS:User|DESC:ActiveRecord model for user entities with RBAC role and permission methods (namespace: TreeHouse\Models, legacy role field removed)
CLS:Connection|DESC:Database connection wrapper with query execution methods
CLS:ActiveRecord|DESC:Base ORM class with CRUD operations and relationship support
CLS:Command|DESC:Base console command class with input/output handling
CLS:InputInterface|DESC:Interface for console command input handling
CLS:OutputInterface|DESC:Interface for console command output formatting
CLS:Application|DESC:Main application container with service registration
CLS:Router|DESC:HTTP request router with middleware support
CLS:RoleMiddleware|DESC:Middleware for role-based route protection
CLS:PermissionMiddleware|DESC:Middleware for permission-based route protection