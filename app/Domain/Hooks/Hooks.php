<?php

namespace App\Domain\Hooks;

class Hooks
{
    protected array $hooks = [];

    /**
     * Register a new hook.
     */
    public function on(string $event, callable $callback, int $priority = 10): void
    {
        if (! isset($this->hooks[$event])) {
            $this->hooks[$event] = [];
        }

        $this->hooks[$event][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];

        // Sort by priority (higher first)
        usort($this->hooks[$event], fn ($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * Apply filter hooks to a value.
     */
    public function apply(string $event, mixed $value, array $context = []): mixed
    {
        if (! isset($this->hooks[$event])) {
            return $value;
        }

        foreach ($this->hooks[$event] as $hook) {
            $value = call_user_func($hook['callback'], $value, $context);
        }

        return $value;
    }

    /**
     * Trigger action hooks.
     */
    public function trigger(string $event, array $context = []): void
    {
        if (! isset($this->hooks[$event])) {
            return;
        }

        foreach ($this->hooks[$event] as $hook) {
            call_user_func($hook['callback'], $context);
        }
    }
}
