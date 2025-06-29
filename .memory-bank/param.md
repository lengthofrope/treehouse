# Parameters and Configuration (PRM)

PRM:database_test_config:array=['driver'=>'sqlite','database'=>':memory:']|DESC:In-memory SQLite configuration for testing
PRM:rbac_table_names:array=['users','roles','permissions','role_permissions','user_roles']|DESC:RBAC system table names
PRM:default_roles:array=['administrator','editor','author','member']|DESC:Default role slugs in system
PRM:role_command_actions:array=['list','create','delete','show','assign','revoke']|DESC:Available RoleCommand actions

PRM:sqlite_date_format:string='Y-m-d H:i:s'|DESC:PHP date format for SQLite timestamp compatibility
PRM:slug_pattern:string='/[^A-Za-z0-9 ]/'|DESC:Regex pattern for slug generation cleanup
PRM:console_success_code:int=0|DESC:Console command success return code
PRM:console_error_code:int=1|DESC:Console command error return code

PRM:permission_categories:array=['User Management','Content Management','System Administration','File Management','Reporting']|DESC:Default permission categories
PRM:test_isolation_tables:array=['user_roles','role_permissions','permissions','roles','users']|DESC:Tables to clean in test tearDown order

PRM:db_helper_globals:array=['app','db_connection']|DESC:Global variables used by db() helper function
PRM:mock_app_service:string='db'|DESC:Service name for database connection in mock application container

PRM:role_permissions_schema:array=['role_id','permission_id']|DESC:role_permissions table column structure
PRM:user_roles_schema:array=['user_id','role_id','created_at','updated_at']|DESC:user_roles table column structure
PRM:roles_schema:array=['id','name','slug','description','created_at','updated_at']|DESC:roles table column structure
PRM:permissions_schema:array=['id','name','slug','description','category','created_at','updated_at']|DESC:permissions table column structure