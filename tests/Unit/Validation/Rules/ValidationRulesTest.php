<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\Rules\Required;
use LengthOfRope\TreeHouse\Validation\Rules\Email;
use LengthOfRope\TreeHouse\Validation\Rules\Numeric;
use LengthOfRope\TreeHouse\Validation\Rules\Integer;
use LengthOfRope\TreeHouse\Validation\Rules\StringRule;
use LengthOfRope\TreeHouse\Validation\Rules\Boolean;
use LengthOfRope\TreeHouse\Validation\Rules\ArrayRule;
use LengthOfRope\TreeHouse\Validation\Rules\Min;
use LengthOfRope\TreeHouse\Validation\Rules\Max;
use LengthOfRope\TreeHouse\Validation\Rules\Between;
use LengthOfRope\TreeHouse\Validation\Rules\In;
use LengthOfRope\TreeHouse\Validation\Rules\NotIn;
use LengthOfRope\TreeHouse\Validation\Rules\Regex;
use LengthOfRope\TreeHouse\Validation\Rules\Alpha;
use LengthOfRope\TreeHouse\Validation\Rules\AlphaNum;
use LengthOfRope\TreeHouse\Validation\Rules\AlphaDash;
use LengthOfRope\TreeHouse\Validation\Rules\Url;
use LengthOfRope\TreeHouse\Validation\Rules\Ip;
use LengthOfRope\TreeHouse\Validation\Rules\Date;
use LengthOfRope\TreeHouse\Validation\Rules\Same;
use LengthOfRope\TreeHouse\Validation\Rules\Different;
use Tests\TestCase;

/**
 * Validation Rules Tests
 *
 * @package Tests\Unit\Validation\Rules
 */
class ValidationRulesTest extends TestCase
{
    public function testRequiredRule()
    {
        $rule = new Required();

        // Test valid values
        $this->assertTrue($rule->passes('test'));
        $this->assertTrue($rule->passes('0'));
        $this->assertTrue($rule->passes(0));
        $this->assertTrue($rule->passes(false));
        $this->assertTrue($rule->passes(['item']));

        // Test invalid values
        $this->assertFalse($rule->passes(null));
        $this->assertFalse($rule->passes(''));
        $this->assertFalse($rule->passes('   '));
        $this->assertFalse($rule->passes([]));

        // Test message
        $message = $rule->message('name');
        $this->assertStringContainsString('name', $message);
        $this->assertStringContainsString('required', $message);
    }

    public function testEmailRule()
    {
        $rule = new Email();

        // Test valid emails
        $this->assertTrue($rule->passes('test@example.com'));
        $this->assertTrue($rule->passes('user.name@domain.co.uk'));
        $this->assertTrue($rule->passes('test+label@example.org'));
        $this->assertTrue($rule->passes(null)); // Allow empty
        $this->assertTrue($rule->passes(''));   // Allow empty

        // Test invalid emails
        $this->assertFalse($rule->passes('invalid-email'));
        $this->assertFalse($rule->passes('test@'));
        $this->assertFalse($rule->passes('@example.com'));
        $this->assertFalse($rule->passes(123));

        $message = $rule->message('email');
        $this->assertStringContainsString('email', $message);
        $this->assertStringContainsString('valid', $message);
    }

    public function testNumericRule()
    {
        $rule = new Numeric();

        // Test valid numeric values
        $this->assertTrue($rule->passes(123));
        $this->assertTrue($rule->passes(123.45));
        $this->assertTrue($rule->passes('123'));
        $this->assertTrue($rule->passes('123.45'));
        $this->assertTrue($rule->passes('-123.45'));
        $this->assertTrue($rule->passes(null)); // Allow empty
        $this->assertTrue($rule->passes(''));   // Allow empty

        // Test invalid values
        $this->assertFalse($rule->passes('abc'));
        $this->assertFalse($rule->passes('123abc'));
        $this->assertFalse($rule->passes([]));

        $message = $rule->message('age');
        $this->assertStringContainsString('age', $message);
        $this->assertStringContainsString('number', $message);
    }

