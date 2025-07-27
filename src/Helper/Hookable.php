<?php

namespace mindtwo\TwoTility\Helper;

/**
 * Trait Hookable
 *
 * Provides a way to register and trigger hooks for various events.
 *
 * Generic type TEvent is used to define a list of events that can be hooked into.
 *
 * @template TEvent
 */
trait Hookable
{
    /**
     * Array to hold registered hooks for different events.
     *
     * @var array<TEvent, array<callable>>
     */
    protected array $hooks = [];

    /**
     * Trigger a hook for a specific event.
     *
     * @param  TEvent  $event
     */
    public function registerHook(string $event, callable $callback): static
    {
        $this->hooks[$event][] = $callback;

        return $this;
    }

    /**
     * Trigger all hooks for a specific event.
     *
     * @param  TEvent  $event  - The event to trigger hooks for.
     * @param  mixed  ...$args  - Additional arguments to pass to the hooks.
     */
    protected function runHooks(string $event, mixed ...$args): void
    {
        foreach ($this->hooks[$event] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
