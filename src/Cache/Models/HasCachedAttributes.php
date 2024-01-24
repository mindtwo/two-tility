<?php

namespace mindtwo\TwoTility\Cache\Models;

use mindtwo\TwoTility\Cache\Data\DataCache;

trait HasCachedAttributes
{
    private bool $disableCache = false;

    private bool $disableCacheLoad = false;

    /**
     * Data that is cached.
     *
     * @var array<string, mixed>
     */
    private array $loadedDataCaches = [];

    public static function bootHasCachedAttributes()
    {
        self::retrieved(function (self $model) {
            if (method_exists($model, 'loadedCachesOnRetrieved')) {
                $model->loadDataCache($model->loadedCachesOnRetrieved());
            }

            if (property_exists($model, 'loadedCachesOnRetrieved')) {
                $model->loadDataCache($model->loadedCachesOnRetrieved);
            }
        });
    }

    /**
     * Get attribute value from data cache.
     *
     * @param  mixed  $name
     * @return mixed
     */
    protected function getCachedAttribute($name)
    {
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
        if ($this->disableCache) {
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
    public function disableCache(bool $onlyLoad = false): self
    {
        if (! $onlyLoad) {
            $this->disableCache = true;
        }

        $this->disableCacheLoad = true;

        return $this;
    }

    /**
     * Alias for disableCache(true).
     */
    public function disableCacheLoad(): self
    {
        return $this->disableCache(true);
    }

    /**
     * Enable data cache.
     */
    public function enableCache(): self
    {
        $this->disableCache = false;
        $this->disableCacheLoad = false;

        return $this;
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
        if ((gettype($name) === 'string' && ! $this->usesDataCache($name)) || $this->disableCacheLoad) {
            return $this;
        }

        if (gettype($name) === 'string') {
            $cache = $this->dataCaches[$name];
            if ($this->isCacheLoaded($name)) {
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
        if (! property_exists($this, 'dataCaches') || ! is_array($this->dataCaches)) {
            return false;
        }

        return array_key_exists($name, $this->dataCaches);
    }
}
