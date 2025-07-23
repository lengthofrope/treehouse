<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use LengthOfRope\TreeHouse\Router\Middleware\RouteProtectionHelper;
use Tests\TestCase;

/**
 * Test cases for the RouteProtectionHelper class
 * 
 * @package Tests\Unit\Router\Middleware
 */
class RouteProtectionHelperTest extends TestCase
{
    public function testCreateReturnsNewInstance(): void
    {
        $helper = RouteProtectionHelper::create();
        $this->assertInstanceOf(RouteProtectionHelper::class, $helper);
        $this->assertTrue($helper->isEmpty());
    }

    public function testAuthWithNoGuards(): void
    {
        $helper = RouteProtectionHelper::create()->auth();
        $this->assertEquals(['auth'], $helper->getMiddleware());
    }

    public function testAuthWithStringGuard(): void
    {
        $helper = RouteProtectionHelper::create()->auth('api');
        $this->assertEquals(['auth:api'], $helper->getMiddleware());
    }

    public function testAuthWithArrayGuards(): void
    {
        $helper = RouteProtectionHelper::create()->auth(['api', 'web']);
        $this->assertEquals(['auth:api,web'], $helper->getMiddleware());
    }

    public function testJwtWithNoGuards(): void
    {
        $helper = RouteProtectionHelper::create()->jwt();
        $this->assertEquals(['jwt'], $helper->getMiddleware());
    }

    public function testJwtWithStringGuard(): void
    {
        $helper = RouteProtectionHelper::create()->jwt('api');
        $this->assertEquals(['jwt:api'], $helper->getMiddleware());
    }

    public function testJwtWithArrayGuards(): void
    {
        $helper = RouteProtectionHelper::create()->jwt(['api', 'mobile']);
        $this->assertEquals(['jwt:api,mobile'], $helper->getMiddleware());
    }

    public function testRolesWithStringRole(): void
    {
        $helper = RouteProtectionHelper::create()->roles('admin');
        $this->assertEquals(['role:admin'], $helper->getMiddleware());
    }

    public function testRolesWithArrayRoles(): void
    {
        $helper = RouteProtectionHelper::create()->roles(['admin', 'editor']);
        $this->assertEquals(['role:admin,editor'], $helper->getMiddleware());
    }

    public function testRolesWithGuards(): void
    {
        $helper = RouteProtectionHelper::create()->roles('admin', 'api');
        $this->assertEquals(['role:admin:auth:api'], $helper->getMiddleware());
    }

    public function testRolesWithArrayGuards(): void
    {
        $helper = RouteProtectionHelper::create()->roles(['admin'], ['api', 'web']);
        $this->assertEquals(['role:admin:auth:api,web'], $helper->getMiddleware());
    }

    public function testPermissionsWithStringPermission(): void
    {
        $helper = RouteProtectionHelper::create()->permissions('edit-posts');
        $this->assertEquals(['permission:edit-posts'], $helper->getMiddleware());
    }

    public function testPermissionsWithArrayPermissions(): void
    {
        $helper = RouteProtectionHelper::create()->permissions(['edit-posts', 'delete-posts']);
        $this->assertEquals(['permission:edit-posts,delete-posts'], $helper->getMiddleware());
    }

    public function testPermissionsWithGuards(): void
    {
        $helper = RouteProtectionHelper::create()->permissions('edit-posts', 'api');
        $this->assertEquals(['permission:edit-posts:auth:api'], $helper->getMiddleware());
    }

    public function testPermissionsWithArrayGuards(): void
    {
        $helper = RouteProtectionHelper::create()->permissions(['edit-posts'], ['api', 'web']);
        $this->assertEquals(['permission:edit-posts:auth:api,web'], $helper->getMiddleware());
    }

    public function testThrottleBasic(): void
    {
        $helper = RouteProtectionHelper::create()->throttle(60);
        $this->assertEquals(['throttle:60,1'], $helper->getMiddleware());
    }

    public function testThrottleWithMinutes(): void
    {
        $helper = RouteProtectionHelper::create()->throttle(100, 5);
        $this->assertEquals(['throttle:100,5'], $helper->getMiddleware());
    }

