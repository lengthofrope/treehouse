<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Support;

use InvalidArgumentException;

/**
 * Email Address
 * 
 * Represents an email address with optional name.
 * Provides validation and formatting capabilities.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Support
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Address
{
    /**
     * Email address
     */
    protected string $email;

    /**
     * Display name (optional)
     */
    protected ?string $name;

    /**
     * Create a new Address instance
     * 
     * @param string $email Email address
     * @param string|null $name Display name
     * @throws InvalidArgumentException If email is invalid
     */
    public function __construct(string $email, ?string $name = null)
    {
        if (!$this->isValidEmail($email)) {
            throw new InvalidArgumentException("Invalid email address: {$email}");
        }

        $this->email = $email;
        $this->name = $name;
    }

    /**
     * Get the email address
     * 
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Get the display name
     * 
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the display name
     * 
     * @param string|null $name
     * @return static
     */
    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get formatted address string
     * 
     * @return string
     */
    public function toString(): string
    {
        if ($this->name) {
            return "\"{$this->name}\" <{$this->email}>";
        }

        return $this->email;
    }

    /**
     * Get formatted address string
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Create Address from string
     * 
     * @param string $address Email address or "Name <email@example.com>" format
     * @return static
     */
    public static function parse(string $address): static
    {
        // Check if it's in "Name <email>" format
        if (preg_match('/^"?([^"]*)"?\s*<([^>]+)>$/', $address, $matches)) {
            $name = trim($matches[1]) ?: null;
            $email = trim($matches[2]);
            return new static($email, $name);
        }

        // Simple email address
        return new static(trim($address));
    }

    /**
     * Validate email address
     * 
     * @param string $email
     * @return bool
     */
    protected function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Convert to array representation
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
        ];
    }

    /**
     * Create Address from array
     * 
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static($data['email'], $data['name'] ?? null);
    }

    /**
     * Check if two addresses are equal
     * 
     * @param Address $other
     * @return bool
     */
    public function equals(Address $other): bool
    {
        return $this->email === $other->email && $this->name === $other->name;
    }
}