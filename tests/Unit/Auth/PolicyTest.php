<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Auth\Policy;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Auth\AuthorizableUser;

/**
 * Policy Base Class Tests
 *
 * Tests for the base policy class and its helper methods.
 */
class PolicyTest extends TestCase
{
    protected TestPolicy $policy;
    protected array $authConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authConfig = [
            'roles' => [
                'admin' => ['*'],
                'editor' => ['edit-posts', 'delete-posts', 'view-posts'],
                'viewer' => ['view-posts'],
            ],
            'permissions' => [
                'manage-users' => ['admin'],
                'edit-posts' => ['admin', 'editor'],
                'delete-posts' => ['admin', 'editor'],
                'view-posts' => ['admin', 'editor', 'viewer'],
            ],
            'default_role' => 'viewer',
        ];

        $this->policy = new TestPolicy();
    }

    public function testBeforeMethodAllowsAdminUsers(): void
    {
        $adminUser = new PolicyTestUser(['id' => 1, 'role' => 'admin']);
        $adminUser->setAuthConfig($this->authConfig);

        $result = $this->policy->before($adminUser, 'any-ability');

        $this->assertTrue($result);
    }

    public function testBeforeMethodReturnsNullForNonAdminUsers(): void
    {
        $editorUser = new PolicyTestUser(['id' => 2, 'role' => 'editor']);
        $editorUser->setAuthConfig($this->authConfig);

        $result = $this->policy->before($editorUser, 'edit-posts');

        $this->assertNull($result);
    }

    public function testMagicCallMethodWithValidUser(): void
    {
        $editorUser = new PolicyTestUser(['id' => 2, 'role' => 'editor']);
        $editorUser->setAuthConfig($this->authConfig);

        // __call should delegate to user's can() method
        $result = $this->policy->callMagicMethod('edit-posts', [$editorUser]);

        $this->assertTrue($result);
    }

    public function testMagicCallMethodWithInvalidUser(): void
    {
        // __call should return false for non-Authorizable user
        $result = $this->policy->callMagicMethod('edit-posts', ['not-a-user']);

        $this->assertFalse($result);
    }

    public function testMagicCallMethodWithNoArguments(): void
    {
        // __call should return false when no user is provided
        $result = $this->policy->callMagicMethod('edit-posts', []);

        $this->assertFalse($result);
    }

    public function testDenyMethod(): void
    {
        $this->assertFalse($this->policy->testDeny());
        $this->assertFalse($this->policy->testDeny('Custom message'));
    }

    public function testAllowMethod(): void
    {
        $this->assertTrue($this->policy->testAllow());
    }

    public function testIsOwnerWithMatchingUserId(): void
    {
        $user = new PolicyTestUser(['id' => 123]);
        $resource = new TestResource(['user_id' => 123, 'title' => 'Test']);

        $result = $this->policy->testIsOwner($user, $resource);

        $this->assertTrue($result);
    }

    public function testIsOwnerWithNonMatchingUserId(): void
    {
        $user = new PolicyTestUser(['id' => 123]);
        $resource = new TestResource(['user_id' => 456, 'title' => 'Test']);

        $result = $this->policy->testIsOwner($user, $resource);

        $this->assertFalse($result);
    }

    public function testIsOwnerWithCustomOwnerField(): void
    {
        $user = new PolicyTestUser(['id' => 123]);
        $resource = new TestResource(['author_id' => 123, 'title' => 'Test']);

        $result = $this->policy->testIsOwner($user, $resource, 'author_id');

        $this->assertTrue($result);
    }

    public function testIsOwnerWithGetOwnerIdMethod(): void
    {
        $user = new PolicyTestUser(['id' => 123]);
        $resource = new TestResourceWithMethod();
        $resource->setOwnerId(123);

        $result = $this->policy->testIsOwner($user, $resource);

        $this->assertTrue($result);
    }

    public function testIsOwnerWithAuthorIdFallback(): void
    {
        $user = new PolicyTestUser(['id' => 123]);
        $resource = new TestResource(['author_id' => 123, 'title' => 'Test']);

        // Should fallback to author_id when user_id is not present
        $result = $this->policy->testIsOwner($user, $resource);

        $this->assertTrue($result);
    }

    public function testIsOwnerWithNonObjectResource(): void
    {
        $user = new PolicyTestUser(['id' => 123]);

        $result = $this->policy->testIsOwner($user, ['user_id' => 123]);

        $this->assertFalse($result);
    }

    public function testIsOwnerWithNoOwnerField(): void
    {
        $user = new PolicyTestUser(['id' => 123]);
        $resource = new TestResource(['title' => 'Test']);

        $result = $this->policy->testIsOwner($user, $resource);

        $this->assertFalse($result);
    }

    public function testHasAnyRoleHelper(): void
    {
        $editorUser = new PolicyTestUser(['id' => 1, 'role' => 'editor']);
        $editorUser->setAuthConfig($this->authConfig);

        $this->assertTrue($this->policy->testHasAnyRole($editorUser, ['admin', 'editor']));
        $this->assertFalse($this->policy->testHasAnyRole($editorUser, ['admin', 'viewer']));
    }

    public function testHasRoleHelper(): void
    {
        $editorUser = new PolicyTestUser(['id' => 1, 'role' => 'editor']);
        $editorUser->setAuthConfig($this->authConfig);

        $this->assertTrue($this->policy->testHasRole($editorUser, 'editor'));
        $this->assertFalse($this->policy->testHasRole($editorUser, 'admin'));
    }

    public function testCanHelper(): void
    {
        $editorUser = new PolicyTestUser(['id' => 1, 'role' => 'editor']);
        $editorUser->setAuthConfig($this->authConfig);

        $this->assertTrue($this->policy->testCan($editorUser, 'edit-posts'));
        $this->assertFalse($this->policy->testCan($editorUser, 'manage-users'));
    }

    public function testConcreteMethodOverridesMagicCall(): void
    {
        $user = new PolicyTestUser(['id' => 1, 'role' => 'editor']);
        $user->setAuthConfig($this->authConfig);
        $resource = new TestResource(['user_id' => 1]);

        // Test that concrete method is called instead of __call
        $result = $this->policy->view($user, $resource);

        $this->assertTrue($result); // Should return true because of concrete implementation
    }

    public function testConcreteMethodWithNonOwner(): void
    {
        $user = new PolicyTestUser(['id' => 1, 'role' => 'viewer']);
        $user->setAuthConfig($this->authConfig);
        $resource = new TestResource(['user_id' => 999]); // Different owner

        $result = $this->policy->view($user, $resource);

        $this->assertTrue($result); // Should still return true because user can 'view-posts'
    }

    public function testConcreteMethodWithNoPermissionAndNotOwner(): void
    {
        $user = new PolicyTestUser(['id' => 1, 'role' => 'viewer']);
        $user->setAuthConfig($this->authConfig);
        $resource = new TestResource(['user_id' => 999]);

        $result = $this->policy->edit($user, $resource);

        $this->assertFalse($result); // Viewer can't edit posts and doesn't own it
    }
}