    public function testThrottleWithStrategy(): void
    {
        $helper = RouteProtectionHelper::create()->throttle(60, 1, 'sliding');
        $this->assertEquals(['throttle:60,1,sliding'], $helper->getMiddleware());
    }

    public function testCustomMiddlewareString(): void
    {
        $helper = RouteProtectionHelper::create()->custom('cors');
        $this->assertEquals(['cors'], $helper->getMiddleware());
    }

    public function testCustomMiddlewareArray(): void
    {
        $helper = RouteProtectionHelper::create()->custom(['cors', 'cache']);
        $this->assertEquals(['cors', 'cache'], $helper->getMiddleware());
    }

    public function testFluentInterface(): void
    {
        $helper = RouteProtectionHelper::create()
            ->auth('api')
            ->roles('admin')
            ->permissions('edit-posts')
            ->throttle(60);

        $expected = ['auth:api', 'role:admin', 'permission:edit-posts', 'throttle:60,1'];
        $this->assertEquals($expected, $helper->getMiddleware());
    }

    public function testWebStaticMethod(): void
    {
        $helper = RouteProtectionHelper::web();
        $this->assertEquals(['auth:web'], $helper->getMiddleware());
    }

    public function testWebWithRoles(): void
    {
        $helper = RouteProtectionHelper::web('admin');
        $this->assertEquals(['auth:web', 'role:admin:auth:web'], $helper->getMiddleware());
    }

    public function testWebWithArrayRoles(): void
    {
        $helper = RouteProtectionHelper::web(['admin', 'editor']);
        $this->assertEquals(['auth:web', 'role:admin,editor:auth:web'], $helper->getMiddleware());
    }

    public function testWebWithPermissions(): void
    {
        $helper = RouteProtectionHelper::web(null, 'edit-posts');
        $this->assertEquals(['auth:web', 'permission:edit-posts:auth:web'], $helper->getMiddleware());
    }

    public function testWebWithRolesAndPermissions(): void
    {
        $helper = RouteProtectionHelper::web('admin', 'edit-posts');
        $expected = ['auth:web', 'role:admin:auth:web', 'permission:edit-posts:auth:web'];
        $this->assertEquals($expected, $helper->getMiddleware());
    }

    public function testApiStaticMethod(): void
    {
        $helper = RouteProtectionHelper::api();
        $this->assertEquals(['jwt:api'], $helper->getMiddleware());
    }

    public function testApiWithRoles(): void
    {
        $helper = RouteProtectionHelper::api('admin');
        $this->assertEquals(['jwt:api', 'role:admin:auth:api'], $helper->getMiddleware());
    }

    public function testApiWithPermissions(): void
    {
        $helper = RouteProtectionHelper::api(null, 'edit-posts');
        $this->assertEquals(['jwt:api', 'permission:edit-posts:auth:api'], $helper->getMiddleware());
    }

    public function testApiWithRateLimit(): void
    {
        $helper = RouteProtectionHelper::api(null, null, 100);
        $this->assertEquals(['jwt:api', 'throttle:100,1'], $helper->getMiddleware());
    }

    public function testApiWithAllOptions(): void
    {
        $helper = RouteProtectionHelper::api('admin', 'edit-posts', 100);
        $expected = ['jwt:api', 'role:admin:auth:api', 'permission:edit-posts:auth:api', 'throttle:100,1'];
        $this->assertEquals($expected, $helper->getMiddleware());
    }

    public function testAdminStaticMethod(): void
    {
        $helper = RouteProtectionHelper::admin();
        $this->assertEquals(['auth:web', 'role:admin:auth:web'], $helper->getMiddleware());
    }

    public function testAdminWithCustomGuards(): void
    {
        $helper = RouteProtectionHelper::admin('api');
        $this->assertEquals(['auth:api', 'role:admin:auth:api'], $helper->getMiddleware());
    }

    public function testAdminWithArrayGuards(): void
    {
        $helper = RouteProtectionHelper::admin(['web', 'api']);
        $this->assertEquals(['auth:web,api', 'role:admin:auth:web,api'], $helper->getMiddleware());
    }

    public function testMultiAuthStaticMethod(): void
    {
        $helper = RouteProtectionHelper::multiAuth(['web', 'api']);
        $this->assertEquals(['auth:web,api'], $helper->getMiddleware());
    }

