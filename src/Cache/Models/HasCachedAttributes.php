<?php

namespace mindtwo\TwoTility\Cache\Models;

use Illuminate\Support\Facades\Cache;

trait HasCachedAttributes
{
    /**
     * The cached attributes for the model.
     * Uninitialized = not loaded yet, array = loaded (may be empty)
     */
    protected array $cachedAttributes;

    /**
     * Get the cache key for data.
     */
    public function cachedAttributeKey(): string
    {
        return cache_key('attributes_cache', [
            'class' => get_class($this),
            'key' => $this->getKey(),
        ])->toString();
    }

    /**
     * Load the cached attributes from cache to memory.
     */
    public function loadCachedAttributes(): void
    {
        if ($this->attemptedCacheLoad()) {
            // Skip if attributes are loaded
            return;
        }

        // Before load
        $this->beforeCachedAttributeLoad();

        // Load
        $this->cachedAttributes = Cache::store()->get($this->cachedAttributeKey(), []);

        // After load
        $this->afterCachedAttributeLoad();
    }

    /**
     * Get attribute.
     *
     * @param  mixed  $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        if (! $name) {
            return;
        }

        if (! $this->attemptedCacheLoad() && $this->shouldLoadOnAttributeName($name)) {
            $this->loadCachedAttributes();
        }

        // if attribute exists, return it
        if ($this->hasCachedAttribute($name)) {
            return $this->getCachedAttribute($name);
        }

        return parent::getAttribute($name);
    }

    /**
     * Get attribute value from data cache.
     *
     * @param  mixed  $name
     * @return mixed
     */
    public function getCachedAttribute($name)
    {
        // Get the cached attribute
        return $this->cachedAttributes[$name] ?? null;
    }

    /**
     * Hook called before we load the cached attributes.
     */
    protected function beforeCachedAttributeLoad(): void
    {
        // ...
    }

    /**
     * Hook called before we load the cached attributes.
     */
    protected function afterCachedAttributeLoad(): void
    {
        // ...
    }

    /**
     * Check if lazy loading should occur based on key.
     *
     * @param  mixed  $name
     */
    protected function shouldLoadOnAttributeName($name): bool
    {
        return in_array($name, $this->cachableAttributes);
    }

    /**
     *  Check whether we attempted to load cached attributes.
     */
    protected function attemptedCacheLoad(): bool
    {
        return isset($this->cachedAttributes);
    }

    /**
     * Check if cached attributes are populated.
     *
     * @return boolean
     */
    protected function areCachedAttributesLoaded(): bool
    {
        return $this->attemptedCacheLoad() && ! empty($this->cachedAttributes);
    }

    /**
     * Check if an attribute is in cache for name.
     *
     * @param  mixed  $name
     */
    protected function hasCachedAttribute($name): bool
    {
        if (! $this->attemptedCacheLoad()) {
            // Check if we attempted to load the attributes
            return false;
        }

        return isset($this->cachedAttributes[$name]);
    }

    /**
     * Check if the cached attribute key exists.
     */
    protected function cachedAttributeKeyExists(): bool
    {
        return Cache::store()->has($this->cachedAttributeKey());
    }

    /**
     * Get cachable attribute keys
     *
     * @return array
     */
    protected function cachableAttributes(): array
    {
        return isset($this->cachableAttributes) ? $this->cachableAttributes : [];
    }
}
