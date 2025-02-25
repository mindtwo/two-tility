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
