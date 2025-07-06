<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Events;

/**
 * Event Listener Interface
 * 
 * Defines the contract for event listeners in the TreeHouse framework.
 * Supports both synchronous and asynchronous processing capabilities.
 * 
 * @package LengthOfRope\TreeHouse\Events
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
interface EventListener
{
    /**
     * Handle the event
     * 
     * @param object $event The event to handle
     * @return mixed Return value from handling the event
     */
    public function handle(object $event): mixed;

    /**
     * Determine if the listener should be queued for asynchronous processing
     * 
     * @return bool
     */
    public function shouldQueue(): bool;

    /**
     * Get the queue name for asynchronous processing
     * 
     * @return string|null Queue name, or null for default queue
     */
    public function getQueue(): ?string;

    /**
     * Get the priority for this listener
     * Higher priority listeners are executed first
     * 
     * @return int
     */
    public function getPriority(): int;

    /**
     * Determine if this listener can handle the given event
     * 
     * @param object $event The event to check
     * @return bool
     */
    public function canHandle(object $event): bool;
}