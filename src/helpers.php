<?php

use mindtwo\TwoTility\Cache\KeyGenerator;

if (! function_exists('cache_key')) {
    /**
     * Get a new KeyGenerator instance from Cache Utility.
     *
     * @param  string|null  $name
     * @param  null|array<string>|array<string,mixed>  $options  - Options for the KeyGenerator instance. If null it will use the value of config('two-tility.cache.default_options'). See KeyGenerator::make() for details.
     * @return \mindtwo\TwoTility\Cache\KeyGenerator|mixed
     */
    function cache_key($name = null, $options = null)
    {
        $name = $name ?? debug_backtrace()[1]['function'];
        $options = $options ?? config('two-tility.cache.default_options');

        return KeyGenerator::make($name, $options);
    }
}

if (! function_exists('cache_key_str')) {
    /**
     * Get a new KeyGenerator instance from Cache Utility.
     *
     * @param  string|null  $name
     * @param  null|array<string>|array<string,mixed>  $options  - Options for the KeyGenerator instance. If null it will use the value of config('two-tility.cache.default_options'). See KeyGenerator::make() for details.
     * @return string
     */
    function cache_key_str($name = null, $options = null)
    {
        return cache_key($name, $options)->toString();
    }
}

if (! function_exists('withTemporaryScope')) {
    /**
     * Temporarily override a scope or value for the duration of a callback,
     * and restore the original value afterward (even if an exception is thrown).
     *
     * This is useful for temporarily changing instance or static properties,
     * config flags, global states, or other scoped settings in a safe and
     * reversible way.
     *
     * Example use cases:
     * - Disabling logging or validation inside a block
     * - Temporarily overriding a model's internal flagu
     * - Switching environment-dependent behavior
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback  The operation to perform while the temporary value is applied.
     * @param  callable(): mixed  $getter  Retrieves the current/original value of the scope.
     * @param  callable(mixed): void  $setter  Sets a new value for the scope.
     * @param  mixed  $temporaryValue  The temporary value to apply during the callback execution.
     * @return TReturn The result of the callback.
     */
    function withTemporaryScope(callable $callback, callable $getter, callable $setter, mixed $temporaryValue): mixed
    {
        $originalValue = $getter();

        try {
            $setter($temporaryValue);

            return $callback();
        } finally {
            $setter($originalValue);
        }
    }
}
