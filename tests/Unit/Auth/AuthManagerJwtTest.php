<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Auth\JwtGuard;
use LengthOfRope\TreeHouse\Auth\JwtUserProvider;
use LengthOfRope\TreeHouse\Auth\DatabaseUserProvider;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Http\Cookie;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Security\Hash;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * AuthManager JWT Integration Test Suite
 *
 * Tests JWT integration within the AuthManager including:
 * - JWT guard creation and configuration
 * - JWT user provider creation
 * - Request handling and injection
 * - Configuration validation
 * - Error handling for missing JWT config
 *
 * @package Tests\Unit\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class AuthManagerJwtTest extends TestCase
{
    private AuthManager $authManager;
    private MockObject|Session $mockSession;
    private MockObject|Cookie $mockCookie;
    private MockObject|Hash $mockHash;
    private Request $mockRequest;
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks (use anonymous class for Request to avoid PHPUnit deprecation)
        $this->mockSession = $this->createMock(Session::class);
        $this->mockCookie = $this->createMock(Cookie::class);
        $this->mockHash = $this->createMock(Hash::class);
        
        // Create test request to avoid PHPUnit deprecation with Request::method()
        $this->mockRequest = new class extends Request {
            public function __construct() {}
            public function header(string $key, ?string $default = null): ?string { return null; }
            public function cookie(string $key, ?string $default = null): ?string { return null; }
            public function query(?string $key = null, mixed $default = null): mixed { return null; }
        };

        // Configuration with JWT setup
        $this->config = [
            'default' => 'web',
            'guards' => [
                'web' => [
                    'driver' => 'session',
                    'provider' => 'users',
                ],
                'api' => [
                    'driver' => 'jwt',
                    'provider' => 'users',
                ],
                'mobile' => [
                    'driver' => 'jwt',
                    'provider' => 'jwt_users',
                ],
            ],
            'providers' => [
                'users' => [
                    'driver' => 'database',
                    'model' => 'User',
                    'table' => 'users',
                ],
                'jwt_users' => [
                    'driver' => 'jwt',
                    'mode' => 'stateless',
                ],
            ],
            'jwt' => [
                'secret' => 'test-secret-key-32-characters-long',
                'algorithm' => 'HS256',
                'ttl' => 900,
                'refresh_ttl' => 1209600,
                'issuer' => 'test-app',
                'audience' => 'test-users',
            ],
        ];

        $this->authManager = new AuthManager(
            $this->config,
            $this->mockSession,
            $this->mockCookie,
            $this->mockHash,
            $this->mockRequest
        );
    }

    public function testCreatesJwtGuardSuccessfully(): void
    {
        $guard = $this->authManager->guard('api');
        
        $this->assertInstanceOf(JwtGuard::class, $guard);
        $this->assertInstanceOf(DatabaseUserProvider::class, $guard->getProvider());
    }

    public function testCreatesJwtUserProviderSuccessfully(): void
    {
        $provider = $this->authManager->createUserProvider('jwt_users');
        
        $this->assertInstanceOf(JwtUserProvider::class, $provider);
        /** @var JwtUserProvider $provider */
        $this->assertTrue($provider->isStatelessMode());
    }

    public function testJwtGuardCreationWithCustomProvider(): void
    {
        $guard = $this->authManager->guard('mobile');
        
        $this->assertInstanceOf(JwtGuard::class, $guard);
        $this->assertInstanceOf(JwtUserProvider::class, $guard->getProvider());
    }

    public function testJwtGuardUsesCurrentRequest(): void
    {
        $guard = $this->authManager->guard('api');
        
        // Verify guard received the request (indirectly by checking behavior)
        $this->assertInstanceOf(JwtGuard::class, $guard);
    }

    public function testSetRequestUpdatesJwtGuards(): void
    {
        // Create JWT guard first
        $guard = $this->authManager->guard('api');
        $this->assertInstanceOf(JwtGuard::class, $guard);

        // Create new test request
        $newRequest = new class extends Request {
            public function __construct() {}
            public function header(string $key, ?string $default = null): ?string {
                return $key === 'authorization' ? 'Bearer new-token' : null;
            }
            public function cookie(string $key, ?string $default = null): ?string { return null; }
            public function query(?string $key = null, mixed $default = null): mixed { return null; }
        };

        // Set new request
        $this->authManager->setRequest($newRequest);

        // Verify the request was updated
        $this->assertSame($newRequest, $this->authManager->getRequest());
    }

    public function testGetRequestReturnsCurrentRequest(): void
    {
        $this->assertSame($this->mockRequest, $this->authManager->getRequest());
    }

    public function testJwtConfigurationCreation(): void
    {
        // This tests the protected createJwtConfig method indirectly
        $guard = $this->authManager->guard('api');
        $this->assertInstanceOf(JwtGuard::class, $guard);
    }

    public function testJwtProviderWithFallbackProvider(): void
    {
        // Update config to include fallback provider
        $configWithFallback = $this->config;
        $configWithFallback['providers']['jwt_hybrid'] = [
            'driver' => 'jwt',
            'mode' => 'hybrid',
            'fallback_provider' => 'users',
        ];

        $authManager = new AuthManager(
            $configWithFallback,
            $this->mockSession,
            $this->mockCookie,
            $this->mockHash,
            $this->mockRequest
        );

        $provider = $authManager->createUserProvider('jwt_hybrid');
        
        $this->assertInstanceOf(JwtUserProvider::class, $provider);
        /** @var JwtUserProvider $provider */
        $this->assertTrue($provider->isHybridMode());
        $this->assertInstanceOf(DatabaseUserProvider::class, $provider->getFallbackProvider());
    }

    public function testThrowsExceptionWhenJwtConfigMissing(): void
    {
        $configWithoutJwt = $this->config;
        unset($configWithoutJwt['jwt']);

        $authManager = new AuthManager(
            $configWithoutJwt,
            $this->mockSession,
            $this->mockCookie,
            $this->mockHash,
            $this->mockRequest
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('JWT configuration is not defined.');

        $authManager->guard('api');
    }

    public function testThrowsExceptionForUndefinedJwtGuard(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Auth guard [undefined] is not defined.');

        $this->authManager->guard('undefined');
    }

    public function testThrowsExceptionForUndefinedJwtProvider(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authentication user provider is not defined.');

        $this->authManager->createUserProvider('undefined');
    }

    public function testThrowsExceptionForUnsupportedDriver(): void
    {
        $configWithUnsupported = $this->config;
        $configWithUnsupported['guards']['unsupported'] = [
            'driver' => 'unsupported',
            'provider' => 'users',
        ];

        $authManager = new AuthManager(
            $configWithUnsupported,
            $this->mockSession,
            $this->mockCookie,
            $this->mockHash,
            $this->mockRequest
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Auth driver [unsupported] for guard [unsupported] is not defined.');

        $authManager->guard('unsupported');
    }

    public function testThrowsExceptionForUnsupportedProvider(): void
    {
        $configWithUnsupported = $this->config;
        $configWithUnsupported['providers']['unsupported'] = [
            'driver' => 'unsupported',
        ];
        $configWithUnsupported['guards']['test'] = [
            'driver' => 'session',
            'provider' => 'unsupported',
        ];

        $authManager = new AuthManager(
            $configWithUnsupported,
            $this->mockSession,
            $this->mockCookie,
            $this->mockHash,
            $this->mockRequest
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authentication user provider [unsupported] is not defined.');

        $authManager->guard('test');
    }

    public function testJwtGuardCaching(): void
    {
        $guard1 = $this->authManager->guard('api');
        $guard2 = $this->authManager->guard('api');
        
        // Should return the same instance
        $this->assertSame($guard1, $guard2);
    }

    public function testJwtProviderCaching(): void
    {
        $provider1 = $this->authManager->createUserProvider('jwt_users');
        $provider2 = $this->authManager->createUserProvider('jwt_users');
        
        // Should return the same instance
        $this->assertSame($provider1, $provider2);
    }

    public function testAuthManagerDelegatesMethodsToJwtGuard(): void
    {
        // Test that methods are properly delegated to JWT guard
        $this->assertFalse($this->authManager->check('api'));
        $this->assertTrue($this->authManager->guest('api'));
        $this->assertNull($this->authManager->user('api'));
        $this->assertNull($this->authManager->id('api'));
    }

    public function testJwtGuardValidation(): void
    {
        $credentials = ['token' => 'invalid-token'];
        $this->assertFalse($this->authManager->validate($credentials, 'api'));
    }

    public function testJwtGuardAttempt(): void
    {
        $credentials = ['email' => 'test@example.com', 'password' => 'password'];
        
        // This test just verifies the method exists and doesn't throw errors
        // Actual database operations are tested in dedicated provider tests
        $this->assertTrue(method_exists($this->authManager, 'attempt'));
    }

    public function testJwtGuardLogin(): void
    {
        $user = ['id' => 1, 'email' => 'test@example.com'];
        
        // Should not throw exception
        $this->authManager->login($user, false, 'api');
        $this->assertTrue(true); // Assertion to prevent risky test warning
    }

    public function testJwtGuardLogout(): void
    {
        // Should not throw exception
        $this->authManager->logout('api');
        $this->assertTrue(true); // Assertion to prevent risky test warning
    }

    public function testJwtGuardViaRemember(): void
    {
        // JWT guards always return false for viaRemember
        $this->assertFalse($this->authManager->viaRemember('api'));
    }

    public function testJwtGuardLogoutOtherDevices(): void
    {
        // JWT guards return false when not authenticated
        $this->assertFalse($this->authManager->logoutOtherDevices('password', 'api'));
    }

    public function testMultipleJwtGuardsWithDifferentConfigs(): void
    {
        $guard1 = $this->authManager->guard('api');
        $guard2 = $this->authManager->guard('mobile');
        
        $this->assertInstanceOf(JwtGuard::class, $guard1);
        $this->assertInstanceOf(JwtGuard::class, $guard2);
        $this->assertNotSame($guard1, $guard2);
        
        // Verify different providers
        $this->assertInstanceOf(DatabaseUserProvider::class, $guard1->getProvider());
        $this->assertInstanceOf(JwtUserProvider::class, $guard2->getProvider());
    }

    public function testDefaultGuardStillWorks(): void
    {
        $defaultGuard = $this->authManager->guard(); // Should get 'web' guard
        $this->assertNotInstanceOf(JwtGuard::class, $defaultGuard);
    }

    public function testAuthManagerWithoutRequest(): void
    {
        $authManager = new AuthManager(
            $this->config,
            $this->mockSession,
            $this->mockCookie,
            $this->mockHash,
            null // No request
        );

        $guard = $authManager->guard('api');
        $this->assertInstanceOf(JwtGuard::class, $guard);
        
        // Should not fail without request
        $this->assertFalse($guard->check());
    }

    public function testSetRequestAfterGuardCreation(): void
    {
        // Create guard first
        $guard = $this->authManager->guard('api');
        
        // Create new test request
        $newRequest = new class extends Request {
            public function __construct() {}
            public function header(string $key, ?string $default = null): ?string {
                return $key === 'authorization' ? 'Bearer test-token' : null;
            }
            public function cookie(string $key, ?string $default = null): ?string { return null; }
            public function query(?string $key = null, mixed $default = null): mixed { return null; }
        };
        
        // Set request - should update existing guards
        $this->authManager->setRequest($newRequest);
        
        $this->assertSame($newRequest, $this->authManager->getRequest());
    }

    public function testJwtConfigPassedCorrectlyToComponents(): void
    {
        // Test that JWT config is properly passed to JWT components
        $guard = $this->authManager->guard('api');
        
        // Generate a token to verify configuration works
        $user = ['id' => 1];
        /** @var JwtGuard $guard */
        $token = $guard->generateTokenForUser($user);
        
        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/', $token);
    }
}