    public function testMultiAuthWithRoles(): void
    {
        $helper = RouteProtectionHelper::multiAuth(['web', 'api'], 'admin');
        $this->assertEquals(['auth:web,api', 'role:admin:auth:web,api'], $helper->getMiddleware());
    }

    public function testMultiAuthWithPermissions(): void
    {
        $helper = RouteProtectionHelper::multiAuth(['web', 'api'], null, 'edit-posts');
        $this->assertEquals(['auth:web,api', 'permission:edit-posts:auth:web,api'], $helper->getMiddleware());
    }

    public function testGuestStaticMethod(): void
    {
        $helper = RouteProtectionHelper::guest();
        $this->assertEquals(['guest'], $helper->getMiddleware());
    }

    public function testOptionalStaticMethod(): void
    {
        $helper = RouteProtectionHelper::optional();
        $this->assertEquals(['optional:web,api'], $helper->getMiddleware());
    }

    public function testOptionalWithCustomGuards(): void
    {
        $helper = RouteProtectionHelper::optional('api');
        $this->assertEquals(['optional:api'], $helper->getMiddleware());
    }

    public function testOptionalWithArrayGuards(): void
    {
        $helper = RouteProtectionHelper::optional(['jwt', 'session']);
        $this->assertEquals(['optional:jwt,session'], $helper->getMiddleware());
    }

    public function testToArrayAlias(): void
    {
        $helper = RouteProtectionHelper::create()->auth('api');
        $this->assertEquals($helper->getMiddleware(), $helper->toArray());
    }

    public function testDebugMethod(): void
    {
        $helper = RouteProtectionHelper::create()
            ->auth('api')
            ->roles('admin')
            ->throttle(60);

        $debug = $helper->debug();
        $this->assertEquals('auth:api -> role:admin -> throttle:60,1', $debug);
    }

    public function testToStringMagicMethod(): void
    {
        $helper = RouteProtectionHelper::create()
            ->auth('api')
            ->roles('admin');

        $string = (string) $helper;
        $this->assertEquals('auth:api -> role:admin', $string);
    }

    public function testIsEmptyMethod(): void
    {
        $helper = RouteProtectionHelper::create();
        $this->assertTrue($helper->isEmpty());

        $helper->auth();
        $this->assertFalse($helper->isEmpty());
    }

    public function testClearMethod(): void
    {
        $helper = RouteProtectionHelper::create()
            ->auth('api')
            ->roles('admin');

        $this->assertFalse($helper->isEmpty());
        $this->assertEquals(2, $helper->count());

        $result = $helper->clear();
        $this->assertSame($helper, $result); // Test fluent interface
        $this->assertTrue($helper->isEmpty());
        $this->assertEquals(0, $helper->count());
    }

    public function testCountMethod(): void
    {
        $helper = RouteProtectionHelper::create();
        $this->assertEquals(0, $helper->count());

        $helper->auth('api');
        $this->assertEquals(1, $helper->count());

        $helper->roles('admin')->permissions('edit-posts');
        $this->assertEquals(3, $helper->count());
    }

    public function testComplexScenario(): void
    {
        // Test a complex real-world scenario
        $helper = RouteProtectionHelper::create()
            ->jwt(['api', 'mobile'])
            ->roles(['admin', 'editor'], 'api')
            ->permissions(['edit-posts', 'publish-posts'], 'api')
            ->throttle(100, 5, 'sliding')
            ->custom('cors');

        $expected = [
            'jwt:api,mobile',
            'role:admin,editor:auth:api',
            'permission:edit-posts,publish-posts:auth:api',
            'throttle:100,5,sliding',
            'cors'
        ];

        $this->assertEquals($expected, $helper->getMiddleware());
        $this->assertEquals(5, $helper->count());
        $this->assertFalse($helper->isEmpty());

        $debugString = $helper->debug();
        $expectedDebug = 'jwt:api,mobile -> role:admin,editor:auth:api -> permission:edit-posts,publish-posts:auth:api -> throttle:100,5,sliding -> cors';
        $this->assertEquals($expectedDebug, $debugString);
    }

    public function testEmptyDebugString(): void
    {
        $helper = RouteProtectionHelper::create();
        $this->assertEquals('', $helper->debug());
        $this->assertEquals('', (string) $helper);
    }
}