    public function testIntegerRule()
    {
        $rule = new Integer();

        // Test valid integers
        $this->assertTrue($rule->passes(123));
        $this->assertTrue($rule->passes(-123));
        $this->assertTrue($rule->passes('123'));
        $this->assertTrue($rule->passes('-123'));
        $this->assertTrue($rule->passes(null)); // Allow empty
        $this->assertTrue($rule->passes(''));   // Allow empty

        // Test invalid values
        $this->assertFalse($rule->passes(123.45));
        $this->assertFalse($rule->passes('123.45'));
        $this->assertFalse($rule->passes('abc'));
        $this->assertFalse($rule->passes([]));

        $message = $rule->message('count');
        $this->assertStringContainsString('count', $message);
        $this->assertStringContainsString('integer', $message);
    }

    public function testStringRule()
    {
        $rule = new StringRule();

        // Test valid strings
        $this->assertTrue($rule->passes('test'));
        $this->assertTrue($rule->passes(''));
        $this->assertTrue($rule->passes(null)); // Allow empty

        // Test invalid values
        $this->assertFalse($rule->passes(123));
        $this->assertFalse($rule->passes([]));
        $this->assertFalse($rule->passes(true));

        $message = $rule->message('name');
        $this->assertStringContainsString('name', $message);
        $this->assertStringContainsString('string', $message);
    }

    public function testBooleanRule()
    {
        $rule = new Boolean();

        // Test valid boolean values
        $this->assertTrue($rule->passes(true));
        $this->assertTrue($rule->passes(false));
        $this->assertTrue($rule->passes(1));
        $this->assertTrue($rule->passes(0));
        $this->assertTrue($rule->passes('1'));
        $this->assertTrue($rule->passes('0'));
        $this->assertTrue($rule->passes('true'));
        $this->assertTrue($rule->passes('false'));
        $this->assertTrue($rule->passes('yes'));
        $this->assertTrue($rule->passes('no'));
        $this->assertTrue($rule->passes('on'));
        $this->assertTrue($rule->passes('off'));
        $this->assertTrue($rule->passes('TRUE')); // Case insensitive
        $this->assertTrue($rule->passes('FALSE'));
        $this->assertTrue($rule->passes(null)); // Allow empty
        $this->assertTrue($rule->passes(''));   // Allow empty

        // Test invalid values
        $this->assertFalse($rule->passes('invalid'));
        $this->assertFalse($rule->passes(2));
        $this->assertFalse($rule->passes([]));

        $message = $rule->message('active');
        $this->assertStringContainsString('active', $message);
        $this->assertStringContainsString('true or false', $message);
    }

    public function testArrayRule()
    {
        $rule = new ArrayRule();

        // Test valid arrays
        $this->assertTrue($rule->passes([]));
        $this->assertTrue($rule->passes(['item']));
        $this->assertTrue($rule->passes(['key' => 'value']));
        $this->assertTrue($rule->passes(null)); // Allow empty
        $this->assertTrue($rule->passes(''));   // Allow empty

        // Test invalid values
        $this->assertFalse($rule->passes('string'));
        $this->assertFalse($rule->passes(123));
        $this->assertFalse($rule->passes(true));

        $message = $rule->message('tags');
        $this->assertStringContainsString('tags', $message);
        $this->assertStringContainsString('array', $message);
    }

