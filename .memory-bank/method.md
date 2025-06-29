# Method Signatures (MTD)

MTD:DatabaseTestCase.setUp():void|DESC:Initialize in-memory SQLite database with RBAC tables
MTD:DatabaseTestCase.tearDown():void|DESC:Clean up database tables between tests for isolation
MTD:DatabaseTestCase.createTestTables():void|DESC:Create RBAC table structure matching migrations
MTD:DatabaseTestCase.insertTestData():void|DESC:Insert sample roles, permissions, and users for testing

MTD:RoleCommand.execute(InputInterface,OutputInterface):int|DESC:Main command execution with action routing
MTD:RoleCommand.createRole(InputInterface,OutputInterface):int|DESC:Create new role with slug generation
MTD:RoleCommand.listRoles(InputInterface,OutputInterface):int|DESC:Display all roles with permission counts
MTD:RoleCommand.deleteRole(InputInterface,OutputInterface):int|DESC:Delete role and cleanup assignments
MTD:RoleCommand.showRole(InputInterface,OutputInterface):int|DESC:Show detailed role information with permissions
MTD:RoleCommand.assignPermissions(InputInterface,OutputInterface):int|DESC:Assign permissions to role
MTD:RoleCommand.revokePermissions(InputInterface,OutputInterface):int|DESC:Remove permissions from role

MTD:Role.hasPermission(string):bool|DESC:Check if role has specific permission
MTD:Role.permissions():array|DESC:Get all permissions assigned to role
MTD:Role.users():array|DESC:Get all users assigned to this role

MTD:User.hasRole(string):bool|DESC:Check if user has specific role
MTD:User.can(string):bool|DESC:Check if user has specific permission through roles
MTD:User.roles():array|DESC:Get all roles assigned to user
MTD:User.permissions():array|DESC:Get all permissions through role assignments

MTD:db():Connection|DESC:Get database connection from application container or create new
MTD:createDatabaseConnection():Connection|DESC:Create database connection from config or environment

MTD:hasRole(string):bool|DESC:Check if current user has specific role
MTD:hasPermission(string):bool|DESC:Check if current user has specific permission
MTD:getCurrentUser():?Authorizable|DESC:Get currently authenticated user instance
MTD:auth():?AuthManager|DESC:Get authentication manager instance with fallback creation for library usage