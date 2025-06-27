<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use LengthOfRope\TreeHouse\Validation\Validator;
use LengthOfRope\TreeHouse\Validation\ValidationException;
use LengthOfRope\TreeHouse\Validation\RuleInterface;
use Tests\TestCase;

/**
 * Validator Tests
 *
 * @package Tests\Unit\Validation
 */
class ValidatorTest extends TestCase
{
    public function testBasicValidation()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25
        ];

        $rules = [
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'age' => ['required', 'integer']
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());

        $validatedData = $validator->validate();
        $this->assertEquals($data, $validatedData);
    }

    public function testValidationFailure()
    {
        $data = [
            'name' => '',
            'email' => 'invalid-email',
            'age' => 'not-a-number'
        ];

        $rules = [
            'name' => ['required'],
            'email' => ['required', 'email'],
            'age' => ['required', 'integer']
        ];

        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->fails());

        $this->expectException(ValidationException::class);
        $validator->validate();
    }

    public function testStringRules()
    {
        $data = [
            'username' => 'john_doe',
            'password' => 'secret123',
            'url' => 'https://example.com'
        ];

        $rules = [
            'username' => ['required', 'string', 'min:3', 'max:20', 'alpha_dash'],
            'password' => ['required', 'string', 'min:8'],
            'url' => ['required', 'url']
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());
    }

    public function testStringRuleFormat()
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => 25
        ];

        $rules = [
            'name' => 'required|string|min:2|max:50',
            'email' => 'required|email',
            'age' => 'required|integer|between:18,120'
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());
    }

    public function testNumericValidation()
    {
        $data = [
            'price' => '19.99',
            'quantity' => 5,
            'rating' => 4.5
        ];

        $rules = [
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'rating' => ['required', 'numeric', 'between:1,5']
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());
    }

    public function testArrayValidation()
    {
        $data = [
            'tags' => ['php', 'laravel', 'mysql'],
            'preferences' => [
                'theme' => 'dark',
                'notifications' => true
            ]
        ];

        $rules = [
            'tags' => ['required', 'array', 'min:1'],
            'preferences' => ['required', 'array']
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());
    }

    public function testBooleanValidation()
    {
        $testCases = [
            ['value' => true, 'expected' => true],
            ['value' => false, 'expected' => true],
            ['value' => 1, 'expected' => true],
            ['value' => 0, 'expected' => true],
            ['value' => '1', 'expected' => true],
            ['value' => '0', 'expected' => true],
            ['value' => 'true', 'expected' => true],
            ['value' => 'false', 'expected' => true],
            ['value' => 'yes', 'expected' => true],
            ['value' => 'no', 'expected' => true],
            ['value' => 'on', 'expected' => true],
            ['value' => 'off', 'expected' => true],
            ['value' => 'invalid', 'expected' => false]
        ];

        foreach ($testCases as $case) {
            $data = ['active' => $case['value']];
            $rules = ['active' => ['boolean']];
            
            $validator = Validator::make($data, $rules);
            $this->assertEquals($case['expected'], $validator->passes(), 
                "Boolean validation failed for value: " . var_export($case['value'], true));
        }
    }

    public function testInValidation()
    {
        $data = [
            'status' => 'active',
            'role' => 'admin'
        ];

        $rules = [
            'status' => ['required', 'in:active,inactive,pending'],
            'role' => ['required', 'in:admin,user,moderator']
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());

        // Test invalid value
        $data['status'] = 'invalid';
        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
    }

    public function testAlphaValidation()
    {
        $testCases = [
            ['value' => 'John', 'rule' => 'alpha', 'expected' => true],
            ['value' => 'John123', 'rule' => 'alpha', 'expected' => false],
            ['value' => 'John123', 'rule' => 'alpha_num', 'expected' => true],
            ['value' => 'john-doe', 'rule' => 'alpha_num', 'expected' => false],
            ['value' => 'john-doe_123', 'rule' => 'alpha_dash', 'expected' => true],
            ['value' => 'john doe', 'rule' => 'alpha_dash', 'expected' => false]
        ];

        foreach ($testCases as $case) {
            $data = ['field' => $case['value']];
            $rules = ['field' => [$case['rule']]];
            
            $validator = Validator::make($data, $rules);
            $this->assertEquals($case['expected'], $validator->passes(),
                "Failed for value '{$case['value']}' with rule '{$case['rule']}'");
        }
    }

    public function testCustomErrorMessages()
    {
        $data = ['name' => ''];
        $rules = ['name' => ['required']];
        $messages = ['name.required' => 'Please provide your name.'];

        $validator = Validator::make($data, $rules, $messages);
        
        try {
            $validator->validate();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertEquals('Please provide your name.', $e->getFirstError('name'));
        }
    }

    public function testCustomFieldLabels()
    {
        $data = ['email' => 'invalid'];
        $rules = ['email' => ['email']];
        $labels = ['email' => 'Email Address'];

        $validator = Validator::make($data, $rules, [], $labels);
        
        try {
            $validator->validate();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $error = $e->getFirstError('email');
            $this->assertStringContainsString('Email Address', $error);
        }
    }

    public function testCustomRule()
    {
        // Create a custom rule
        $customRule = new class implements RuleInterface {
            public function passes(mixed $value, array $parameters = [], array $data = []): bool
            {
                return $value === 'custom-value';
            }

            public function message(string $field, array $parameters = []): string
            {
                return "The {$field} must be 'custom-value'.";
            }
        };

        Validator::extend('custom', $customRule);

        $data = ['field' => 'custom-value'];
        $rules = ['field' => ['custom']];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());

        // Test failure
        $data['field'] = 'wrong-value';
        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
    }

    public function testSameRule()
    {
        $data = [
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ];

        $rules = [
            'password' => ['required'],
            'password_confirmation' => ['required', 'same:password']
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());

        // Test failure
        $data['password_confirmation'] = 'different';
        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
    }

    public function testDifferentRule()
    {
        $data = [
            'current_password' => 'old123',
            'new_password' => 'new456'
        ];

        $rules = [
            'current_password' => ['required'],
            'new_password' => ['required', 'different:current_password']
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());

        // Test failure
        $data['new_password'] = 'old123';
        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
    }

    public function testRegexRule()
    {
        $data = ['phone' => '+1-555-123-4567'];
        $rules = ['phone' => ['regex:/^\+[1-9][\d\-]{1,14}$/']];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());

        // Test invalid format
        $data['phone'] = 'invalid-phone';
        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
    }

    public function testEmptyValuesAllowed()
    {
        $data = [
            'name' => '',
            'email' => null,
            'age' => ''
        ];

        $rules = [
            'name' => ['string', 'max:50'], // No required rule
            'email' => ['email'],           // No required rule
            'age' => ['integer']            // No required rule
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());
    }

    public function testValidatedDataOnlyIncludesRuleFields()
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'extra_field' => 'should not be included'
        ];

        $rules = [
            'name' => ['required'],
            'email' => ['required', 'email']
        ];

        $validator = Validator::make($data, $rules);
        $validatedData = $validator->validate();

        $this->assertArrayHasKey('name', $validatedData);
        $this->assertArrayHasKey('email', $validatedData);
        $this->assertArrayNotHasKey('extra_field', $validatedData);
    }

    public function testGetErrors()
    {
        $data = [
            'name' => '',
            'email' => 'invalid'
        ];

        $rules = [
            'name' => ['required'],
            'email' => ['required', 'email']
        ];

        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());

        $errors = $validator->getErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertIsArray($errors['name']);
        $this->assertIsArray($errors['email']);
    }
}
