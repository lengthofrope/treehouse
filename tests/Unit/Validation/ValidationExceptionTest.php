<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use LengthOfRope\TreeHouse\Validation\ValidationException;
use Tests\TestCase;

/**
 * ValidationException Tests
 *
 * @package Tests\Unit\Validation
 */
class ValidationExceptionTest extends TestCase
{
    public function testBasicExceptionCreation()
    {
        $errors = [
            'name' => ['The name field is required.'],
            'email' => ['The email field must be a valid email address.']
        ];

        $data = ['name' => '', 'email' => 'invalid'];

        $exception = new ValidationException($errors, $data);

        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals($errors, $exception->getErrors());
        $this->assertEquals($data, $exception->getData());
    }

    public function testGetFieldErrors()
    {
        $errors = [
            'name' => ['The name field is required.', 'The name field must be at least 2 characters.'],
            'email' => ['The email field must be a valid email address.']
        ];

        $exception = new ValidationException($errors);

        $nameErrors = $exception->getFieldErrors('name');
        $this->assertCount(2, $nameErrors);
        $this->assertEquals('The name field is required.', $nameErrors[0]);
        $this->assertEquals('The name field must be at least 2 characters.', $nameErrors[1]);

        $emailErrors = $exception->getFieldErrors('email');
        $this->assertCount(1, $emailErrors);
        $this->assertEquals('The email field must be a valid email address.', $emailErrors[0]);

        $nonExistentErrors = $exception->getFieldErrors('nonexistent');
        $this->assertEmpty($nonExistentErrors);
    }

    public function testGetFirstError()
    {
        $errors = [
            'name' => ['First error', 'Second error'],
            'email' => ['Email error']
        ];

        $exception = new ValidationException($errors);

        $this->assertEquals('First error', $exception->getFirstError('name'));
        $this->assertEquals('Email error', $exception->getFirstError('email'));
        $this->assertNull($exception->getFirstError('nonexistent'));
    }

    public function testHasError()
    {
        $errors = [
            'name' => ['Name error'],
            'email' => []
        ];

        $exception = new ValidationException($errors);

        $this->assertTrue($exception->hasError('name'));
        $this->assertFalse($exception->hasError('email')); // Empty array
        $this->assertFalse($exception->hasError('nonexistent'));
    }

    public function testGetAllMessages()
    {
        $errors = [
            'name' => ['Name error 1', 'Name error 2'],
            'email' => ['Email error'],
            'age' => ['Age error 1', 'Age error 2', 'Age error 3']
        ];

        $exception = new ValidationException($errors);
        $allMessages = $exception->getAllMessages();

        $this->assertCount(6, $allMessages);
        $this->assertContains('Name error 1', $allMessages);
        $this->assertContains('Name error 2', $allMessages);
        $this->assertContains('Email error', $allMessages);
        $this->assertContains('Age error 1', $allMessages);
        $this->assertContains('Age error 2', $allMessages);
        $this->assertContains('Age error 3', $allMessages);
    }

    public function testGetFirstMessage()
    {
        $errors = [
            'name' => ['Name error'],
            'email' => ['Email error']
        ];

        $exception = new ValidationException($errors);
        $this->assertEquals('Name error', $exception->getFirstMessage());

        // Test with empty errors
        $emptyException = new ValidationException([]);
        $this->assertNull($emptyException->getFirstMessage());

        // Test with empty arrays
        $emptyArraysException = new ValidationException(['name' => [], 'email' => []]);
        $this->assertNull($emptyArraysException->getFirstMessage());
    }

    public function testToArray()
    {
        $errors = [
            'name' => ['Name error'],
            'email' => ['Email error']
        ];

        $data = ['name' => '', 'email' => 'invalid'];

        $exception = new ValidationException($errors, $data, 'Custom message');
        $array = $exception->toArray();

        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('data', $array);

        $this->assertEquals('Custom message', $array['message']);
        $this->assertEquals($errors, $array['errors']);
        $this->assertEquals($data, $array['data']);
    }

    public function testGetSummary()
    {
        // Test with no errors
        $noErrorsException = new ValidationException([]);
        $this->assertEquals('No validation errors', $noErrorsException->getSummary());

        // Test with one error field
        $oneErrorException = new ValidationException(['name' => ['Error']]);
        $this->assertEquals('Validation failed for 1 field', $oneErrorException->getSummary());

        // Test with multiple error fields
        $multipleErrorsException = new ValidationException([
            'name' => ['Error 1'],
            'email' => ['Error 2'],
            'age' => ['Error 3']
        ]);
        $this->assertEquals('Validation failed for 3 fields', $multipleErrorsException->getSummary());
    }

    public function testCustomMessage()
    {
        $errors = ['name' => ['Name error']];
        $customMessage = 'User input validation failed';

        $exception = new ValidationException($errors, [], $customMessage);
        $this->assertEquals($customMessage, $exception->getMessage());
    }

    public function testEmptyData()
    {
        $errors = ['name' => ['Name error']];
        $exception = new ValidationException($errors);

        $this->assertEquals([], $exception->getData());
    }
}
