<?php

namespace mindtwo\TwoTility\Cache\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Facades\Cache;
use JsonSerializable;
use Stringable;

/**
 * @template T extends \Illuminate\Database\Eloquent\Model
 *
 * @property T $model
 *
 * @implements Arrayable<string, mixed>
 */
abstract class DataCache implements Arrayable, Jsonable, JsonSerializable, Stringable
{
    /**
     * Data that is cached.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Time to live in seconds.
     */
    protected int $ttl = 60;

    /**
     * Allow empty data cache.
     */
    protected bool $allowEmpty = false;

    /**
     * Cache driver.
     */
    protected ?string $cacheDriver = null;

    /**
     * DataCache constructor.
     *
     * @param  T  $model
     */
    public function __construct(
        protected $model,
    ) {
        $this->loadDataCache();
    }

    public function load(): void
    {
        if (! $this->canLoad()) {
            return;
        }

        $this->loadDataCache();
    }

    /**
     * Unload data cache.
     */
    public function unload(): void
    {
        if (! $this->isLoaded()) {
            return;
        }

        $this->cacheInstance()->forget($this->cacheKey());
    }

    /**
     * Get attribute value from data cache.
     *
     * @param  mixed  $name
     * @return mixed
     */
    public function get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        return null;
    }

    /**
     * Refresh data cache.
     */
    public function refresh(): void
    {
        // Refresh model
        $this->model->refresh();

        $this->loadDataCache(true);
    }

    /**
     * Load data cache.
     */
    public function loadDataCache(bool $forceLoad = false): void
    {
        // Do nothing if data cache is not allowed to be loaded
        if (! $this->canLoad()) {
            return;
        }

        if ($forceLoad) {
            $this->loadData();
            $this->saveData();

            return;
        }

        // if data cache is already loaded, do nothing
        if ($this->isLoaded()) {
            return;
        }

        // Load data cache from cache store if available
        if ($this->cacheInstance()->has($this->cacheKey())) {
            $this->data = $this->cacheInstance()->get($this->cacheKey());

            return;
        }

        // get data we want to cache
        $this->loadData();
        $this->saveData();
    }

    /**
     * Get attribute value from data cache.
     *
     * @param  mixed  $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Check if data cache has data.
     */
    public function hasData(): bool
    {
        return ! empty($this->data);
    }

    /**
     * Check if data cache is loaded.
     */
    public function isLoaded(): bool
    {
        if (! $this->canLoad()) {
            return false;
        }

        return $this->cacheInstance()->has($this->cacheKey()) && $this->hasData();
    }

    /**
     * Load the data.
     */
    private function loadData(): void
    {
        $this->data = $this->cacheData();
    }

    /**
     * Save the data if applicable.
     */
    private function saveData(): void
    {
        if (empty($this->data) && ! $this->allowEmpty) {
            return;
        }

        $this->cacheInstance()->put($this->cacheKey(), $this->data, now()->addSeconds($this->ttl()));
    }

    /**
     * Get cache key.
     */
    protected function cacheKey(): string
    {
        return cache_key('data_cache', [
            'class' => class_basename($this),
        ])->toString();
    }

    /**
     * Get time to live in seconds.
     */
    protected function ttl(): int
    {
        return $this->ttl;
    }

    protected function canLoad(): bool
    {
        return true;
    }

    /**
     * Get cache instance.
     */
    protected function cacheInstance(): \Illuminate\Contracts\Cache\Repository
    {
        $driver = $this->cacheDriver ?? config('cache.default');

        return Cache::store($driver);
    }

    /**
     * Get attribute value from data cache.
     *
     * @return array<string, mixed>
     */
    abstract public function cacheData(): array;

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Get the instance as an array.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->data, $options);
    }

    /**
     * Convert the object to string which is its JSON representation.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