    public function testMinRule()
    {
        $rule = new Min();

        // Test with string length
        $this->assertTrue($rule->passes('hello', ['3']));
        $this->assertTrue($rule->passes('hello', ['5']));
        $this->assertFalse($rule->passes('hi', ['3']));

        // Test with numeric values
        $this->assertTrue($rule->passes(10, ['5']));
        $this->assertTrue($rule->passes('10', ['5']));
        $this->assertFalse($rule->passes(3, ['5']));

        // Test with arrays
        $this->assertTrue($rule->passes(['a', 'b', 'c'], ['2']));
        $this->assertFalse($rule->passes(['a'], ['2']));

        // Test empty values allowed
        $this->assertTrue($rule->passes(null, ['5']));
        $this->assertTrue($rule->passes('', ['5']));

        // Test without parameters
        $this->assertFalse($rule->passes('test', []));

        $message = $rule->message('password', ['8']);
        $this->assertStringContainsString('password', $message);
        $this->assertStringContainsString('8', $message);
    }

    public function testMaxRule()
    {
        $rule = new Max();

        // Test with string length
        $this->assertTrue($rule->passes('hi', ['5']));
        $this->assertTrue($rule->passes('hello', ['5']));
        $this->assertFalse($rule->passes('hello world', ['5']));

        // Test with numeric values
        $this->assertTrue($rule->passes(3, ['5']));
        $this->assertTrue($rule->passes('3', ['5']));
        $this->assertFalse($rule->passes(10, ['5']));

        // Test with arrays
        $this->assertTrue($rule->passes(['a'], ['2']));
        $this->assertFalse($rule->passes(['a', 'b', 'c'], ['2']));

        // Test empty values allowed
        $this->assertTrue($rule->passes(null, ['5']));
        $this->assertTrue($rule->passes('', ['5']));

        $message = $rule->message('title', ['100']);
        $this->assertStringContainsString('title', $message);
        $this->assertStringContainsString('100', $message);
    }

    public function testBetweenRule()
    {
        $rule = new Between();

        // Test with string length
        $this->assertTrue($rule->passes('hello', ['3', '10']));
        $this->assertFalse($rule->passes('hi', ['3', '10']));
        $this->assertFalse($rule->passes('hello world!', ['3', '10']));

        // Test with numeric values
        $this->assertTrue($rule->passes(5, ['1', '10']));
        $this->assertFalse($rule->passes(0, ['1', '10']));
        $this->assertFalse($rule->passes(15, ['1', '10']));

        // Test with arrays
        $this->assertTrue($rule->passes(['a', 'b'], ['1', '3']));
        $this->assertFalse($rule->passes([], ['1', '3']));

        // Test empty values allowed
        $this->assertTrue($rule->passes(null, ['1', '10']));

        // Test insufficient parameters
        $this->assertFalse($rule->passes('test', ['5']));

        $message = $rule->message('age', ['18', '65']);
        $this->assertStringContainsString('age', $message);
        $this->assertStringContainsString('18', $message);
        $this->assertStringContainsString('65', $message);
    }

    public function testInRule()
    {
        $rule = new In();

        // Test valid values
        $this->assertTrue($rule->passes('apple', ['apple', 'banana', 'orange']));
        $this->assertTrue($rule->passes('1', ['1', '2', '3']));
        $this->assertTrue($rule->passes(null, ['apple', 'banana'])); // Allow empty
        $this->assertTrue($rule->passes('', ['apple', 'banana']));   // Allow empty

        // Test invalid values
        $this->assertFalse($rule->passes('grape', ['apple', 'banana', 'orange']));
        $this->assertFalse($rule->passes(1, ['apple', 'banana'])); // Type mismatch

        $message = $rule->message('fruit', ['apple', 'banana', 'orange']);
        $this->assertStringContainsString('fruit', $message);
        $this->assertStringContainsString('apple', $message);
    }

    public function testNotInRule()
    {
        $rule = new NotIn();

        // Test valid values
        $this->assertTrue($rule->passes('grape', ['apple', 'banana', 'orange']));
        $this->assertTrue($rule->passes(null, ['apple', 'banana'])); // Allow empty
        $this->assertTrue($rule->passes('', ['apple', 'banana']));   // Allow empty

        // Test invalid values
        $this->assertFalse($rule->passes('apple', ['apple', 'banana', 'orange']));

        $message = $rule->message('username');
        $this->assertStringContainsString('username', $message);
        $this->assertStringContainsString('invalid', $message);
    }

