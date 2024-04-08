<?php

namespace mindtwo\TwoTility\Cache\Models;

use mindtwo\TwoTility\Cache\Data\DataCache;

trait HasCachedAttributes
{
    private static bool $disableCache = false;

    private static bool $disableCacheLoad = false;

    /**
     * Data caches that should be loaded on access.
     */
    protected array $loadsOnAccess = [];

    /**
     * Data caches that should be loaded on retrieved.
     */
    protected array $loadsOnRetrieved = [];

    /**
     * Retrieved cached attributes.
     *
     * @var array<string, mixed>
     */
    protected array $retrievedCachedAttributes = [];

    /**
     * Data caches.
     *
     * @var array<string, DataCache>
     */
    protected array $dataCaches = [];

    protected array $cacheAbleAttributes = [];

    /**
     * Data that is cached.
     *
     * @var array<string, mixed>
     */
    private array $loadedDataCaches = [];

    public static function bootHasCachedAttributes()
    {
        static::created(fn ($model) => self::bootDataCaches($model));
        static::retrieved(fn ($model) => self::bootDataCaches($model));
    }

    protected static function bootDataCaches($model)
    {
        if (static::$disableCache) {
            return;
        }

        $dataCaches = $model->getDataCaches();

        foreach ($dataCaches as $key => $cache) {
            $clz = $cache;

            if (! $clz || ! is_a($clz, DataCache::class, true)) {
                continue;
            }

            $cache = new $clz($model);
            $model->dataCaches[$key] = $cache;

            $model->cacheAbleAttributes = array_merge($model->cacheAbleAttributes, $cache->keys());

            if ($cache->loadOnAccess()) {
                $model->loadsOnAccess[] = $key;
            }

            if ($cache->loadOnRetrieved()) {
                $model->loadsOnRetrieved[] = $key;
            }
        }

        if (static::$disableCacheLoad) {
            return;
        }

        $model->loadDataCache(array_keys($model->loadsOnRetrieved));
    }

    /**
     * Get data caches.
     *
     * @return null|array<string, class-string<DataCache>>
     */
    abstract public function getDataCaches(): ?array;

    protected function getDataCacheClassName(string $name): ?string
    {
        if (! isset($this->dataCaches[$name])) {
            return null;
        }

        return get_class($this->dataCaches[$name]) ?? null;
    }

    protected function getDataCache(string $name): ?DataCache
    {
        if (! isset($this->dataCaches[$name])) {
            return null;
        }

        return $this->dataCaches[$name];
    }

    /**
     * Get attribute value from data cache.
     *
     * @param  mixed  $name
     * @return mixed
     */
    protected function getCachedAttribute($name)
    {
        if (isset($this->retrievedCachedAttributes[$name])) {
            return $this->retrievedCachedAttributes[$name];
        }

        $this->loadDataCache($this->loadsOnAccess);
        foreach ($this->loadedDataCaches as $key => $value) {
            if (! $value || ! $value instanceof DataCache) {
                continue;
            }

            $cachedData = $value->data();
            $this->retrievedCachedAttributes = array_merge($this->retrievedCachedAttributes, $cachedData);
            if (array_key_exists($name, $cachedData)) {
                return $cachedData[$name];
            }
        }

        return $this->throwMissingAttributeExceptionIfApplicable($name);
    }

    /**
     * Check if attribute is cacheable.
     *
     * @param  mixed  $name
     */
    protected function isCacheableAttribute($name): bool
    {
        return in_array($name, $this->cacheAbleAttributes);
    }

    /**
     * Get attribute.
     *
     * @param  mixed  $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        // if cache is disabled, return attribute directly
        if (static::$disableCache) {
            return parent::getAttribute($name);
        }

        // if attribute exists, return it
        if ($this->isCacheableAttribute($name)) {
            return $this->getCachedAttribute($name);
        }

        return parent::getAttribute($name);
    }

    /**
     * Disable data caching.
     */
    public static function disableCache(bool $onlyLoad = false): void
    {
        if (! $onlyLoad) {
            static::$disableCache = true;
        }

        static::$disableCacheLoad = true;

    }

    /**
     * Alias for disableCache(true).
     */
    public static function disableCacheLoad(): void
    {
        self::disableCache(true);
    }

    /**
     * Enable data cache.
     */
    public static function enableCache(): void
    {
        static::$disableCache = false;
        static::$disableCacheLoad = false;
    }

    /**
     * Load data cache by name.
     */
    public function withCache(string|array $name): self
    {
        $this->loadDataCache($name);

        return $this;
    }

    protected function refreshDataCache(string|array $name): self
    {
        if (! $this->usesDataCache($name)) {
            return $this;
        }

        if (gettype($name) === 'string') {
            $this->loadedDataCaches[$name]->refresh();

            return $this;
        }

        foreach ($name as $cacheName) {
            $this->refreshDataCache($cacheName);
        }

        return $this;
    }

    /**
     * Unload data cache by name.
     *
     * @param  string|array<string>  $name
     */
    protected function unloadDataCache(string|array $name): self
    {
        if (! $this->usesDataCache($name)) {
            return $this;
        }

        if (gettype($name) === 'string') {
            $this->loadedDataCaches[$name]->unload();
            $this->loadedDataCaches[$name] = false;

            return $this;
        }

        foreach ($name as $cacheName) {
            $this->unloadDataCache($cacheName);
        }

        return $this;
    }

    /**
     * Load available data cache by name.
     */
    protected function loadDataCache(string|array $name): self
    {
        if ((gettype($name) === 'string' && ! $this->usesDataCache($name)) || static::$disableCacheLoad) {
            return $this;
        }

        if (gettype($name) === 'string') {
            if (isset($this->loadedDataCaches[$name])) {
                return $this;
            }

            $cache = $this->getDataCache($name);

            if (! $cache || ! $cache instanceof DataCache) {
                return $this;
            }

            // Load cache
            $cache->load();

            $this->loadedDataCaches[$name] = $cache;

            return $this;
        }

        // Load all caches
        foreach ($name as $cacheName) {
            $this->loadDataCache($cacheName);
        }

        return $this;
    }

    /**
     * Check if model uses data cache with given name.
     */
    private function usesDataCache(string $name): bool
    {
        if (empty($this->getDataCaches())) {
            return false;
        }

        return array_key_exists($name, $this->getDataCaches());
    }
}
