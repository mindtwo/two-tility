<?php

namespace mindtwo\TwoTility\Cache;

use Illuminate\Support\Facades\Log;

trait BustsCacheKeys
{
    public static function bootBustsCacheKeys()
    {
        $events = self::getBustingEvents();

        if (is_null($events)) {
            return;
        }

        $events = is_array($events) ? $events : [$events];
        foreach ($events as $eventName) {
            if ($eventName === 'updated') {
                // If the model is not dirty, we don't need to bust cache keys.
                static::updated(function ($model) {
                    if (!$model->isDirty()) {
                        return;
                    }

                    $model->bustCacheKeys('updated');
                });
            }

            static::$eventName(function ($model) use ($eventName) {
                $model->bustCacheKeys($eventName);
            });
        }
    }

    /**
     * Remove cache keys from cache.
     *
     * @return void
     */
    protected function bustCacheKeys(string $event)
    {
        if (! method_exists($this, 'getCacheKeysToBust')) {
            Log::warning('Class '.get_class($this).' uses BustsCacheKeys trait but does not implement getCacheKeysToBust() method.');

            return;
        }

        $keys = $this->getCacheKeysToBust($event);

        if (! is_array($keys) || empty($keys)) {
            Log::warning('Class '.get_class($this).' method "getCacheKeysToBust" returned other value than array or empty cache keys for event '.$event.'.');

            return;
        }

        if (array_filter($keys, function ($key) {
            return ! is_string($key) && ! $key instanceof KeyGenerator && ! is_callable($key);
        })) {
            Log::warning('Class '.get_class($this).' method "getCacheKeysToBust" returned non-string cache keys for event '.$event.'.');

            return;
        }

        foreach ($keys as $key) {
            if (is_callable($key) && is_string($value = $key())) {
                $key = $value;
            }

            if ($key instanceof KeyGenerator) {
                $key = $key->__toString();
            }

            is_string($key) ? cache()->forget($key) : null;
        }
    }

    /**
     * Get the events that will bust cache keys.
     *
     * @return null|array|string
     */
    protected static function getBustingEvents()
    {
        $events = isset(self::$bustOn) ? self::$bustOn : config('two-tility.cache.bust_on');

        if (empty($events) || (! is_array($events) && ! is_string($events))) {
            return null;
        }

        return $events;
    }
}