    public function testRegexRule()
    {
        $rule = new Regex();

        // Test valid patterns
        $this->assertTrue($rule->passes('123', ['/^\d+$/']));
        $this->assertTrue($rule->passes('abc', ['/^[a-z]+$/']));
        $this->assertTrue($rule->passes(null, ['/^\d+$/'])); // Allow empty
        $this->assertTrue($rule->passes('', ['/^\d+$/']));   // Allow empty

        // Test invalid patterns
        $this->assertFalse($rule->passes('abc', ['/^\d+$/']));
        $this->assertFalse($rule->passes(123, ['/^\d+$/'])); // Not a string

        // Test without parameters
        $this->assertFalse($rule->passes('test', []));

        $message = $rule->message('code');
        $this->assertStringContainsString('code', $message);
        $this->assertStringContainsString('format', $message);
    }

    public function testAlphaRule()
    {
        $rule = new Alpha();

        // Test valid values
        $this->assertTrue($rule->passes('abc', []));
        $this->assertTrue($rule->passes('ABC', []));
        $this->assertTrue($rule->passes('AbC', []));
        $this->assertTrue($rule->passes(null, [])); // Allow empty
        $this->assertTrue($rule->passes('', []));   // Allow empty

        // Test invalid values
        $this->assertFalse($rule->passes('abc123', []));
        $this->assertFalse($rule->passes('abc-def', []));
        $this->assertFalse($rule->passes('abc def', []));
        $this->assertFalse($rule->passes(123, []));

        $message = $rule->message('name');
        $this->assertStringContainsString('name', $message);
        $this->assertStringContainsString('letters', $message);
    }

    public function testAlphaNumRule()
    {
        $rule = new AlphaNum();

        // Test valid values
        $this->assertTrue($rule->passes('abc', []));
        $this->assertTrue($rule->passes('123', []));
        $this->assertTrue($rule->passes('abc123', []));
        $this->assertTrue($rule->passes('ABC123', []));
        $this->assertTrue($rule->passes(null, [])); // Allow empty
        $this->assertTrue($rule->passes('', []));   // Allow empty

        // Test invalid values
        $this->assertFalse($rule->passes('abc-123', []));
        $this->assertFalse($rule->passes('abc 123', []));
        $this->assertFalse($rule->passes('abc_123', []));
        $this->assertFalse($rule->passes(123, []));

        $message = $rule->message('username');
        $this->assertStringContainsString('username', $message);
        $this->assertStringContainsString('letters and numbers', $message);
    }

    public function testAlphaDashRule()
    {
        $rule = new AlphaDash();

        // Test valid values
        $this->assertTrue($rule->passes('abc', []));
        $this->assertTrue($rule->passes('abc123', []));
        $this->assertTrue($rule->passes('abc-123', []));
        $this->assertTrue($rule->passes('abc_123', []));
        $this->assertTrue($rule->passes('abc-def_123', []));
        $this->assertTrue($rule->passes(null, [])); // Allow empty
        $this->assertTrue($rule->passes('', []));   // Allow empty

        // Test invalid values
        $this->assertFalse($rule->passes('abc 123', []));
        $this->assertFalse($rule->passes('abc.123', []));
        $this->assertFalse($rule->passes('abc@123', []));
        $this->assertFalse($rule->passes(123, []));

        $message = $rule->message('slug');
        $this->assertStringContainsString('slug', $message);
        $this->assertStringContainsString('letters, numbers, dashes, and underscores', $message);
    }

