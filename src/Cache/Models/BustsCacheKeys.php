<?php

namespace mindtwo\TwoTility\Cache\Models;

use Closure;
use mindtwo\TwoTility\Cache\KeyGenerator;

trait BustsCacheKeys
{
    /**
     * Override to define which events trigger cache busting for this model.
     *
     * @var array<string>
     */
    protected static array $bustOn = [];

    /**
     * Get the cache keys to bust for the given event.
     *
     * @return array<string|KeyGenerator|callable>
     */
    abstract protected function getCacheKeysToBust(string $eventName): array;

    /**
     * Initialize the trait, register lifecycle event listeners.
     */
    public static function bootBustsCacheKeys(): void
    {
        /** @var array<string> $events */
        $events = self::$bustOn ?: config('two-tility.cache.bust_on');

        foreach ($events as $eventName) {
            if ($eventName === 'updated') {
                static::updated(function ($model) {
                    if ($model->isDirty()) {
                        $model->bustCacheKeys('updated');
                    }
                });

                continue;
            }

            static::$eventName(function ($model) use ($eventName) {
                $model->bustCacheKeys($eventName);
            });
        }
    }

    /**
     * Clear cache for the given $event.
     */
    protected function bustCacheKeys(string $event): void
    {
        $keys = $this->getCacheKeysToBust($event);

        if (empty($keys)) {
            return;
        }

        foreach ($keys as $key) {
            if ($key instanceof Closure) {
                $key = $key();
            }

            if ($key instanceof KeyGenerator) {
                $key = (string) $key;
            }

            if (is_string($key)) {
                cache()->forget($key);
            }
        }
    }
}
