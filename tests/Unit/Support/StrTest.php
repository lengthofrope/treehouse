<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use LengthOfRope\TreeHouse\Support\Str;
use Tests\TestCase;

/**
 * Test cases for Str class
 * 
 * @package Tests\Unit\Support
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
class StrTest extends TestCase
{
    public function testAfter(): void
    {
        $this->assertEquals('name', Str::after('user.name', '.'));
        $this->assertEquals('user.name', Str::after('user.name', ''));
        $this->assertEquals('user.name', Str::after('user.name', '|'));
    }

    public function testAfterLast(): void
    {
        $this->assertEquals('txt', Str::afterLast('file.name.txt', '.'));
        $this->assertEquals('file.name.txt', Str::afterLast('file.name.txt', '|'));
        $this->assertEquals('file', Str::afterLast('file', '.'));
    }

    public function testAscii(): void
    {
        $this->assertEquals('Hello World', Str::ascii('Hello World'));
        $this->assertEquals('caf', Str::ascii('café'));
        $this->assertEquals('', Str::ascii(''));
    }

    public function testBefore(): void
    {
        $this->assertEquals('user', Str::before('user.name', '.'));
        $this->assertEquals('user.name', Str::before('user.name', ''));
        $this->assertEquals('user.name', Str::before('user.name', '|'));
    }

    public function testBeforeLast(): void
    {
        $this->assertEquals('file.name', Str::beforeLast('file.name.txt', '.'));
        $this->assertEquals('file.name.txt', Str::beforeLast('file.name.txt', '|'));
        $this->assertEquals('file.name.txt', Str::beforeLast('file.name.txt', ''));
    }

    public function testBetween(): void
    {
        $this->assertEquals('name', Str::between('[name]', '[', ']'));
        $this->assertEquals('[name]', Str::between('[name]', '', ']'));
        $this->assertEquals('[name]', Str::between('[name]', '[', ''));
        $this->assertEquals('hello world', Str::between('say hello world please', 'say ', ' please'));
    }

    public function testBetweenFirst(): void
    {
        $this->assertEquals('name', Str::betweenFirst('[name][other]', '[', ']'));
        $this->assertEquals('[name][other]', Str::betweenFirst('[name][other]', '', ']'));
        $this->assertEquals('[name][other]', Str::betweenFirst('[name][other]', '[', ''));
    }

    public function testCamel(): void
    {
        $this->assertEquals('fooBar', Str::camel('foo_bar'));
        $this->assertEquals('fooBar', Str::camel('foo-bar'));
        $this->assertEquals('fooBar', Str::camel('foo bar'));
        $this->assertEquals('fooBar', Str::camel('FooBar'));
    }

    public function testContains(): void
    {
        $this->assertTrue(Str::contains('Hello World', 'World'));
        $this->assertFalse(Str::contains('Hello World', 'world'));
        $this->assertTrue(Str::contains('Hello World', 'world', true)); // ignore case
        $this->assertTrue(Str::contains('Hello World', ['Hello', 'World']));
        $this->assertFalse(Str::contains('Hello World', ['foo', 'bar']));
    }

    public function testContainsAll(): void
    {
        $this->assertTrue(Str::containsAll('Hello World', ['Hello', 'World']));
        $this->assertFalse(Str::containsAll('Hello World', ['Hello', 'foo']));
        $this->assertTrue(Str::containsAll('Hello World', [])); // Empty array returns true
    }

    public function testEndsWith(): void
    {
        $this->assertTrue(Str::endsWith('Hello World', 'World'));
        $this->assertFalse(Str::endsWith('Hello World', 'Hello'));
        $this->assertTrue(Str::endsWith('Hello World', ['World', 'Universe']));
        $this->assertFalse(Str::endsWith('Hello World', ['Hello', 'Universe']));
    }

    public function testFinish(): void
    {
        $this->assertEquals('test/', Str::finish('test', '/'));
        $this->assertEquals('test/', Str::finish('test/', '/'));
        $this->assertEquals('test/', Str::finish('test///', '/'));
    }

    public function testIs(): void
    {
        $this->assertTrue(Str::is('foo*', 'foobar'));
        $this->assertFalse(Str::is('foo*', 'barfoo'));
        $this->assertTrue(Str::is(['foo*', 'bar*'], 'foobar'));
        $this->assertTrue(Str::is(['foo*', 'bar*'], 'barfoo'));
        $this->assertFalse(Str::is(['baz*', 'qux*'], 'foobar'));
    }

    public function testIsAscii(): void
    {
        $this->assertTrue(Str::isAscii('Hello World'));
        $this->assertFalse(Str::isAscii('café'));
        $this->assertTrue(Str::isAscii(''));
    }

    public function testIsJson(): void
    {
        $this->assertTrue(Str::isJson('{"name":"value"}'));
        $this->assertTrue(Str::isJson('[]'));
        $this->assertFalse(Str::isJson('not json'));
        $this->assertFalse(Str::isJson(''));
    }

    public function testIsUuid(): void
    {
        $this->assertTrue(Str::isUuid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertFalse(Str::isUuid('not-a-uuid'));
        $this->assertFalse(Str::isUuid(''));
    }

    public function testKebab(): void
    {
        $this->assertEquals('foo-bar', Str::kebab('fooBar'));
        $this->assertEquals('foo_bar', Str::kebab('foo_bar'));
        $this->assertEquals('foo-bar', Str::kebab('foo bar'));
        $this->assertEquals('foo-bar', Str::kebab('FooBar'));
    }

    public function testLength(): void
    {
        $this->assertEquals(5, Str::length('hello'));
        $this->assertEquals(0, Str::length(''));
        $this->assertEquals(11, Str::length('hello world'));
    }

    public function testLimit(): void
    {
        $this->assertEquals('Hello...', Str::limit('Hello World', 5));
        $this->assertEquals('Hello World', Str::limit('Hello World', 20));
        $this->assertEquals('Hello***', Str::limit('Hello World', 5, '***'));
    }

    public function testLower(): void
    {
        $this->assertEquals('hello world', Str::lower('HELLO WORLD'));
        $this->assertEquals('hello world', Str::lower('Hello World'));
        $this->assertEquals('hello world', Str::lower('hello world'));
    }

    public function testWords(): void
    {
        $this->assertEquals('Hello...', Str::words('Hello World Universe', 1));
        $this->assertEquals('Hello World...', Str::words('Hello World Universe', 2));
        $this->assertEquals('Hello World Universe', Str::words('Hello World Universe', 5));
        $this->assertEquals('Hello***', Str::words('Hello World Universe', 1, '***'));
    }

    public function testMask(): void
    {
        $this->assertEquals('hel**', Str::mask('hello', '*', 3));
        $this->assertEquals('***lo', Str::mask('hello', '*', 0, 3));
        $this->assertEquals('he***', Str::mask('hello', '*', -3));
    }

    public function testMatch(): void
    {
        $this->assertEquals('bar', Str::match('/foo(.*)/', 'foobar'));
        $this->assertEquals('foobar', Str::match('/foo.*/', 'foobar'));
        $this->assertEquals('', Str::match('/baz/', 'foobar'));
    }

    public function testMatchAll(): void
    {
        $matches = Str::matchAll('/\d+/', 'abc123def456');
        $this->assertCount(2, $matches);
        $this->assertEquals(['123', '456'], $matches->all());
    }

    public function testIsMatch(): void
    {
        $this->assertTrue(Str::isMatch('/foo.*/', 'foobar'));
        $this->assertFalse(Str::isMatch('/baz.*/', 'foobar'));
        $this->assertTrue(Str::isMatch(['/foo.*/', '/bar.*/'], 'foobar'));
    }

    public function testPadBoth(): void
    {
        $this->assertEquals('  hello  ', Str::padBoth('hello', 9));
        $this->assertEquals('--hello--', Str::padBoth('hello', 9, '-'));
        $this->assertEquals('hello', Str::padBoth('hello', 3)); // No padding if length is less
    }

    public function testPadLeft(): void
    {
        $this->assertEquals('    hello', Str::padLeft('hello', 9));
        $this->assertEquals('----hello', Str::padLeft('hello', 9, '-'));
        $this->assertEquals('hello', Str::padLeft('hello', 3));
    }

    public function testPadRight(): void
    {
        $this->assertEquals('hello    ', Str::padRight('hello', 9));
        $this->assertEquals('hello----', Str::padRight('hello', 9, '-'));
        $this->assertEquals('hello', Str::padRight('hello', 3));
    }

    public function testParseCallback(): void
    {
        $this->assertEquals(['Class', 'method'], Str::parseCallback('Class@method'));
        $this->assertEquals(['Class', null], Str::parseCallback('Class'));
        $this->assertEquals(['Class', 'default'], Str::parseCallback('Class', 'default'));
    }

    public function testPlural(): void
    {
        $this->assertEquals('cars', Str::plural('car'));
        $this->assertEquals('car', Str::plural('car', 1));
        $this->assertEquals('boxes', Str::plural('box'));
        $this->assertEquals('cities', Str::plural('city'));
        $this->assertEquals('knives', Str::plural('knife'));
        $this->assertEquals('wives', Str::plural('wife'));
    }

    public function testPluralStudly(): void
    {
        $this->assertEquals('UserCars', Str::pluralStudly('UserCar'));
        $this->assertEquals('UserCar', Str::pluralStudly('UserCar', 1));
    }

    public function testRandom(): void
    {
        $random1 = Str::random(10);
        $random2 = Str::random(10);
        
        $this->assertEquals(10, strlen($random1));
        $this->assertEquals(10, strlen($random2));
        $this->assertNotEquals($random1, $random2); // Should be different
        
        // Test default length
        $randomDefault = Str::random();
        $this->assertEquals(16, strlen($randomDefault));
    }

    public function testRepeat(): void
    {
        $this->assertEquals('aaaa', Str::repeat('a', 4));
        $this->assertEquals('abcabc', Str::repeat('abc', 2));
        $this->assertEquals('', Str::repeat('a', 0));
    }

    public function testReplaceArray(): void
    {
        $this->assertEquals('Hello Jane', Str::replaceArray('?', ['Hello', 'Jane'], '? ?'));
        $this->assertEquals('The quick brown fox', Str::replaceArray('?', ['quick', 'brown', 'fox'], 'The ? ? ?'));
    }

    public function testReplaceFirst(): void
    {
        $this->assertEquals('Hello Universe World', Str::replaceFirst('World', 'Universe', 'Hello World World'));
        $this->assertEquals('Hello World World', Str::replaceFirst('foo', 'bar', 'Hello World World'));
    }

    public function testReplaceLast(): void
    {
        $this->assertEquals('Hello World Universe', Str::replaceLast('World', 'Universe', 'Hello World World'));
        $this->assertEquals('Hello World World', Str::replaceLast('foo', 'bar', 'Hello World World'));
    }

    public function testRemove(): void
    {
        $this->assertEquals('Hello', Str::remove(' World', 'Hello World'));
        $this->assertEquals('Hello', Str::remove(' World', 'Hello World World'));
        $this->assertEquals('Hello', Str::remove([' World', ' Universe'], 'Hello World Universe'));
    }

    public function testReverse(): void
    {
        $this->assertEquals('olleh', Str::reverse('hello'));
        $this->assertEquals('dlrow olleh', Str::reverse('hello world'));
        $this->assertEquals('', Str::reverse(''));
    }

    public function testStart(): void
    {
        $this->assertEquals('/test', Str::start('test', '/'));
        $this->assertEquals('/test', Str::start('/test', '/'));
        $this->assertEquals('//test', Str::start('test', '//'));
    }

    public function testStartsWith(): void
    {
        $this->assertTrue(Str::startsWith('Hello World', 'Hello'));
        $this->assertFalse(Str::startsWith('Hello World', 'World'));
        $this->assertTrue(Str::startsWith('Hello World', ['Hello', 'Hi']));
        $this->assertFalse(Str::startsWith('Hello World', ['World', 'Hi']));
    }

    public function testStudly(): void
    {
        $this->assertEquals('FooBar', Str::studly('foo_bar'));
        $this->assertEquals('FooBar', Str::studly('foo-bar'));
        $this->assertEquals('FooBar', Str::studly('foo bar'));
        $this->assertEquals('FooBar', Str::studly('fooBar'));
    }

    public function testSubstr(): void
    {
        $this->assertEquals('llo', Str::substr('hello', 2));
        $this->assertEquals('ll', Str::substr('hello', 2, 2));
        $this->assertEquals('lo', Str::substr('hello', -2));
        $this->assertEquals('l', Str::substr('hello', -2, 1));
    }

    public function testSubstrCount(): void
    {
        $this->assertEquals(2, Str::substrCount('Hello World World', 'World'));
        $this->assertEquals(0, Str::substrCount('Hello World', 'foo'));
        $this->assertEquals(3, Str::substrCount('aaa', 'a'));
    }

    public function testSubstrReplace(): void
    {
        $this->assertEquals('heLLo', Str::substrReplace('hello', 'LL', 2, 2));
        $this->assertEquals('heLLlo', Str::substrReplace('hello', 'LL', 2, 1));
    }

    public function testSwap(): void
    {
        $this->assertEquals('Hello Universe', Str::swap(['World' => 'Universe'], 'Hello World'));
        $this->assertEquals('Hi Universe', Str::swap(['Hello' => 'Hi', 'World' => 'Universe'], 'Hello World'));
    }

    public function testTitle(): void
    {
        $this->assertEquals('Hello World', Str::title('hello world'));
        $this->assertEquals('Hello World', Str::title('HELLO WORLD'));
        $this->assertEquals('Hello World', Str::title('Hello World'));
    }

    public function testUcfirst(): void
    {
        $this->assertEquals('Hello world', Str::ucfirst('hello world'));
        $this->assertEquals('Hello world', Str::ucfirst('Hello world'));
        $this->assertEquals('HELLO WORLD', Str::ucfirst('hELLO WORLD'));
    }

    public function testUcsplit(): void
    {
        $this->assertEquals(['Foo', 'Bar'], Str::ucsplit('FooBar'));
        $this->assertEquals(['Hello', 'World', 'Test'], Str::ucsplit('HelloWorldTest'));
    }

    public function testUpper(): void
    {
        $this->assertEquals('HELLO WORLD', Str::upper('hello world'));
        $this->assertEquals('HELLO WORLD', Str::upper('Hello World'));
        $this->assertEquals('HELLO WORLD', Str::upper('HELLO WORLD'));
    }

    public function testWordWrap(): void
    {
        $result = Str::wordWrap('hello world', fn($matches) => strtoupper($matches[0]));
        $this->assertEquals('HELLO WORLD', $result);
    }

    public function testSingular(): void
    {
        $this->assertEquals('car', Str::singular('cars'));
        $this->assertEquals('box', Str::singular('boxes'));
        $this->assertEquals('city', Str::singular('cities'));
        $this->assertEquals('knife', Str::singular('knives'));
        $this->assertEquals('wife', Str::singular('wives'));
    }

    public function testSlug(): void
    {
        $this->assertEquals('hello-world', Str::slug('Hello World'));
        $this->assertEquals('hello_world', Str::slug('Hello World', '_'));
        $this->assertEquals('hello-world', Str::slug('Hello   World'));
        $this->assertEquals('hello-world', Str::slug('Hello-World'));
    }

    public function testSnake(): void
    {
        $this->assertEquals('foo_bar', Str::snake('fooBar'));
        $this->assertEquals('foo-bar', Str::snake('fooBar', '-'));
        $this->assertEquals('foo_bar', Str::snake('foo bar'));
        $this->assertEquals('foo_bar', Str::snake('FooBar'));
    }

    public function testReplace(): void
    {
        $this->assertEquals('Hello Universe', Str::replace('World', 'Universe', 'Hello World'));
        $this->assertEquals('Hi Universe', Str::replace(['Hello', 'World'], ['Hi', 'Universe'], 'Hello World'));
    }

    public function testUuid(): void
    {
        $uuid = Str::uuid();
        
        $this->assertIsString($uuid);
        $this->assertEquals(36, strlen($uuid)); // UUID format: 8-4-4-4-12 = 36 chars
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    public function testOrderedUuid(): void
    {
        $uuid = Str::orderedUuid();
        
        $this->assertIsString($uuid);
        $this->assertEquals(36, strlen($uuid));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        );
    }
}