    public function testUrlRule()
    {
        $rule = new Url();

        // Test valid URLs
        $this->assertTrue($rule->passes('https://example.com', []));
        $this->assertTrue($rule->passes('http://example.com', []));
        $this->assertTrue($rule->passes('https://subdomain.example.com/path', []));
        $this->assertTrue($rule->passes('ftp://example.com', []));
        $this->assertTrue($rule->passes(null, [])); // Allow empty
        $this->assertTrue($rule->passes('', []));   // Allow empty

        // Test invalid URLs
        $this->assertFalse($rule->passes('example.com', []));
        $this->assertFalse($rule->passes('not-a-url', []));
        $this->assertFalse($rule->passes(123, []));

        $message = $rule->message('website');
        $this->assertStringContainsString('website', $message);
        $this->assertStringContainsString('valid URL', $message);
    }

    public function testIpRule()
    {
        $rule = new Ip();

        // Test valid IP addresses
        $this->assertTrue($rule->passes('127.0.0.1', []));
        $this->assertTrue($rule->passes('192.168.1.1', []));
        $this->assertTrue($rule->passes('::1', [])); // IPv6
        $this->assertTrue($rule->passes('2001:db8::1', [])); // IPv6
        $this->assertTrue($rule->passes(null, [])); // Allow empty
        $this->assertTrue($rule->passes('', []));   // Allow empty

        // Test invalid IP addresses
        $this->assertFalse($rule->passes('256.256.256.256', []));
        $this->assertFalse($rule->passes('not-an-ip', []));
        $this->assertFalse($rule->passes(123, []));

        $message = $rule->message('ip_address');
        $this->assertStringContainsString('ip_address', $message);
        $this->assertStringContainsString('valid IP address', $message);
    }

    public function testDateRule()
    {
        $rule = new Date();

        // Test valid dates
        $this->assertTrue($rule->passes('2023-12-25', []));
        $this->assertTrue($rule->passes('12/25/2023', []));
        $this->assertTrue($rule->passes('December 25, 2023', []));
        $this->assertTrue($rule->passes(new \DateTime(), []));
        $this->assertTrue($rule->passes(time(), [])); // Timestamp
        $this->assertTrue($rule->passes(null, [])); // Allow empty
        $this->assertTrue($rule->passes('', []));   // Allow empty

        // Test invalid dates
        $this->assertFalse($rule->passes('not-a-date', []));
        $this->assertFalse($rule->passes('2023-13-40', [])); // Invalid date
        $this->assertFalse($rule->passes([], []));

        $message = $rule->message('birth_date');
        $this->assertStringContainsString('birth_date', $message);
        $this->assertStringContainsString('valid date', $message);
    }

    public function testSameRule()
    {
        $rule = new Same();

        $data = [
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'different_field' => 'different_value'
        ];

        // Test matching values
        $this->assertTrue($rule->passes('secret123', ['password'], $data));

        // Test non-matching values
        $this->assertFalse($rule->passes('different', ['password'], $data));

        // Test with missing parameter
        $this->assertFalse($rule->passes('value', [], $data));

        // Test with non-existent field
        $this->assertFalse($rule->passes('value', ['nonexistent'], $data));

        $message = $rule->message('password_confirmation', ['password']);
        $this->assertStringContainsString('password_confirmation', $message);
        $this->assertStringContainsString('password', $message);
        $this->assertStringContainsString('match', $message);
    }

    public function testDifferentRule()
    {
        $rule = new Different();

        $data = [
            'current_password' => 'old123',
            'new_password' => 'new456'
        ];

        // Test different values
        $this->assertTrue($rule->passes('new456', ['current_password'], $data));

        // Test same values
        $this->assertFalse($rule->passes('old123', ['current_password'], $data));

        // Test with missing parameter
        $this->assertFalse($rule->passes('value', [], $data));

        $message = $rule->message('new_password', ['current_password']);
        $this->assertStringContainsString('new_password', $message);
        $this->assertStringContainsString('current_password', $message);
        $this->assertStringContainsString('different', $message);
    }
}
