<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Output;

/**
 * Output Interface
 * 
 * Defines the contract for handling command line output in the TreeHouse CLI.
 * 
 * @package LengthOfRope\TreeHouse\Console\Output
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface OutputInterface
{
    /**
     * Write a message to output
     */
    public function write(string $message): void;

    /**
     * Write a message to output with a newline
     */
    public function writeln(string $message): void;

    /**
     * Set output verbosity level
     */
    public function setVerbosity(int $level): void;

    /**
     * Get output verbosity level
     */
    public function getVerbosity(): int;

    /**
     * Check if output is quiet
     */
    public function isQuiet(): bool;

    /**
     * Check if output is verbose
     */
    public function isVerbose(): bool;

    /**
     * Check if output is very verbose
     */
    public function isVeryVerbose(): bool;

    /**
     * Check if output is debug level
     */
    public function isDebug(): bool;
}