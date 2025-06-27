<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use LengthOfRope\TreeHouse\Http\Cookie;
use Tests\TestCase;

/**
 * Cookie Test
 * 
 * @package Tests\Unit\Http
 */
class CookieTest extends TestCase
{
    public function testBasicCookieCreation(): void
    {
        $cookie = new Cookie('test_cookie', 'test_value', 0, '/', 'example.com', true, false, 'Strict');

        $this->assertEquals('test_cookie', $cookie->getName());
        $this->assertEquals('test_value', $cookie->getValue());
        $this->assertEquals(0, $cookie->getExpires());
        $this->assertEquals('/', $cookie->getPath());
        $this->assertEquals('example.com', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertEquals('Strict', $cookie->getSameSite());
    }

    public function testMakeCookie(): void
    {
        $cookie = Cookie::make('session', 'abc123', 60, '/app', 'test.com', false, true, 'Lax');

        $this->assertEquals('session', $cookie->getName());
        $this->assertEquals('abc123', $cookie->getValue());
        $this->assertEquals('/app', $cookie->getPath());
        $this->assertEquals('test.com', $cookie->getDomain());
        $this->assertFalse($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEquals('Lax', $cookie->getSameSite());

        // Check expiration is set correctly (within 1 second tolerance)
        $expectedExpires = time() + (60 * 60);
        $this->assertEqualsWithDelta($expectedExpires, $cookie->getExpires(), 1);
    }

    public function testForeverCookie(): void
    {
        $cookie = Cookie::forever('remember_token', 'token123');

        $this->assertEquals('remember_token', $cookie->getName());
        $this->assertEquals('token123', $cookie->getValue());

        // Should expire in about 5 years (2628000 minutes)
        $expectedExpires = time() + (2628000 * 60);
        $this->assertEqualsWithDelta($expectedExpires, $cookie->getExpires(), 60);
    }

    public function testForgetCookie(): void
    {
        $cookie = Cookie::forget('old_cookie', '/path', 'domain.com');

        $this->assertEquals('old_cookie', $cookie->getName());
        $this->assertEquals('', $cookie->getValue());
        $this->assertEquals('/path', $cookie->getPath());
        $this->assertEquals('domain.com', $cookie->getDomain());

        // Should be expired (in the past)
        $this->assertTrue($cookie->isExpired());
    }

    public function testCookieSetters(): void
    {
        $cookie = new Cookie('test');

        $cookie->setName('new_name')
               ->setValue('new_value')
               ->setPath('/new/path')
               ->setDomain('new.domain.com')
               ->setSecure(true)
               ->setHttpOnly(false)
               ->setSameSite('None');

        $this->assertEquals('new_name', $cookie->getName());
        $this->assertEquals('new_value', $cookie->getValue());
        $this->assertEquals('/new/path', $cookie->getPath());
        $this->assertEquals('new.domain.com', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertEquals('None', $cookie->getSameSite());
    }

    public function testExpiresIn(): void
    {
        $cookie = new Cookie('test');
        $cookie->expiresIn(120); // 2 hours

        $expectedExpires = time() + (120 * 60);
        $this->assertEqualsWithDelta($expectedExpires, $cookie->getExpires(), 1);
    }

    public function testSetExpires(): void
    {
        $cookie = new Cookie('test');
        $futureTime = time() + 3600;
        $cookie->setExpires($futureTime);

        $this->assertEquals($futureTime, $cookie->getExpires());
    }

    public function testIsExpired(): void
    {
        // Future cookie
        $futureCookie = new Cookie('future', 'value', time() + 3600);
        $this->assertFalse($futureCookie->isExpired());

        // Past cookie
        $pastCookie = new Cookie('past', 'value', time() - 3600);
        $this->assertTrue($pastCookie->isExpired());

        // Session cookie (expires = 0)
        $sessionCookie = new Cookie('session', 'value', 0);
        $this->assertFalse($sessionCookie->isExpired());
    }

    public function testIsSessionCookie(): void
    {
        $sessionCookie = new Cookie('session', 'value', 0);
        $this->assertTrue($sessionCookie->isSessionCookie());

        $persistentCookie = new Cookie('persistent', 'value', time() + 3600);
        $this->assertFalse($persistentCookie->isSessionCookie());
    }

    public function testGetMaxAge(): void
    {
        // Session cookie
        $sessionCookie = new Cookie('session', 'value', 0);
        $this->assertEquals(0, $sessionCookie->getMaxAge());

        // Future cookie
        $futureTime = time() + 1800; // 30 minutes
        $futureCookie = new Cookie('future', 'value', $futureTime);
        $this->assertEqualsWithDelta(1800, $futureCookie->getMaxAge(), 1);

        // Past cookie
        $pastCookie = new Cookie('past', 'value', time() - 3600);
        $this->assertEquals(0, $pastCookie->getMaxAge());
    }

    public function testToHeaderString(): void
    {
        $cookie = new Cookie('test_cookie', 'test_value', time() + 3600, '/path', 'example.com', true, true, 'Strict');
        $headerString = $cookie->toHeaderString();

        $this->assertStringContainsString('test_cookie=test_value', $headerString);
        $this->assertStringContainsString('Path=/path', $headerString);
        $this->assertStringContainsString('Domain=example.com', $headerString);
        $this->assertStringContainsString('Secure', $headerString);
        $this->assertStringContainsString('HttpOnly', $headerString);
        $this->assertStringContainsString('SameSite=Strict', $headerString);
        $this->assertStringContainsString('Expires=', $headerString);
        $this->assertStringContainsString('Max-Age=', $headerString);
    }

    public function testToHeaderStringSessionCookie(): void
    {
        $cookie = new Cookie('session', 'value');
        $headerString = $cookie->toHeaderString();

        $this->assertStringContainsString('session=value', $headerString);
        $this->assertStringNotContainsString('Expires=', $headerString);
        $this->assertStringNotContainsString('Max-Age=', $headerString);
    }

    public function testToHeaderStringWithoutOptionalAttributes(): void
    {
        $cookie = new Cookie('simple', 'value', 0, '', '', false, false, '');
        $headerString = $cookie->toHeaderString();

        $this->assertStringContainsString('simple=value', $headerString);
        $this->assertStringNotContainsString('Path=', $headerString);
        $this->assertStringNotContainsString('Domain=', $headerString);
        $this->assertStringNotContainsString('Secure', $headerString);
        $this->assertStringNotContainsString('HttpOnly', $headerString);
        $this->assertStringNotContainsString('SameSite=', $headerString);
    }

    public function testIsValidName(): void
    {
        // Valid names
        $this->assertTrue(Cookie::isValidName('valid_name'));
        $this->assertTrue(Cookie::isValidName('ValidName123'));
        $this->assertTrue(Cookie::isValidName('name-with-dashes'));

        // Invalid names
        $this->assertFalse(Cookie::isValidName(''));
        $this->assertFalse(Cookie::isValidName('name with spaces'));
        $this->assertFalse(Cookie::isValidName('name;with;semicolons'));
        $this->assertFalse(Cookie::isValidName('name,with,commas'));
        $this->assertFalse(Cookie::isValidName('name(with)parentheses'));
        $this->assertFalse(Cookie::isValidName('name<with>brackets'));
        $this->assertFalse(Cookie::isValidName('name@with@at'));
        $this->assertFalse(Cookie::isValidName('name:with:colons'));
        $this->assertFalse(Cookie::isValidName('name\\with\\backslashes'));
        $this->assertFalse(Cookie::isValidName('name"with"quotes'));
        $this->assertFalse(Cookie::isValidName('name/with/slashes'));
        $this->assertFalse(Cookie::isValidName('name[with]squares'));
        $this->assertFalse(Cookie::isValidName('name?with?questions'));
        $this->assertFalse(Cookie::isValidName('name=with=equals'));
        $this->assertFalse(Cookie::isValidName('name{with}braces'));
        $this->assertFalse(Cookie::isValidName("name\twith\ttabs"));
        $this->assertFalse(Cookie::isValidName("name\rwith\rreturns"));
        $this->assertFalse(Cookie::isValidName("name\nwith\nnewlines"));
    }

    public function testIsValidSameSite(): void
    {
        $this->assertTrue(Cookie::isValidSameSite('Strict'));
        $this->assertTrue(Cookie::isValidSameSite('Lax'));
        $this->assertTrue(Cookie::isValidSameSite('None'));

        $this->assertFalse(Cookie::isValidSameSite('Invalid'));
        $this->assertFalse(Cookie::isValidSameSite('strict')); // Case sensitive
        $this->assertFalse(Cookie::isValidSameSite(''));
    }

    public function testStaticGetAndHas(): void
    {
        // Backup original $_COOKIE
        $originalCookie = $_COOKIE;

        try {
            // Set up test cookies
            $_COOKIE = [
                'existing_cookie' => 'cookie_value',
                'another_cookie' => 'another_value'
            ];

            // Test get
            $this->assertEquals('cookie_value', Cookie::get('existing_cookie'));
            $this->assertEquals('another_value', Cookie::get('another_cookie'));
            $this->assertEquals('default', Cookie::get('nonexistent', 'default'));
            $this->assertNull(Cookie::get('nonexistent'));

            // Test has
            $this->assertTrue(Cookie::has('existing_cookie'));
            $this->assertTrue(Cookie::has('another_cookie'));
            $this->assertFalse(Cookie::has('nonexistent'));
        } finally {
            // Restore original $_COOKIE
            $_COOKIE = $originalCookie;
        }
    }

    public function testToString(): void
    {
        $cookie = new Cookie('test', 'value');
        $this->assertEquals($cookie->toHeaderString(), (string) $cookie);
    }

    public function testUrlEncodingInHeader(): void
    {
        $cookie = new Cookie('test', 'value with spaces');
        $headerString = $cookie->toHeaderString();

        $this->assertStringContainsString('test=value%20with%20spaces', $headerString);
    }
}