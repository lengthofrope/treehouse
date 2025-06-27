<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Concerns;

/**
 * Manages view stacks for assets and content
 * 
 * @package LengthOfRope\TreeHouse\View\Concerns
 */
trait ManagesStack
{
    /**
     * Content stacks
     */
    protected array $stacks = [];

    /**
     * Current stack being built
     */
    protected ?string $currentStack = null;

    /**
     * Push content to a stack
     */
    public function push(string $stack, string $content): self
    {
        if (!isset($this->stacks[$stack])) {
            $this->stacks[$stack] = [];
        }
        
        $this->stacks[$stack][] = $content;
        return $this;
    }

    /**
     * Prepend content to a stack
     */
    public function prepend(string $stack, string $content): self
    {
        if (!isset($this->stacks[$stack])) {
            $this->stacks[$stack] = [];
        }
        
        array_unshift($this->stacks[$stack], $content);
        return $this;
    }

    /**
     * Start pushing to a stack
     */
    public function startPush(string $stack): void
    {
        $this->currentStack = $stack;
        ob_start();
    }

    /**
     * End pushing to a stack
     */
    public function endPush(): void
    {
        if ($this->currentStack === null) {
            throw new \RuntimeException('No stack started');
        }

        $content = ob_get_clean();
        $this->push($this->currentStack, $content);
        $this->currentStack = null;
    }

    /**
     * Get stack content
     */
    public function yieldStack(string $stack): string
    {
        if (!isset($this->stacks[$stack])) {
            return '';
        }

        return implode("\n", $this->stacks[$stack]);
    }

    /**
     * Check if stack has content
     */
    public function hasStack(string $stack): bool
    {
        return isset($this->stacks[$stack]) && !empty($this->stacks[$stack]);
    }

    /**
     * Clear a stack
     */
    public function clearStack(string $stack): self
    {
        unset($this->stacks[$stack]);
        return $this;
    }

    /**
     * Get all stacks
     */
    public function getStacks(): array
    {
        return $this->stacks;
    }

    /**
     * Yield stack content or default
     */
    public function yieldStackOr(string $stack, string $default = ''): string
    {
        return $this->hasStack($stack) ? $this->yieldStack($stack) : $default;
    }
}
