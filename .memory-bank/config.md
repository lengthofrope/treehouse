# Configuration Documentation (CONFIG)

## Authorization Configuration
CONFIG:auth.roles|DESC:Role definitions with associated permissions array
CONFIG:auth.permissions|DESC:Permission to role mapping configuration
CONFIG:auth.default_role|DESC:Default role assigned to new users
CONFIG:auth.role_hierarchy|DESC:Role inheritance configuration for permission elevation

## Authorization Values
VAL:role:admin|DESC:Administrator role with all permissions (wildcard *)
VAL:role:editor|DESC:Editor role with content management permissions
VAL:role:auditor|DESC:Auditor role with read-only access permissions
VAL:role:viewer|DESC:Basic viewer role with minimal permissions
VAL:permission:manage-users|DESC:User management permission (admin only)
VAL:permission:edit-posts|DESC:Post editing permission (admin, editor)
VAL:permission:delete-posts|DESC:Post deletion permission (admin, editor)
VAL:permission:view-posts|DESC:Post viewing permission (all roles)
VAL:permission:view-users|DESC:User viewing permission (admin, auditor)
VAL:permission:view-analytics|DESC:Analytics viewing permission (admin, auditor)

## Middleware Configuration
MIDDLEWARE:role:admin|DESC:Restrict route access to admin role only
MIDDLEWARE:role:admin,editor|DESC:Allow access to admin OR editor roles
MIDDLEWARE:permission:manage-users|DESC:Restrict access to users with manage-users permission
MIDDLEWARE:permission:edit-posts,delete-posts|DESC:Allow access with edit-posts OR delete-posts permission

## Template Directives
DIRECTIVE:th:auth|DESC:Show content only to authenticated users
DIRECTIVE:th:guest|DESC:Show content only to guest (non-authenticated) users
DIRECTIVE:th:role="admin"|DESC:Show content only to users with admin role
DIRECTIVE:th:role="admin,editor"|DESC:Show content to users with admin OR editor roles
DIRECTIVE:th:permission="manage-users"|DESC:Show content to users with manage-users permission
DIRECTIVE:th:permission="edit-posts,delete-posts"|DESC:Show content to users with edit-posts OR delete-posts permission