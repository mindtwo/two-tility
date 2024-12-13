<?php

namespace mindtwo\TwoTility\Cache\Data;

// @phpstan-ignore-next-line - Ignore the missing template for now
class ReadonlyDataCache extends DataCache
{
    public function __construct(
        private string $key,
    ) {}

    public function cacheData(): array
    {
        // Load data cache from cache store if available
        if ($this->isDataCached()) {
            $this->data = $this->cacheInstance()->get($this->cacheKey());

            return $this->data;
        }

        return [];
    }

    public function keys(): array
    {
        return [];
    }

    /**
     * Refresh the cache data.
     */
    public function refresh(): void
    {
        $this->cacheData();
    }

    protected function saveData(): void
    {
        // Do not save our data
    }

    /**
     * Save the cache data.
     */
    public function unload(): void
    {
        $this->data = [];
    }

    public function cacheKey(): string
    {
        return $this->key;
    }
}