/**
 * Test Policy implementation for testing
 */
class TestPolicy extends Policy
{
    public function view(Authorizable $user, mixed $resource): bool
    {
        return $user->can('view-posts') || $this->isOwner($user, $resource);
    }

    public function edit(Authorizable $user, mixed $resource): bool
    {
        return $user->can('edit-posts') || $this->isOwner($user, $resource);
    }

    // Expose protected methods for testing
    public function testDeny(?string $message = null): bool
    {
        return $this->deny($message);
    }

    public function testAllow(): bool
    {
        return $this->allow();
    }

    public function testIsOwner(Authorizable $user, mixed $resource, string $ownerField = 'user_id'): bool
    {
        return $this->isOwner($user, $resource, $ownerField);
    }

    public function testHasAnyRole(Authorizable $user, array $roles): bool
    {
        return $this->hasAnyRole($user, $roles);
    }

    public function testHasRole(Authorizable $user, string $role): bool
    {
        return $this->hasRole($user, $role);
    }

    public function testCan(Authorizable $user, string $permission): bool
    {
        return $this->can($user, $permission);
    }

    // Expose __call for testing
    public function callMagicMethod(string $method, array $arguments): bool
    {
        return $this->__call($method, $arguments);
    }
}

/**
 * Test User class for policy testing
 */
class PolicyTestUser implements Authorizable
{
    use AuthorizableUser;

    protected array $attributes;
    protected array $authConfig = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->attributes['id'] ?? null;
    }

    public function getRole(): string|array
    {
        return $this->attributes['role'] ?? 'viewer';
    }

    protected function setRole(string|array $role): void
    {
        $this->attributes['role'] = $role;
    }

    protected function getAuthConfig(): array
    {
        return $this->authConfig;
    }

    public function setAuthConfig(array $config): void
    {
        $this->authConfig = $config;
    }

    public function save(): bool
    {
        return true;
    }
}

/**
 * Test Resource class
 */
class TestResource
{
    public function __construct(public array $attributes = [])
    {
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
}

/**
 * Test Resource with getOwnerId method
 */
class TestResourceWithMethod
{
    private mixed $ownerId = null;

    public function setOwnerId(mixed $id): void
    {
        $this->ownerId = $id;
    }

    public function getOwnerId(): mixed
    {
        return $this->ownerId;
    }
}