<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Input;

/**
 * Input Interface
 * 
 * Defines the contract for handling command line input in the TreeHouse CLI.
 * 
 * @package LengthOfRope\TreeHouse\Console\Input
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface InputInterface
{
    /**
     * Get an argument value by name
     */
    public function getArgument(string $name): mixed;

    /**
     * Check if an argument exists
     */
    public function hasArgument(string $name): bool;

    /**
     * Get all arguments
     *
     * @return array<string, mixed>
     */
    public function getArguments(): array;

    /**
     * Get an option value by name
     */
    public function getOption(string $name): mixed;

    /**
     * Check if an option exists
     */
    public function hasOption(string $name): bool;

    /**
     * Get all options
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    /**
     * Get the raw command line arguments
     *
     * @return string[]
     */
    public function getRawArguments(): array;
}