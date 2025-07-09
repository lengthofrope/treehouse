<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Support;

use ArrayAccess;
use Countable;
use Iterator;
use InvalidArgumentException;

/**
 * Address List
 * 
 * Collection of email addresses with validation and formatting capabilities.
 * Implements array-like access and iteration.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Support
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class AddressList implements ArrayAccess, Countable, Iterator
{
    /**
     * Collection of addresses
     * 
     * @var Address[]
     */
    protected array $addresses = [];

    /**
     * Iterator position
     */
    protected int $position = 0;

    /**
     * Create a new AddressList instance
     * 
     * @param array $addresses Initial addresses
     */
    public function __construct(array $addresses = [])
    {
        foreach ($addresses as $address) {
            $this->add($address);
        }
    }

    /**
     * Add an address to the list
     * 
     * @param string|Address $address
     * @return static
     */
    public function add(string|Address $address): static
    {
        if (is_string($address)) {
            $address = Address::parse($address);
        }

        $this->addresses[] = $address;
        return $this;
    }

    /**
     * Remove an address from the list
     * 
     * @param string|Address $address
     * @return static
     */
    public function remove(string|Address $address): static
    {
        if (is_string($address)) {
            $address = Address::parse($address);
        }

        $this->addresses = array_filter($this->addresses, function (Address $addr) use ($address) {
            return !$addr->equals($address);
        });

        $this->addresses = array_values($this->addresses); // Re-index
        return $this;
    }

    /**
     * Check if an address exists in the list
     * 
     * @param string|Address $address
     * @return bool
     */
    public function has(string|Address $address): bool
    {
        if (is_string($address)) {
            $address = Address::parse($address);
        }

        foreach ($this->addresses as $addr) {
            if ($addr->equals($address)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all addresses
     * 
     * @return Address[]
     */
    public function all(): array
    {
        return $this->addresses;
    }

    /**
     * Get first address
     * 
     * @return Address|null
     */
    public function first(): ?Address
    {
        return $this->addresses[0] ?? null;
    }

    /**
     * Get last address
     * 
     * @return Address|null
     */
    public function last(): ?Address
    {
        return end($this->addresses) ?: null;
    }

    /**
     * Clear all addresses
     * 
     * @return static
     */
    public function clear(): static
    {
        $this->addresses = [];
        return $this;
    }

    /**
     * Check if the list is empty
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->addresses);
    }

    /**
     * Convert to comma-separated string
     * 
     * @return string
     */
    public function toString(): string
    {
        return implode(', ', array_map(fn(Address $addr) => $addr->toString(), $this->addresses));
    }

    /**
     * Convert to string
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Convert to array representation
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_map(fn(Address $addr) => $addr->toArray(), $this->addresses);
    }

    /**
     * Create AddressList from array
     * 
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $addresses = array_map(fn(array $addr) => Address::fromArray($addr), $data);
        return new static($addresses);
    }

    /**
     * Create AddressList from mixed input
     * 
     * @param string|array|Address|AddressList $input
     * @return static
     */
    public static function parse(string|array|Address|AddressList $input): static
    {
        if ($input instanceof AddressList) {
            return clone $input;
        }

        if ($input instanceof Address) {
            return new static([$input]);
        }

        if (is_string($input)) {
            // Split by comma and parse each address
            $emails = array_map('trim', explode(',', $input));
            $addresses = array_map(fn(string $email) => Address::parse($email), $emails);
            return new static($addresses);
        }

        if (is_array($input)) {
            return new static($input);
        }

        throw new InvalidArgumentException('Invalid input type for AddressList');
    }

    // ArrayAccess implementation

    public function offsetExists($offset): bool
    {
        return isset($this->addresses[$offset]);
    }

    public function offsetGet($offset): ?Address
    {
        return $this->addresses[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (!$value instanceof Address) {
            $value = Address::parse($value);
        }

        if ($offset === null) {
            $this->addresses[] = $value;
        } else {
            $this->addresses[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->addresses[$offset]);
        $this->addresses = array_values($this->addresses); // Re-index
    }

    // Countable implementation

    public function count(): int
    {
        return count($this->addresses);
    }

    // Iterator implementation

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): ?Address
    {
        return $this->addresses[$this->position] ?? null;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function valid(): bool
    {
        return isset($this->addresses[$this->position]);
    }
}