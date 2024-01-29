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
    protected array $loadOnAccess = [];

    protected array $loadOnRetrieved = [];

    protected array $cacheClassNames = [];

    /**
     * Data that is cached.
     *
     * @var array<string, mixed>
     */
    private array $loadedDataCaches = [];

    public static function bootHasCachedAttributes()
    {
        self::retrieved(function (self $model) {
            if (static::$disableCache || static::$disableCacheLoad) {
                return;
            }

            $dataCaches = $model->getDataCaches();

            $model->cacheClassNames = array_map(function ($cache) {
                if (gettype($cache) === 'string' && is_a($cache, DataCache::class, true)) {
                    return $cache;
                }

                if (gettype($cache) === 'array' && ($clz = $cache['class'] ?? false) && is_a($clz, DataCache::class, true)) {
                    return $clz;
                }

                return null;
            }, $dataCaches);

            $model->loadOnAccess = array_keys(array_filter($model->getDataCaches(), function ($cache) {
                return $cache['loadOnAccess'] ?? false;
            }));

            $model->loadOnRetrieved = array_keys(array_filter($model->getDataCaches(), function ($cache) {
                return $cache['loadOnRetrieved'] ?? false;
            }));

            $model->loadDataCache(array_keys($model->loadOnRetrieved));
        });
    }

    /**
     * Get data caches.
     *
     * @return null|array<string, array|string>
     */
    abstract public function getDataCaches(): ?array;

    protected function getDataCacheClassName(string $name): ?string
    {
        return $this->cacheClassNames[$name] ?? null;
    }

    /**
     * Get attribute value from data cache.
     *
     * @param  mixed  $name
     * @return mixed
     */
    protected function getCachedAttribute($name)
    {
        $this->loadDataCache($this->loadOnAccess);

        foreach ($this->loadedDataCaches as $key => $value) {
            if (! $value || ! $value instanceof DataCache) {
                continue;
            }

            $value = $value->get($name);

            if ($value) {
                return $value;
            }
        }

        return $this->throwMissingAttributeExceptionIfApplicable($name);
    }

    private function getAttributeSafely($name)
    {
        try {
            return parent::getAttribute($name);
        } catch (\Throwable $th) {
            $value = $this->getCachedAttribute($name);

            if ($value) {
                return $value;
            }

            throw $th;
        }
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

        // if the model prevents accessing missing attributes, we need to wrap the call
        if (static::preventsAccessingMissingAttributes()) {
            return $this->getAttributeSafely($name);
        }

        return parent::getAttribute($name) ?? $this->getCachedAttribute($name);
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
            $cache = $this->getDataCacheClassName($name);
            if (!$cache || $this->isCacheLoaded($name)) {
                return $this;
            }

            // Check if cache is valid
            if (gettype($cache) !== 'string' || ! is_a($cache, DataCache::class, true)) {
                throw new \Exception('Can not load cache for name '.$name, 1);
            }

            // Load cache
            $this->loadedDataCaches[$name] = new $cache($this);

            return $this;
        }

        // Load all caches
        foreach ($name as $cacheName) {
            $this->loadDataCache($cacheName);
        }

        return $this;
    }

    /**
     * Check if data cache is loaded.
     *
     * @param string $name
     * @return boolean
     */
    private function isCacheLoaded(string $name): bool
    {
        if (! isset($this->loadedDataCaches[$name])) {
            return false;
        }

        $dataCache = $this->loadedDataCaches[$name];
        return $dataCache instanceof DataCache && $dataCache->isLoaded();
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
