<?php

namespace mindtwo\TwoTility\Tests\Mock\Cache;

use Illuminate\Support\Facades\Gate;
use mindtwo\TwoTility\Cache\Data\DataCache;

class CachedAttributesDataCache extends DataCache
{
    protected bool $loadOnAccess = true;

    protected bool $loadOnlyOnce = true;

    protected bool $allowEmpty = true;

    /**
     * Get cache key.
     */
    public function cacheKey(): string
    {
        return cache_key('data_cache', [
            'class' => class_basename($this),
        ])->toString();
    }

    public function keys(): array
    {
        return [
            'foo',
            'baz',
        ];
    }

    public function cacheData(): array
    {
        return [
            'foo' => 'bar',
            'baz' => 'qux',
        ];
    }

    public function loadOnAccess(): bool
    {
        return $this->model->loadOnAccess;
    }

    public function allowEmpty(): bool
    {
        return $this->model->allowEmpty;
    }

    protected function authorize(): bool
    {
        // Authorization logic can be added here if needed
        return Gate::allows('access-cached-attributes');
    }
}
